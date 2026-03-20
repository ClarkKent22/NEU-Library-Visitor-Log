<?php
session_start();
require_once '../includes/db_connect.php'; 

// 1. SECURITY CHECK
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// --- DATA FETCHING LOGIC (Handles both AJAX and Initial Load) ---
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$filterDate = isset($_GET['filterDate']) ? $conn->real_escape_string($_GET['filterDate']) : '';
$filterMonth = isset($_GET['filterMonth']) ? $conn->real_escape_string($_GET['filterMonth']) : '';
$filterYear = isset($_GET['filterYear']) ? $conn->real_escape_string($_GET['filterYear']) : '';

$sql = "SELECT h.*, 
        CASE WHEN h.user_type = 'Student' THEN s.firstName ELSE e.firstName END as fName,
        CASE WHEN h.user_type = 'Student' THEN s.lastName ELSE e.lastName END as lName,
        CASE WHEN h.user_type = 'Student' THEN d1.departmentName ELSE d2.departmentName END as deptName,
        COALESCE(e.role, '') as employeeRole
        FROM history_logs h
        LEFT JOIN students s ON h.user_identifier = s.studentID AND h.user_type = 'Student'
        LEFT JOIN employees e ON h.user_identifier = e.emplID AND h.user_type != 'Student'
        LEFT JOIN departments d1 ON s.departmentID = d1.departmentID
        LEFT JOIN departments d2 ON e.departmentID = d2.departmentID
        WHERE 1=1";

if (!empty($search)) {
    $sql .= " AND (
        s.firstName LIKE '%$search%' 
        OR s.lastName LIKE '%$search%' 
        OR e.firstName LIKE '%$search%' 
        OR e.lastName LIKE '%$search%' 
        OR h.user_identifier LIKE '%$search%'
        OR h.user_type LIKE '%$search%'
        OR d1.departmentName LIKE '%$search%'
        OR d2.departmentName LIKE '%$search%'
        OR h.reason LIKE '%$search%'
        OR DATE_FORMAT(h.date, '%Y-%m-%d') LIKE '%$search%'
        OR DATE_FORMAT(h.date, '%b %e, %Y') LIKE '%$search%'
        OR TIME_FORMAT(h.time, '%H:%i') LIKE '%$search%'
        OR TIME_FORMAT(h.time, '%h:%i %p') LIKE '%$search%'
    )";
}
if (!empty($filterDate)) { $sql .= " AND h.date = '$filterDate'"; }
if (!empty($filterMonth)) { $sql .= " AND MONTH(h.date) = '$filterMonth'"; }
if (!empty($filterYear)) { $sql .= " AND YEAR(h.date) = '$filterYear'"; }

$sql .= " ORDER BY h.date DESC, h.time DESC";
$result = $conn->query($sql);

// 2. AJAX HANDLER: If request is AJAX, only return the table rows and stop execution
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()): 
            $fName = trim($row['fName'] ?? '');
            $lName = trim($row['lName'] ?? '');
            $userType = $row['user_type'];
            $employeeRole = trim($row['employeeRole'] ?? '');
            
            // Determine role display
            if (strcasecmp($userType, 'Student') === 0) {
                $roleDisplay = 'Student';
                $roleBadgeClass = 'role-student';
            } 
            // Check if it's Faculty/Admin (old admin role)
            elseif (strcasecmp($employeeRole, 'Faculty/Admin') === 0) {
                $roleDisplay = 'Admin';
                $roleBadgeClass = 'role-admin';
            }
            // Check if it's Employee / Faculty
            elseif (!empty($employeeRole) && (stripos($employeeRole, 'Employee') !== false || stripos($employeeRole, 'Faculty') !== false)) {
                $roleDisplay = 'Faculty';
                $roleBadgeClass = 'role-faculty';
            }
            // Check for pure Admin
            elseif (!empty($employeeRole) && stripos($employeeRole, 'Admin') !== false) {
                $roleDisplay = 'Admin';
                $roleBadgeClass = 'role-admin';
            }
            // Check if user_type mentions Admin
            elseif (strcasecmp($userType, 'Admin') === 0) {
                $roleDisplay = 'Admin';
                $roleBadgeClass = 'role-admin';
            } else {
                $roleDisplay = 'Faculty';
                $roleBadgeClass = 'role-faculty';
            }
            
            $displayName = (!empty($fName) || !empty($lName)) ? htmlspecialchars($fName . ' ' . $lName) : strtoupper($roleDisplay);
            ?>
            <tr>
                <td class="ps-4 fw-bold text-blue"><?php echo $row['user_identifier']; ?></td>
                <td class="fw-semibold"><?php echo $displayName; ?></td>
                <td>
                    <span class="badge-role <?php echo $roleBadgeClass; ?>">
                        <?php echo strtoupper($roleDisplay); ?>
                    </span>
                </td>
                <td class="small text-muted"><?php echo htmlspecialchars($row['deptName'] ?? 'N/A'); ?></td>
                <td class="small"><i><?php echo htmlspecialchars($row['reason']); ?></i></td>
                <td class="fw-bold"><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                <td class="text-blue fw-bold"><?php echo date('h:i A', strtotime($row['time'])); ?></td>
            </tr>
        <?php endwhile;
    } else {
        echo "<tr><td colspan='7' class='text-center py-5 text-muted'><i class='bi bi-search fs-1 d-block mb-3 opacity-25'></i>No records found matching your filters.</td></tr>";
    }
    exit; // Stop further execution for AJAX requests
}

// --- NORMAL PAGE LOAD CONTINUES BELOW ---
$adminID = $_SESSION['emplID'];
$adminQuery = $conn->prepare("SELECT profile_image, firstName, lastName FROM employees WHERE emplID = ?");
$adminQuery->bind_param("s", $adminID);
$adminQuery->execute();
$adminData = $adminQuery->get_result()->fetch_assoc();

function getInitials($firstname, $lastname) {
    return strtoupper(substr($firstname ?? '', 0, 1) . substr($lastname ?? '', 0, 1));
}

$photoFilename = $adminData['profile_image'] ?? null;
$photoUrl = "../profilepictures/admin/" . $photoFilename;
$photoFilePath = __DIR__ . "/../profilepictures/admin/" . $photoFilename;
$hasPhoto = (!empty($photoFilename) && file_exists($photoFilePath));
$adminInitials = getInitials($adminData['firstName'] ?? '', $adminData['lastName'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor History | NEU Library Admin</title>
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
        }
        body {
            background:
                radial-gradient(1200px 700px at 20% -10%, rgba(43, 111, 255, 0.18), transparent 60%),
                radial-gradient(900px 500px at 80% 10%, rgba(16, 185, 129, 0.08), transparent 55%),
                var(--bg);
            font-family: 'Sora', sans-serif;
            color: var(--text);
        }
        .navbar { background: rgba(11, 15, 23, 0.9); border-bottom: 1px solid var(--border); padding: 0.7rem 2rem; backdrop-filter: blur(12px); }
        .nav-link { font-weight: 600; color: var(--muted); transition: 0.2s; border-radius: 10px; margin: 0 4px; }
        .nav-link:hover { color: #fff; background: rgba(43, 111, 255, 0.12); }
        .nav-link.active { color: #fff !important; background: rgba(43, 111, 255, 0.2); }
        .text-blue { color: var(--neu-blue) !important; }
        .btn-blue { background: linear-gradient(135deg, #2b6fff, #1c4ed8); color: white; border-radius: 12px; transition: 0.3s; border: 1px solid rgba(43,111,255,0.35); }
        .btn-blue:hover { background: linear-gradient(135deg, #1f5bff, #1741b3); color: white; }
        .initials-avatar { width: 40px; height: 40px; border-radius: 50%; background: rgba(43, 111, 255, 0.2); color: #9cc1ff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; border: 1px solid var(--border); }
        .filter-section { background: var(--card); border-radius: 16px; padding: 25px; box-shadow: 0 18px 40px rgba(4, 10, 24, 0.6); margin-bottom: 25px; border: 1px solid var(--border); }
        .table-card { background: var(--card); border-radius: 16px; overflow: hidden; box-shadow: 0 18px 40px rgba(4, 10, 24, 0.6); border: 1px solid var(--border); }
        .badge-role { padding: 5px 12px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
        .role-student { background: rgba(43,111,255,0.15); color: #9cc1ff; }
        .role-employee { background: rgba(251,146,60,0.15); color: #9cc1ff; }
        .role-admin { background: rgba(43,111,255,0.15); color: #9cc1ff; }
        .role-faculty { background: rgba(251,146,60,0.15); color: #9cc1ff; }
        
        #loadingOverlay { display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(8, 12, 24, 0.7); z-index: 10; align-items: center; justify-content: center; border-radius: 16px; }
        .pagination .page-link { color: #9cc1ff; border: 1px solid var(--border); background: var(--panel); margin: 0 2px; border-radius: 8px; cursor: pointer; }
        .pagination .page-item.active .page-link { background-color: var(--neu-blue); color: white; border-color: rgba(43,111,255,0.5); }
        .table { color: var(--text); }
        .table thead th { color: var(--muted); border-color: var(--border); }
        .table td { border-color: var(--border); }
        .form-control, .form-select { background: var(--panel); border: 1px solid var(--border); color: var(--text); }
        .form-control::placeholder { color: #7a879e; }
        .form-control:focus, .form-select:focus { border-color: rgba(43,111,255,0.6); box-shadow: 0 0 0 0.2rem rgba(43,111,255,0.15); }
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
            <a class="sidebar-link" href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a class="sidebar-link active" href="visitor_history.php"><i class="bi bi-clock-history"></i> Visitor Logs</a>
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
                <div class="content-title">Visitor Logs</div>
                <div class="content-subtitle">Review and manage library visit records</div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <a href="reports.php" class="btn btn-blue">Generate Report</a>
            </div>
        </div>
        <div class="app-content">

<div class="container-fluid px-4 px-md-5 py-4">
    <div class="mb-4">
        <h2 class="fw-bold mb-0 text-blue">Visitor Access Logs</h2>
        <p class="text-muted">Search and filter library access records in real time.</p>
    </div>

    <div class="filter-section">
        <form id="filterForm" class="row g-3">
            <input type="hidden" name="ajax" value="1">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted">Search User</label>
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" id="searchInput" class="form-control border-start-0 border-end-0" autocomplete="off" aria-label="Search">
                    <button class="btn btn-blue" type="submit"><i class="bi bi-arrow-return-left me-1"></i> Search</button>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Date</label>
                <input type="date" name="filterDate" class="form-control shadow-sm filter-input">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Month</label>
                <select name="filterMonth" class="form-select shadow-sm filter-input">
                    <option value="">All Months</option>
                    <?php for ($m=1; $m<=12; $m++) echo "<option value='$m'>".date('F', mktime(0,0,0,$m,1))."</option>"; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted">Year</label>
                <select name="filterYear" class="form-select shadow-sm filter-input">
                    <option value="">All Years</option>
                    <?php for ($y=date('Y'); $y>=2023; $y--) echo "<option value='$y'>$y</option>"; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" id="clearFiltersBtn" class="btn btn-outline-secondary w-100 shadow-sm"><i class="bi bi-x-circle"></i> Clear</button>
            </div>
        </form>
    </div>

    <div class="table-card position-relative">
        <div id="loadingOverlay">
            <div class="spinner-border text-primary" role="status"></div>
        </div>
        <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-blue"><i class="bi bi-clock-history me-2"></i>Visitor Records</h5>
            <div class="d-flex align-items-center gap-2">
                <label class="small text-muted fw-bold">Rows:</label>
                <select id="rowsPerPage" class="form-select form-select-sm" style="width: auto;">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">ID Number</th>
                        <th>Full Name</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Reason</th>
                        <th>Date</th>
                        <th>Time In</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    </tbody>
            </table>
        </div>
        <div class="p-3 bg-white border-top d-flex justify-content-between align-items-center">
            <div class="small text-muted" id="paginationInfo">Showing 0 to 0 of 0 entries</div>
            <nav><ul class="pagination pagination-sm mb-0" id="paginationControls"></ul></nav>
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
let rowsPerPage = 10;

function applyPagination() {
    const $rows = $("#tableBody tr");
    const totalRows = $rows.length;
    rowsPerPage = parseInt($('#rowsPerPage').val(), 10) || 10;

    const totalPages = Math.max(Math.ceil(totalRows / rowsPerPage), 1);
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    $rows.hide();
    const start = (currentPage - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    $rows.slice(start, end).show();

    updatePaginationUI(totalRows, totalPages);
}

function updatePaginationUI(totalRows, totalPages) {
    const startIdx = totalRows > 0 ? (currentPage - 1) * rowsPerPage + 1 : 0;
    const endIdx = Math.min(currentPage * rowsPerPage, totalRows);
    $('#paginationInfo').text(`Showing ${startIdx} to ${endIdx} of ${totalRows} entries`);

    let controls = "";
    if (totalPages > 1) {
        controls += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}"><a class="page-link" onclick="changePage(${currentPage - 1})">Prev</a></li>`;
        for (let i = 1; i <= totalPages; i++) {
            controls += `<li class="page-item ${i === currentPage ? 'active' : ''}"><a class="page-link" onclick="changePage(${i})">${i}</a></li>`;
        }
        controls += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}"><a class="page-link" onclick="changePage(${currentPage + 1})">Next</a></li>`;
    }
    $('#paginationControls').html(controls);
}

function changePage(page) {
    currentPage = page;
    applyPagination();
}

$(document).ready(function() {
    function fetchLogs() {
        $('#loadingOverlay').css('display', 'flex');
        let formData = $('#filterForm').serialize();

        $.ajax({
            url: 'visitor_history.php',
            type: 'GET',
            data: formData,
            success: function(response) {
                $('#tableBody').html(response);
                $('#loadingOverlay').hide();
                currentPage = 1;
                applyPagination();
            }
        });
    }

    // Initial Load
    fetchLogs();

    // Submit Search (Enter Button)
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        fetchLogs();
    });

    // Auto-search on Dropdowns/Date Change
    $('.filter-input').change(function() {
        fetchLogs();
    });

    // Clear Filters
    $('#clearFiltersBtn').click(function() {
        $('#filterForm')[0].reset();
        fetchLogs();
    });

    // Live Search while typing (Optional - remove if you only want it on click)
    let typingTimer;
    $('#searchInput').on('keyup', function() {
        clearTimeout(typingTimer);
        typingTimer = setTimeout(fetchLogs, 500); // Wait 500ms after user stops typing
    });

    $('#rowsPerPage').on('change', function() {
        currentPage = 1;
        applyPagination();
    });
});
</script>
</body>
</html>
