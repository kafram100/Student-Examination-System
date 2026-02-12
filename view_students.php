<?php
require 'db.php';
require 'auth.php';

requireLogin();

// Fetch all students ordered by Department, then Program
$stmt = $pdo->query("SELECT * FROM students ORDER BY department ASC, program ASC, full_name ASC");
$students = $stmt->fetchAll();

// Group students
$grouped_students = [];
foreach ($students as $student) {
    $dept = $student['department'] ?: 'Unknown Department';
    $prog = $student['program'] ?: 'Unknown Program';
    $grouped_students[$dept][$prog][] = $student;
}
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Database</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="theme.css" rel="stylesheet">
    <style>
        body {
            background: var(--theme-bg);
            color: var(--theme-text);
        }
        .card-header.bg-primary {
            background: linear-gradient(135deg, var(--theme-primary) 0%, var(--theme-primary-dark) 100%) !important;
        }
        .navbar-modern {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
            padding: 0.75rem 0;
        }
        body.theme-dark .navbar-modern {
            background: linear-gradient(135deg, #020617 0%, #1e1b4b 100%);
        }
        .navbar-modern .navbar-brand {
            color: white !important;
            font-weight: 600;
        }
        .navbar-modern .btn-outline-light {
            color: #fff;
            border-color: rgba(255,255,255,0.5);
        }
        .navbar-modern .btn-outline-light:hover {
            background: rgba(255,255,255,0.1);
            border-color: #fff;
        }
        body.theme-dark .table-light {
            --bs-table-bg: #1f2937;
            --bs-table-border-color: #334155;
            --bs-table-color: #f1f5f9;
        }
        body.theme-dark .table {
            --bs-table-border-color: #334155;
            --bs-table-striped-bg: #1e293b;
            --bs-table-hover-bg: #273449;
            --bs-table-color: #f1f5f9;
            background-color: #0f172a;
        }
        body.theme-dark .table thead th {
            background-color: #1f2937 !important;
            color: #f8fafc !important;
        }
        body.theme-dark .table tbody td {
            background-color: #0f172a !important;
            color: #f1f5f9 !important;
        }
        body.theme-dark .table-striped > tbody > tr:nth-of-type(odd) > * {
            background-color: #111827 !important;
        }
        body.theme-dark .table-hover > tbody > tr:hover > * {
            background-color: #1e293b !important;
        }
        body.theme-dark .table td,
        body.theme-dark .table th {
            border-color: #334155 !important;
        }
        body.theme-dark .card {
            background: #1e293b;
            border-color: #334155;
            color: #e2e8f0;
        }
        body.theme-dark .text-secondary {
            color: #cbd5e1 !important;
        }
        body.theme-dark .btn-secondary {
            background-color: #334155;
            border-color: #334155;
        }
        body.theme-dark .btn-secondary:hover {
            background-color: #475569;
            border-color: #475569;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-modern mb-4">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Exam System</a>
            <div class="d-flex">
                 <form method="POST" action="logout.php" class="d-inline">
                     <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                     <button type="submit" class="btn btn-outline-light btn-sm">Logout</button>
                 </form>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Registered Students</h2>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
        
        <?php if (empty($grouped_students)): ?>
            <div class="alert alert-info text-center">No students have registered yet.</div>
        <?php else: ?>
            <?php foreach ($grouped_students as $dept => $programs): ?>
                <div class="card mb-5 border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Department: <?= htmlspecialchars($dept) ?></h4>
                    </div>
                    <div class="card-body">
                        <?php foreach ($programs as $prog => $students_in_prog): ?>
                            <div class="mb-4">
                                <h5 class="text-secondary border-bottom pb-2">Program: <?= htmlspecialchars($prog) ?></h5>
                                <div class="table-responsive">
                                    <table class="table table-hover table-bordered table-sm">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Index Number</th>
                                                <th>Full Name</th>
                                                <th>Registered Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students_in_prog as $student): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($student['index_number']) ?></td>
                                                    <td><?= htmlspecialchars($student['full_name']) ?></td>
                                                    <td><?= date('Y-m-d H:i', strtotime($student['created_at'])) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <script defer src="theme.js"></script>
</body>
</html>

