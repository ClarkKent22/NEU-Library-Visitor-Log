<?php
session_start();
require_once '../includes/db_connect.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$adminID = $_SESSION['emplID'];
$adminQuery = $conn->prepare("SELECT profile_image, firstName, lastName FROM employees WHERE emplID = ?");
$adminQuery->bind_param("s", $adminID);
$adminQuery->execute();
$adminData = $adminQuery->get_result()->fetch_assoc();

$deptQuery = $conn->query("SELECT * FROM departments ORDER BY departmentName ASC");
$departments = $deptQuery->fetch_all(MYSQLI_ASSOC);

// Helper function for Table Avatars
function renderProfileImage($firstName, $lastName, $image, $type) {
    $folder = ($type === 'Student') ? 'student' : 'admin';
    $filename = $image;
    $urlPath = "../profilepictures/$folder/" . $filename;
    $filePath = __DIR__ . "/../profilepictures/$folder/" . $filename;

    if (!empty($filename) && $filename !== 'default.png' && file_exists($filePath)) {
        return '<img src="'.htmlspecialchars($urlPath).'" class="profile-img-sm me-2" alt="Profile">';
    }

    $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
    return '<div class="initials-avatar me-2">'.$initials.'</div>';
}

// --- AJAX DATA HANDLER ---
if (isset($_GET['ajax'])) {
    $deptFilter = $_GET['dept'] ?? '';
    $search = $_GET['search'] ?? '';
    $tableType = $_GET['type'] ?? 'students';
    $tableType = ($tableType === 'employees') ? 'employees' : 'students';

    $idCol = ($tableType === 'students') ? 'studentID' : 'emplID';
    $sortBy = $_GET['sort_by'] ?? 'lastName';
    $sortDir = $_GET['sort_dir'] ?? 'ASC';

    // Sanitize sort input
    $sortBy = ($sortBy === 'id') ? 'id' : 'lastName';
    $sortDir = (strtoupper($sortDir) === 'DESC') ? 'DESC' : 'ASC';

    $sortColumn = ($sortBy === 'id') ? "u.$idCol" : "u.lastName";

    // Fetch latest login time for today (avoid duplicates)
    $sql = "SELECT u.*, d.departmentName, h.last_login
            FROM $tableType u
            LEFT JOIN departments d ON u.departmentID = d.departmentID
            LEFT JOIN (
                SELECT user_identifier, MAX(time) AS last_login
                FROM history_logs
                WHERE date = CURDATE()
                GROUP BY user_identifier
            ) h ON h.user_identifier = u.$idCol
            WHERE 1=1";
    
    if ($deptFilter) {
        $sql .= " AND u.departmentID = '" . $conn->real_escape_string($deptFilter) . "'";
    }
    if ($search) {
        $searchVal = $conn->real_escape_string($search);
        $sql .= " AND (u.firstName LIKE '%$searchVal%' OR u.lastName LIKE '%$searchVal%' OR $idCol LIKE '%$searchVal%')";
    }
    $sql .= " ORDER BY $sortColumn $sortDir";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $userTypeLabel = ($tableType === 'students' ? 'Student' : 'Employee');
            
            // Determine login status
            $loginStatus = 'Not logged in';
            $loginBadgeClass = 'bg-secondary';
            $loginTime = '';
            
            if (!empty($row['last_login'])) {
                $loginStatus = 'Logged in today';
                $loginBadgeClass = 'bg-success';
                $loginTime = date('h:i A', strtotime($row['last_login']));
            }
            
            echo "<tr>";
            echo "<td class='ps-4'><div class='d-flex align-items-center'>" . renderProfileImage($row['firstName'], $row['lastName'], $row['profile_image'], $userTypeLabel) . "<span class='fw-bold'>{$row['firstName']} {$row['lastName']}</span></div></td>";
            echo "<td><span class='text-blue fw-semibold'>{$row[$idCol]}</span></td>";
            
            // Shared Columns for both tables
            echo "<td><span class='text-muted small'>".($row['departmentName'] ?? 'N/A')."</span></td>";
            
            if ($tableType === 'students') {
                echo "<td><span class='badge bg-light text-dark border'>Student</span></td>";
            } else {
                echo "<td><span class='badge bg-light text-dark border'>{$row['role']}</span></td>";
            }
            
            echo "<td><span class='status-badge bg-" . strtolower($row['status']) . "'>{$row['status']}</span></td>";
            echo "<td><span class='badge {$loginBadgeClass}'>{$loginStatus}</span>" . (!empty($loginTime) ? "<br><small class='text-muted'>{$loginTime}</small>" : "") . "</td>";
            echo "<td class='text-center'><button class='btn btn-sm btn-outline-primary px-3 view-user' data-id='{$row[$idCol]}' data-type='{$userTypeLabel}'><i class='bi bi-eye me-1'></i> View</button></td>";
            echo "</tr>";
        }
    } else {
        echo "NO_RESULTS_FOUND";
    }
    exit();
}

$photoFilename = $adminData['profile_image'] ?? null;
$photoUrl = "../profilepictures/admin/" . $photoFilename;
$photoFilePath = __DIR__ . "/../profilepictures/admin/" . $photoFilename;
$hasPhoto = (!empty($photoFilename) && file_exists($photoFilePath));
$adminInitials = strtoupper(substr($adminData['firstName'] ?? '', 0, 1) . substr($adminData['lastName'] ?? '', 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | NEU Library Admin</title>
    <link rel="icon" type="image/png" href="../assets/neu.png">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root { --neu-blue: #0038a8; --neu-hover: #002a80; --bg-light: #f1f4f9; }
        body { background-color: var(--bg-light); font-family: 'Segoe UI', sans-serif; }
        .navbar { background: white; border-bottom: 2px solid var(--neu-blue); padding: 0.5rem 2rem; }
        .nav-link { font-weight: 600; color: #555; transition: 0.2s; border-radius: 8px; margin: 0 3px; }
        .nav-link:hover { color: var(--neu-blue); background: #f8f9fa; }
        .nav-link.active { color: var(--neu-blue) !important; background: #eef2ff; }
        .text-blue { color: var(--neu-blue) !important; }
        .initials-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--neu-blue); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; }
        .profile-img-sm { width: 35px; height: 35px; object-fit: cover; border-radius: 50%; border: 1px solid #ddd; }
        .user-card, .table-card { background: white; border-radius: 16px; box-shadow: 0 16px 40px rgba(0,0,0,0.06); border: none; overflow: hidden; }
        .page-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1.5rem; }
        .page-header h2 { margin-bottom: 0.25rem; }
        .page-header p { margin-bottom: 0; }
        .filter-bar { background: #ffffff; border: 1px solid rgba(0,0,0,0.08); border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; }
        .filter-bar .form-control, .filter-bar .form-select { min-width: 170px; }
        .filter-bar .input-group { flex: 1 1 300px; max-width: 420px; }
        .table-card thead { background: rgba(0, 56, 168, 0.08); }
        .table-card tr:hover { background: rgba(0, 56, 168, 0.04); }
        .table-card th, .table-card td { vertical-align: middle; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .bg-active { background: #dcfce7; color: #15803d; }
        .bg-blocked { background: #fee2e2; color: #b91c1c; }
        #loadingOverlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.75); display: none; align-items: center; justify-content: center; z-index: 10; border-radius: 16px; }
        #confirmModal { z-index: 1070; }
        #deleteConfirmModal { z-index: 1080; }

        /* SweetAlert2 modal sizing + centering tweaks */
        .swal2-popup {
            max-width: 440px !important;
            width: 100% !important;
            padding: 1.25rem !important;
            box-shadow: 0 20px 52px rgba(0,0,0,0.18) !important;
        }
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
            <a class="sidebar-link" href="visitor_history.php"><i class="bi bi-clock-history"></i> Visitor Logs</a>
            <a class="sidebar-link" href="block.php"><i class="bi bi-shield-slash"></i> Blocked Users</a>
            <a class="sidebar-link" href="reports.php"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a>
            <a class="sidebar-link active" href="user_management.php"><i class="bi bi-people"></i> Users</a>
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
                <div class="content-title">Visitor Management</div>
                <div class="content-subtitle">Manage students and employees in one place</div>
            </div>
        </div>
        <div class="app-content">

<div class="container-fluid px-0 py-2">
        <div class="page-header">
            <div>
                <h2 class="fw-bold text-blue mb-1">Visitor Management</h2>
                <p class="text-muted mb-0">Real-time monitoring and administration of students and faculty access.</p>
            </div>
            <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-plus-lg me-2"></i> Add User
            </button>
        </div>

        <?php if (isset($_GET['msg'])): ?>
        <?php 
            $isSuccess = in_array($_GET['msg'], ['StatusUpdated', 'UserDeleted']);
            $alertClass = $isSuccess ? 'alert-success' : 'alert-danger';
            $icon = $isSuccess ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
        ?>
        <div id="statusAlert" class="alert <?= $alertClass ?> alert-dismissible fade show shadow-sm border-0 mb-4" role="alert" style="border-left: 5px solid <?= $isSuccess ? '#10b981' : '#dc3545' ?> !important;">
            <i class="bi <?= $icon ?> me-2"></i>
            <strong><?= $isSuccess ? 'Success!' : 'Error!' ?></strong>
            <?php 
                if ($_GET['msg'] == 'UserDeleted') echo 'User account and associated data have been permanently removed.';
                elseif ($_GET['msg'] == 'StatusUpdated') echo 'User status has been updated successfully.';
                else echo 'An error occurred during the operation.';
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="table-card position-relative">
        <div id="loadingOverlay"><div class="spinner-border text-primary"></div></div>

        <form id="filterForm" class="filter-bar">
            <div class="d-flex flex-wrap align-items-center gap-2 w-100">
                <div class="flex-grow-1">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search visitors by name or ID">
                    </div>
                </div>

                <div class="d-flex gap-2 align-items-center">
                    <button type="button" id="clearBtn" class="btn btn-outline-secondary btn-sm">Clear</button>
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted fw-bold mb-0">Rows:</label>
                    <select id="rowsPerPage" class="form-select form-select-sm" style="width: 80px;">
                        <option value="8" selected>8</option>
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="30">30</option>
                    </select>
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 w-100 mt-2">
                <select id="deptFilter" class="form-select form-select-sm">
                    <option value="">All Departments</option>
                    <?php foreach($departments as $d): ?>
                        <option value="<?= $d['departmentID'] ?>"><?= $d['departmentName'] ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="sortBy" class="form-select form-select-sm">
                    <option value="lastName">Sort by Last Name</option>
                    <option value="id">Sort by ID</option>
                </select>

                <select id="sortDir" class="form-select form-select-sm">
                    <option value="ASC">Ascending</option>
                    <option value="DESC">Descending</option>
                </select>
            </div>
        </form>

        <ul class="nav nav-tabs px-3 pt-2 bg-light border-bottom" id="userTabs">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#students-pane" id="studentTabBtn">
                    <i class="bi bi-person-fill me-1"></i> Students
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#employees-pane" id="employeeTabBtn">
                    <i class="bi bi-person-badge-fill me-1"></i> Faculty / Admin
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="students-pane">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small text-uppercase">
                            <tr><th class="ps-4">Full Name</th><th>Student ID</th><th>Department</th><th>Role</th><th>Status</th><th>Today's Activity</th><th class="text-center">Action</th></tr>
                        </thead>
                        <tbody id="studentTableBody"></tbody>
                    </table>
                </div>
                <div class="p-3 bg-white border-top d-flex justify-content-between align-items-center">
                    <div class="small text-muted" id="studentPagInfo"></div>
                    <nav><ul class="pagination pagination-sm mb-0" id="studentPagination"></ul></nav>
                </div>
            </div>

            <div class="tab-pane fade" id="employees-pane">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small text-uppercase">
                            <tr><th class="ps-4">Full Name</th><th>Employee ID</th><th>Department</th><th>Role</th><th>Status</th><th>Today's Activity</th><th class="text-center">Action</th></tr>
                        </thead>
                        <tbody id="employeeTableBody"></tbody>
                    </table>
                </div>
                <div class="p-3 bg-white border-top d-flex justify-content-between align-items-center">
                    <div class="small text-muted" id="employeePagInfo"></div>
                    <nav><ul class="pagination pagination-sm mb-0" id="employeePagination"></ul></nav>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-blue"><i class="bi bi-person-plus-fill me-2"></i>Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addUserForm" enctype="multipart/form-data">
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">User Type</label>
                        <select class="form-select" name="user_type" id="addTypeSelect" required>
                            <option value="Student" selected>Student</option>
                            <option value="Employee">Faculty / Admin</option>
                        </select>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">First Name</label>
                            <input type="text" name="firstName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Last Name</label>
                            <input type="text" name="lastName" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Institutional Email</label>
                        <input type="email" name="institutionalEmail" class="form-control" placeholder="example@neu.edu.ph" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Department</label>
                        <select name="departmentID" class="form-select" required>
                            <option value="">Select Department...</option>
                            <?php foreach($departments as $d): ?>
                                <option value="<?= $d['departmentID'] ?>"><?= $d['departmentName'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3" id="passwordFieldContainer" style="display: none;">
                        <label class="form-label fw-bold small text-muted">Password (Plain Text)</label>
                        <div class="input-group">
                            <input type="password" name="password" id="addPassword" class="form-control" placeholder="Set user password">
                            <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn">
                                <i class="bi bi-eye-slash" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Profile Image (Optional)</label>
                        <input type="file" name="profile_image" class="form-control" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-blue px-4 rounded-pill fw-bold">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="userDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4" id="userDetailsContent"></div>
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

<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="bi bi-trash3 text-danger mb-3 d-block" style="font-size: 3rem;"></i>
                <p class="fs-5 mb-1">Permanently delete <strong><span id="delUserNameText"></span></strong>?</p>
                <p class="text-muted small px-3">This action is irreversible. All activity logs and profile information for this user will be removed from the database.</p>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4">
                <button type="button" class="btn btn-light px-4 rounded-pill fw-bold" data-bs-dismiss="modal">Cancel</button>
                <a id="delFinalBtn" href="#" class="btn btn-danger px-4 rounded-pill fw-bold shadow-sm">Confirm Delete</a>
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
let currentType = 'students';
let currentPage = 1;

function fetchUsers(isAutoSwitch = false) {
    $('#loadingOverlay').css('display', 'flex');
    const search = $('#searchInput').val();
    const dept = $('#deptFilter').val();
    const sortBy = $('#sortBy').val();
    const sortDir = $('#sortDir').val();

    $.ajax({
        url: 'user_management.php',
        method: 'GET',
        data: { ajax: 1, type: currentType, search: search, dept: dept, sort_by: sortBy, sort_dir: sortDir },
        success: function(res) {
            const targetBody = currentType === 'students' ? '#studentTableBody' : '#employeeTableBody';
            
            if (res.trim() === "NO_RESULTS_FOUND" && search !== "") {
                const otherType = currentType === 'students' ? 'employees' : 'students';
                $.ajax({
                    url: 'user_management.php',
                    method: 'GET',
                    data: { ajax: 1, type: otherType, search: search, dept: dept, sort_by: sortBy, sort_dir: sortDir },
                    success: function(otherRes) {
                        if (otherRes.trim() !== "NO_RESULTS_FOUND") {
                            currentType = otherType;
                            const tabId = (currentType === 'students') ? '#studentTabBtn' : '#employeeTabBtn';
                            bootstrap.Tab.getOrCreateInstance(document.querySelector(tabId)).show();
                            fetchUsers(true);
                        } else {
                            $(targetBody).html("<tr><td colspan='7' class='text-center py-5 text-muted'>No users found match.</td></tr>");
                            currentPage = 1;
                            applyPagination();
                            $('#loadingOverlay').hide();
                        }
                    }
                });
            } else {
                if (res.trim() === "NO_RESULTS_FOUND") {
                    $(targetBody).html("<tr><td colspan='7' class='text-center py-5 text-muted'>No users found match.</td></tr>");
                } else {
                    $(targetBody).html(res);
                }
                
                currentPage = 1;
                applyPagination();
                $('#loadingOverlay').hide();
            }
        }
    });
}

function applyPagination() {
    const tableBody = currentType === 'students' ? '#studentTableBody' : '#employeeTableBody';
    const rows = $(tableBody).find('tr');
    const totalRows = rows.length;
    const rowsPerPage = parseInt($('#rowsPerPage').val());
    const totalPages = Math.max(Math.ceil(totalRows / rowsPerPage), 1);

    if (currentPage > totalPages) currentPage = totalPages;
    rows.hide().slice((currentPage - 1) * rowsPerPage, currentPage * rowsPerPage).show();

    const infoId = currentType === 'students' ? '#studentPagInfo' : '#employeePagInfo';
    const pagId = currentType === 'students' ? '#studentPagination' : '#employeePagination';
    
    $(infoId).text(`Showing ${totalRows > 0 ? (currentPage - 1) * rowsPerPage + 1 : 0} to ${Math.min(currentPage * rowsPerPage, totalRows)} of ${totalRows} entries`);

    let controls = "";
    if (totalPages > 1) {
        controls += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}"><a class="page-link" href="javascript:void(0)" onclick="changePage(${currentPage - 1})">Prev</a></li>`;
        for (let i = 1; i <= totalPages; i++) {
            controls += `<li class="page-item ${i === currentPage ? 'active' : ''}"><a class="page-link" href="javascript:void(0)" onclick="changePage(${i})">${i}</a></li>`;
        }
        controls += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}"><a class="page-link" href="javascript:void(0)" onclick="changePage(${currentPage + 1})">Next</a></li>`;
    }
    $(pagId).html(controls);
}

function changePage(page) {
    currentPage = page;
    applyPagination();
}

function showDeleteModal(id, type, name) {
    $('#userDetailsModal').modal('hide');
    $('#delUserNameText').text(name);
    $('#delFinalBtn').attr('href', `delete_user.php?id=${id}&type=${type.toLowerCase()}`);
    const delModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    delModal.show();
}

function confirmStatusChange(url, action, name) {
    $('#userDetailsModal').modal('hide');
    const modalBody = document.getElementById('modalBodyText');
    const confirmBtn = document.getElementById('modalConfirmBtn');
    
    if (action.toLowerCase() === 'block') {
        modalBody.innerHTML = `
            <p>Are you sure you want to <strong>block</strong> ${name}?</p>
            <div class="mt-3">
                <label class="small fw-bold text-muted mb-1">Reason for blocking:</label>
                <textarea id="blockReasonInput" class="form-control" rows="3" placeholder="e.g. Violation of policy" required></textarea>
            </div>`;
        confirmBtn.className = 'btn btn-danger px-4 rounded-pill';
        confirmBtn.innerText = 'Block User';
    } else {
        modalBody.innerHTML = `Are you sure you want to <strong>restore access</strong> for <b>${name}</b>?`;
        confirmBtn.className = 'btn btn-success px-4 rounded-pill';
        confirmBtn.innerText = 'Unblock User';
    }

    confirmBtn.onclick = function(e) {
        e.preventDefault();
        let finalUrl = url;
        if (action.toLowerCase() === 'block') {
            const reasonInput = document.getElementById('blockReasonInput');
            const reason = reasonInput.value.trim();
            if (!reason) {
                reasonInput.classList.add('is-invalid');
                reasonInput.focus();
                return;
            }
            finalUrl += `&reason=${encodeURIComponent(reason)}`;
        }
        window.location.href = finalUrl;
    };

    const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
    confirmModal.show();
}

$(document).ready(function() {
    fetchUsers();
    $('#filterForm').on('submit', function(e) { e.preventDefault(); fetchUsers(); });
    $('#clearBtn').on('click', function() { $('#filterForm')[0].reset(); fetchUsers(); });
    $('#rowsPerPage').on('change', function() { currentPage = 1; applyPagination(); });
    $('#sortBy, #sortDir').on('change', function() { currentPage = 1; fetchUsers(); });

    $('#studentTabBtn').on('click', function() { if(currentType !== 'students') { currentType = 'students'; fetchUsers(); } });
    $('#employeeTabBtn').on('click', function() { if(currentType !== 'employees') { currentType = 'employees'; fetchUsers(); } });

    $(document).on('click', '.view-user', function() {
        const id = $(this).data('id');
        const type = $(this).data('type');
        $('#userDetailsModal').modal('show');
        $('#userDetailsContent').html('<div class="p-5 text-center"><div class="spinner-border text-primary"></div></div>');
        $.ajax({
            url: 'fetch_user_details.php',
            method: 'POST',
            data: { id: id, type: type },
            success: function(response) { $('#userDetailsContent').html(response); }
        });
    });

    if ($('#statusAlert').length) setTimeout(() => { bootstrap.Alert.getOrCreateInstance($('#statusAlert')[0]).close(); }, 5000);

    // ==========================================
    // ADD USER MODAL JAVASCRIPT LOGIC
    // ==========================================
    
    // 1. Toggle Password field visibility based on user type
    $('#addTypeSelect').on('change', function() {
        if ($(this).val() === 'Employee') {
            $('#passwordFieldContainer').slideDown();
            $('#addPassword').attr('required', true);
        } else {
            $('#passwordFieldContainer').slideUp();
            $('#addPassword').removeAttr('required').val('');
        }
    });

    // 2. Toggle Show/Hide for Plaintext Password
    $('#togglePasswordBtn').on('click', function() {
        const passField = $('#addPassword');
        const icon = $('#togglePasswordIcon');
        if (passField.attr('type') === 'password') {
            passField.attr('type', 'text');
            icon.removeClass('bi-eye-slash').addClass('bi-eye');
        } else {
            passField.attr('type', 'password');
            icon.removeClass('bi-eye').addClass('bi-eye-slash');
        }
    });

    // 3. Handle Add User Form Submission via AJAX
    $('#addUserForm').on('submit', function(e) {
        e.preventDefault();
        
        // Domain Check
        const emailInput = $(this).find('input[name="institutionalEmail"]').val().toLowerCase();
        const domain = "@neu.edu.ph";
        if (!emailInput.endsWith(domain)) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Email',
                text: 'Only @neu.edu.ph addresses are permitted.',
                confirmButtonColor: '#0038a8',
                position: 'center',
                width: 420
            });
            return false;
        }

        let formData = new FormData(this);
        const submitBtn = $(this).find('button[type="submit"]');
        const originalBtnHtml = submitBtn.html();
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');

        $.ajax({
            url: 'add_user_process.php',
            method: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(res) {
                if(res.trim() === 'success') {
                    Swal.fire({ icon: 'success', title: 'User Added Successfully!', showConfirmButton: false, timer: 2000, position: 'top-end', toast: true });
                    
                    // Close modal and reset form
                    $('#addUserModal').modal('hide');
                    $('#addUserForm')[0].reset();
                    $('#passwordFieldContainer').hide();
                    
                    // Refresh the table
                    fetchUsers(); 
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Add Failed',
                        text: res,
                        confirmButtonColor: '#0038a8',
                        position: 'center',
                        width: 420
                    });
                }
                submitBtn.prop('disabled', false).html(originalBtnHtml);
            },
            error: function() {
                alert("A system error occurred.");
                submitBtn.prop('disabled', false).html(originalBtnHtml);
            }
        });
    });
});

function loadEditForm(id, type) {
    $('#userDetailsContent').html('<div class="p-5 text-center"><div class="spinner-border text-primary"></div></div>');
    $.ajax({
        url: 'fetch_edit_from.php',
        method: 'POST',
        data: { id: id, type: type },
        success: function(response) { $('#userDetailsContent').html(response); }
    });
}

$(document).on('submit', '#editUserForm', function(e) {
    e.preventDefault();
    const emailInput = $(this).find('input[name="email"]').val().toLowerCase();
    const domain = "@neu.edu.ph";

    if (!emailInput.endsWith(domain)) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid Email',
            text: 'Only @neu.edu.ph addresses are permitted.',
            confirmButtonColor: '#0038a8'
        });
        return false;
    }

    let formData = new FormData(this);
    const submitBtn = $(this).find('button[type="submit"]');
    const originalBtnHtml = submitBtn.html();
    submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');

    $.ajax({
        url: 'update_user_process.php',
        method: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        success: function(res) {
            if(res.trim() === 'success') {
                Swal.fire({ icon: 'success', title: 'Changes Saved!', showConfirmButton: false, timer: 2000, position: 'top-end', toast: true });
                viewUserDetails(formData.get('id'), formData.get('type'));
                fetchUsers(); 
            } else {
                Swal.fire({ icon: 'error', title: 'Update Failed', text: res });
                submitBtn.prop('disabled', false).html(originalBtnHtml);
            }
        },
        error: function() {
            alert("A system error occurred.");
            submitBtn.prop('disabled', false).html(originalBtnHtml);
        }
    });
});

function viewUserDetails(id, type) {
    $.ajax({
        url: 'fetch_user_details.php',
        method: 'POST',
        data: { id: id, type: type },
        success: function(response) { $('#userDetailsContent').html(response); }
    });
}
</script>
</body>
</html>
