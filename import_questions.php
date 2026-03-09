<?php
require 'db.php';
require 'auth.php';

requireLogin();

if (!isset($_GET['exam_id'])) {
    die("Exam ID required.");
}

$exam_id = $_GET['exam_id'];
$user_id = $_SESSION['user_id'];

// Verify ownership
$stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND user_id = ?");
$stmt->execute([$exam_id, $user_id]);
$exam = $stmt->fetch();

if (!$exam) {
    die("Exam not found or access denied.");
}

if ($exam['is_published']) {
    die("Cannot add questions to a published exam.");
}

$success = '';
$error = '';
$imported_count = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    checkCSRF();
    $file = $_FILES['csv_file']['tmp_name'];
    
    if (is_uploaded_file($file)) {
        $handle = fopen($file, "r");
        
        // Skip header row
        fgetcsv($handle);
        
        $stmt = $pdo->prepare("INSERT INTO questions (exam_id, q_type, question_text, option_a, option_b, option_c, option_d, option_e, correct_option, marks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            // Validation: Ensure minimum columns exist (type + question + correct + marks)
            if (count($row) < 4) continue;
            
            $q_type = strtolower(trim($row[0]));
            $question_text = trim($row[1]);
            $option_a = trim($row[2]);
            $option_b = trim($row[3]);
            $option_c = trim($row[4]);
            $option_d = trim($row[5]);
            $option_e = trim($row[6]);
            $correct_answer = trim($row[7]);
            $marks = isset($row[8]) ? (int)$row[8] : 1;
            
            // Validate question type
            if (!in_array($q_type, ['mcq', 'fill_in', 'theory', 'file'])) {
                continue; // Skip invalid question types
            }
            
            if (empty($question_text)) {
                continue; // Skip questions without text
            }
            
            // Handle different question types
            $correct_option = null;
            
            if ($q_type === 'mcq') {
                // For MCQ, correct_answer should be A, B, C, D, or E
                $correct_option = strtoupper($correct_answer);
                if (!in_array($correct_option, ['A', 'B', 'C', 'D', 'E'])) {
                    continue; // Invalid correct option
                }
                if (empty($option_a) || empty($option_b)) {
                    continue; // MCQ must have at least 2 options
                }
            } elseif ($q_type === 'fill_in') {
                // For fill-in, correct_answer is the actual answer text
                if (empty($correct_answer)) {
                    continue; // Fill-in must have a correct answer
                }
                $correct_option = $correct_answer;
            } elseif ($q_type === 'theory' || $q_type === 'file') {
                // Theory and file questions don't need correct answers
                $correct_option = null;
            }
            
            // Clean up empty options
            $option_c = $option_c !== '' ? $option_c : null;
            $option_d = $option_d !== '' ? $option_d : null;
            $option_e = $option_e !== '' ? $option_e : null;
            
            // Insert
            try {
                $stmt->execute([$exam_id, $q_type, $question_text, $option_a, $option_b, $option_c, $option_d, $option_e, $correct_option, $marks]);
                $imported_count++;
            } catch (Exception $e) {
                // Ignore specific failed rows
            }
        }
        
        fclose($handle);
        
        if ($imported_count > 0) {
            $success = "Successfully imported $imported_count questions.";
        } else {
            $error = "No valid questions found to import.";
        }
    } else {
        $error = "Please upload a valid CSV file.";
    }
}
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Import Questions - <?= htmlspecialchars($exam['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="theme.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        Import Questions for: <strong><?= htmlspecialchars($exam['title']) ?></strong>
                        <a href="view_exam.php?id=<?= $exam_id ?>" class="btn btn-sm btn-secondary float-end">Back to Exam</a>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= $success ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>

                        <p>Upload a CSV file with the following columns: <br>
                        <code>question_type, question_text, option_a, option_b, option_c, option_d, option_e, correct_answer, marks</code></p>
                        
                        <div class="alert alert-info">
                            <strong>Question Types:</strong><br>
                            <ul class="mb-0">
                                <li><strong>mcq</strong> - Multiple Choice (requires options A-E and correct answer as A/B/C/D/E)</li>
                                <li><strong>fill_in</strong> - Fill-in-the-blank (requires the correct answer text)</li>
                                <li><strong>theory</strong> - Theory/Short answer (no correct answer needed)</li>
                                <li><strong>file</strong> - File upload (students upload a file)</li>
                            </ul>
                        </div>
                        
                        <a href="download_template.php" class="btn btn-outline-primary mb-3">Download CSV Template</a>

                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <div class="mb-3">
                                <label>Choose CSV File</label>
                                <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                            </div>
                            <button type="submit" class="btn btn-success">Upload & Import</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script defer src="theme.js"></script>
</body>
</html>

