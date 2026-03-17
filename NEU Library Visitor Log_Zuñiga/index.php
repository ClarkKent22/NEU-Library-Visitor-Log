<?php
session_start();
include 'includes/db_connect.php';

// Logic Fix: Only clear sessions if there is NO error and NO login attempt
if (!isset($_POST['check_user']) && !isset($_GET['error'])) {
    unset($_SESSION['user_id']);
    unset($_SESSION['role']);
}

$show_error_modal = false; 
if (isset($_GET['error']) && $_GET['error'] == 1) {
    $show_error_modal = true;
}

if (isset($_POST['check_user'])) {
    $user_id = $_POST['user_id'];
    $user_email = $_POST['user_email'];
    
    // 1. First, find the valid ID and user details from Students or Employees
    $found_id = null;
    $user_data = null;

    // Search Students
    $stmt = $conn->prepare("SELECT studentID, firstName, lastName, profile_image, departmentID, status, block_reason FROM students WHERE studentID = ? OR institutionalEmail = ?");
    $stmt->bind_param("ss", $user_id, $user_email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) { 
        $found_id = $row['studentID']; 
        $user_data = $row;
        $user_data['type'] = 'student';
    }

    if (!$found_id) {
        // Search Employees
        $stmt = $conn->prepare("SELECT emplID, firstName, lastName, profile_image, departmentID, status, block_reason FROM employees WHERE emplID = ? OR institutionalEmail = ?");
        $stmt->bind_param("ss", $user_id, $user_email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) { 
            $found_id = $row['emplID']; 
            $user_data = $row;
            $user_data['type'] = 'admin'; 
        }
    }

    // 2. CHECK IF BLOCKED BEFORE PROCEEDING
    if ($found_id) {
        if (strtolower($user_data['status']) === 'blocked') {
            $_SESSION['blocked_user'] = $user_data;
            header("Location: index.php?error=blocked");
            exit();
        }

        // 3. If NOT blocked, check if they already logged in TODAY
        $check_log = $conn->prepare("SELECT logID FROM history_logs WHERE user_identifier = ? AND date = CURDATE()");
        $check_log->bind_param("s", $found_id);
        $check_log->execute();
        if ($check_log->get_result()->num_rows > 0) {
            header("Location: index.php?error=already_logged");
            exit();
        } else {
            $_SESSION['user_id'] = $found_id;
            header("Location: entrynavpages/Visitorentryform.php");
            exit();
        }
    } else {
        header("Location: index.php?error=1");
        exit();
    }
}

$count_query = $conn->query("SELECT COUNT(*) as total FROM history_logs WHERE DATE(date) = CURDATE()");
$visitor_data = $count_query->fetch_assoc();
$todays_count = $visitor_data['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>NEU Library Visitor Log System</title>
    <link rel="icon" type="image/png" href="assets/neu.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://unpkg.com/html5-qrcode"></script>
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
        
        /* Universal Box Sizing to prevent layout blowouts */
        *, *::before, *::after {
            box-sizing: border-box;
        }

        html, body { 
            height: 100%; 
            margin: 0; 
            overflow-x: hidden; /* Prevent horizontal scrolling */
        }
        
        body {
            background:
                radial-gradient(1200px 600px at 20% -10%, rgba(43, 111, 255, 0.18), transparent 60%),
                radial-gradient(900px 500px at 90% 10%, rgba(16, 185, 129, 0.12), transparent 55%),
                var(--bg);
            color: var(--text);
            font-family: 'Plus Jakarta Sans', sans-serif;
            display: block;
        }

        .support-shell { 
            background: rgba(10, 14, 24, 0.8);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 1000;
            margin-bottom: 30px; 
        }
        .support-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 15px 20px;
        }

        .btn-admin {
            background: linear-gradient(135deg, #2b6fff, #1c4ed8) !important;
            color: #fff !important;
            border-radius: 12px;
            font-weight: 700;
            padding: 10px 20px;
            text-decoration: none;
            box-shadow: 0 10px 25px rgba(43, 111, 255, 0.3);
        }
        .main-wrapper { flex: 1 0 auto; display: flex; justify-content: center; align-items: center; padding: 48px 20px 40px; }
        
        .toast-container { position: fixed; top: 90px; right: 20px; z-index: 9999; }
        .custom-toast {
            background: var(--card);
            border-left: 4px solid var(--neu-red);
            padding: 16px 22px;
            border-radius: 14px;
            box-shadow: var(--glow);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 320px;
            transform: translateX(120%);
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .custom-toast.show { transform: translateX(0); }
        
        /* FIXED GRID CONTAINER */
        .kiosk-shell {
            display: grid !important;
            width: 100%;
            max-width: 1200px;
            /* Proportioned columns: Left side flexible, Right side contained */
            grid-template-columns: minmax(0, 1.2fr) minmax(320px, 380px) !important; 
            gap: 30px;
            align-items: start;
            margin: 0 auto 30px;
            padding: 0 20px; /* Ensure spacing on smaller screens */
        }

        /* Prevent children from stretching the grid horizontally */
        .kiosk-hero, .kiosk-card { 
            min-width: 0; 
            width: 100%;
        }

        .kiosk-hero { padding: 0; }
        
        .slider-wrapper {
            position: relative;
            height: 320px;
            border-radius: 22px;
            overflow: hidden;
            border: 1px solid var(--border);
            box-shadow: var(--glow);
            width: 100%;
        }
        .slides-container { display: flex; height: 100%; transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1); }
        .slide { min-width: 100%; height: 100%; background-size: cover; background-position: center; display: flex; flex-direction: column; justify-content: flex-end; padding: 28px; position: relative; }
        .slide::after { content: ''; position: absolute; inset: 0; background: linear-gradient(transparent, rgba(3, 7, 18, 0.9)); }
        .slide-content { position: relative; z-index: 2; color: white; }
        .arrow { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(15, 23, 42, 0.7); border: 1px solid var(--border); color: white; border-radius: 50%; width: 44px; height: 44px; z-index: 10; cursor: pointer; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px); transition: 0.3s; }
        .arrow:hover { background: rgba(43, 111, 255, 0.35); }
        .arrow-left { left: 20px; } .arrow-right { right: 20px; }
        .dots { position: absolute; bottom: 18px; left: 50%; transform: translateX(-50%); display: flex; gap: 8px; z-index: 10; }
        .dot { width: 10px; height: 10px; background: rgba(255,255,255,0.3); border-radius: 50%; border: none; cursor: pointer; padding: 0; }
        .dot.active { background: #fff; transform: scale(1.2); }
        
        .kiosk-metrics { 
            display: grid !important;
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important; 
            gap: 15px; 
            margin-top: 24px;
            width: 100%;
        }
        .metric { 
            padding: 18px; 
            border-radius: 16px; 
            background: var(--card-2);
            border: 1px solid var(--border);
            text-align: center;
        }
        .metric .label {
            font-size: 0.75rem;
            color: var(--muted);
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .metric .value {
            font-size: 1.05rem;
            font-weight: 800;
            color: #fff;
        }

        .kiosk-card { 
            padding: 28px; 
            background: linear-gradient(180deg, rgba(18, 24, 38, 0.96), rgba(12, 18, 32, 0.98));
            border-radius: 26px;
            border: 1px solid var(--border);
            box-shadow: var(--glow);
        }

        .divcardmain { width: 100%; flex-shrink: 0; }
        
        .scanner-instruction {
            text-align: center;
            font-size: 0.7rem;
            font-weight: 800;
            color: #8fb1ff;
            letter-spacing: 2px;
            margin-bottom: 16px;
            text-transform: uppercase;
        }
        .scanner-viewport {
            width: 100%;
            height: 240px;
            background: #0b1220;
            border-radius: 22px;
            margin: 0 auto 24px auto;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--border);
            box-shadow: inset 0 0 40px rgba(0,0,0,0.4);
        }
        #reader { width: 100% !important; height: 100% !important; }
        #reader video { width: 100% !important; height: 100% !important; object-fit: cover !important; }
        .scanner-mask { position: absolute; inset: 0; background: rgba(2, 6, 23, 0.4); z-index: 5; pointer-events: none; }
        .scanner-frame { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 180px; height: 180px; z-index: 10; box-shadow: 0 0 0 1000px rgba(0, 0, 0, 0.25); }
        .bracket { position: absolute; width: 22px; height: 22px; border: 3px solid #8fb1ff; }
        .tl { top: 0; left: 0; border-right: none; border-bottom: none; } .tr { top: 0; right: 0; border-left: none; border-bottom: none; }
        .bl { bottom: 0; left: 0; border-right: none; border-top: none; } .br { bottom: 0; right: 0; border-left: none; border-top: none; }
        .scanning-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #e2e8f0; font-size: 0.7rem; font-weight: 700; letter-spacing: 3px; z-index: 11; text-shadow: 0 2px 4px rgba(0,0,0,0.5); animation: blink 1.5s infinite; }
        @keyframes blink { 0%, 100% { opacity: 0.35; } 50% { opacity: 1; } }
        .laser { position: absolute; width: 100%; height: 2px; background: #2b6fff; box-shadow: 0 0 14px #2b6fff; top: 0; z-index: 12; animation: scanline 2.5s infinite ease-in-out alternate; }
        @keyframes scanline { 0% { top: 0%; } 100% { top: 100%; } }
        #cam-switch-btn { position: absolute; bottom: 12px; right: 12px; z-index: 25; background: rgba(15, 23, 42, 0.95); border: 1px solid var(--border); padding: 6px 10px; border-radius: 10px; color: #e2e8f0; display: none; align-items: center; gap: 6px; box-shadow: 0 6px 10px rgba(0,0,0,0.3); cursor: pointer; }
        #cam-switch-btn span { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; }
        
        .divider { display: flex; align-items: center; text-align: center; margin: 24px 0; color: #718096; font-size: 0.7rem; font-weight: 800; letter-spacing: 1px; }
        .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid var(--border); }
        .divider::before { margin-right: 15px; } .divider::after { margin-left: 15px; }
        footer { flex-shrink: 0; padding: 25px; text-align: center; color: #7a879e; font-size: 0.85rem; border-top: 1px solid var(--border); background: var(--bg-2); }
        .italic { font-style: italic; }

        @media (max-width: 1150px) { 
            .kiosk-shell {
                grid-template-columns: 1fr !important;
                max-width: 700px;
                padding: 0 20px;
            }
        }
        @media (max-width: 768px) {
            #cam-switch-btn { display: flex; }
            .support-topbar { flex-direction: column; gap: 16px; text-align: center; }
            .kiosk-metrics { grid-template-columns: 1fr !important; }
        }
    </style>
    <link rel="stylesheet" href="assets/theme.css">
</head>
<body class="theme-dark">

    <audio id="scanSound" src="assets/beep.mp3" preload="auto"></audio>

    <div class="toast-container" id="toastContainer">
        <div class="custom-toast" id="errorToast">
            <i class="bi bi-exclamation-circle-fill text-danger fs-5 me-2"></i>
            <span id="toastMsg" style="font-weight:600; font-size:0.9rem; color:#334155;">Error</span>
        </div>
    </div>

    <div class="support-shell">
        <div class="support-topbar">
            <div class="d-flex align-items-center gap-2">
                <img src="assets/neu.png" alt="NEU" width="36" onerror="this.style.display='none'">
                <div>
                    <div class="fw-bold">NEU Library</div>
                    <div class="small text-muted">Visitor log system</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="index.php" class="btn btn-blue">Home</a>
                <a href="entrynavpages/qrmaker.php" class="btn btn-secondary">My QR Code</a>
                <a href="entrynavpages/help.php" class="btn btn-secondary">Help</a>
                <a href="admin_dashboard/login.php" class="btn btn-admin">Admin Login</a>
            </div>
        </div>
    </div>

    <div class="kiosk-shell">
        <div class="kiosk-hero">
            <div class="slider-wrapper">
                    <button class="arrow arrow-left" onclick="plusSlides(-1)"><i class="bi bi-chevron-left"></i></button>
                    <button class="arrow arrow-right" onclick="plusSlides(1)"><i class="bi bi-chevron-right"></i></button>
                    <div class="slides-container" id="slides">
                        <div class="slide" style="background-image: url('assets/sildeshow image/library1.jpg'); background-color: #333;"><div class="slide-content"><h1>Welcome to NEU</h1><p>Excellence in academic research.</p></div></div>
                        <div class="slide" style="background-image: url('assets/sildeshow image/library2.jpg'); background-color: #444;"><div class="slide-content"><h1>Digital Learning Hub</h1><p>Access global knowledge and resources.</p></div></div>
                        <div class="slide" style="background-image: url('assets/sildeshow image/library3.jpg'); background-color: #555;"><div class="slide-content"><h1>Quiet Study Spaces</h1><p>A focused space for learning</p></div></div>
                        <div class="slide" style="background-image: url('assets/sildeshow image/library4.jpg'); background-color: #666;"><div class="slide-content"><h1>Innovation and Technology</h1><p>Empowering modern learning through technology.</p></div></div>
                        <div class="slide" style="background-image: url('assets/sildeshow image/library5.jpg'); background-color: #777;"><div class="slide-content"><h1>Library Resources</h1><p>Explore thousands of academic materials.</p></div></div>
                    </div>
                    <div class="dots" id="dots"></div>
            </div>
            <div class="kiosk-metrics">
                <div class="metric">
                    <div class="label">Library Hours</div>
                    <div class="value">8:00 AM to 7:00 PM</div>
                </div>
                <div class="metric">
                    <div class="label">Scan Ready</div>
                    <div class="value">ID or QR Code</div>
                </div>
                <div class="metric">
                    <div class="label">Today</div>
                    <div class="value"><?php echo $todays_count; ?> Visitors</div>
                </div>
            </div>
        </div>

        <div class="kiosk-card">
            <div class="divcardmain" style="box-shadow:none; border:none; padding:0; background:transparent;">
                <?php if (isset($_GET['error']) && $_GET['error'] == 'already_logged'): ?>
                    <div class="alert alert-warning alert-dismissible fade show p-2 mb-3 shadow-sm" role="alert" style="border-radius: 12px; border-left: 4px solid #ffc107;">
                        <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill text-warning fs-5 me-2"></i>
                            <div style="font-size: 0.75rem; line-height: 1.2;">
                            <strong class="d-block">ACCESS DENIED</strong>
                                You have already recorded an entry for today.
                            </div>
                        <button type="button" class="btn-close small" data-bs-dismiss="alert" style="padding: 0.8rem;"></button>
                        </div>
                    </div>
                <?php endif; ?>

                <?php 
                if (isset($_GET['status']) && $_GET['status'] == 'success'): 
                    $stmt = $conn->prepare("
                        SELECT h.date, h.time, h.reason,
                               COALESCE(s.firstName, e.firstName) as fName, 
                               COALESCE(s.lastName, e.lastName) as lName,
                               COALESCE(s.departmentID, e.departmentID) as dept,
                               COALESCE(s.profile_image, e.profile_image) as img,
                               CASE WHEN s.studentID IS NOT NULL THEN 'Student' ELSE 'Faculty/Staff' END as display_role,
                               CASE WHEN s.studentID IS NOT NULL THEN 'student' ELSE 'admin' END as user_type
                        FROM history_logs h
                        LEFT JOIN students s ON h.user_identifier = s.studentID
                        LEFT JOIN employees e ON h.user_identifier = e.emplID
                        ORDER BY h.logID DESC LIMIT 1
                    ");
                    $stmt->execute();
                    $logData = $stmt->get_result()->fetch_assoc();
                ?>
                <div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="true" data-bs-keyboard="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content text-center border-0 shadow-lg" style="border-radius: 30px; overflow: hidden; position: relative;">
                            <div class="modal-body p-5">
                                <div class="mb-4 mt-2">
                                    <img src="assets/neu.png" alt="NEU Logo" class="mb-2" style="height: 70px;">
                                    <div class="fw-bold mb-3" style="color: white; font-size: 1.1rem; letter-spacing: 0.5px;">
                                        NEW ERA UNIVERSITY LIBRARY
                                    </div>
                                    
                                    <div class="mx-auto bg-success d-flex align-items-center justify-content-center shadow" style="width: 90px; height: 90px; border-radius: 50%;">
                                        <i class="bi bi-check-lg text-white" style="font-size: 3rem;"></i>
                                    </div>
                                    <h2 class="fw-bold mt-3 mb-0" style="color: #0038a8;">LOGGED SUCCESSFULLY</h2>
                                    <p class="text-muted fw-semibold">Welcome to the NEU Library!</p>
                                </div>
                                <div class="row align-items-center text-start bg-secondary-subtle rounded-4 p-4 mx-md-2">
                                    <div class="col-md-4 text-center mb-3 mb-md-0">
                                        <img src="profilepictures/<?php echo $logData['user_type']; ?>/<?php echo $logData['img']; ?>"
                                            class="rounded-circle border border-5 border-white shadow-sm" 
                                            style="width: 160px; height: 160px; object-fit: cover;"
                                            onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($logData['fName'] . ' ' . $logData['lName']); ?>&background=0038a8&color=fff&bold=true'">
                                    </div>
                                    <div class="col-md-8 ps-md-4">
                                        <div class="mb-3">
                                            <label class="small text-uppercase fw-bold text-muted">Name</label>
                                            <div class="h3 fw-bold text-white mb-0"><?php echo $logData['fName'] . ' ' . $logData['lName']; ?></div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-4">
                                                <label class="small text-uppercase fw-bold text-muted">Role</label>
                                                <div class="h6 text-white mb-0 fw-bold"><?php echo $logData['display_role']; ?></div>
                                            </div>
                                            <div class="col-4">
                                                <label class="small text-uppercase fw-bold text-muted">Department</label>
                                                <div class="h6 text-white fw-bold mb-0"><?php echo $logData['dept']; ?></div>
                                            </div>
                                            <div class="col-4">
                                                <label class="small text-uppercase fw-bold text-muted">Reason of Visit</label>
                                                <div class="h6 text-primary mb-0 fw-bold"><?php echo strtoupper($logData['reason']); ?></div>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-4 pt-2 border-top">
                                            <div><label class="small text-uppercase fw-bold text-muted">Date</label><div class="fw-bold text-white"><?php echo date("M d, Y", strtotime($logData['date'])); ?></div></div>
                                            <div><label class="small text-uppercase fw-bold text-muted">Time In</label><div class="fw-bold text-white"><?php echo date("h:i A", strtotime($logData['time'])); ?></div></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4 fs-5 text-muted">Closing in <span id="timerText" class="fw-bold" style="color: #0038a8;">30</span>...</div>
                            </div>
                        </div>
                    </div>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function() { 
                        const successModalElement = document.getElementById('successModal');
                        const successModal = new bootstrap.Modal(successModalElement);
                        const timerText = document.getElementById('timerText');
                        let timeLeft = 30;
                        successModal.show();
                        const countdown = setInterval(() => {
                            timeLeft--;
                            if(timeLeft >= 0) timerText.innerText = timeLeft;
                            if(timeLeft <= 5) timerText.style.color = '#dc3545';
                            if (timeLeft <= 0) { clearInterval(countdown); successModal.hide(); }
                        }, 1000);
                        successModalElement.addEventListener('hidden.bs.modal', () => {
                            clearInterval(countdown);
                            window.history.replaceState(null, null, window.location.pathname);
                        });
                    });
                </script>
                <?php endif; ?>

                <h2 class="fw-800 h4 mb-1">Library Access</h2>
                <p class="text-muted small mb-4">Please verify your identity to proceed</p>

                <div class="scanner-instruction"><i class="bi bi-qr-code-scan me-1"></i>Scan your QR code below</div>
                <div class="scanner-viewport">
                    <div id="reader"></div>
                    <div class="scanner-mask"></div>
                    <div class="scanner-frame">
                        <div class="bracket tl"></div><div class="bracket tr"></div>
                        <div class="bracket bl"></div><div class="bracket br"></div>
                        <div class="laser"></div>
                    </div>
                    <div class="scanning-text">SCANNING QR...</div>
                    <button id="cam-switch-btn" onclick="toggleCamera()">
                        <i class="bi bi-camera-rotate-fill"></i>
                        <span>Switch Cam</span>
                    </button>
                </div>

                <form method="POST" id="loginForm">
                    <input type="hidden" name="check_user" value="1">
                    <div class="mb-3">
                        <label class="small fw-bold text-muted mb-1 d-block">STUDENT / Employee ID</label>
                        <input type="text" name="user_id" id="user_id" class="form-control border-2" placeholder="ID Number" inputmode="numeric" maxlength="7" style="border-radius: 10px;">
                    </div>
                    <div class="divider">OR</div>
                    <div class="mb-4">
                        <label class="small fw-bold text-muted mb-1 d-block">Institutional Email</label>
                        <input type="email" name="user_email" id="user_email" class="form-control border-2" placeholder="firstname.lastname@neu.edu.ph" style="border-radius: 10px;">
                    </div>
                    <button type="submit" name="check_user" class="btn btn-primary w-100 py-3 fw-bold shadow-sm" style="border-radius:12px;">SUBMIT</button>
                </form>

                <div class="pt-3 border-top mt-4 small text-muted">
                    Keep your ID or QR code ready.
                </div>
            </div>
        </div>
    </div>

    <footer>&copy; 2026 NEU Library Visitor Log System. All rights reserved.</footer>

    <div class="modal fade" id="noRecordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="border-radius: 20px;">
                <div class="modal-body text-center p-5">
                    <i class="bi bi-person-x-fill text-danger" style="font-size: 4rem;"></i>
                    <h3 class="fw-800 mt-3">No Record Found</h3>
                    <p class="text-muted">The ID or Email you entered does not exist in our System.</p>
                    <button type="button" class="btn btn-danger w-100 py-3 fw-bold mt-3" data-bs-dismiss="modal" style="border-radius:12px;">TRY AGAIN</button>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['error']) && $_GET['error'] == 'blocked' && isset($_SESSION['blocked_user'])): 
        $blocked = $_SESSION['blocked_user'];
        $fullName = $blocked['firstName'] . ' ' . $blocked['lastName'];
        $picPath = "profilepictures/" . $blocked['type'] . "/" . $blocked['profile_image'];
    ?>
    <div class="modal fade" id="blockedModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 30px; overflow: hidden;">
                <div class="modal-header bg-danger text-white border-0 py-3 justify-content-center">
                    <h5 class="modal-title fw-800"><i class="bi bi-shield-lock-fill me-2"></i>ACCESS RESTRICTED</h5>
                </div>
                <div class="modal-body text-center p-5">
                    <div class="position-relative d-inline-block mb-4">
                        <img src="<?php echo $picPath; ?>" 
                             class="rounded-circle border border-4 border-danger shadow" 
                             style="width: 140px; height: 140px; object-fit: cover;"
                             onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($fullName); ?>&background=dc3545&color=fff'">
                        
                        <span class="position-absolute bottom-0 end-0 bg-danger rounded-circle d-flex align-items-center justify-content-center border border-4 border-white" 
                            style="width: 42px; height: 42px;">
                            <i class="bi bi-x-lg text-white" style="font-size: 1rem;"></i>
                        </span>
                    </div>
                    <h3 class="fw-800 mb-1"><?php echo strtoupper($fullName); ?></h3>
                    <p class="text-muted small fw-bold mb-3">ID: <?php echo ($blocked['studentID'] ?? $blocked['emplID']); ?></p>

                    <div class="mb-4">
                        <label class="small text-danger fw-800 d-block text-uppercase mb-1" style="letter-spacing: 1px;">Reason for Restriction</label>
                        <div class="p-3 border border-danger border-opacity-25 rounded-4 bg-danger bg-opacity-10">
                            <span class="fw-bold text-dark italic">
                                "<?php echo !empty($blocked['block_reason']) ? htmlspecialchars($blocked['block_reason']) : 'No specific reason provided.'; ?>"
                            </span>
                        </div>
                    </div>

                    <div class="bg-light rounded-4 p-3 mb-4">
                        <div class="row g-0">
                            <div class="col-6 border-end">
                                <label class="small text-muted fw-bold d-block text-uppercase">Department</label>
                                <span class="fw-bold"><?php echo $blocked['departmentID']; ?></span>
                            </div>
                            <div class="col-6">
                                <label class="small text-muted fw-bold d-block text-uppercase">Status</label>
                                <span class="text-danger fw-800">BLOCKED</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-secondary mb-4">Your library access has been suspended. Please coordinate with the <strong>Library Administration</strong> to resolve this issue.</p>
                    <button type="button" class="btn btn-dark w-100 py-3 fw-bold shadow-sm" data-bs-dismiss="modal" style="border-radius:12px;">CLOSE WINDOW</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var myBlockedModal = new bootstrap.Modal(document.getElementById('blockedModal'));
            myBlockedModal.show();
            document.getElementById('blockedModal').addEventListener('hidden.bs.modal', function () {
                window.history.replaceState(null, null, window.location.pathname);
            });
        });
    </script>
    <?php unset($_SESSION['blocked_user']); endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showError(msg) {
            const toast = document.getElementById('errorToast');
            document.getElementById('toastMsg').innerText = msg;
            toast.classList.add('show');
            setTimeout(() => { toast.classList.remove('show'); }, 4000);
        }

        let slideIndex = 0;
        const slides = document.querySelectorAll('.slide');
        const container = document.getElementById('slides');
        const dotsBox = document.getElementById('dots');
        slides.forEach((_, i) => {
            const d = document.createElement('button'); d.className = 'dot'; d.onclick = () => showSlide(i); dotsBox.appendChild(d);
        });
        function showSlide(n) {
            slideIndex = (n + slides.length) % slides.length;
            container.style.transform = `translateX(-${slideIndex * 100}%)`;
            document.querySelectorAll('.dot').forEach((d, i) => d.classList.toggle('active', i === slideIndex));
        }
        function plusSlides(n) { showSlide(slideIndex + n); }
        setInterval(() => plusSlides(1), 5000); showSlide(0);

        const html5QrCode = new Html5Qrcode("reader");
        let availableCameras = [];
        let currentCamIdx = 0;

        // MODIFIED: Added sound trigger logic
        function onScanSuccess(decodedText) {
            if (/^\d{7}$/.test(decodedText)) {
                // Play sound
                const beep = document.getElementById('scanSound');
                if (beep) {
                    beep.currentTime = 0;
                    beep.play().catch(e => console.log("Audio play blocked:", e));
                }

                const idInput = document.getElementById('user_id');
                idInput.value = decodedText;
                idInput.style.borderColor = "var(--neu-blue)";
                window.history.replaceState(null, null, window.location.href);
                
                // Submit after a tiny delay so the sound can be heard
                setTimeout(() => {
                    document.getElementById('loginForm').submit();
                }, 200);
            } else { showError("Invalid QR Code format."); }
        }

        async function startScanner() {
            try {
                availableCameras = await Html5Qrcode.getCameras();
                if (availableCameras.length > 0) {
                    const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
                    currentCamIdx = isMobile ? availableCameras.length - 1 : 0; 
                    await html5QrCode.start(availableCameras[currentCamIdx].id, { fps: 20, qrbox: 180 }, onScanSuccess);
                }
            } catch (err) {
                console.error(err);
                showError("Camera Error: Please check permissions.");
            }
        }

        async function toggleCamera() {
            if (availableCameras.length < 2) return;
            await html5QrCode.stop();
            currentCamIdx = (currentCamIdx + 1) % availableCameras.length;
            await html5QrCode.start(availableCameras[currentCamIdx].id, { fps: 20, qrbox: 180 }, onScanSuccess);
        }

        window.addEventListener('load', startScanner);

        const idInput = document.getElementById('user_id');
        const emailInput = document.getElementById('user_email');
        const loginForm = document.getElementById('loginForm');

        idInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if(this.value) emailInput.value = ''; 
        });

        emailInput.addEventListener('input', () => { if(emailInput.value) idInput.value = ''; });

        loginForm.addEventListener('submit', function(e) {
            const idValue = idInput.value.trim();
            const emailValue = emailInput.value.trim();
            if (!idValue && !emailValue) {
                e.preventDefault();
                showError("Action required: Enter ID or Email.");
            } else if (idValue && idValue.length !== 7) {
                e.preventDefault();
                showError("Format Error: ID must be 7 digits.");
            } else if (emailValue && !emailValue.endsWith("@neu.edu.ph")) {
                e.preventDefault();
                showError("Invalid Domain: Use @neu.edu.ph only.");
            }
        });

        <?php if ($show_error_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var myModal = new bootstrap.Modal(document.getElementById('noRecordModal'));
            myModal.show();
        });
        <?php endif; ?>
    </script>
</body>
</html>