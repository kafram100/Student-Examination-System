<?php
/**
 * Fetch Data API Endpoint
 * Returns exams and questions data for the logged-in user
 * Supports incremental sync with 'since' timestamp
 */

require '../db.php';
require '../auth.php';

// Ensure user is logged in
requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$since = isset($_GET['since']) ? $_GET['since'] : null;

try {
    $response = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'exams' => [],
        'questions' => []
    ];

    // Fetch exams
    if ($since) {
        $stmt = $pdo->prepare("SELECT * FROM exams WHERE user_id = ? AND (created_at > ? OR updated_at > ?)");
        $stmt->execute([$user_id, $since, $since]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM exams WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }
    
    $exams = $stmt->fetchAll();
    
    // Format exam data
    foreach ($exams as $exam) {
        $response['exams'][] = [
            'id' => (int)$exam['id'],
            'user_id' => (int)$exam['user_id'],
            'title' => $exam['title'],
            'exam_type' => $exam['exam_type'],
            'course_name' => $exam['course_name'],
            'course_code' => $exam['course_code'],
            'instructions' => $exam['instructions'],
            'duration' => (int)$exam['duration'],
            'attempts_allowed' => (int)$exam['attempts_allowed'],
            'start_datetime' => $exam['start_datetime'],
            'end_datetime' => $exam['end_datetime'],
            'is_published' => (bool)$exam['is_published'],
            'results_released' => (bool)$exam['results_released'],
            'exam_code' => $exam['exam_code'],
            'assessment_file' => $exam['assessment_file'],
            'created_at' => $exam['created_at'],
            'updated_at' => $exam['created_at'] // Use created_at as fallback
        ];
    }

    // Fetch questions for user's exams
    $examIds = array_column($exams, 'id');
    
    if (!empty($examIds)) {
        $placeholders = implode(',', array_fill(0, count($examIds), '?'));
        
        if ($since) {
            $sql = "SELECT * FROM questions WHERE exam_id IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($examIds);
        } else {
            $sql = "SELECT * FROM questions WHERE exam_id IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($examIds);
        }
        
        $questions = $stmt->fetchAll();
        
        // Format question data
        foreach ($questions as $question) {
            $response['questions'][] = [
                'id' => (int)$question['id'],
                'exam_id' => (int)$question['exam_id'],
                'q_type' => $question['q_type'],
                'question_text' => $question['question_text'],
                'option_a' => $question['option_a'],
                'option_b' => $question['option_b'],
                'option_c' => $question['option_c'],
                'option_d' => $question['option_d'],
                'option_e' => $question['option_e'],
                'correct_option' => $question['correct_option'],
                'marks' => (int)$question['marks'],
                'created_at' => $exam['created_at'] ?? date('Y-m-d H:i:s'),
                'updated_at' => $exam['created_at'] ?? date('Y-m-d H:i:s')
            ];
        }
    }

    // Get last sync time for this user
    $stmt = $pdo->prepare("SELECT last_sync_at FROM sync_metadata WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $syncMeta = $stmt->fetch();
    
    if ($syncMeta) {
        $response['last_sync_at'] = $syncMeta['last_sync_at'];
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
