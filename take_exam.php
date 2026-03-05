<?php
require 'db.php';
require 'auth.php';

if (!isset($_SESSION['student_fullname']) || !isset($_SESSION['student_index']) || !isset($_SESSION['exam_id'])) {
    header("Location: student_login.php");
    exit;
}

$student_fullname = $_SESSION['student_fullname'];
$student_index = $_SESSION['student_index'];
$exam_id = $_SESSION['exam_id'];

// Fetch Exam Details
$stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();

// Require proctoring for all exams
$requires_proctoring = true;

// If proctoring is required but not enabled, redirect
if ($requires_proctoring && !isset($_SESSION['proctoring_enabled'])) {
    $_SESSION['exam_id'] = $exam_id;  // Store exam ID for after proctoring check
    $_SESSION['student_index'] = $student_index;
}

// Fetch Exam Details
$stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();

if (!$exam || !$exam['is_published']) {
    die("Exam not available.");
}

// Check/Create Attempt
// 1. Check for Active (ongoing) attempt
$stmt = $pdo->prepare("SELECT * FROM attempts WHERE exam_id = ? AND student_index = ? AND status = 'ongoing'");
$stmt->execute([$exam_id, $student_index]);
$active_attempt = $stmt->fetch();

if ($active_attempt) {
    // Continue existing attempt
    $attempt = $active_attempt;
} else {
    // 2. Check total attempts made
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attempts WHERE exam_id = ? AND student_index = ?");
    $stmt->execute([$exam_id, $student_index]);
    $attempt_count = $stmt->fetchColumn();

    if ($attempt_count >= $exam['attempts_allowed']) {
         // Limit reached, redirect to results
         header("Location: student_result.php");
         exit;
    }

    // 3. Start New Attempt
    $stmt = $pdo->prepare("INSERT INTO attempts (exam_id, student_index, student_fullname, start_time) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$exam_id, $student_index, $student_fullname]);
    
    // Fetch the newly created attempt
    $stmt = $pdo->prepare("SELECT * FROM attempts WHERE exam_id = ? AND student_index = ? AND status = 'ongoing'");
    $stmt->execute([$exam_id, $student_index]);
    $attempt = $stmt->fetch();
    
    // Set the exam attempt ID in session for proctoring
    $_SESSION['exam_attempt_id'] = $attempt['id'];
}

// Calculate remaining time
$is_unlimited = ($exam['duration'] <= 0);
$remaining_sec = 0;
$auto_submit = false;

if (!$is_unlimited) {
    $start_time = strtotime($attempt['start_time']);
    $duration_sec = $exam['duration'] * 60;
    $end_time = $start_time + $duration_sec;
    $current_time = time();
    $remaining_sec = $end_time - $current_time;

    if ($remaining_sec <= 0) {
        $auto_submit = true;
    }
}

$csrf_token = generateCSRFToken();

if ($auto_submit) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Submitting Exam...</title>
    </head>
    <body>
        <form id="autoSubmit" action="submit_exam.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="auto_submit" value="1">
        </form>
        <script>
            document.getElementById('autoSubmit').submit();
        </script>
        <script defer src="theme.js"></script>
</body>
    </html>
    <?php
    exit;
}

// Fetch Questions
$stmt = $pdo->prepare("SELECT * FROM questions WHERE exam_id = ?");
$stmt->execute([$exam_id]);
$questions = $stmt->fetchAll();

// Fetch saved answers for autosave/resume
$stmt = $pdo->prepare("SELECT question_id, selected_option, theory_answer, file_upload FROM answers WHERE attempt_id = ?");
$stmt->execute([$attempt['id']]);
$saved_answers = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $saved_answers[$row['question_id']] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($exam['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="theme.css" rel="stylesheet">
    <style>
        .timer-bar { position: fixed; top: 0; left: 0; width: 100%; background: #dc3545; color: white; padding: 10px; text-align: center; font-weight: bold; z-index: 1000; }
        .content { margin-top: 60px; }
        .autosave-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #6c757d;
        }
        .autosave-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #6c757d;
            display: inline-block;
        }
        .autosave-status.is-saving .autosave-dot { background: #0d6efd; }
        .autosave-status.is-offline .autosave-dot { background: #dc3545; }
        .autosave-status.is-saved .autosave-dot { background: #198754; }
        
        /* Proctoring overlay styles */
        .proctoring-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .proctoring-modal {
            background: white;
            color: #212529; /* Explicit dark text */
            padding: 2rem;
            border-radius: 10px;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        }
        
        .proctoring-modal h3, .proctoring-modal p, .proctoring-modal div {
            color: #212529;
        }
        
        .proctoring-video-preview {
            width: 100%;
            max-width: 320px;
            margin: 1rem auto;
            border: 2px solid #ddd;
            border-radius: 8px;
            display: none;
        }
        
        .security-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404; /* Explicit warning text color */
            border-radius: 5px;
            padding: 1rem;
            margin: 1rem 0;
            text-align: left;
        }
        
        .security-notice h5, .security-notice li {
            color: #856404 !important;
        }
    </style>
    <script src="js/proctoring.js"></script>
    <script>
        // Anti-screenshot, anti-copy measures
        document.addEventListener('contextmenu', function(e) {
            logActivity('right_click', 'User attempted to right-click', 'medium');
            e.preventDefault();
        });
        
        document.addEventListener('keydown', function(e) {
            // Prevent Print Screen, F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+U, PrntScr
            if (e.keyCode === 123 || // F12
                (e.ctrlKey && e.shiftKey && (e.keyCode === 73 || e.keyCode === 74)) || // Ctrl+Shift+I/J
                (e.ctrlKey && e.keyCode === 85) || // Ctrl+U
                e.keyCode === 44 || // Print Screen
                (e.ctrlKey && [67, 86, 88].includes(e.keyCode)) // Ctrl+C, V, X
            ) {
                e.preventDefault();
                
                let activityType = 'other';
                let description = 'User attempted to use keyboard shortcut';
                
                if (e.keyCode === 123) { activityType = 'dev_tools'; description = 'User attempted to open developer tools (F12)'; }
                else if (e.ctrlKey && e.shiftKey && e.keyCode === 73) { activityType = 'dev_tools'; description = 'User attempted to open developer tools (Ctrl+Shift+I)'; }
                else if (e.ctrlKey && e.shiftKey && e.keyCode === 74) { activityType = 'dev_tools'; description = 'User attempted to open developer tools (Ctrl+Shift+J)'; }
                else if (e.ctrlKey && e.keyCode === 85) { activityType = 'dev_tools'; description = 'User attempted to view page source (Ctrl+U)'; }
                else if (e.keyCode === 44) { activityType = 'screenshot_attempt'; description = 'User attempted to take screenshot (Print Screen)'; }
                else if (e.ctrlKey && e.keyCode === 67) { activityType = 'copy_attempt'; description = 'User attempted to copy content (Ctrl+C)'; }
                else if (e.ctrlKey && e.keyCode === 86) { activityType = 'paste_attempt'; description = 'User attempted to paste content (Ctrl+V)'; }
                else if (e.ctrlKey && e.keyCode === 88) { activityType = 'cut_attempt'; description = 'User attempted to cut content (Ctrl+X)'; }
                
                logActivity(activityType, description, 'medium');
            }
        });
        
        // Prevent drag and drop
        document.addEventListener('dragstart', function(e) {
            logActivity('drag_attempt', 'User attempted to drag content', 'low');
            e.preventDefault();
        });
        
        // Prevent text selection
        document.addEventListener('selectstart', function(e) {
            logActivity('selection_attempt', 'User attempted to select text', 'low');
            e.preventDefault();
        });
        
        // Prevent print
        window.addEventListener('beforeprint', function(e) {
            logActivity('print_attempt', 'User attempted to print', 'medium');
            e.preventDefault();
        });
        
        // Prevent screenshot via CSS
        document.documentElement.style.cssText += '; -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none;';
        
        // Function to log activity
        function logActivity(type, description, severity) {
            const formData = new FormData();
            formData.append('activity_type', type);
            formData.append('description', description);
            formData.append('severity', severity);
            formData.append('exam_attempt_id', <?= $attempt['id'] ?? 0 ?>);
            formData.append('user_id', <?= $_SESSION['user_id'] ?? 0 ?>);
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');
            
            fetch('log_exam_activity.php', {
                method: 'POST',
                body: formData
            }).catch(err => console.error('Failed to log activity:', err));
        }
        
        // Function to capture screenshot
        function captureScreenshot(type, description) {
            // Create a canvas to capture the screen
            const canvas = document.createElement('canvas');
            canvas.width = window.screen.width;
            canvas.height = window.screen.height;
            
            const ctx = canvas.getContext('2d');
            
            // Draw the current page content to canvas
            html2canvas(document.body, {
                onclone: function(clonedDoc) {
                    // Hide any overlays or modals that shouldn't be captured
                    const overlays = clonedDoc.querySelectorAll('.proctoring-overlay, .proctoring-modal');
                    overlays.forEach(overlay => overlay.style.display = 'none');
                }
            }).then(canvas => {
                const imageData = canvas.toDataURL('image/jpeg', 0.8);
                
                const formData = new FormData();
                formData.append('image_data', imageData);
                formData.append('exam_attempt_id', <?= $attempt['id'] ?? 0 ?>);
                formData.append('activity_type', type);
                formData.append('description', description);
                formData.append('severity', getSeverityForActivity(type));
                formData.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');
                
                fetch('capture_image.php', {
                    method: 'POST',
                    body: formData
                }).catch(err => console.error('Failed to capture screenshot:', err));
            });
        }
        
        function getSeverityForActivity(activityType) {
            const severityMap = {
                'tab_switch': 'high',
                'window_blur': 'medium',
                'print_attempt': 'medium',
                'right_click': 'low',
                'dev_tools': 'high',
                'copy_attempt': 'medium',
                'paste_attempt': 'medium',
                'screenshot_attempt': 'high',
                'exit_fullscreen': 'high',
                'camera_disabled': 'critical'
            };
            return severityMap[activityType] || 'medium';
        }
        
        // Load html2canvas library for screenshot functionality
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
        document.head.appendChild(script);
        
        // Monitor for tab switching and capture screenshots
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                // Tab switched away - capture screenshot
                captureScreenshot('tab_switch', 'Student switched away from exam tab');
                logActivity('tab_switch', 'Student switched away from exam tab', 'high');
            }
        });
        
        window.addEventListener('blur', function() {
            // Window lost focus - capture screenshot
            captureScreenshot('window_blur', 'Exam window lost focus');
            logActivity('window_blur', 'Exam window lost focus', 'medium');
        });
        
        // Monitor for multiple device usage
        window.addEventListener('focus', function() {
            // Record focus events that might indicate tab switching
            logActivity('window_focus', 'Window gained focus', 'low');
        });
        let timeLeft = <?= $remaining_sec ?>;
        let isUnlimited = <?= $is_unlimited ? 'true' : 'false' ?>;
        
        function updateTimer() {
            if (isUnlimited) return; // No timer for unlimited assessments

            let minutes = Math.floor(timeLeft / 60);
            let seconds = timeLeft % 60;
            document.getElementById('timer').innerText = minutes + "m " + seconds + "s";
            
            if (timeLeft <= 0) {
                // Ensure all answers are saved before auto-submitting
                saveAllAnswers();
                // Small delay to allow autosave to complete
                setTimeout(() => {
                    document.getElementById('examForm').submit();
                }, 500);
            } else {
                timeLeft--;
                setTimeout(updateTimer, 1000);
            }
        }
        
        // Function to manually save all answers
        function saveAllAnswers() {
            // Trigger all pending autosaves
            if (typeof processAllQueues !== 'undefined') {
                processAllQueues();
            }
            
            // Manually trigger input events to queue any unsaved changes
            document.querySelectorAll('input[type="text"][data-answer-type="fill_in"], textarea[data-answer-type="theory"]').forEach(input => {
                if (input.value !== input.dataset.lastSavedValue) {
                    input.dispatchEvent(new Event('input'));
                    input.dataset.lastSavedValue = input.value;
                }
            });
            
            // For radio buttons and checkboxes
            document.querySelectorAll('input[type="radio"][data-answer-type="mcq"]').forEach(input => {
                if (input.checked && !input.dataset.lastSavedChecked) {
                    input.dispatchEvent(new Event('change'));
                    input.dataset.lastSavedChecked = true;
                }
            });
        }
        
        // Helper function to process all pending queues
        function processAllQueues() {
            // Get all question IDs
            const questionIds = [];
            document.querySelectorAll('[data-question-id]').forEach(el => {
                const qid = el.getAttribute('data-question-id');
                if (qid && !questionIds.includes(qid)) {
                    questionIds.push(qid);
                }
            });
            
            // Process queue for each question
            questionIds.forEach(qid => {
                if (typeof processQueue === 'function') {
                    processQueue(parseInt(qid));
                }
            });
        }
        
        // Show proctoring modal on load if required
        window.addEventListener('load', () => {
            // Check if proctoring is required for this exam type
            const examTitle = document.querySelector('h2')?.textContent || '';
            const examTypeRequired = <?= json_encode($requires_proctoring) ?>;
            
            if (examTypeRequired) {
                // Show the proctoring modal
                document.getElementById('proctoringModal').style.display = 'flex';
                
                // Request camera and mic access
                initializeProctoring();
                
                // Set up the start button
                document.getElementById('start-proctoring-btn').addEventListener('click', startExamWithProctoring);
            } else {
                // Skip proctoring, start exam normally
                updateTimer();
                initAutosave();
            }
        });
        
        async function initializeProctoring() {
            try {
                // Request camera and microphone access
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { width: { ideal: 640 }, height: { ideal: 480 } },
                    audio: true
                });
                
                // Display video preview
                const videoPreview = document.getElementById('proctoring-video-preview');
                videoPreview.srcObject = stream;
                videoPreview.style.display = 'block';
                
                // Enable start button
                document.getElementById('start-proctoring-btn').disabled = false;
                document.getElementById('camera-permission-status').innerHTML = '<span class="text-success">Camera and microphone access granted</span>';
                
                // Store stream for later use
                window.proctoringStream = stream;
                
            } catch (err) {
                console.error('Error accessing media devices:', err);
                document.getElementById('camera-permission-status').innerHTML = '<span class="text-danger">Camera/microphone access denied. Exam cannot start.</span>';
                
                // Still allow exam to start but log the security issue
                document.getElementById('start-proctoring-btn').disabled = false;
                document.getElementById('start-proctoring-btn').textContent = 'Continue Without Proctoring';
            }
        }
        
        function startExamWithProctoring() {
            // Hide the proctoring modal
            document.getElementById('proctoringModal').style.display = 'none';
            // Show the exam content
            document.getElementById('exam-content-area').style.display = 'block';
            
            // Start the proctoring system
            if (window.examProctoring) {
                if (window.proctoringStream) {
                    window.examProctoring.videoStream = window.proctoringStream;
                    window.examProctoring.startRecording();
                    window.examProctoring.connectMonitoringServer();
                    window.examProctoring.enforceStreamActivity();
                }
                window.examProctoring.startExam();
            }
            
            // Initialize autosave
            initAutosave();
            
            // Start the timer
            updateTimer();
        }

        function initAutosave() {
            const autosaveStatus = document.getElementById('autosave-status');
            const autosaveText = document.getElementById('autosave-text');
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
            const attemptId = <?= (int)$attempt['id'] ?>;
            const autosaveUrl = 'autosave_answer.php';
            const draftKey = `ses_draft_${attemptId}`;
            const pendingPayloads = new Map();
            const savingLocks = new Map();
            const debounceTimers = new Map();

            function setStatus(state, text) {
                if (!autosaveStatus || !autosaveText) return;
                autosaveStatus.classList.remove('is-saving', 'is-saved', 'is-offline');
                if (state) autosaveStatus.classList.add(state);
                autosaveText.textContent = text;
            }

            function loadDraft() {
                try {
                    const raw = localStorage.getItem(draftKey);
                    return raw ? JSON.parse(raw) : {};
                } catch (e) {
                    return {};
                }
            }

            function saveDraft(questionId, payload) {
                const draft = loadDraft();
                draft[questionId] = { ...payload, updatedAt: Date.now() };
                try {
                    localStorage.setItem(draftKey, JSON.stringify(draft));
                } catch (e) {
                    // Ignore storage errors (quota, disabled)
                }
            }

            function applyDraftToUI() {
                const draft = loadDraft();
                const ids = Object.keys(draft);
                if (!ids.length) return;

                ids.forEach((qid) => {
                    const entry = draft[qid];
                    if (!entry || !entry.type) return;

                    if (entry.type === 'mcq') {
                        const target = document.querySelector(
                            `input[type="radio"][data-question-id="${qid}"][value="${entry.value}"]`
                        );
                        if (target) {
                            target.checked = true;
                        }
                    } else if (entry.type === 'theory') {
                        const textarea = document.querySelector(`textarea[data-question-id="${qid}"]`);
                        if (textarea) {
                            textarea.value = entry.value ?? '';
                        }
                    }
                });

                setStatus('is-saved', 'Restored locally saved answers.');
            }

            async function sendAutosave(formData) {
                if (!navigator.onLine) {
                    throw new Error('offline');
                }

                const response = await fetch(autosaveUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                let data = null;
                try {
                    data = await response.json();
                } catch (e) {
                    data = null;
                }

                if (!response.ok || !data || !data.success) {
                    const message = data && data.error ? data.error : 'Autosave failed.';
                    throw new Error(message);
                }

                return data;
            }

            function buildFormData(payload) {
                const formData = new FormData();
                formData.append('csrf_token', csrfToken);
                formData.append('question_id', payload.questionId);
                formData.append('answer_type', payload.type);

                if (payload.type === 'mcq') {
                    formData.append('selected_option', payload.value || '');
                } else if (payload.type === 'theory') {
                    formData.append('theory_answer', payload.value || '');
                } else if (payload.type === 'file' && payload.file) {
                    formData.append('file_answer', payload.file);
                }

                return formData;
            }

            async function processQueue(questionId) {
                if (savingLocks.get(questionId)) return;
                savingLocks.set(questionId, true);

                while (pendingPayloads.has(questionId)) {
                    const payload = pendingPayloads.get(questionId);
                    pendingPayloads.delete(questionId);
                    setStatus('is-saving', 'Saving...');

                    try {
                        const formData = buildFormData(payload);
                        await sendAutosave(formData);
                        setStatus('is-saved', 'All changes saved.');
                    } catch (err) {
                        if (err.message === 'offline') {
                            setStatus('is-offline', 'Offline. Saving locally...');
                        } else {
                            setStatus('is-offline', 'Autosave failed. Will retry.');
                        }
                        pendingPayloads.set(questionId, payload);
                        break;
                    }
                }

                savingLocks.delete(questionId);
            }

            function queueSave(payload) {
                if (!payload.questionId || !payload.type) return;

                const draftPayload = {
                    questionId: payload.questionId,
                    type: payload.type
                };
                if (payload.type === 'mcq' || payload.type === 'theory') {
                    draftPayload.value = payload.value;
                }

                saveDraft(payload.questionId, draftPayload);
                pendingPayloads.set(payload.questionId, payload);
                processQueue(payload.questionId);
            }

            function debounceSave(questionId, payload, delay = 600) {
                if (debounceTimers.has(questionId)) {
                    clearTimeout(debounceTimers.get(questionId));
                }
                debounceTimers.set(questionId, setTimeout(() => queueSave(payload), delay));
            }

            function attachListeners() {
                document.querySelectorAll('input[type="radio"][data-answer-type="mcq"]').forEach((input) => {
                    input.addEventListener('change', (event) => {
                        const qid = event.target.getAttribute('data-question-id');
                        queueSave({ questionId: qid, type: 'mcq', value: event.target.value });
                    });
                });

                document.querySelectorAll('textarea[data-answer-type="theory"]').forEach((textarea) => {
                    textarea.addEventListener('input', (event) => {
                        const qid = event.target.getAttribute('data-question-id');
                        debounceSave(qid, { questionId: qid, type: 'theory', value: event.target.value });
                    });
                });

                document.querySelectorAll('input[type="file"][data-answer-type="file"]').forEach((input) => {
                    input.addEventListener('change', (event) => {
                        const qid = event.target.getAttribute('data-question-id');
                        const file = event.target.files && event.target.files[0] ? event.target.files[0] : null;
                        if (!file) {
                            return;
                        }
                        queueSave({ questionId: qid, type: 'file', file });
                    });
                });

                // Handle fill-in questions
                document.querySelectorAll('input[type="text"][data-answer-type="fill_in"]').forEach((input) => {
                    input.addEventListener('input', (event) => {
                        const qid = event.target.getAttribute('data-question-id');
                        debounceSave(qid, { questionId: qid, type: 'fill_in', value: event.target.value });
                    });
                });
            }

            function syncDraftOnReconnect() {
                const draft = loadDraft();
                const entries = Object.values(draft);
                if (!entries.length) return;
                entries.forEach((entry) => {
                    if (entry.type === 'file') return;
                    queueSave(entry);
                });
            }

            function syncPendingPayloads() {
                pendingPayloads.forEach((_, questionId) => {
                    processQueue(questionId);
                });
            }

            applyDraftToUI();
            attachListeners();

            if (!navigator.onLine) {
                setStatus('is-offline', 'Offline. Saving locally...');
            } else {
                setStatus('', 'Autosave enabled.');
            }

            window.addEventListener('online', () => {
                setStatus('is-saving', 'Back online. Syncing...');
                syncDraftOnReconnect();
                syncPendingPayloads();
            });

            window.addEventListener('offline', () => {
                setStatus('is-offline', 'Offline. Saving locally...');
            });
        }
    </script>
</head>
<body>
    <!-- Proctoring Modal -->
    <div id="proctoringModal" class="proctoring-overlay" style="display: none;">
        <div class="proctoring-modal">
            <h3><i class="bi bi-shield-lock me-2"></i>Exam Security Verification</h3>
            <p>Please allow camera and microphone access to begin your proctored exam.</p>
            
            <div class="security-notice">
                <h5><i class="bi bi-exclamation-triangle me-2"></i>Security Notice:</h5>
                <ul class="mb-0" style="text-align: left;">
                    <li>Your video and audio will be recorded during this exam</li>
                    <li>Any suspicious activity will be flagged</li>
                    <li>Do not switch tabs or close this window</li>
                    <li>Ensure you are in a quiet, well-lit environment</li>
                </ul>
            </div>
            
            <video id="proctoring-video-preview" class="proctoring-video-preview" autoplay muted></video>
            <div id="camera-permission-status">Camera access required</div>
            
            <div class="mt-3">
                <button id="start-proctoring-btn" class="btn btn-success btn-lg" disabled>Start Proctored Exam</button>
            </div>
        </div>
    </div>
    <div id="exam-content-area" style="display: none;">
        <?php if (!$is_unlimited): ?>
            <div class="timer-bar">
                Time Remaining: <span id="timer"></span>
            </div>
        <?php endif; ?>

        <div class="container content" style="<?= $is_unlimited ? 'margin-top: 20px;' : '' ?>">
        <h2 class="mb-4"><?= htmlspecialchars($exam['title']) ?></h2>
        <p class="lead"><?= nl2br(htmlspecialchars($exam['instructions'])) ?></p>
        <div class="autosave-status mb-3" id="autosave-status">
            <span class="autosave-dot"></span>
            <span id="autosave-text">Autosave enabled.</span>
        </div>
        
        <?php if ($exam['assessment_file']): ?>
            <div class="alert alert-info py-2">
                <strong>Assessment Document:</strong> 
                <a href="<?= htmlspecialchars($exam['assessment_file']) ?>" class="btn btn-sm btn-primary ms-2" download>Download & Read Questions</a>
            </div>
        <?php endif; ?>

        <form id="examForm" action="submit_exam.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <?php foreach ($questions as $index => $q): ?>
                <?php $saved = $saved_answers[$q['id']] ?? null; ?>
                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title fw-bold">Q<?= $index + 1 ?>: <?= htmlspecialchars($q['question_text']) ?></h5>
                        <div class="mt-3">
                            <?php if ($q['q_type'] === 'mcq'): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="answers[<?= $q['id'] ?>]" value="A" id="q<?= $q['id'] ?>A" data-question-id="<?= $q['id'] ?>" data-answer-type="mcq" <?= ($saved && $saved['selected_option'] === 'A') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="q<?= $q['id'] ?>A">A) <?= htmlspecialchars($q['option_a']) ?></label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="answers[<?= $q['id'] ?>]" value="B" id="q<?= $q['id'] ?>B" data-question-id="<?= $q['id'] ?>" data-answer-type="mcq" <?= ($saved && $saved['selected_option'] === 'B') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="q<?= $q['id'] ?>B">B) <?= htmlspecialchars($q['option_b']) ?></label>
                                </div>
                                <?php if ($q['option_c']): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="answers[<?= $q['id'] ?>]" value="C" id="q<?= $q['id'] ?>C" data-question-id="<?= $q['id'] ?>" data-answer-type="mcq" <?= ($saved && $saved['selected_option'] === 'C') ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="q<?= $q['id'] ?>C">C) <?= htmlspecialchars($q['option_c']) ?></label>
                                    </div>
                                <?php endif; ?>
                                <?php if ($q['option_d']): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="answers[<?= $q['id'] ?>]" value="D" id="q<?= $q['id'] ?>D" data-question-id="<?= $q['id'] ?>" data-answer-type="mcq" <?= ($saved && $saved['selected_option'] === 'D') ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="q<?= $q['id'] ?>D">D) <?= htmlspecialchars($q['option_d']) ?></label>
                                    </div>
                                <?php endif; ?>
                                <?php if ($q['option_e']): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="answers[<?= $q['id'] ?>]" value="E" id="q<?= $q['id'] ?>E" data-question-id="<?= $q['id'] ?>" data-answer-type="mcq" <?= ($saved && $saved['selected_option'] === 'E') ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="q<?= $q['id'] ?>E">E) <?= htmlspecialchars($q['option_e']) ?></label>
                                    </div>
                                <?php endif; ?>

                            <?php elseif ($q['q_type'] === 'fill_in'): ?>
                                <input type="text" name="fill_in_answers[<?= $q['id'] ?>]" class="form-control" placeholder="Type your answer here..." value="<?= htmlspecialchars($saved['theory_answer'] ?? '') ?>" data-question-id="<?= $q['id'] ?>" data-answer-type="fill_in">

                            <?php elseif ($q['q_type'] === 'theory'): ?>
                                <textarea name="theory_answers[<?= $q['id'] ?>]" class="form-control" rows="4" placeholder="Type your answer here..." data-question-id="<?= $q['id'] ?>" data-answer-type="theory"><?= htmlspecialchars($saved['theory_answer'] ?? '') ?></textarea>

                            <?php elseif ($q['q_type'] === 'file'): ?>
                                <div class="border p-3 bg-light rounded">
                                    <label class="form-label fw-bold">Upload your work (PDF, Image, or Document)</label>
                                    <?php if (!empty($q['option_e'])): ?>
                                        <div class="mb-2">
                                            <a href="<?= htmlspecialchars($q['option_e']) ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="bi bi-file-earmark-arrow-down me-1"></i>Download Question File
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($saved['file_upload'])): ?>
                                        <div class="alert alert-success py-1 px-2 small mb-2">
                                            Saved file detected. You can upload a new file to replace it.
                                        </div>
                                        <a class="btn btn-sm btn-outline-success mb-2" href="<?= htmlspecialchars($saved['file_upload']) ?>" target="_blank">View Uploaded File</a>
                                    <?php endif; ?>
                                    <input type="file" name="file_answers_<?= $q['id'] ?>" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" data-question-id="<?= $q['id'] ?>" data-answer-type="file">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="text-center">
                <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm mb-5">Submit My Work</button>
            </div>
        </form>
    </div> <!-- container -->
    </div> <!-- exam-content-area -->
    <script defer src="theme.js"></script>
</body>
</html>

