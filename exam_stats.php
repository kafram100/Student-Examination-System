<?php
require 'db.php';
require 'auth.php';

requireLogin();

if (!isset($_GET['id'])) {
    die("Exam ID required.");
}

$exam_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Check ownership
$stmt = $pdo->prepare("SELECT * FROM exams WHERE id = ? AND user_id = ?");
$stmt->execute([$exam_id, $user_id]);
$exam = $stmt->fetch();

if (!$exam) {
    die("Exam not found.");
}

// Fetch Stats with Student Details
$stmt = $pdo->prepare("
    SELECT a.*, s.full_name, s.department, s.program,
           (
               SELECT COUNT(*) FROM answers ax
               JOIN questions qx ON ax.question_id = qx.id
               WHERE ax.attempt_id = a.id AND qx.q_type <> 'mcq'
           ) AS manual_count,
           (
               SELECT COUNT(*) FROM answers ax
               JOIN questions qx ON ax.question_id = qx.id
               WHERE ax.attempt_id = a.id AND qx.q_type <> 'mcq' AND ax.marks_awarded IS NOT NULL
           ) AS manual_graded
    FROM attempts a 
    LEFT JOIN students s ON a.student_index = s.index_number 
    WHERE a.exam_id = ? 
    ORDER BY a.score DESC
");
$stmt->execute([$exam_id]);
$attempts = $stmt->fetchAll();

// Calculate Summary
$total_attempts = count($attempts);
$avg_score = 0;
$highest_score = 0;
$lowest_score = 0;

if ($total_attempts > 0) {
    $scores = array_column($attempts, 'score');
    $avg_score = array_sum($scores) / $total_attempts;
    $highest_score = max($scores);
    $lowest_score = min($scores);
}
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Results - <?= htmlspecialchars($exam['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="theme.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .results-hero {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.25rem 1.5rem;
            border-radius: 16px;
            background: var(--theme-card-bg);
            border: 1px solid var(--theme-card-border);
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        .results-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
        }
        .results-subtitle {
            color: var(--theme-muted);
            margin: 0.25rem 0 0;
        }
        .breadcrumb {
            margin-bottom: 0;
            background: transparent;
        }
        .breadcrumb-item.active {
            color: var(--theme-muted);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            position: relative;
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.25rem 1.25rem;
            border-radius: 16px;
            background: var(--theme-card-bg);
            border: 1px solid var(--theme-card-border);
            box-shadow: 0 8px 22px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .stat-card::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 6px;
            background: var(--stat-accent);
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: var(--stat-accent);
            background: rgba(13, 110, 253, 0.12);
        }
        .stat-meta {
            display: flex;
            flex-direction: column;
        }
        .stat-label {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--theme-muted);
            font-weight: 600;
        }
        .stat-value {
            font-size: 1.9rem;
            font-weight: 700;
            color: var(--theme-text);
            line-height: 1.2;
        }
        .table-card {
            background: var(--theme-card-bg);
            border: 1px solid var(--theme-card-border);
            border-radius: 16px;
            padding: 0.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
        }
        .results-table thead th {
            background: var(--theme-table-stripe);
            color: var(--theme-text);
            border-bottom: 1px solid var(--theme-card-border);
            font-weight: 600;
        }
        .results-table tbody tr:hover {
            background: var(--theme-table-stripe);
        }
        .status-badge {
            font-size: 0.78rem;
            letter-spacing: 0.02em;
        }
        .action-col {
            white-space: nowrap;
        }
        body.theme-dark .stat-icon {
            background: rgba(255,255,255,0.06);
        }
        body.theme-dark .results-hero,
        body.theme-dark .table-card,
        body.theme-dark .stat-card {
            box-shadow: 0 12px 30px rgba(0,0,0,0.35);
        }
        body.theme-dark .results-hero {
            background: #141b23;
            border-color: #2a3546;
        }
        body.theme-dark .results-title {
            color: #e6edf3;
        }
        body.theme-dark .results-subtitle {
            color: #9aa4b2;
        }
        body.theme-dark .breadcrumb-item a {
            color: #8ab4ff;
        }
        body.theme-dark .breadcrumb-item + .breadcrumb-item::before {
            color: #8b98a8;
        }
        body.theme-dark .table-card {
            background: #141b23;
            border-color: #2a3546;
        }
        body.theme-dark .results-table thead th {
            background: #1b2430;
            color: #e6edf3;
            border-bottom-color: #2a3546;
        }
        body.theme-dark .results-table tbody td {
            color: #e6edf3;
        }
        body.theme-dark .results-table tbody tr {
            border-color: #2a3546;
        }
        body.theme-dark .stat-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01));
            border-color: #2a3546;
        }
        body.theme-dark .stat-value {
            color: #f1f5f9;
        }
        body.theme-dark .stat-label {
            color: #cbd5e1;
        }
        body.theme-dark .stat-icon {
            color: var(--stat-accent);
            background: rgba(138, 180, 255, 0.12);
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
        body.theme-dark .results-table {
            --bs-table-bg: #141b23;
            --bs-table-striped-bg: #1b2430;
            --bs-table-hover-bg: #1f2a38;
            --bs-table-color: #e6edf3;
        }
        .pdf-preview-frame {
            width: 100%;
            height: 620px;
            border: 1px solid var(--theme-card-border);
            border-radius: 12px;
            background: #fff;
            display: block;
        }
        .pdf-preview-body {
            min-height: 120px;
        }
        body.theme-dark .pdf-preview-frame {
            background: #0b1220;
            border-color: #2a3546;
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .results-hero {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }
            .results-title {
                font-size: 1.25rem;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .stat-card {
                padding: 1rem;
            }
            .stat-value {
                font-size: 1.5rem;
            }
            .table-card {
                overflow-x: auto;
            }
            .results-table {
                min-width: 600px;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }
            .results-hero {
                padding: 0.75rem;
            }
            .breadcrumb {
                font-size: 0.875rem;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            .stat-value {
                font-size: 1.25rem;
            }
            .stat-label {
                font-size: 0.75rem;
            }
            .btn {
                width: 100%;
            }
            .results-hero .btn-danger {
                width: 100%;
            }
        }
        
        /* Smart watch / Very small screens */
        @media (max-width: 320px) {
            .results-title {
                font-size: 1rem;
            }
            .results-subtitle {
                font-size: 0.875rem;
            }
            .stat-card {
                padding: 0.75rem;
            }
            .stat-icon {
                width: 32px;
                height: 32px;
                font-size: 0.875rem;
            }
            .stat-value {
                font-size: 1.125rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-modern mb-4">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Exam System</a>
            <form method="POST" action="logout.php" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <button type="submit" class="btn btn-outline-light btn-sm">Logout</button>
            </form>
        </div>
    </nav>

    <div class="container" id="results-content">
        <div class="results-hero">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="view_exam.php?id=<?= $exam_id ?>"><?= htmlspecialchars($exam['title']) ?></a></li>
                        <li class="breadcrumb-item active">Results</li>
                    </ol>
                </nav>
                <h2 class="results-title">Results: <?= htmlspecialchars($exam['title']) ?></h2>
                <p class="results-subtitle">Performance summary and attempt details</p>
            </div>
            <button onclick="exportToPDF()" class="btn btn-danger" id="export-btn">
                <i class="bi bi-file-earmark-pdf"></i> Export Results (PDF)
            </button>
        </div>

        <div class="stats-grid">
            <div class="stat-card" style="--stat-accent:#3b82f6;">
                <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                <div class="stat-meta">
                    <div class="stat-label">Attempts</div>
                    <div class="stat-value"><?= $total_attempts ?></div>
                </div>
            </div>
            <div class="stat-card" style="--stat-accent:#10b981;">
                <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
                <div class="stat-meta">
                    <div class="stat-label">Average</div>
                    <div class="stat-value"><?= round($avg_score, 1) ?></div>
                </div>
            </div>
            <div class="stat-card" style="--stat-accent:#0ea5e9;">
                <div class="stat-icon"><i class="bi bi-trophy-fill"></i></div>
                <div class="stat-meta">
                    <div class="stat-label">Highest</div>
                    <div class="stat-value"><?= $highest_score ?></div>
                </div>
            </div>
            <div class="stat-card" style="--stat-accent:#f59e0b;">
                <div class="stat-icon"><i class="bi bi-arrow-down-circle-fill"></i></div>
                <div class="stat-meta">
                    <div class="stat-label">Lowest</div>
                    <div class="stat-value"><?= $lowest_score ?></div>
                </div>
            </div>
        </div>

        <div class="table-card">
            <table class="table table-hover align-middle results-table mb-0">
                <thead>
                    <tr>
                        <th>Index Number</th>
                        <th>Full Name</th>
                        <th>Program</th>
                        <th>Score</th>
                        <th>Total Marks</th>
                        <th>%</th>
                        <th>Grading</th>
                        <th>Time Spent</th>
                        <th>Date</th>
                        <th class="no-print">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attempts as $attempt): ?>
                        <?php
                            $start = strtotime($attempt['start_time']);
                            $end = strtotime($attempt['submit_time']);
                            $duration = $end - $start;
                            $minutes = floor($duration / 60);
                            $seconds = $duration % 60;
                            $percentage = ($attempt['total_marks'] > 0) ? ($attempt['score'] / $attempt['total_marks']) * 100 : 0;
                            $manual_count = (int)($attempt['manual_count'] ?? 0);
                            $manual_graded = (int)($attempt['manual_graded'] ?? 0);
                            if ($manual_count === 0) {
                                $grade_label = 'Auto';
                                $grade_class = 'bg-success';
                                $grade_text = $grade_label;
                            } elseif ($manual_graded >= $manual_count) {
                                $grade_label = 'Graded';
                                $grade_class = 'bg-primary';
                                $grade_text = $grade_label . " ({$manual_graded}/{$manual_count})";
                            } else {
                                $grade_label = 'Pending';
                                $grade_class = 'bg-warning text-dark';
                                $grade_text = $grade_label . " ({$manual_graded}/{$manual_count})";
                            }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($attempt['student_index']) ?></td>
                            <td><?= htmlspecialchars($attempt['full_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($attempt['program'] ?? 'N/A') ?></td>
                            <td><strong><?= $attempt['score'] ?></strong></td>
                            <td><?= $attempt['total_marks'] ?></td>
                            <td><?= round($percentage, 2) ?>%</td>
                            <td><span class="badge status-badge <?= $grade_class ?>"><?= $grade_text ?></span></td>
                            <td><?= $minutes ?>m <?= $seconds ?>s</td>
                            <td><?= date('M d, Y H:i', strtotime($attempt['submit_time'])) ?></td>
                            <td class="no-print action-col">
                                <a href="grade_attempt.php?id=<?= $attempt['id'] ?>" class="btn btn-sm btn-outline-primary">View / Grade</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- PDF Preview section commented out
        <div class="table-card no-print pdf-preview-section mt-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-2">
                <div>
                    <h5 class="mb-0">PDF Preview</h5>
                    <small class="text-muted">Generate a preview before downloading.</small>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary btn-sm" onclick="previewPDF()" id="preview-btn">
                        <i class="bi bi-eye"></i> Preview PDF
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="clearPreview()" id="clear-preview-btn" disabled>
                        <i class="bi bi-x-circle"></i> Clear
                    </button>
                </div>
            </div>
            <div class="pdf-preview-body px-3 pb-3">
                <div id="pdf-preview-placeholder" class="text-muted small">
                    Click "Preview PDF" to generate a preview.
                </div>
                <div id="pdf-preview-loading" class="text-muted small d-none">Generating preview...</div>
                <iframe id="pdf-preview-frame" class="pdf-preview-frame d-none"></iframe>
            </div>
        </div>
        -->
    </div>

    <!-- PDF Export Script -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        let pdfPreviewUrl = null;

        function getPdfOptions() {
            return {
                margin:       [10, 10],
                filename:     'Results_<?= str_replace(" ", "_", preg_replace("/[^A-Za-z0-9 ]/", "", $exam["title"])) ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  {
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    backgroundColor: '#ffffff',
                    onclone: (doc) => {
                        preparePdfDocument(doc);
                    }
                },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };
        }

        function preparePdfDocument(doc) {
            const root = doc.getElementById('results-content');
            if (!root) {
                return;
            }

            doc.body.classList.remove('theme-dark');
            doc.documentElement.setAttribute('data-theme', 'light');

            // Set fixed width for PDF generation to ensure table doesn't compress
            root.style.width = '1000px';
            root.style.background = '#ffffff';
            root.style.color = '#111827';
            root.style.height = 'auto';
            root.style.maxHeight = 'none';
            root.style.overflow = 'visible';
            root.style.setProperty('--theme-bg', '#ffffff');
            root.style.setProperty('--theme-text', '#111827');
            root.style.setProperty('--theme-card-bg', '#ffffff');
            root.style.setProperty('--theme-card-border', '#e2e8f0');
            root.style.setProperty('--theme-muted', '#64748b');
            root.style.setProperty('--theme-table-stripe', '#f1f5f9');

            // Safely remove UI elements from PDF
            const breadcrumb = root.querySelector('nav');
            if (breadcrumb) breadcrumb.remove();

            const exportBtn = root.querySelector('#export-btn');
            if (exportBtn) exportBtn.remove();

            const previewSection = root.querySelector('.pdf-preview-section');
            if (previewSection) previewSection.remove();

            // Remove the summary cards row
            const summaryRow = root.querySelector('.stats-grid');
            if (summaryRow) summaryRow.remove();

            const noPrintEls = root.querySelectorAll('.no-print');
            noPrintEls.forEach(el => el.remove());

            // Remove PDF-only columns: Total Marks, %, Grading
            const pdfTable = root.querySelector('.results-table');
            if (pdfTable) {
                const headerCells = Array.from(pdfTable.querySelectorAll('thead th'));
                const removeLabels = ['Total Marks', '%', 'Grading'];
                const removeIndexes = headerCells
                    .map((th, idx) => ({ text: th.textContent.trim(), idx }))
                    .filter(item => removeLabels.includes(item.text))
                    .map(item => item.idx)
                    .sort((a, b) => b - a);

                if (removeIndexes.length) {
                    const rows = pdfTable.querySelectorAll('tr');
                    rows.forEach(row => {
                        const cells = row.children;
                        removeIndexes.forEach(i => {
                            if (cells[i]) {
                                cells[i].remove();
                            }
                        });
                    });
                }
            }

            // Remove the original page heading div (we have the custom PDF header)
            const pageHeading = root.querySelector('.results-hero');
            if (pageHeading) pageHeading.remove();

            const tableCard = root.querySelector('.table-card');
            if (tableCard) {
                tableCard.style.overflow = 'visible';
                tableCard.style.maxHeight = 'none';
                tableCard.style.height = 'auto';
            }

            const existingHeader = root.querySelector('.pdf-generated-header');
            if (existingHeader) {
                existingHeader.remove();
            }

            const header = doc.createElement('div');
            header.className = 'pdf-generated-header';
            header.innerHTML = `
                <div style="text-align: center; margin-bottom: 20px; font-family: Arial, sans-serif;">
                    <h1 style="color: #0d6efd; margin-bottom: 5px;">Official Exam Result Sheet</h1>
                    <h3 style="margin-bottom: 5px;"><?= htmlspecialchars($exam['title']) ?></h3>
                    <p style="margin-bottom: 5px;">
                        <strong>Course:</strong> <?= htmlspecialchars($exam['course_name']) ?>
                        <?= !empty($exam['course_code']) ? ' (' . htmlspecialchars($exam['course_code']) . ')' : '' ?>
                    </p>
                    <p style="color: #666; font-size: 12px;">Generated on: ${new Date().toLocaleString()}</p>
                    <hr style="border: 1px solid #ddd;">
                </div>
            `;
            root.prepend(header);
        }

        function setPreviewMessage(message, isError = false) {
            const placeholder = document.getElementById('pdf-preview-placeholder');
            if (!placeholder) return;
            placeholder.textContent = message;
            placeholder.classList.remove('d-none');
            placeholder.classList.toggle('text-danger', isError);
            placeholder.classList.toggle('text-muted', !isError);
        }

        function showPreviewLoading() {
            const placeholder = document.getElementById('pdf-preview-placeholder');
            const loading = document.getElementById('pdf-preview-loading');
            const frame = document.getElementById('pdf-preview-frame');
            if (placeholder) placeholder.classList.add('d-none');
            if (frame) frame.classList.add('d-none');
            if (loading) loading.classList.remove('d-none');
        }

        function showPreviewFrame(url) {
            const loading = document.getElementById('pdf-preview-loading');
            const frame = document.getElementById('pdf-preview-frame');
            if (loading) loading.classList.add('d-none');
            if (frame) {
                frame.src = url;
                frame.classList.remove('d-none');
                // Ensure the iframe scrolls to top when loaded
                frame.onload = function() {
                    try {
                        frame.contentWindow.scrollTo(0, 0);
                    } catch (e) {
                        // Cross-origin restrictions may prevent this
                    }
                };
            }
        }

        function exportToPDF() {
            const btn = document.getElementById('export-btn');
            const originalText = btn.innerHTML;

            // Get only the table card for export
            const tableCard = document.querySelector('.table-card');
            if (!tableCard) {
                alert('Unable to locate results table for export.');
                return;
            }

            // Create a wrapper for PDF content
            const wrapper = document.createElement('div');
            wrapper.style.padding = '0';
            wrapper.style.background = '#ffffff';
            wrapper.style.margin = '0';
            wrapper.style.position = 'absolute';
            wrapper.style.top = '0';
            wrapper.style.left = '0';

            // Add header
            const header = document.createElement('div');
            header.innerHTML = `
                <div style="text-align: center; padding: 10px 20px 15px 20px; font-family: Arial, sans-serif; background: #ffffff;">
                    <h1 style="color: #0d6efd; margin: 0 0 5px 0; font-size: 18px;">Official Exam Result Sheet</h1>
                    <h3 style="margin: 5px 0; font-size: 14px;"><?= htmlspecialchars($exam['title']) ?></h3>
                    <p style="margin: 5px 0; font-size: 12px;">
                        <strong>Course:</strong> <?= htmlspecialchars($exam['course_name']) ?>
                        <?= !empty($exam['course_code']) ? ' (' . htmlspecialchars($exam['course_code']) . ')' : '' ?>
                    </p>
                    <p style="color: #666; font-size: 10px; margin: 5px 0 0 0;">Generated on: ${new Date().toLocaleString()}</p>
                    <hr style="border: 1px solid #ddd; margin: 10px 0 0 0;">
                </div>
            `;
            wrapper.appendChild(header);

            // Clone table for export (without action, total marks, %, grading columns)
            const tableClone = tableCard.cloneNode(true);
            tableClone.style.margin = '0';
            tableClone.style.width = '100%';
            const actionCols = tableClone.querySelectorAll('.no-print');
            actionCols.forEach(el => el.remove());

            // Remove Total Marks, %, Grading columns from PDF
            const headerCells = Array.from(tableClone.querySelectorAll('thead th'));
            const removeLabels = ['Total Marks', '%', 'Grading'];
            const removeIndexes = headerCells
                .map((th, idx) => ({ text: th.textContent.trim(), idx }))
                .filter(item => removeLabels.includes(item.text))
                .map(item => item.idx)
                .sort((a, b) => b - a);

            if (removeIndexes.length) {
                const rows = tableClone.querySelectorAll('tr');
                rows.forEach(row => {
                    const cells = row.children;
                    removeIndexes.forEach(i => {
                        if (cells[i]) {
                            cells[i].remove();
                        }
                    });
                });
            }

            wrapper.appendChild(tableClone);
            
            // Show loading state
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Generating PDF...';
            btn.disabled = true;

            try {
                const opt = {
                    margin: [5, 5, 5, 5],
                    filename: 'Results_<?= str_replace(" ", "_", preg_replace("/[^A-Za-z0-9 ]/", "", $exam["title"])) ?>.pdf',
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: {
                        scale: 2,
                        useCORS: true,
                        logging: false,
                        backgroundColor: '#ffffff',
                        windowWidth: 1200
                    },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' },
                    pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
                };

                html2pdf().set(opt).from(wrapper).save().then(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }).catch(err => {
                    console.error("PDF Error:", err);
                    alert("Failed to generate PDF. Please try again or use Ctrl+P to print.");
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
            } catch (e) {
                console.error("Script Error:", e);
                alert("An error occurred: " + e.message);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

        function previewPDF() {
            const btn = document.getElementById('preview-btn');
            const clearBtn = document.getElementById('clear-preview-btn');
            const originalText = btn.innerHTML;

            // Get only the table card for preview
            const tableCard = document.querySelector('.table-card');
            if (!tableCard) {
                setPreviewMessage('Unable to locate results table for preview.', true);
                return;
            }

            // Create a wrapper for PDF content
            const wrapper = document.createElement('div');
            wrapper.style.padding = '0';
            wrapper.style.background = '#ffffff';
            wrapper.style.margin = '0';
            wrapper.style.position = 'absolute';
            wrapper.style.top = '0';
            wrapper.style.left = '0';

            // Add header
            const header = document.createElement('div');
            header.innerHTML = `
                <div style="text-align: center; padding: 10px 20px 15px 20px; font-family: Arial, sans-serif; background: #ffffff;">
                    <h1 style="color: #0d6efd; margin: 0 0 5px 0; font-size: 18px;">Official Exam Result Sheet</h1>
                    <h3 style="margin: 5px 0; font-size: 14px;"><?= htmlspecialchars($exam['title']) ?></h3>
                    <p style="margin: 5px 0; font-size: 12px;">
                        <strong>Course:</strong> <?= htmlspecialchars($exam['course_name']) ?>
                        <?= !empty($exam['course_code']) ? ' (' . htmlspecialchars($exam['course_code']) . ')' : '' ?>
                    </p>
                    <p style="color: #666; font-size: 10px; margin: 5px 0 0 0;">Generated on: ${new Date().toLocaleString()}</p>
                    <hr style="border: 1px solid #ddd; margin: 10px 0 0 0;">
                </div>
            `;
            wrapper.appendChild(header);

            // Clone table for preview (without action, total marks, %, grading columns)
            const tableClone = tableCard.cloneNode(true);
            tableClone.style.margin = '0';
            tableClone.style.width = '100%';
            const actionCols = tableClone.querySelectorAll('.no-print');
            actionCols.forEach(el => el.remove());

            // Remove Total Marks, %, Grading columns from preview
            const headerCells = Array.from(tableClone.querySelectorAll('thead th'));
            const removeLabels = ['Total Marks', '%', 'Grading'];
            const removeIndexes = headerCells
                .map((th, idx) => ({ text: th.textContent.trim(), idx }))
                .filter(item => removeLabels.includes(item.text))
                .map(item => item.idx)
                .sort((a, b) => b - a);

            if (removeIndexes.length) {
                const rows = tableClone.querySelectorAll('tr');
                rows.forEach(row => {
                    const cells = row.children;
                    removeIndexes.forEach(i => {
                        if (cells[i]) {
                            cells[i].remove();
                        }
                    });
                });
            }

            wrapper.appendChild(tableClone);

            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Generating Preview...';
            btn.disabled = true;
            showPreviewLoading();

            if (pdfPreviewUrl) {
                URL.revokeObjectURL(pdfPreviewUrl);
                pdfPreviewUrl = null;
            }

            try {
                const opt = {
                    margin: [5, 5, 5, 5],
                    filename: 'Results_<?= str_replace(" ", "_", preg_replace("/[^A-Za-z0-9 ]/", "", $exam["title"])) ?>.pdf',
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: {
                        scale: 2,
                        useCORS: true,
                        logging: false,
                        backgroundColor: '#ffffff',
                        windowWidth: 1200
                    },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' },
                    pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
                };

                html2pdf().set(opt).from(wrapper).toPdf().get('pdf').then(pdf => {
                    const blob = pdf.output('blob');
                    pdfPreviewUrl = URL.createObjectURL(blob);
                    showPreviewFrame(pdfPreviewUrl);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    if (clearBtn) clearBtn.disabled = false;
                }).catch(err => {
                    console.error("Preview Error:", err);
                    setPreviewMessage("Failed to generate preview. Please try again.", true);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
            } catch (e) {
                console.error("Preview Script Error:", e);
                setPreviewMessage("An error occurred while generating the preview.", true);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

        function clearPreview() {
            const frame = document.getElementById('pdf-preview-frame');
            const clearBtn = document.getElementById('clear-preview-btn');

            if (pdfPreviewUrl) {
                URL.revokeObjectURL(pdfPreviewUrl);
                pdfPreviewUrl = null;
            }

            if (frame) {
                frame.src = '';
                frame.classList.add('d-none');
            }

            setPreviewMessage('Click "Preview PDF" to generate a preview.');
            if (clearBtn) clearBtn.disabled = true;
        }
    </script>
    <script defer src="theme.js"></script>
</body>
</html>
