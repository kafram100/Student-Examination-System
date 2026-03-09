<?php
function isHttpsRequest() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
}

// Security Headers
function setSecurityHeaders() {
    $is_https = isHttpsRequest();

    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    // Enable XSS protection (legacy)
    header('X-XSS-Protection: 1; mode=block');
    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Content Security Policy (basic)
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https: blob:; font-src 'self' https://cdn.jsdelivr.net; frame-src 'self' blob:; object-src 'self' blob:;");

    if ($is_https) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// Set secure session parameters
function setSecureSessionParams() {
    $is_https = isHttpsRequest();
    $cookie_params = session_get_cookie_params();

    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', $is_https ? 1 : 0);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', 3600); // 1 hour

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookie_params['path'] ?? '/',
        'domain' => $cookie_params['domain'] ?? '',
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

setSecurityHeaders();
if (session_status() === PHP_SESSION_NONE) {
    setSecureSessionParams();
    session_start();
}

// Generate CSRF token if not exists
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function rotateCSRFToken() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Check CSRF token for POST requests
function checkCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($token)) {
            http_response_code(403);
            die('Invalid CSRF token');
        }
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        header('Location: dashboard.php');
        exit;
    }
}

function rateLimitFilePath($identifier) {
    $safe_key = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', (string)$identifier);
    $hash = sha1($safe_key);
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
    return $dir . DIRECTORY_SEPARATOR . 'ses_rl_' . $hash . '.json';
}

function resetRateLimit($identifier) {
    $file = rateLimitFilePath($identifier);
    if (is_file($file)) {
        @unlink($file);
    }
}

// Rate limiting for login attempts (file-based; falls back to session)
function checkRateLimit($identifier, $maxAttempts = 5, $windowSeconds = 300) {
    $now = time();

    $file = rateLimitFilePath($identifier);
    $data = ['attempts' => 0, 'first_attempt' => $now];

    if (is_readable($file)) {
        $raw = @file_get_contents($file);
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && isset($decoded['attempts'], $decoded['first_attempt'])) {
            $data = $decoded;
        }
    }

    // Reset if window has passed
    if ($now - $data['first_attempt'] > $windowSeconds) {
        $data = ['attempts' => 1, 'first_attempt' => $now];
        @file_put_contents($file, json_encode($data), LOCK_EX);
        return true;
    }

    if ($data['attempts'] >= $maxAttempts) {
        return false;
    }

    $data['attempts']++;
    @file_put_contents($file, json_encode($data), LOCK_EX);
    return true;
}

// Validate password strength
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = 'Password must contain at least one special character';
    }
    
    return $errors;
}

// Sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function normalizeAssessmentType($examType) {
    if (!is_string($examType)) {
        return '';
    }

    $decoded = html_entity_decode($examType, ENT_QUOTES, 'UTF-8');
    return strtolower(trim($decoded));
}

function requiresProctoringForExamType($examType) {
    $normalized = normalizeAssessmentType($examType);
    return in_array($normalized, ['exam', 'mid-semester', 'mid semester', 'quiz'], true);
}
// Validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function storeUploadedFile($file, $uploadDir, array $allowedExtensions, array $allowedMimeTypes, $maxBytes, $filenamePrefix) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['path' => null, 'error' => 'Upload failed. Please try again.'];
    }

    if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
        return ['path' => null, 'error' => 'File size is not allowed.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (empty($ext) || !in_array($ext, $allowedExtensions, true)) {
        return ['path' => null, 'error' => 'Invalid file type.'];
    }

    if (!function_exists('finfo_open')) {
        return ['path' => null, 'error' => 'File validation is not available on this server.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
    if ($finfo) {
        finfo_close($finfo);
    }

    if (empty($mime) || !in_array($mime, $allowedMimeTypes, true)) {
        return ['path' => null, 'error' => 'Invalid file content.'];
    }

    $uploadDir = rtrim($uploadDir, '/\\');
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    if (!is_dir($uploadDir)) {
        return ['path' => null, 'error' => 'Upload directory is not available.'];
    }

    $safePrefix = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)$filenamePrefix);
    $filename = $safePrefix . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
    $destPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['path' => null, 'error' => 'Failed to save uploaded file.'];
    }

    return ['path' => str_replace('\\', '/', $destPath), 'error' => null];
}
?>


