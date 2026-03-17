<?php
session_start();
require_once '../includes/db_connect.php'; 

// 1. SECURITY CHECK
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// --- AJAX STATUS UPDATE HANDLER ---
if (isset($_POST['ajax_update'])) {
    $rid = $_POST['report_id'];
    $newStatus = $_POST['new_status'];
    $stmt = $conn->prepare("UPDATE problem_reports SET status = ? WHERE reportID = ?");
    $stmt->bind_param("si", $newStatus, $rid);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit();
}

// --- ADMIN DATA FETCHING ---
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

// --- FILTER LOGIC ---
$where_clauses = [];
$params = [];
$types = "";

if (!empty($_GET['status_filter'])) {
    $where_clauses[] = "status = ?";
    $params[] = $_GET['status_filter'];
    $types .= "s";
}

if (!empty($_GET['type_filter'])) {
    $where_clauses[] = "issue_type = ?";
    $params[] = $_GET['type_filter'];
    $types .= "s";
}

if (!empty($_GET['date_from'])) {
    $where_clauses[] = "DATE(created_at) >= ?";
    $params[] = $_GET['date_from'];
    $types .= "s";
}

if (!empty($_GET['date_to'])) {
    $where_clauses[] = "DATE(created_at) <= ?";
    $params[] = $_GET['date_to'];
    $types .= "s";
}

$sql = "SELECT * FROM problem_reports";
if (count($where_clauses) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$reportsQuery = $stmt->get_result();

$typeOptions = $conn->query("SELECT DISTINCT issue_type FROM problem_reports");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Problem Reports | NEU Library Admin</title>
    <link rel="icon" type="image/png" href="../assets/neu.png">
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
        .btn-blue { background-color: var(--neu-blue); color: white; border-radius: 50px; }
        .btn-blue:hover { background-color: var(--neu-hover); color: white; }
        .initials-avatar { width: 40px; height: 40px; border-radius: 50%; background: var(--neu-blue); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; }
        
        .filter-section { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .table-card { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: none; }
        
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; transition: all 0.3s ease; }
        .status-pending { background: #fff7ed; color: #c2410c; }
        .status-inprogress { background: #eef2ff; color: #4338ca; }
        .status-resolved { background: #dcfce7; color: #15803d; }

        .delete-col { display: none; width: 50px; text-align: center; }

        /* Toast Styling */
        #ajaxToast {
            position: fixed; top: 20px; right: 20px; z-index: 9999;
            display: none; background: #333; color: white;
            padding: 12px 24px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.2);
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
            <a class="sidebar-link active" href="reports.php"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a>
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
                <div class="content-title">Problem Reports</div>
                <div class="content-subtitle">Review and manage submitted issues</div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-blue" onclick="exportToCSV()">Export CSV</button>
            </div>
        </div>
        <div class="app-content">

<div id="ajaxToast"></div>

<div class="container-fluid px-0 py-2">
    <div class="mb-4">
        <h2 class="fw-bold mb-0 text-blue">Problem Reports</h2>
        <p class="text-muted mb-0">Review and manage technical or facility issues.</p>
    </div>

    <div class="filter-section">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">Status</label>
                <select name="status_filter" class="form-select shadow-sm">
                    <option value="">All Statuses</option>
                    <option value="Pending" <?= (($_GET['status_filter'] ?? '') == 'Pending') ? 'selected' : '' ?>>Pending</option>
                    <option value="In Progress" <?= (($_GET['status_filter'] ?? '') == 'In Progress') ? 'selected' : '' ?>>In Progress</option>
                    <option value="Resolved" <?= (($_GET['status_filter'] ?? '') == 'Resolved') ? 'selected' : '' ?>>Resolved</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">Issue Type</label>
                <select name="type_filter" class="form-select shadow-sm">
                    <option value="">All Types</option>
                    <?php while($t = $typeOptions->fetch_assoc()): ?>
                        <option value="<?= $t['issue_type'] ?>" <?= (($_GET['type_filter'] ?? '') == $t['issue_type']) ? 'selected' : '' ?>>
                            <?= $t['issue_type'] ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted">Filter by Date Range</label>
                <div class="input-group">
                    <input type="date" name="date_from" class="form-control shadow-sm" value="<?= $_GET['date_from'] ?? '' ?>">
                    <span class="input-group-text bg-transparent border-0">to</span>
                    <input type="date" name="date_to" class="form-control shadow-sm" value="<?= $_GET['date_to'] ?? '' ?>">
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-blue w-100 shadow-sm"><i class="bi bi-filter"></i> Apply</button>
            </div>
        </form>
    </div>

    <div class="table-card">
        <div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-blue"><i class="bi bi-exclamation-triangle me-2"></i>Reported Issues</h5>
            
            <div class="d-flex align-items-center gap-2" style="width: 45%;">
                <input type="text" id="reportSearch" class="form-control form-control-sm" placeholder="Search reports...">
                <div id="deleteActionControls">
                    <button type="button" id="toggleDeleteMode" class="btn btn-outline-danger btn-sm rounded-pill px-3 text-nowrap">
                        <i class="bi bi-trash3 me-1"></i> Delete a Report
                    </button>
                    <div id="activeDeleteControls" class="d-none d-flex gap-2">
                        <button type="button" id="btnBulkDelete" class="btn btn-danger btn-sm rounded-pill px-3 shadow-sm text-nowrap" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal" disabled>
                            Delete (<span id="selectedCount">0</span>)
                        </button>
                        <button type="button" id="cancelDeleteMode" class="btn btn-light btn-sm rounded-pill px-3 border text-nowrap">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <form id="bulkDeleteForm" method="POST" action="delete_report.php">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="reportsTable">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 delete-col"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                            <th class="ps-4">ID</th>
                            <th>User</th>
                            <th>Issue Type</th>
                            <th style="width: 30%;">Description</th>
                            <th>Date Reported</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $reportsQuery->fetch_assoc()): 
                            $statusClean = strtolower(str_replace(' ', '', $row['status']));
                        ?>
                        <tr id="row-<?= $row['reportID'] ?>">
                            <td class="ps-4 delete-col">
                                <input type="checkbox" name="report_ids[]" value="<?= $row['reportID'] ?>" class="form-check-input report-checkbox">
                            </td>
                            <td class="ps-4 text-muted small">#<?= $row['reportID'] ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($row['user_identifier']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['issue_type']) ?></span></td>
                            <td class="small text-muted"><?= htmlspecialchars($row['description']) ?></td>
                            <td class="small"><?= date('M d, Y | h:i A', strtotime($row['created_at'])) ?></td>
                            <td>
                                <span class="status-badge status-<?= $statusClean ?>" id="badge-<?= $row['reportID'] ?>">
                                    <?= $row['status'] ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <select class="form-select form-select-sm d-inline-block w-auto shadow-sm status-ajax-trigger" 
                                        data-id="<?= $row['reportID'] ?>">
                                    <option value="Pending" <?= $row['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="In Progress" <?= $row['status'] == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="Resolved" <?= $row['status'] == 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                                </select>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4"><p>Are you sure you want to delete the selected reports?</p></div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger px-4 rounded-pill" onclick="document.getElementById('bulkDeleteForm').submit();">Confirm Delete</button>
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
    $(document).ready(function(){
        // 1. AJAX Status Update
        $(".status-ajax-trigger").on("change", function() {
            const select = $(this);
            const reportID = select.data('id');
            const newStatus = select.val();
            const badge = $("#badge-" + reportID);

            // Visual feedback while saving
            select.prop('disabled', true);
            
            $.ajax({
                url: 'reports.php',
                method: 'POST',
                data: { 
                    ajax_update: 1, 
                    report_id: reportID, 
                    new_status: newStatus 
                },
                dataType: 'json',
                success: function(response) {
                    if(response.status === 'success') {
                        // Update the badge UI without reload
                        badge.text(newStatus);
                        // Clear old status classes and add new one
                        badge.removeClass('status-pending status-inprogress status-resolved');
                        const classStatus = newStatus.toLowerCase().replace(/\s/g, '');
                        badge.addClass('status-' + classStatus);
                        
                        showToast("Status updated to " + newStatus);
                    } else {
                        alert("Error updating status. Please try again.");
                    }
                },
                error: function() {
                    alert("System error. Check connection.");
                },
                complete: function() {
                    select.prop('disabled', false);
                }
            });
        });

        function showToast(message) {
            const toast = $("#ajaxToast");
            toast.text(message).fadeIn();
            setTimeout(() => toast.fadeOut(), 3000);
        }

        // 2. Toggle Delete Mode (Rest of your script remains)
        $("#toggleDeleteMode").on("click", function() {
            $(this).addClass("d-none");
            $("#activeDeleteControls").removeClass("d-none").addClass("d-flex");
            $(".delete-col").fadeIn();
        });

        $("#cancelDeleteMode").on("click", function() {
            $("#activeDeleteControls").removeClass("d-flex").addClass("d-none");
            $("#toggleDeleteMode").removeClass("d-none");
            $(".delete-col").fadeOut();
            $(".report-checkbox, #selectAll").prop('checked', false);
            updateDeleteCount();
        });

        $("#selectAll").on("click", function() {
            $(".report-checkbox").prop('checked', $(this).prop('checked'));
            updateDeleteCount();
        });

        $(document).on("change", ".report-checkbox", function() {
            updateDeleteCount();
        });

        function updateDeleteCount() {
            var checkedCount = $(".report-checkbox:checked").length;
            $("#selectedCount").text(checkedCount);
            $("#btnBulkDelete").prop('disabled', checkedCount === 0);
        }

        $("#reportSearch").on("keyup", function() {
            var value = $(this).val().toLowerCase();
            $("#reportsTable tbody tr").filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });
    });
</script>
</body>
</html>
