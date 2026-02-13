<?php
/**
 * Sync API Endpoint
 * Receives batched sync operations from client and applies them to the database
 */

require '../db.php';
require '../auth.php';

// Ensure user is logged in
requireLogin();

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['operations']) || !is_array($input['operations'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$operations = $input['operations'];
$results = [];
$user_id = $_SESSION['user_id'];

// Process each operation
foreach ($operations as $operation) {
    $operationId = $operation['operation_id'] ?? null;
    $operationType = $operation['operation_type'] ?? null;
    $tableName = $operation['table_name'] ?? null;
    $data = $operation['data'] ?? [];
    $recordId = $operation['record_id'] ?? null;

    if (!$operationId || !$operationType || !$tableName) {
        $results[] = [
            'operation_id' => $operationId,
            'success' => false,
            'error' => 'Missing required fields'
        ];
        continue;
    }

    try {
        $result = processOperation($pdo, $user_id, $operationType, $tableName, $data, $recordId);
        $results[] = [
            'operation_id' => $operationId,
            'success' => true,
            'data' => $result
        ];
    } catch (Exception $e) {
        $results[] = [
            'operation_id' => $operationId,
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Update sync metadata
try {
    $stmt = $pdo->prepare("INSERT INTO sync_metadata (user_id, last_sync_at) 
                           VALUES (?, NOW()) 
                           ON DUPLICATE KEY UPDATE last_sync_at = NOW()");
    $stmt->execute([$user_id]);
} catch (Exception $e) {
    // Non-critical error, continue
}

echo json_encode(['success' => true, 'results' => $results]);
exit;

/**
 * Process a single sync operation
 */
function processOperation($pdo, $user_id, $operationType, $tableName, $data, $recordId) {
    switch ($tableName) {
        case 'exams':
            return processExamOperation($pdo, $user_id, $operationType, $data, $recordId);
        case 'questions':
            return processQuestionOperation($pdo, $user_id, $operationType, $data, $recordId);
        default:
            throw new Exception("Unknown table: $tableName");
    }
}

/**
 * Process exam operations
 */
function processExamOperation($pdo, $user_id, $operationType, $data, $recordId) {
    switch ($operationType) {
        case 'create_exam':
            return createExam($pdo, $user_id, $data);
        case 'update_exam':
            return updateExam($pdo, $user_id, $data, $recordId);
        case 'delete_exam':
            return deleteExam($pdo, $user_id, $recordId);
        default:
            throw new Exception("Unknown operation type: $operationType");
    }
}

/**
 * Process question operations
 */
function processQuestionOperation($pdo, $user_id, $operationType, $data, $recordId) {
    switch ($operationType) {
        case 'add_question':
            return addQuestion($pdo, $user_id, $data);
        case 'update_question':
            return updateQuestion($pdo, $user_id, $data, $recordId);
        case 'delete_question':
            return deleteQuestion($pdo, $user_id, $recordId);
        default:
            throw new Exception("Unknown operation type: $operationType");
    }
}

/**
 * Create a new exam
 */
function createExam($pdo, $user_id, $data) {
    // Validate required fields
    $title = sanitizeInput($data['title'] ?? '');
    $exam_type = sanitizeInput($data['exam_type'] ?? '');
    $course_name = sanitizeInput($data['course_name'] ?? '');
    $course_code = sanitizeInput($data['course_code'] ?? '');
    $instructions = sanitizeInput($data['instructions'] ?? '');
    $duration = (int)($data['duration'] ?? 0);
    $attempts_allowed = (int)($data['attempts_allowed'] ?? 1);
    $exam_code = sanitizeInput($data['exam_code'] ?? '');
    $start_datetime = !empty($data['start_datetime']) ? str_replace('T', ' ', $data['start_datetime']) : null;
    $end_datetime = !empty($data['end_datetime']) ? str_replace('T', ' ', $data['end_datetime']) : null;

    // Validate exam type
    $valid_types = ['Exam', 'Mid-semester', 'Quiz', 'Assignment'];
    if (!in_array($exam_type, $valid_types)) {
        $exam_type = 'Exam';
    }

    // Generate exam code if not provided
    if (empty($exam_code)) {
        $exam_code = generateExamCode($pdo);
    }

    // Check if exam code already exists
    $stmt = $pdo->prepare("SELECT id FROM exams WHERE exam_code = ?");
    $stmt->execute([$exam_code]);
    if ($stmt->fetch()) {
        // Generate a new unique code
        $exam_code = generateExamCode($pdo);
    }

    $is_exam_type = ($exam_type === 'Exam' || $exam_type === 'Mid-semester');
    
    $stmt = $pdo->prepare("INSERT INTO exams (user_id, title, exam_type, course_name, course_code, instructions, duration, attempts_allowed, exam_code, start_datetime, end_datetime, is_published) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
    $stmt->execute([$user_id, $title, $exam_type, $course_name, $course_code, $instructions, 
                    $is_exam_type ? $duration : 0, $attempts_allowed, $exam_code, $start_datetime, $end_datetime]);
    
    $exam_id = $pdo->lastInsertId();
    
    return ['exam_id' => $exam_id, 'exam_code' => $exam_code];
}

/**
 * Update an existing exam
 */
function updateExam($pdo, $user_id, $data, $examId) {
    // Verify ownership
    $stmt = $pdo->prepare("SELECT id FROM exams WHERE id = ? AND user_id = ?");
    $stmt->execute([$examId, $user_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Exam not found or access denied');
    }

    $title = sanitizeInput($data['title'] ?? '');
    $exam_type = sanitizeInput($data['exam_type'] ?? '');
    $course_name = sanitizeInput($data['course_name'] ?? '');
    $course_code = sanitizeInput($data['course_code'] ?? '');
    $instructions = sanitizeInput($data['instructions'] ?? '');
    $duration = (int)($data['duration'] ?? 0);
    $attempts_allowed = (int)($data['attempts_allowed'] ?? 1);
    $exam_code = sanitizeInput($data['exam_code'] ?? '');
    $start_datetime = !empty($data['start_datetime']) ? str_replace('T', ' ', $data['start_datetime']) : null;
    $end_datetime = !empty($data['end_datetime']) ? str_replace('T', ' ', $data['end_datetime']) : null;

    // Validate exam type
    $valid_types = ['Exam', 'Mid-semester', 'Quiz', 'Assignment'];
    if (!in_array($exam_type, $valid_types)) {
        $exam_type = 'Exam';
    }

    $is_exam_type = ($exam_type === 'Exam' || $exam_type === 'Mid-semester');

    $stmt = $pdo->prepare("UPDATE exams SET title = ?, exam_type = ?, course_name = ?, course_code = ?, 
                           instructions = ?, duration = ?, attempts_allowed = ?, exam_code = ?, 
                           start_datetime = ?, end_datetime = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$title, $exam_type, $course_name, $course_code, $instructions, 
                    $is_exam_type ? $duration : 0, $attempts_allowed, $exam_code, 
                    $start_datetime, $end_datetime, $examId, $user_id]);

    return ['exam_id' => $examId];
}

/**
 * Delete an exam
 */
function deleteExam($pdo, $user_id, $examId) {
    // Verify ownership
    $stmt = $pdo->prepare("SELECT id FROM exams WHERE id = ? AND user_id = ?");
    $stmt->execute([$examId, $user_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Exam not found or access denied');
    }

    $stmt = $pdo->prepare("DELETE FROM exams WHERE id = ? AND user_id = ?");
    $stmt->execute([$examId, $user_id]);

    return ['exam_id' => $examId];
}

/**
 * Add a new question
 */
function addQuestion($pdo, $user_id, $data) {
    $exam_id = (int)($data['exam_id'] ?? 0);
    
    // Verify exam ownership
    $stmt = $pdo->prepare("SELECT id FROM exams WHERE id = ? AND user_id = ?");
    $stmt->execute([$exam_id, $user_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Exam not found or access denied');
    }

    // Check if exam is published
    $stmt = $pdo->prepare("SELECT is_published FROM exams WHERE id = ?");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch();
    if ($exam && $exam['is_published']) {
        throw new Exception('Cannot add questions to a published exam');
    }

    $q_type = sanitizeInput($data['q_type'] ?? 'mcq');
    $question_text = sanitizeInput($data['question_text'] ?? '');
    $option_a = sanitizeInput($data['option_a'] ?? '');
    $option_b = sanitizeInput($data['option_b'] ?? '');
    $option_c = sanitizeInput($data['option_c'] ?? '');
    $option_d = sanitizeInput($data['option_d'] ?? '');
    $option_e = !empty($data['option_e']) ? sanitizeInput($data['option_e']) : null;
    $correct_option = sanitizeInput($data['correct_option'] ?? '');
    $marks = (int)($data['marks'] ?? 1);

    $stmt = $pdo->prepare("INSERT INTO questions (exam_id, q_type, question_text, option_a, option_b, option_c, option_d, option_e, correct_option, marks) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$exam_id, $q_type, $question_text, $option_a, $option_b, $option_c, $option_d, $option_e, $correct_option, $marks]);

    $question_id = $pdo->lastInsertId();

    return ['question_id' => $question_id, 'exam_id' => $exam_id];
}

/**
 * Update an existing question
 */
function updateQuestion($pdo, $user_id, $data, $questionId) {
    // Verify ownership through exam
    $stmt = $pdo->prepare("SELECT q.id FROM questions q 
                           JOIN exams e ON q.exam_id = e.id 
                           WHERE q.id = ? AND e.user_id = ?");
    $stmt->execute([$questionId, $user_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Question not found or access denied');
    }

    $q_type = sanitizeInput($data['q_type'] ?? 'mcq');
    $question_text = sanitizeInput($data['question_text'] ?? '');
    $option_a = sanitizeInput($data['option_a'] ?? '');
    $option_b = sanitizeInput($data['option_b'] ?? '');
    $option_c = sanitizeInput($data['option_c'] ?? '');
    $option_d = sanitizeInput($data['option_d'] ?? '');
    $option_e = !empty($data['option_e']) ? sanitizeInput($data['option_e']) : null;
    $correct_option = sanitizeInput($data['correct_option'] ?? '');
    $marks = (int)($data['marks'] ?? 1);

    $stmt = $pdo->prepare("UPDATE questions SET q_type = ?, question_text = ?, option_a = ?, option_b = ?, 
                           option_c = ?, option_d = ?, option_e = ?, correct_option = ?, marks = ? 
                           WHERE id = ?");
    $stmt->execute([$q_type, $question_text, $option_a, $option_b, $option_c, $option_d, $option_e, 
                    $correct_option, $marks, $questionId]);

    return ['question_id' => $questionId];
}

/**
 * Delete a question
 */
function deleteQuestion($pdo, $user_id, $questionId) {
    // Verify ownership through exam
    $stmt = $pdo->prepare("SELECT q.id FROM questions q 
                           JOIN exams e ON q.exam_id = e.id 
                           WHERE q.id = ? AND e.user_id = ?");
    $stmt->execute([$questionId, $user_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Question not found or access denied');
    }

    $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
    $stmt->execute([$questionId]);

    return ['question_id' => $questionId];
}

/**
 * Generate a unique exam code
 */
function generateExamCode($pdo) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        $stmt = $pdo->prepare("SELECT id FROM exams WHERE exam_code = ?");
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    
    return $code;
}

/**
 * Sanitize input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
