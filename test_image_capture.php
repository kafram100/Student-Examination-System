<?php
// Test script to verify image capture functionality
require_once 'db.php';
require_once 'auth.php';

// Simulate a test image capture
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_capture'])) {
    // Create a dummy image for testing
    $image = imagecreate(640, 480);
    $background = imagecolorallocate($image, 255, 255, 255); // White background
    $text_color = imagecolorallocate($image, 0, 0, 0); // Black text
    
    // Add some test content
    imagestring($image, 5, 50, 200, 'Test Image Capture', $text_color);
    imagestring($image, 5, 50, 250, date('Y-m-d H:i:s'), $text_color);
    
    // Output as base64 encoded data URL
    ob_start();
    imagejpeg($image, null, 80);
    $image_data = ob_get_contents();
    ob_end_clean();
    
    $base64 = base64_encode($image_data);
    $image_url = 'data:image/jpeg;base64,' . $base64;
    
    // Clean up
    imagedestroy($image);
    
    echo "<script>
    // Simulate sending the image to the server
    const formData = new FormData();
    formData.append('image_data', '$image_url');
    formData.append('exam_attempt_id', 1);
    formData.append('activity_type', 'test_capture');
    formData.append('description', 'Test image capture');
    formData.append('severity', 'low');
    formData.append('csrf_token', '{$_SESSION['csrf_token']}');
    
    fetch('capture_image.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('result').innerHTML = '<div class=\"alert alert-success\">Image captured successfully: ' + data.filename + '</div>';
        } else {
            document.getElementById('result').innerHTML = '<div class=\"alert alert-danger\">Error: ' + data.error + '</div>';
        }
    })
    .catch(error => {
        document.getElementById('result').innerHTML = '<div class=\"alert alert-danger\">Error: ' + error + '</div>';
    });
    </script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Test Image Capture</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Test Image Capture Functionality</h1>
        
        <form method="POST">
            <input type="hidden" name="test_capture" value="1">
            <button type="submit" class="btn btn-primary">Test Image Capture</button>
        </form>
        
        <div id="result" class="mt-3"></div>
        
        <div class="mt-4">
            <h3>How the Image Capture Works:</h3>
            <ul>
                <li>When students switch tabs or screens during exams, the system captures images from their webcam</li>
                <li>Screenshots are taken when students try to access other applications</li>
                <li>All captured evidence is stored securely in the uploads/proctoring/ directory</li>
                <li>Evidence is linked to the student's exam attempt and security logs</li>
                <li>Lecturers can view all evidence in the Proctoring Monitor section</li>
            </ul>
        </div>
    </div>
</body>
</html>