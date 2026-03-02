<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Online Examination System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="theme.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .hero { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 60px 0; }
        .feature-icon { font-size: 2rem; color: #764ba2; margin-bottom: 20px; }
        .card { border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: transform 0.2s; }
        .card:hover { transform: translateY(-5px); }
    </style>
</head>
<body>

    <!-- Hero Section -->
    <header class="hero text-center">
        <div class="container">
            <h1 class="display-4 fw-bold mb-3">Student Online Examination System</h1>
            <p class="lead mb-4">A secure, timed, and automated platform for conducting online assessments.</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="#students" class="btn btn-light btn-lg text-primary fw-bold">I am a Student</a>
                <a href="#lecturers" class="btn btn-outline-light btn-lg">I am a Lecturer</a>
            </div>
        </div>
    </header>

    <!-- Content -->
    <div class="container my-5">
        
        <!-- How It Works -->
        <div class="row text-center mb-5">
            <div class="col-md-4">
                <div class="card p-4 h-100">
                    <div class="feature-icon">📝</div>
                    <h4>Create Exams</h4>
                    <p class="text-muted">Lecturers can easily create timed multiple-choice exams with automatic grading.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 h-100">
                    <div class="feature-icon">⏱️</div>
                    <h4>Timed Assessments</h4>
                    <p class="text-muted">Students take exams with a strict server-side timer to ensure fairness.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 h-100">
                    <div class="feature-icon">📊</div>
                    <h4>Instant Results</h4>
                    <p class="text-muted">Scores are calculated immediately. Lecturers control when results are released.</p>
                </div>
            </div>
        </div>

        <hr class="my-5">

        <!-- Roles Section -->
        <div class="row align-items-center mb-5" id="students">
            <div class="col-md-6 order-md-2">
                <img src="https://images.unsplash.com/photo-1434030216411-0b793f4b4173?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="Student" class="img-fluid rounded shadow-lg">
            </div>
            <div class="col-md-6 order-md-1">
                <h2 class="text-primary">For Students</h2>
                <p class="lead">Ready to take your exam?</p>
                <ul class="list-unstyled">
                    <li class="mb-2">✅ Get the <strong>Exam Code</strong> from your lecturer.</li>
                    <li class="mb-2">✅ Have your <strong>Index Number</strong> ready.</li>
                    <li class="mb-2">✅ Ensure you have a stable internet connection.</li>
                </ul>
                <a href="student_login.php" class="btn btn-primary btn-lg mt-3">Go to Exam Portal</a>
            </div>
        </div>

        <div class="row align-items-center" id="lecturers">
            <div class="col-md-6">
                <img src="https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="Lecturer" class="img-fluid rounded shadow-lg">
            </div>
            <div class="col-md-6">
                <h2 class="text-success">For Lecturers</h2>
                <p class="lead">Manage your assessments efficiently.</p>
                <ul class="list-unstyled">
                    <li class="mb-2">🛠️ Create and edit questions.</li>
                    <li class="mb-2">📈 Monitor student performance in real-time.</li>
                    <li class="mb-2">🔒 Secure exam links and timer enforcement.</li>
                </ul>
                <div class="d-flex gap-2 mt-3">
                    <a href="login.php" class="btn btn-success btn-lg">Lecturer Login</a>
                    <a href="register.php" class="btn btn-outline-secondary btn-lg">Register</a>
                </div>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4 mt-5">
        <p class="mb-0">&copy; <?= date('Y') ?> Student Online Examination System. All rights reserved.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="theme.js"></script>
</body>
</html>

