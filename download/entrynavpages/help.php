<?php
session_start();
require_once '../includes/db_connect.php';

// Fetch today's visitor count (Matching index logic)
$count_query = $conn->query("SELECT COUNT(*) as total FROM history_logs WHERE DATE(date) = CURDATE()");
$visitor_data = $count_query->fetch_assoc();
$todays_count = $visitor_data['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & Support | NEU Library Visitor Log</title>
    <link rel="icon" type="image/png" href="../assets/neu.png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --neu-blue: #2b6fff;
            --neu-blue-soft: #1b2d57;
            --neu-red: #ef4444;
            --bg: #0b0f17;
            --bg-2: #0d1220;
            --card: #121826;
            --card-2: #0f172a;
            --border: #1f2a44;
            --text: #e6edf5;
            --muted: #9aa7bd;
            --glow: 0 20px 60px rgba(8, 18, 40, 0.6);
        }
        
        /* Full Height Body Setup */
        html, body { height: 100%; margin: 0; padding: 0; }
        body {
            background:
                radial-gradient(1200px 600px at 20% -10%, rgba(43, 111, 255, 0.18), transparent 60%),
                radial-gradient(900px 500px at 90% 10%, rgba(16, 185, 129, 0.12), transparent 55%),
                var(--bg);
            color: var(--text);
            font-family: 'Plus Jakarta Sans', sans-serif;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Topbar & Header Styling */
        .support-shell { 
            background: rgba(10, 14, 24, 0.8);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .support-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
            padding: 15px 24px;
        }

        .btn-blue { background: linear-gradient(135deg, #2b6fff, #1c4ed8) !important; color: #fff !important; border: none; }
        .btn-admin {
            background: linear-gradient(135deg, #2b6fff, #1c4ed8) !important;
            color: #fff !important;
            border-radius: 12px;
            font-weight: 700;
            padding: 10px 20px;
            text-decoration: none;
            box-shadow: 0 10px 25px rgba(43, 111, 255, 0.3);
        }

        /* Typography Override for Dark Mode */
        .fw-800 { font-weight: 800; }
        .fw-600 { font-weight: 600; }
        .text-primary { color: var(--neu-blue) !important; }
        .text-dark { color: #fff !important; } /* Make bootstrap text-dark white */
        .text-muted { color: var(--muted) !important; }

        /* Centered Main Wrapper */
        .main-wrapper { 
            flex: 1; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            padding: 40px 24px; 
            width: 100%;
        }

        /* Side-by-Side Grid Layout */
        .support-grid {
            display: grid;
            width: 100%;
            max-width: 1280px;
            grid-template-columns: 1.4fr 1fr; /* Split the columns! */
            gap: 40px;
            align-items: start;
            margin: 0 auto;
        }

        /* Panel Card Styling */
        .support-panel {
            background: linear-gradient(180deg, rgba(18, 24, 38, 0.96), rgba(12, 18, 32, 0.98));
            border-radius: 26px;
            border: 1px solid var(--border);
            box-shadow: var(--glow);
            padding: 32px;
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        /* Form Inputs (Dark Theme) */
        .form-label-custom {
            font-size: 0.75rem;
            font-weight: 800;
            color: #8fb1ff;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .form-control, .form-select {
            background-color: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border);
            color: #fff;
            border-radius: 12px;
            padding: 12px 15px;
        }
        .form-control:focus, .form-select:focus {
            background-color: rgba(15, 23, 42, 1);
            border-color: var(--neu-blue);
            color: #fff;
            box-shadow: 0 0 0 0.25rem rgba(43, 111, 255, 0.25);
        }
        .form-select option { background-color: var(--bg-2); color: #fff; }

        /* Accordion (Dark Theme) */
        .accordion-item { 
            background-color: rgba(15, 23, 42, 0.4) !important; 
            border: 1px solid var(--border) !important; 
            margin-bottom: 1rem; 
            border-radius: 15px !important; 
            overflow: hidden;
        }
        .accordion-button { 
            background-color: transparent !important; 
            color: var(--text) !important; 
            box-shadow: none !important; 
            font-weight: 600; 
            font-size: 0.95rem; 
            padding: 18px 20px;
        }
        .accordion-button:not(.collapsed) { 
            background-color: rgba(43, 111, 255, 0.1) !important; 
            color: #fff !important; 
        }
        .accordion-button::after { filter: invert(1) grayscale(100%) brightness(200%); }
        .accordion-body { border-top: 1px solid var(--border); }

        /* Inner blocks */
        .bg-light-dark { background-color: rgba(15, 23, 42, 0.5); }
        .border-dashed { border: 1px dashed var(--border) !important; }

        footer { margin-top: auto; padding: 25px; text-align: center; color: #7a879e; font-size: 0.85rem; border-top: 1px solid var(--border); background: var(--bg-2); width: 100%; }

        /* Responsive Fixes */
        @media (max-width: 1024px) { 
            .support-grid { grid-template-columns: 1fr; max-width: 700px; }
        }
        @media (max-width: 768px) {
            .support-topbar { flex-direction: column; gap: 16px; text-align: center; }
            .main-wrapper { padding: 20px 16px; }
        }
    </style>
    <link rel="stylesheet" href="../assets/theme.css">
</head>
<body class="theme-dark">

    <div class="support-shell">
        <div class="support-topbar">
            <div class="d-flex align-items-center gap-2">
                <img src="../assets/neu.png" alt="NEU" width="36" onerror="this.style.display='none'">
                <div>
                    <div class="fw-bold text-white">NEU Library Support</div>
                    <div class="small text-muted">Help center and issue reporting</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="../index.php" class="btn btn-secondary text-white border-secondary">Home</a>
                <a href="../entrynavpages/qrmaker.php" class="btn btn-secondary text-white border-secondary">My QR Code</a>
                <a href="help.php" class="btn btn-blue">Help Center</a>
                <a href="../admin_dashboard/login.php" class="btn btn-admin">Admin Login</a>
            </div>
        </div>
    </div>

    <main class="main-wrapper">
        <div class="support-grid">
            
            <section class="support-panel">
                <?php if(isset($_GET['status']) && $_GET['status'] == 'success'): ?>
                    <div class="alert alert-success border-0 d-flex align-items-center shadow-sm mb-4" style="border-radius: 15px; background: rgba(16, 185, 129, 0.15); color: #10b981;">
                        <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                        <div>
                            <strong class="d-block text-white">Report Submitted</strong>
                            <span class="small">Our technical team has been notified.</span>
                        </div>
                    </div>
                <?php endif; ?>

                <h2 class="fw-800 text-dark mb-1">Help Center</h2>
                <p class="text-muted mb-4" style="font-size: 0.9rem;">Access support articles or report technical issues below. <br> You may also contact faculty members or the Front Desk for further assistance.</p>

                <div class="accordion accordion-flush mb-5" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q1">
                                <i class="bi bi-qr-code-scan me-3 text-primary"></i>QR Code Scanning Issues
                            </button>
                        </h2>
                        <div id="q1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted small">
                                <p>Glare from digital screens may interfere with scanning. Please try the following:</p>
                                <ul>
                                    <li>Set your phone’s brightness to maximum <strong>(100%)</strong>.</li>
                                    <li>Position your device approximately 6 inches away from the scanner.</li>
                                    <li><strong>Pro Tip:</strong> For faster and more reliable scanning, we recommend <strong>printing your QR code</strong> n a small card or attaching it to the back of your physical ID. Printed QR codes are generally detected more quickly than those displayed on mobile screens.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q2">
                            <i class="bi bi-person-x me-3 text-primary"></i>No Record Found

                            </button>
                        </h2>
                        <div id="q2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted small">
                                This message indicates that your ID number has not yet been synchronized with the Library Database. If you are a transferee or a first-year student, please allow up to two (2) working days for processing.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q3">
                                <i class="bi bi-door-closed me-3 text-primary"></i>Multiple Entries in One Day
                            </button>
                        </h2>
                        <div id="q3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted small">
                                The system is configured to record <strong>one primary entry per day</strong> for statistical purposes. If you temporarily leave the library and return within the same day, you are not required to scan again. Simply present your previous entry confirmation or your ID to the assigned personnel.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q4">
                                <i class="bi bi-question-circle me-3 text-primary"></i>Forgot Your QR Code
                            </button>
                        </h2>
                        <div id="q4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted small">
                                No problem. You may still access the library by using the <strong>Manual Entry</strong> option available on the Home page. Simply enter your 7-digit Student or Employee ID, or use your official institutional email address (e.g., @neu.edu.ph) to proceed with your entry.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q5">
                                <i class="bi bi-shield-lock me-3 text-primary"></i>Data Privacy and Protection
                            </button>
                        </h2>
                        <div id="q5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted small">
                               Your privacy is important to us. Only your ID and visit time are recorded for library access. All information is handled in accordance with the Data Privacy Act and is kept secure and confidential.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-4 rounded-4 bg-light-dark border-dashed">
                    <div class="d-flex align-items-center mb-4">
                        <div class="bg-primary text-white rounded-3 p-2 me-3" style="background: var(--neu-blue) !important;">
                            <i class="bi bi-tools"></i>
                        </div>
                        <h6 class="fw-800 mb-0 text-white">Report a Technical Issue</h6>
                    </div>

                    <form action="../entrynavpages/sendreport.php" method="POST">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label-custom">Student / Employee ID</label>
                                <input type="text" name="userID" class="form-control" placeholder="Enter your 7-digit ID (e.g., 2101234)" required maxlength="7">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label-custom">Issue Category</label>
                                <select name="issue_type" class="form-select">
                                    <option value="Scanner Issue">Account Not Found</option>
                                    <option value="Database Error">QR Code Generation Issue</option>
                                    <option value="QR Issue">Scanner Unresponsive</option>
                                    <option value="Other">Other Concerns</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label-custom">Detailed Description</label>
                                <textarea name="message" class="form-control" rows="3" placeholder="Provide a brief description of the issue encountered" required></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-800 mt-4 py-3 shadow-sm" style="border-radius: 12px; background: linear-gradient(135deg, #2b6fff, #1c4ed8); border:none;">
                            SUBMIT SUPPORT TICKET
                        </button>
                    </form>
                </div>
            </section>

            <aside class="support-panel">
                <h5 class="fw-800 mb-4 text-white">Library Support</h5>
                
                <div class="mb-4">
                    <label class="form-label-custom d-block">Location</label>
                    <div class="d-flex align-items-start">
                        <i class="bi bi-geo-alt-fill text-primary me-3 mt-1"></i>
                        <span class="fw-600 small text-white">Main Library, <br>New Era University Main Campus, No. 9, Central Avenue, Barangay New Era, Quezon City, Philippines, 1107</span>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label-custom d-block">Official Email</label>
                    <div class="d-flex align-items-center">
                        <i class="bi bi-envelope-at-fill text-primary me-3"></i>
                        <span class="fw-600 small text-white">clarkkent.zuniga@neu.edu.ph</span>
                    </div>
                </div>

                <div class="mb-5">
                    <label class="form-label-custom d-block">Service Hours</label>
                    <div class="d-flex align-items-start">
                        <i class="bi bi-clock-fill text-primary me-3 mt-1"></i>
                        <div>
                            <div class="fw-600 small text-white"> Monday to Friday, 8:00 AM to 7:00 PM
</div>
                            <div class="text-muted" style="font-size: 0.7rem;">No entry during weekends.</div>
                        </div>
                    </div>
                </div>

                <div class="p-4 rounded-4 border shadow-sm" style="background-color: rgba(15, 23, 42, 0.6); border-color: var(--border) !important;">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background: rgba(43, 111, 255, 0.15); color: var(--neu-blue);">
                                <i class="bi bi-graph-up-arrow fs-5"></i>
                            </div>
                        </div>
                        <div class="ms-3">
                            <div class="text-muted small fw-800 text-uppercase" style="font-size: 0.7rem; letter-spacing: 1px;">Today's Visitors</div>
                            <div class="fw-800 h4 mb-0 text-white"><?php echo $todays_count; ?> <span class="text-muted fs-6 fw-600">Visitors</span></div>
                        </div>
                    </div>
                </div>
            </aside>

        </div>
    </main>

    <footer>&copy; <?php echo date('Y'); ?> NEU Library Visitor Log System. All rights reserved.</footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>