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
    SELECT a.*, a.student_fullname as full_name, a.student_index as index_number,
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
    WHERE a.exam_id = ? AND a.status = 'completed'
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
                        <th>Score</th>
                        <th>Total Marks</th>
                        <th>Time Spent</th>
                        <th>Date</th>
                        <th class="no-print">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attempts)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No completed attempts yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($attempts as $attempt): ?>
                            <?php
                                $start = !empty($attempt['start_time']) ? strtotime($attempt['start_time']) : false;
                                $end = !empty($attempt['submit_time']) ? strtotime($attempt['submit_time']) : false;
                                $duration = ($start !== false && $end !== false && $end >= $start) ? ($end - $start) : 0;
                                $minutes = floor($duration / 60);
                                $seconds = $duration % 60;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($attempt['student_index']) ?></td>
                                <td><?= htmlspecialchars($attempt['full_name'] ?? 'N/A') ?></td>
                                <td><strong><?= $attempt['score'] ?></strong></td>
                                <td><?= $attempt['total_marks'] ?></td>
                                <td><?= $minutes ?>m <?= $seconds ?>s</td>
                                <td><?= !empty($attempt['submit_time']) ? date('M d, Y H:i', strtotime($attempt['submit_time'])) : 'N/A' ?></td>
                                <td class="no-print action-col">
                                    <a href="grade_attempt.php?id=<?= $attempt['id'] ?>" class="btn btn-sm btn-outline-primary">View / Grade</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        let pdfPreviewUrl = null;
        const PDF_TITLE = 'Official Results Sheet';
        const PDF_FILENAME = 'Results_<?= str_replace(" ", "_", preg_replace("/[^A-Za-z0-9 ]/", "", $exam["title"])) ?>.pdf';
        const PDF_COLUMNS = ['Index Number', 'Full Name', 'Score'];

        function getResultsRows() {
            const table = document.querySelector('.results-table');
            if (!table) return null;

            return Array.from(table.querySelectorAll('tbody tr')).map(function (tr) {
                const tds = tr.querySelectorAll('td');
                return [
                    tds[0] ? tds[0].textContent.trim() : '',
                    tds[1] ? tds[1].textContent.trim() : '',
                    tds[2] ? tds[2].textContent.trim() : ''
                ];
            });
        }

        function buildPdfHeaderHtml() {
            return `
                <div style="text-align: center; padding: 10px 20px 15px 20px; font-family: Arial, sans-serif; background: #ffffff;">
                    <h1 style="color: #0d6efd; margin: 0 0 5px 0; font-size: 18px;">${PDF_TITLE}</h1>
                    <h3 style="margin: 5px 0; font-size: 14px;"><?= htmlspecialchars($exam['title']) ?></h3>
                    <p style="margin: 5px 0; font-size: 12px;">
                        <strong>Course:</strong> <?= htmlspecialchars($exam['course_name']) ?>
                        <?= !empty($exam['course_code']) ? ' (' . htmlspecialchars($exam['course_code']) . ')' : '' ?>
                    </p>
                    <p style="color: #666; font-size: 10px; margin: 5px 0 0 0;">Generated on: ${new Date().toLocaleString()}</p>
                    <hr style="border: 1px solid #ddd; margin: 10px 0 0 0;">
                </div>
            `;
        }

        function buildPdfTableFromRows(rows) {
            const pdfTable = document.createElement('table');
            pdfTable.style.width = '100%';
            pdfTable.style.borderCollapse = 'collapse';
            pdfTable.style.fontFamily = 'Arial, sans-serif';
            pdfTable.style.fontSize = '12px';
            pdfTable.style.color = '#111827';
            pdfTable.style.backgroundColor = '#ffffff';

            const thead = document.createElement('thead');
            const headRow = document.createElement('tr');
            PDF_COLUMNS.forEach(function (label) {
                const th = document.createElement('th');
                th.textContent = label;
                th.style.backgroundColor = '#f8fafc';
                th.style.color = '#111827';
                th.style.border = '1px solid #e2e8f0';
                th.style.padding = '8px 10px';
                th.style.textAlign = 'left';
                headRow.appendChild(th);
            });
            thead.appendChild(headRow);
            pdfTable.appendChild(thead);

            const tbody = document.createElement('tbody');
            rows.forEach(function (rowData, rowIndex) {
                const tr = document.createElement('tr');
                tr.style.backgroundColor = (rowIndex % 2 === 0) ? '#ffffff' : '#f9fafb';

                rowData.forEach(function (cellValue) {
                    const td = document.createElement('td');
                    td.textContent = String(cellValue || '');
                    td.style.backgroundColor = 'transparent';
                    td.style.color = '#111827';
                    td.style.border = '1px solid #e2e8f0';
                    td.style.padding = '8px 10px';
                    td.style.textAlign = 'left';
                    tr.appendChild(td);
                });

                tbody.appendChild(tr);
            });
            pdfTable.appendChild(tbody);

            return pdfTable;
        }

        function createPdfWrapperFromRows(rows, hidden = false) {
            const wrapper = document.createElement('div');
            wrapper.style.padding = '0';
            wrapper.style.background = '#ffffff';
            wrapper.style.color = '#111827';
            wrapper.style.margin = '0';
            wrapper.style.position = 'fixed';
            wrapper.style.top = '0';
            wrapper.style.left = '0';
            wrapper.style.width = '1200px';
            wrapper.style.zIndex = '-1';

            if (hidden) {
                wrapper.style.opacity = '0';
                wrapper.style.pointerEvents = 'none';
            }

            const header = document.createElement('div');
            header.innerHTML = buildPdfHeaderHtml();
            wrapper.appendChild(header);
            wrapper.appendChild(buildPdfTableFromRows(rows));

            return wrapper;
        }

        function getHtml2PdfOptions(filename) {
            return {
                margin: [5, 5, 5, 5],
                filename: filename,
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

        async function exportToPDF() {
            const btn = document.getElementById('export-btn');
            const originalText = btn.innerHTML;
            const rows = getResultsRows();

            if (!rows) {
                alert('Unable to locate results table for export.');
                return;
            }

            const tryHtml2PdfFallback = function () {
                return new Promise(function (resolve, reject) {
                    if (typeof html2pdf !== 'function') {
                        reject(new Error('html2pdf is not available'));
                        return;
                    }

                    const wrapper = createPdfWrapperFromRows(rows, true);
                    document.body.appendChild(wrapper);

                    const cleanup = function () {
                        if (wrapper && wrapper.parentNode) {
                            wrapper.parentNode.removeChild(wrapper);
                        }
                    };

                    html2pdf().set(getHtml2PdfOptions(PDF_FILENAME)).from(wrapper).save().then(function () {
                        cleanup();
                        resolve();
                    }).catch(function (err) {
                        cleanup();
                        reject(err);
                    });
                });
            };

            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Generating PDF...';
            btn.disabled = true;

            try {
                const JsPdfCtor = (window.jspdf && window.jspdf.jsPDF) ? window.jspdf.jsPDF : (window.jsPDF || null);

                if (!JsPdfCtor) {
                    await tryHtml2PdfFallback();
                } else {
                    const doc = new JsPdfCtor({ unit: 'mm', format: 'a4', orientation: 'landscape' });
                    const pageWidth = doc.internal.pageSize.getWidth();
                    const pageHeight = doc.internal.pageSize.getHeight();
                    const marginX = 10;
                    const marginY = 10;
                    const usableWidth = pageWidth - (marginX * 2);
                    const colWidths = [
                        Math.round(usableWidth * 0.22),
                        Math.round(usableWidth * 0.56),
                        Math.round(usableWidth * 0.22)
                    ];

                    const tableX = marginX;
                    const rowHeight = 8;
                    const headerRowHeight = 9;
                    let y = marginY;

                    const drawTitleBlock = function () {
                        doc.setFont('helvetica', 'bold');
                        doc.setFontSize(16);
                        doc.text(PDF_TITLE, pageWidth / 2, y, { align: 'center' });
                        y += 7;

                        doc.setFontSize(12);
                        doc.text('<?= htmlspecialchars($exam['title']) ?>', pageWidth / 2, y, { align: 'center' });
                        y += 6;

                        doc.setFont('helvetica', 'normal');
                        doc.setFontSize(10);
                        doc.text('Course: <?= htmlspecialchars($exam['course_name']) ?><?= !empty($exam['course_code']) ? ' (' . htmlspecialchars($exam['course_code']) . ')' : '' ?>', pageWidth / 2, y, { align: 'center' });
                        y += 5;
                        doc.text('Generated on: ' + new Date().toLocaleString(), pageWidth / 2, y, { align: 'center' });
                        y += 5;

                        doc.setDrawColor(210, 210, 210);
                        doc.line(marginX, y, pageWidth - marginX, y);
                        y += 4;
                    };

                    const drawTableHeader = function () {
                        let x = tableX;

                        doc.setFont('helvetica', 'bold');
                        doc.setFontSize(10);
                        doc.setTextColor(17, 24, 39);

                        PDF_COLUMNS.forEach(function (label, idx) {
                            doc.text(label, x + 1.5, y + 5.5);
                            x += colWidths[idx];
                        });

                        doc.setDrawColor(226, 232, 240);
                        doc.line(tableX, y + 7, tableX + usableWidth, y + 7);
                        y += headerRowHeight;
                    };

                    const drawRow = function (cells) {
                        let x = tableX;
                        doc.setFont('helvetica', 'normal');
                        doc.setFontSize(10);
                        doc.setTextColor(17, 24, 39);

                        cells.forEach(function (value, idx) {
                            const text = String(value || '');
                            const lines = doc.splitTextToSize(text, colWidths[idx] - 3);
                            const clipped = lines.length ? lines[0] : '';
                            doc.text(clipped, x + 1.5, y + 5.5);
                            x += colWidths[idx];
                        });

                        doc.setDrawColor(241, 245, 249);
                        doc.line(tableX, y + rowHeight - 1, tableX + usableWidth, y + rowHeight - 1);
                        y += rowHeight;
                    };

                    drawTitleBlock();
                    drawTableHeader();

                    if (!rows.length) {
                        doc.setFont('helvetica', 'italic');
                        doc.setFontSize(10);
                        doc.text('No results available.', tableX + 2, y + 6);
                    } else {
                        rows.forEach(function (cells) {
                            if (y + rowHeight > pageHeight - marginY) {
                                doc.addPage();
                                y = marginY;
                                drawTitleBlock();
                                drawTableHeader();
                            }
                            drawRow(cells);
                        });
                    }

                    doc.save(PDF_FILENAME);
                }
            } catch (err) {
                console.error('Primary PDF export failed:', err);
                try {
                    await tryHtml2PdfFallback();
                } catch (fallbackErr) {
                    console.error('Fallback PDF export failed:', fallbackErr);
                    const msg = (fallbackErr && fallbackErr.message) ? fallbackErr.message : 'Unknown error';
                    alert('Failed to generate PDF. Please try again. (' + msg + ')');
                }
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }
        function previewPDF() {
            const btn = document.getElementById('preview-btn');
            const clearBtn = document.getElementById('clear-preview-btn');
            const originalText = btn.innerHTML;
            const rows = getResultsRows();

            if (!rows) {
                setPreviewMessage('Unable to locate results table for preview.', true);
                return;
            }

            const wrapper = createPdfWrapperFromRows(rows, true);
            document.body.appendChild(wrapper);

            const cleanup = function () {
                if (wrapper && wrapper.parentNode) {
                    wrapper.parentNode.removeChild(wrapper);
                }
            };

            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Generating Preview...';
            btn.disabled = true;
            showPreviewLoading();

            if (pdfPreviewUrl) {
                URL.revokeObjectURL(pdfPreviewUrl);
                pdfPreviewUrl = null;
            }

            try {
                html2pdf().set(getHtml2PdfOptions(PDF_FILENAME)).from(wrapper).toPdf().get('pdf').then(pdf => {
                    cleanup();
                    const blob = pdf.output('blob');
                    pdfPreviewUrl = URL.createObjectURL(blob);
                    showPreviewFrame(pdfPreviewUrl);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    if (clearBtn) clearBtn.disabled = false;
                }).catch(err => {
                    cleanup();
                    console.error('Preview Error:', err);
                    setPreviewMessage('Failed to generate preview. Please try again.', true);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
            } catch (e) {
                cleanup();
                console.error('Preview Script Error:', e);
                setPreviewMessage('An error occurred while generating the preview.', true);
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










