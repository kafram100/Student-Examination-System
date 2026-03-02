<?php
require 'db.php';
require 'auth.php';

if (!isset($_SESSION['student_index']) || !isset($_SESSION['exam_id'])) {
    header("Location: student_login.php");
    exit;
}

$student_index = $_SESSION['student_index'];
$exam_id = $_SESSION['exam_id'];

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
    $stmt = $pdo->prepare("INSERT INTO attempts (exam_id, student_index, start_time) VALUES (?, ?, NOW())");
    $stmt->execute([$exam_id, $student_index]);
    
    // Fetch the newly created attempt
    $stmt = $pdo->prepare("SELECT * FROM attempts WHERE exam_id = ? AND student_index = ? AND status = 'ongoing'");
    $stmt->execute([$exam_id, $student_index]);
    $attempt = $stmt->fetch();
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
            padding: 2rem;
            border-radius: 10px;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
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
            border-radius: 5px;
            padding: 1rem;
            margin: 1rem 0;
            text-align: left;
        }
    </style>
    <script src="js/proctoring.js"></script>
    <script>
        let timeLeft = <?= $remaining_sec ?>;
        let isUnlimited = <?= $is_unlimited ? 'true' : 'false' ?>;
        
        function updateTimer() {
            if (isUnlimited) return; // No timer for unlimited assessments

            let minutes = Math.floor(timeLeft / 60);
            let seconds = timeLeft % 60;
            document.getElementById('timer').innerText = minutes + "m " + seconds + "s";
            
            if (timeLeft <= 0) {
                document.getElementById('examForm').submit();
            } else {
                timeLeft--;
                setTimeout(updateTimer, 1000);
            }
        }
        
        // Show proctoring modal on load
        window.addEventListener('load', () => {
            // Show the proctoring modal
            document.getElementById('proctoringModal').style.display = 'flex';
            
            // Request camera and mic access
            initializeProctoring();
            
            // Set up the start button
            document.getElementById('start-proctoring-btn').addEventListener('click', startExamWithProctoring);
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
            
            // Start the proctoring system
            if (window.examProctoring) {
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
    </div>
    <script defer src="theme.js"></script>
</body>
</html>

