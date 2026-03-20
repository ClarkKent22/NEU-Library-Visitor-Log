<?php
session_start();
require_once '../includes/db_connect.php'; 

// 1. SECURITY CHECK
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$adminID = $_SESSION['emplID']; 
$adminName = $_SESSION['admin_name'];
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime("-1 day"));

// Fetch Admin Profile
$adminQuery = $conn->prepare("SELECT profile_image, firstName, lastName FROM employees WHERE emplID = ?");
$adminQuery->bind_param("s", $adminID);
$adminQuery->execute();
$adminData = $adminQuery->get_result()->fetch_assoc() ?: [
    'profile_image' => null,
    'firstName' => 'Administrator',
    'lastName' => ''
];

function getInitials($firstname, $lastname) {
    return strtoupper(substr($firstname ?? '', 0, 1) . substr($lastname ?? '', 0, 1));
}

$photoFilename = $adminData['profile_image'] ?? null;
$photoUrl = "../profilepictures/admin/" . $photoFilename;
$photoFilePath = __DIR__ . "/../profilepictures/admin/" . $photoFilename;
$hasPhoto = (!empty($photoFilename) && file_exists($photoFilePath));
$adminInitials = getInitials($adminData['firstName'] ?? '', $adminData['lastName'] ?? '');

// --- TREND CALCULATIONS ---
$todayRes = $conn->query("SELECT COUNT(*) as count FROM history_logs WHERE date = '$today'");
$countToday = $todayRes->fetch_assoc()['count'] ?? 0;
$yesterdayRes = $conn->query("SELECT COUNT(*) as count FROM history_logs WHERE date = '$yesterday'");
$countYesterday = $yesterdayRes->fetch_assoc()['count'] ?? 0;
$trendDay = ($countYesterday > 0) ? round((($countToday - $countYesterday) / $countYesterday) * 100) : ($countToday > 0 ? 100 : 0);

$weekRes = $conn->query("SELECT COUNT(*) as count FROM history_logs WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$countWeek = $weekRes->fetch_assoc()['count'] ?? 0;
$prevWeekRes = $conn->query("SELECT COUNT(*) as count FROM history_logs WHERE date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$countPrevWeek = $prevWeekRes->fetch_assoc()['count'] ?? 0;
$trendWeek = ($countPrevWeek > 0) ? round((($countWeek - $countPrevWeek) / $countPrevWeek) * 100) : ($countWeek > 0 ? 100 : 0);

$monthRes = $conn->query("SELECT COUNT(*) as count FROM history_logs WHERE MONTH(date) = MONTH(CURRENT_DATE()) AND YEAR(date) = YEAR(CURRENT_DATE())");
$countMonth = $monthRes->fetch_assoc()['count'] ?? 0;
$prevMonthRes = $conn->query("SELECT COUNT(*) as count FROM history_logs WHERE MONTH(date) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) AND YEAR(date) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))");
$countPrevMonth = $prevMonthRes->fetch_assoc()['count'] ?? 0;
$trendMonth = ($countPrevMonth > 0) ? round((($countMonth - $countPrevMonth) / $countPrevMonth) * 100) : ($countMonth > 0 ? 100 : 0);

$overallRes = $conn->query("SELECT COUNT(*) as count FROM history_logs");
$countOverall = $overallRes->fetch_assoc()['count'] ?? 0;
$activeRes = $conn->query("SELECT (SELECT COUNT(*) FROM students WHERE status != 'Blocked') + (SELECT COUNT(*) FROM employees WHERE status != 'Blocked') as count");
$countActive = $activeRes->fetch_assoc()['count'] ?? 0;
$blockedRes = $conn->query("SELECT (SELECT COUNT(*) FROM students WHERE LOWER(status) = 'blocked') + (SELECT COUNT(*) FROM employees WHERE LOWER(status) = 'blocked') as total_blocked");
$countBlocked = $blockedRes->fetch_assoc()['total_blocked'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | NEU Library Admin</title>
    <link rel="icon" type="image/png" href="../assets/neu.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --neu-blue: #2b6fff;
            --neu-hover: #1c4ed8;
            --bg: #0b0f17;
            --panel: #0f172a;
            --card: #121826;
            --border: #1f2a44;
            --text: #e6edf5;
            --muted: #9aa7bd;
            --success: #22c55e;
            --danger: #ef4444;
        }
        body {
            background:
                radial-gradient(1200px 700px at 20% -10%, rgba(43, 111, 255, 0.18), transparent 60%),
                radial-gradient(900px 500px at 80% 10%, rgba(16, 185, 129, 0.08), transparent 55%),
                var(--bg);
            font-family: 'Sora', sans-serif;
            color: var(--text);
        }
        .navbar {
            background: rgba(11, 15, 23, 0.9);
            border-bottom: 1px solid var(--border);
            padding: 0.7rem 2rem;
            backdrop-filter: blur(12px);
        }
        .nav-link { font-weight: 600; color: var(--muted); transition: 0.2s; border-radius: 10px; margin: 0 4px; }
        .nav-link:hover { color: #fff; background: rgba(43, 111, 255, 0.12); }
        .nav-link.active { color: #fff !important; background: rgba(43, 111, 255, 0.2); }

        .text-blue { color: var(--neu-blue) !important; }
        .btn-blue {
            background: linear-gradient(135deg, #2b6fff, #1c4ed8);
            color: white;
            border-radius: 14px;
            border: 1px solid rgba(43, 111, 255, 0.35);
            box-shadow: 0 10px 25px rgba(43, 111, 255, 0.25);
        }
        .btn-blue:hover { background: linear-gradient(135deg, #1f5bff, #1741b3); color: white; }

        .analytics-card {
            background: var(--card);
            border-radius: 16px;
            padding: 1.25rem;
            border: 1px solid var(--border);
            box-shadow: 0 18px 40px rgba(4, 10, 24, 0.6);
            position: relative;
            transition: transform 0.2s;
            height: 100%;
        }
        .analytics-card:hover { transform: translateY(-6px); }
        .card-label { font-size: 0.75rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.6px; }
        .card-value { font-size: 2rem; font-weight: 800; color: #fff; margin: 6px 0; }
        .card-icon-box {
            position: absolute; top: 18px; right: 18px;
            width: 42px; height: 42px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
            background: rgba(43, 111, 255, 0.15); color: #9cc1ff;
        }
        .trend-up { font-size: 0.75rem; color: #22c55e; font-weight: 600; background: rgba(34,197,94,0.15); padding: 3px 8px; border-radius: 6px; }
        .trend-down { font-size: 0.75rem; color: #ef4444; font-weight: 600; background: rgba(239,68,68,0.15); padding: 3px 8px; border-radius: 6px; }

        .stat-card { background: var(--card); border-radius: 16px; padding: 1.5rem; box-shadow: 0 18px 40px rgba(4, 10, 24, 0.6); border-left: 5px solid var(--neu-blue); border: 1px solid var(--border); }
        .stat-card.blocked { border-left-color: var(--danger); }
        .table-card { background: var(--card); border-radius: 16px; overflow: hidden; box-shadow: 0 18px 40px rgba(4, 10, 24, 0.6); border: 1px solid var(--border); }
        .visitor-avatar { width: 45px; height: 45px; object-fit: cover; border-radius: 50%; }
        .initials-avatar { width: 45px; height: 45px; border-radius: 50%; background: rgba(43, 111, 255, 0.2); color: #9cc1ff; display: flex; align-items: center; justify-content: center; font-weight: 700; border: 1px solid var(--border); }
        .badge-role { padding: 5px 12px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
        .role-student { background: rgba(43,111,255,0.15); color: #9cc1ff; }
        .role-employee { background: rgba(251,146,60,0.15); color: #fdba74; }
        .btn-status-action { font-size: 0.75rem; font-weight: 700; padding: 6px 14px; border-radius: 10px; text-transform: uppercase; border: 1px solid transparent; }
        .btn-block { background: rgba(239,68,68,0.15); color: #fca5a5; border-color: rgba(239,68,68,0.35); }
        .btn-unblock { background: rgba(34,197,94,0.15); color: #86efac; border-color: rgba(34,197,94,0.35); }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }
        .animate-pulse { animation: pulse 1.5s infinite; color: #fff; margin-right: 5px; }

        .pagination .page-link { color: #9cc1ff; border: 1px solid var(--border); background: var(--panel); margin: 0 2px; border-radius: 8px; cursor: pointer; }
        .pagination .page-item.active .page-link { background-color: var(--neu-blue); color: white; border-color: rgba(43,111,255,0.5); }

        .table { color: var(--text); }
        .table thead th { color: var(--muted); border-color: var(--border); }
        .table td { border-color: var(--border); }
        .alert { background: var(--card); color: var(--text); border: 1px solid var(--border); }
        .dropdown-menu { background: var(--card); border: 1px solid var(--border); }
        .dropdown-item { color: var(--text); }
        .dropdown-item:hover { background: rgba(43,111,255,0.15); color: #fff; }
    </style>
    <link rel="stylesheet" href="../assets/theme.css">
</head>
<body class="theme-dark">
<div class="app-shell">
    <aside class="app-sidebar d-flex flex-column">
        <div class="app-brand">
            <img src="../assets/neu.png" alt="Logo" width="32" height="32">
            <span>NEU Library</span>
        </div>
        <nav class="sidebar-nav">
            <a class="sidebar-link active" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a class="sidebar-link" href="visitor_history.php"><i class="bi bi-clock-history"></i> Visitor Logs</a>
            <a class="sidebar-link" href="block.php"><i class="bi bi-shield-slash"></i> Blocked Users</a>
            <a class="sidebar-link" href="reports.php"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a>
            <a class="sidebar-link" href="user_management.php"><i class="bi bi-people"></i> Users</a>
        </nav>
        <div class="sidebar-footer mt-auto">
            <div class="d-flex align-items-center gap-2">
                <?php if ($hasPhoto): ?>
                    <img src="<?php echo htmlspecialchars($photoUrl); ?>" class="rounded-circle" style="width:40px; height:40px; object-fit:cover; border: 2px solid var(--neu-blue);">
                <?php else: ?>
                    <div class="initials-avatar"><?php echo $adminInitials; ?></div>
                <?php endif; ?>
                <div>
                    <div class="fw-bold small"><?php echo htmlspecialchars($adminData['firstName'] . ' ' . $adminData['lastName']); ?></div>
                    <div class="text-muted" style="font-size: 10px;">Administrator</div>
                </div>
            </div>
            <a href="logout.php" class="btn btn-outline-danger w-100 mt-3">Logout</a>
        </div>
    </aside>

    <div class="app-main">
        <div class="app-topbar">
            <div>
                <div class="content-title">Dashboard Overview</div>
                <div class="content-subtitle">Live activity for <?php echo date('F d, Y'); ?></div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted small">System Status: Online</span>
                <a href="reports.php" class="btn btn-blue">Export Reports</a>
            </div>
        </div>

        <div class="app-content">
            <div class="container-fluid px-0">
    <?php if (isset($_SESSION['show_welcome']) && $_SESSION['show_welcome'] === true): ?>
        <div id="welcomeAlert" class="alert alert-white shadow-sm border-0 d-flex align-items-center p-4 animate__animated animate__fadeInDown" 
             style="border-left: 5px solid var(--neu-blue) !important; border-radius: 15px; background: white;">
            <div class="bg-light rounded-circle p-3 me-3 text-blue">
                <i class="bi bi-hand-thumbs-up-fill fs-4" style="color: var(--neu-blue);"></i>
            </div>
            <div>
                <h4 class="fw-bold mb-0 text-blue" style="color: var(--neu-blue);">Welcome back, <?= htmlspecialchars($adminData['firstName']) ?>!</h4>
                <p class="text-muted mb-0 small">You are logged in as a System Administrator. Have a productive day!</p>
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php 
            // Unset immediately so it doesn't show on refresh or when coming back from other pages
            unset($_SESSION['show_welcome']); 
        ?>
    <?php endif; ?>
            </div>
            <div class="container-fluid px-4 px-md-5 py-4">
    <?php if(isset($_GET['msg'])): ?>
        <div id="statusAlert" class="alert <?php echo ($_GET['msg'] == 'StatusUpdated') ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show shadow-sm border-0 mb-4" role="alert" style="border-left: 5px solid <?php echo ($_GET['msg'] == 'StatusUpdated') ? '#10b981' : '#dc3545'; ?> !important;">
            <i class="bi <?php echo ($_GET['msg'] == 'StatusUpdated') ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?> me-2"></i>
            <strong><?php echo ($_GET['msg'] == 'StatusUpdated') ? 'Success!' : 'Error!'; ?></strong> 
            <?php echo ($_GET['msg'] == 'StatusUpdated') ? 'User status has been updated successfully.' : 'An error occurred while updating status.'; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">Daily Attendance Overview</h2>
            <p class="text-muted">Live dashboard for <strong><?php echo date('F d, Y'); ?></strong></p>
        </div>
        <div class="dropdown">
            <button class="btn btn-blue px-4 shadow-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-download me-2"></i> Export Logs
            </button>
            <ul class="dropdown-menu shadow border-0">
                <li><a class="dropdown-item" href="#" onclick="exportToCSV()"><i class="bi bi-filetype-csv me-2 text-success"></i> Save as Excel/CSV</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" onclick="window.print()"><i class="bi bi-printer me-2 text-primary"></i> Print View</a></li>
            </ul>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="analytics-card">
                <div class="card-label">Visitor 
                    Today</div>
                <div class="card-value text-blue" id="valToday"><?php echo number_format($countToday); ?></div>
                <div class="card-icon-box" style="background: #e0f2fe; color: #0369a1;"><i class="bi bi-people-fill"></i></div>
                <span id="trendToday" class="<?php echo ($trendDay >= 0) ? 'trend-up' : 'trend-down'; ?>">
                    <i id="trendIcon" class="bi <?php echo ($trendDay >= 0) ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow'; ?>"></i> 
                    <span id="trendText"><?php echo abs($trendDay); ?>%</span>
                </span> 
                <span class="text-muted small">vs yesterday</span>
            </div>
        </div>
        <div class="col-md-2">
            <div class="analytics-card">
                <div class="card-label">This Week</div>
                <div class="card-value" style="color: #a16207;" id="valWeek"><?php echo number_format($countWeek); ?></div>
                <div class="card-icon-box" style="background: #fef9c3; color: #a16207;"><i class="bi bi-calendar-event"></i></div>
                <span id="trendWeek" class="<?php echo ($trendWeek >= 0) ? 'trend-up' : 'trend-down'; ?>">
                    <i class="bi <?php echo ($trendWeek >= 0) ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow'; ?>"></i> <?php echo abs($trendWeek); ?>%
                </span> 
                <span class="text-muted small">vs last week</span>
            </div>
        </div>
        <div class="col-md-2">
            <div class="analytics-card">
                <div class="card-label">This Month</div>
                <div class="card-value" style="color: #15803d;" id="valMonth"><?php echo number_format($countMonth); ?></div>
                <div class="card-icon-box" style="background: #dcfce7; color: #15803d;"><i class="bi bi-calendar-month"></i></div>
                <span id="trendMonth" class="<?php echo ($trendMonth >= 0) ? 'trend-up' : 'trend-down'; ?>">
                    <i class="bi <?php echo ($trendMonth >= 0) ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow'; ?>"></i> <?php echo abs($trendMonth); ?>%
                </span> 
                <span class="text-muted small">vs last month</span>
            </div>
        </div>
        <div class="col-md-2">
            <div class="analytics-card">
                <div class="card-label">Overall Visitors</div>
                <div class="card-value" style="color: #0369a1;" id="valOverall"><?php echo number_format($countOverall); ?></div>
                <div class="card-icon-box" style="background: #f0fdf4; color: #16a34a;"><i class="bi bi-person-check-fill"></i></div>
                <span class="text-muted small">All-time logs</span>
            </div>
        </div>
        <div class="col-md-2">
            <div class="analytics-card">
                <div class="card-label">Total Active</div>
                <div class="card-value" style="color: #7e22ce;" id="valActive"><?php echo number_format($countActive); ?></div>
                <div class="card-icon-box" style="background: #f3e8ff; color: #7e22ce;"><i class="bi bi-bar-chart-fill"></i></div>
                <span class="trend-up" style="background: #f3e8ff; color: #7e22ce;"><i class="bi bi-arrow-up-right"></i> System Wide</span>
            </div>
        </div>
        <div class="col-md-2">
            <div class="analytics-card" id="securityCard">
                <div class="card-label">Security Status</div>
                <div class="card-value" id="securityStatusText" style="color: #be185d;">OK</div>
                <div class="card-icon-box" id="securityIconBox" style="background: #fce7f3; color: #be185d;">
                    <i class="bi bi-shield-check" id="securityIcon"></i>
                </div>
                <span id="securitySubtext" class="trend-up" style="background: #fce7f3; color: #be185d;">all systems safe</span>
            </div>
        </div>
    </div>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="stat-card blocked d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-danger small fw-bold mb-1">GLOBAL BLOCKED ACCOUNTS</div>
                    <h2 class="fw-bold mb-0 text-danger" id="valBlocked"><?php echo $countBlocked; ?></h2>
                </div>
                <i class="bi bi-shield-lock-fill text-danger fs-1 opacity-1"></i>
            </div>
        </div>
    </div>

    <div class="table-card">
        <div class="p-4 bg-white border-bottom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-blue"><i class="bi bi-people-fill me-2"></i>Current Visitors</h5>
            <div class="d-flex align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted fw-bold">Rows:</label>
                    <select id="rowsPerPage" class="form-select form-select-sm" style="width: auto;">
                        <option value="5" selected>5</option>
                        <option value="10">10</option>
                        <option value="20">20</option>
                    </select>
                </div>
                <span class="badge bg-success rounded-pill small"><i class="bi bi-record-fill animate-pulse"></i>LIVE UPDATING</span>
            </div>
        </div>

        <div class="p-3 bg-light border-bottom">
            <div class="row g-2">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="tableSearch" class="form-control border-start-0" placeholder="Search by Name or ID...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select id="filterDept" class="form-select">
                        <option value="">All Departments</option>
                        <?php
                        $depts = $conn->query("SELECT departmentName FROM departments ORDER BY departmentName ASC");
                        while($d = $depts->fetch_assoc()) {
                            echo "<option value='".htmlspecialchars($d['departmentName'])."'>".$d['departmentName']."</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="filterRole" class="form-select">
                        <option value="">All Roles</option>
                        <option value="STUDENT">Student</option>
                        <option value="EMPLOYEE">Employee</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" id="clearFilters" class="btn btn-outline-secondary w-100">Clear</button>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="mainVisitorTable">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Visitor Details</th>
                        <th>Program/Department</th>
                        <th>User Role</th> 
                        <th>Log Timestamp</th> 
                        <th>Reason for Visit</th> 
                        <th>Status</th>
                        <th class="text-center action-col">Actions</th>
                    </tr>
                </thead>
                <tbody id="logTableBody"></tbody>
            </table>
        </div>

        <div class="p-3 bg-white border-top d-flex justify-content-between align-items-center">
            <div class="small text-muted" id="paginationInfo">Showing 0 to 0 of 0 entries</div>
            <nav><ul class="pagination pagination-sm mb-0" id="paginationControls"></ul></nav>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <div id="modalBodyText">Are you sure you want to perform this action?</div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <a id="modalConfirmBtn" href="#" class="btn btn-blue px-4 rounded-pill">Proceed</a>
            </div>
        </div>
    </div>
</div>

            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let currentPage = 1;
    let rowsPerPage = 5;

    // MODAL REASON LOGIC
    function confirmStatusChange(url, action, name) {
        const modalBody = document.getElementById('modalBodyText');
        const confirmBtn = document.getElementById('modalConfirmBtn');
        
        if (action.toLowerCase() === 'block') {
            modalBody.innerHTML = `
                <p>Are you sure you want to <strong>block</strong> ${name}?</p>
                <div class="mt-3">
                    <label class="small fw-bold text-muted mb-1">Reason for blocking:</label>
                    <textarea id="blockReasonInput" class="form-control" rows="3" placeholder="Enter reason here..." required title="Please provide a reason for blocking."></textarea>
                </div>
            `;
            confirmBtn.className = 'btn btn-danger px-4 rounded-pill';
        } else {
            modalBody.innerHTML = `Are you sure you want to <strong>unblock</strong> ${name}?`;
            confirmBtn.className = 'btn btn-success px-4 rounded-pill';
        }

        confirmBtn.onclick = function(e) {
            e.preventDefault();
            let finalUrl = url;
            
            if (action.toLowerCase() === 'block') {
                const reasonInput = document.getElementById('blockReasonInput');
                const reason = reasonInput.value.trim();
                
                if (!reason) {
                    reasonInput.classList.add('is-invalid');
                    reasonInput.reportValidity();
                    reasonInput.focus();
                    return;
                }
                finalUrl += `&reason=${encodeURIComponent(reason)}`;
            }
            
            window.location.href = finalUrl;
        };

        new bootstrap.Modal(document.getElementById('confirmModal')).show();
    }

    function exportToCSV() {
        let csv = [];
        csv.push("Visitor,ID,Program,Role,Timestamp,Reason,Status");
        $("#logTableBody tr").each(function() {
            let row = [];
            row.push('"' + $(this).find('td:eq(0) .fw-bold').text().trim() + '"');
            row.push('"' + $(this).find('td:eq(0) .text-muted').text().replace('ID: ', '').trim() + '"');
            row.push('"' + $(this).find('td:eq(1)').text().trim() + '"');
            row.push('"' + $(this).find('td:eq(2)').text().trim() + '"');
            row.push('"' + $(this).find('td:eq(3) .fw-bold').text().trim() + " " + $(this).find('td:eq(3) .text-muted').text().trim() + '"');
            row.push('"' + $(this).find('td:eq(4)').text().trim() + '"');
            row.push('"' + $(this).find('td:eq(5)').text().trim() + '"');
            csv.push(row.join(","));
        });
        let csv_string = csv.join("\n");
        let link = document.createElement("a");
        link.style.display = 'none';
        link.setAttribute("target", "_blank");
        link.setAttribute("href", "data:text/csv;charset=utf-8," + encodeURIComponent(csv_string));
        link.setAttribute("download", "Library_Logs_" + new Date().toLocaleDateString() + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function applyPagination() {
        const $rows = $("#logTableBody tr");
        const searchTerm = $('#tableSearch').val().toLowerCase();
        const deptTerm = $('#filterDept').val().toLowerCase();
        const roleTerm = $('#filterRole').val().toUpperCase();
        rowsPerPage = parseInt($('#rowsPerPage').val());

        let visibleRows = [];
        $rows.each(function() {
            const rowText = $(this).text().toLowerCase();
            const rowDept = $(this).find('td:eq(1)').text().toLowerCase();
            const rowRole = $(this).find('.badge-role').text().toUpperCase();

            const matchesSearch = rowText.indexOf(searchTerm) > -1;
            const matchesDept = deptTerm === "" || rowDept.indexOf(deptTerm) > -1;
            const matchesRole = roleTerm === "" || rowRole === roleTerm;

            if (matchesSearch && matchesDept && matchesRole) {
                visibleRows.push($(this));
            } else {
                $(this).hide();
            }
        });

        const totalRows = visibleRows.length;
        const totalPages = Math.ceil(totalRows / rowsPerPage);
        if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
        
        $rows.hide(); 
        const start = (currentPage - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        visibleRows.slice(start, end).forEach(row => row.show());
        
        if (totalRows === 0 && searchTerm !== "") {
            // Check if the searched user exists in the system but hasn't logged in today
            checkUserExists(searchTerm);
        } else {
            updatePaginationUI(totalRows, totalPages);
        }
    }

    function checkUserExists(searchTerm) {
        $.ajax({
            url: 'check_user_exists.php',
            type: 'POST',
            dataType: 'json',
            data: { search: searchTerm },
            success: function(response) {
                if (response && response.exists) {
                    // User exists - check if blocked or just hasn't logged in
                    let message, subMessage;
                    if (response.status && response.status.toLowerCase() === 'blocked') {
                        message = "This account is currently blocked";
                        subMessage = `${response.name} cannot access the system due to account restrictions.`;
                    } else {
                        message = "It looks like he/she haven't logged in yet";
                        subMessage = `${response.name} exists in the system but has no activity today.`;
                    }
                    
                    $('#logTableBody').html(`
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-info-circle fs-1 mb-3 d-block"></i>
                                    <h5 class="fw-bold">${message}</h5>
                                    <p class="mb-0">${subMessage}</p>
                                </div>
                            </td>
                        </tr>
                    `);
                    updatePaginationUI(0, 0);
                } else {
                    // User doesn't exist in the system
                    $('#logTableBody').html(`
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-search fs-1 mb-3 d-block"></i>
                                No visitors found matching your search.
                            </td>
                        </tr>
                    `);
                    updatePaginationUI(0, 0);
                }
            },
            error: function() {
                // Fallback to normal no results message
                $('#logTableBody').html(`
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-search fs-1 mb-3 d-block"></i>
                            No visitors found matching your search.
                        </td>
                    </tr>
                `);
                updatePaginationUI(0, 0);
            }
        });
    }

    function updatePaginationUI(totalRows, totalPages) {
        const startIdx = totalRows > 0 ? (currentPage - 1) * rowsPerPage + 1 : 0;
        const endIdx = Math.min(currentPage * rowsPerPage, totalRows);
        $('#paginationInfo').text(`Showing ${startIdx} to ${endIdx} of ${totalRows} entries`);
        let controls = "";
        if (totalPages > 1) {
            controls += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}"><a class="page-link" onclick="changePage(${currentPage - 1})">Prev</a></li>`;
            for (let i = 1; i <= totalPages; i++) controls += `<li class="page-item ${i === currentPage ? 'active' : ''}"><a class="page-link" onclick="changePage(${i})">${i}</a></li>`;
            controls += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}"><a class="page-link" onclick="changePage(${currentPage + 1})">Next</a></li>`;
        }
        $('#paginationControls').html(controls);
    }

    function changePage(page) { if(page >= 1) { currentPage = page; applyPagination(); } }

    function loadLogs() {
        $.ajax({
            url: 'fetch_logs.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                $('#logTableBody').html(response.html);
                applyPagination();
                $('#valToday').text(response.todayCount.toLocaleString());
                $('#valWeek').text(response.weekCount.toLocaleString());
                $('#valMonth').text(response.monthCount.toLocaleString());
                $('#valOverall').text(response.overallCount.toLocaleString());
                $('#valActive').text(response.totalActiveCount.toLocaleString());
                $('#valBlocked').text(response.globalBlockedCount.toLocaleString());

                const trendDay = response.yesterdayCount > 0 
                    ? Math.round(((response.todayCount - response.yesterdayCount) / response.yesterdayCount) * 100) 
                    : (response.todayCount > 0 ? 100 : 0);
                
                $('#trendText').text(Math.abs(trendDay) + '%');
                $('#trendToday').attr('class', trendDay >= 0 ? 'trend-up' : 'trend-down');
                $('#trendIcon').attr('class', trendDay >= 0 ? 'bi bi-graph-up-arrow' : 'bi bi-graph-down-arrow');

                const blocked = parseInt(response.globalBlockedCount);
                if (blocked > 10) {
                    $('#securityStatusText').text("ALERT").css("color", "#dc3545");
                    $('#securityIconBox').css({"background": "#fef2f2", "color": "#dc3545"});
                    $('#securityIcon').attr("class", "bi bi-shield-exclamation");
                    $('#securitySubtext').css({"background": "#fef2f2", "color": "#dc3545"}).text("High block volume");
                } else if (blocked > 0) {
                    $('#securityStatusText').text("STRICT").css("color", "#a16207");
                    $('#securityIconBox').css({"background": "#fef9c3", "color": "#a16207"});
                    $('#securityIcon').attr("class", "bi bi-shield-lock");
                    $('#securitySubtext').css({"background": "#fef9c3", "color": "#a16207"}).text("Active restrictions");
                } else {
                    $('#securityStatusText').text("OK").css("color", "#be185d");
                    $('#securityIconBox').css({"background": "#fce7f3", "color": "#be185d"});
                    $('#securityIcon').attr("class", "bi bi-shield-check");
                    $('#securitySubtext').css({"background": "#fce7f3", "color": "#be185d"}).text("all systems safe");
                }
            }
        });
    }

    $(document).ready(function() {
        // --- WELCOME ALERT AUTO-CLOSE ---
        if ($('#welcomeAlert').length) {
            setTimeout(function() {
                var welcomeAlert = document.getElementById('welcomeAlert');
                if (welcomeAlert) {
                    var bsAlert = bootstrap.Alert.getOrCreateInstance(welcomeAlert);
                    bsAlert.close();
                }
            }, 5000);
        }

        loadLogs();
        setInterval(loadLogs, 5000);
        $('#tableSearch').on('keyup', function() { currentPage = 1; applyPagination(); });
        $('#filterDept, #filterRole, #rowsPerPage').on('change', function() { currentPage = 1; applyPagination(); });
        $('#clearFilters').on('click', function() {
            $('#tableSearch').val(''); $('#filterDept').val(''); $('#filterRole').val('');
            currentPage = 1; applyPagination();
        });
        if ($('#statusAlert').length) setTimeout(() => { bootstrap.Alert.getOrCreateInstance($('#statusAlert')[0]).close(); }, 5000);
    });
</script>
</body>
</html>
