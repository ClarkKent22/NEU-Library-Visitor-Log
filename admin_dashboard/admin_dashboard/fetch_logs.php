<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

require_once '../includes/db_connect.php';

date_default_timezone_set('Asia/Manila'); 
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime("-1 day"));

// Analytics Queries
$todayRes = $conn->query("SELECT COUNT(*) as count FROM history_logs WHERE date = '$today'");
$countToday = $todayRes->fetch_assoc()['count'] ?? 0;

$yesterdayRes = $conn->query("SELECT COUNT(*) as count FROM history_logs WHERE date = '$yesterday'");
$countYesterday = $yesterdayRes->fetch_assoc()['count'] ?? 0;

$weekRes = $conn->query("SELECT COUNT(*) as count FROM history_logs WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$countWeek = $weekRes->fetch_assoc()['count'] ?? 0;

$prevWeekRes = $conn->query("SELECT COUNT(*) as count FROM history_logs WHERE date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$countPrevWeek = $prevWeekRes->fetch_assoc()['count'] ?? 0;

$monthRes = $conn->query("SELECT COUNT(*) as count FROM history_logs WHERE MONTH(date) = MONTH(CURRENT_DATE()) AND YEAR(date) = YEAR(CURRENT_DATE())");
$countMonth = $monthRes->fetch_assoc()['count'] ?? 0;

$prevMonthRes = $conn->query("SELECT COUNT(*) as count FROM history_logs WHERE MONTH(date) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) AND YEAR(date) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))");
$countPrevMonth = $prevMonthRes->fetch_assoc()['count'] ?? 0;

$overallRes = $conn->query("SELECT COUNT(*) as count FROM history_logs");
$countOverall = $overallRes->fetch_assoc()['count'] ?? 0;

$blockedRes = $conn->query("SELECT (SELECT COUNT(*) FROM students WHERE status = 'Blocked') + (SELECT COUNT(*) FROM employees WHERE status = 'Blocked') as total_blocked");
$totalBlockedGlobal = $blockedRes->fetch_assoc()['total_blocked'] ?? 0;

$activeRes = $conn->query("SELECT (SELECT COUNT(*) FROM students WHERE status = 'Active') + (SELECT COUNT(*) FROM employees WHERE status = 'Active') as count");
$totalActiveGlobal = $activeRes->fetch_assoc()['count'] ?? 0;

$trendWeek = ($countPrevWeek > 0) ? round((($countWeek - $countPrevWeek) / $countPrevWeek) * 100) : ($countWeek > 0 ? 100 : 0);
$trendMonth = ($countPrevMonth > 0) ? round((($countMonth - $countPrevMonth) / $countPrevMonth) * 100) : ($countMonth > 0 ? 100 : 0);

// Table Data Query
$logQuery = "
    SELECT 
        h.logID, h.user_identifier, h.user_type, h.date, h.time, h.reason,
        COALESCE(s.firstName, e.firstName, '') as firstName,
        COALESCE(s.lastName, e.lastName, '') as lastName,
        COALESCE(s.profile_image, e.profile_image) as user_pic,
        COALESCE(s.status, e.status) as user_status,
        COALESCE(d1.departmentName, d2.departmentName) as program
    FROM history_logs h
    LEFT JOIN students s ON h.user_identifier = s.studentID AND h.user_type = 'Student'
    LEFT JOIN employees e ON h.user_identifier = e.emplID AND h.user_type != 'Student'
    LEFT JOIN departments d1 ON s.departmentID = d1.departmentID
    LEFT JOIN departments d2 ON e.departmentID = d2.departmentID
    WHERE h.date = '$today' 
    ORDER BY h.time DESC";

$logsResult = $conn->query($logQuery);

$html = "";
if($logsResult && $logsResult->num_rows > 0) {
    while($row = $logsResult->fetch_assoc()) {
        // Handle names carefully for deleted users
        $firstName = trim($row['firstName'] ?? '');
        $lastName = trim($row['lastName'] ?? '');
        
        // If no name found, use the role type instead
        if (empty($firstName) && empty($lastName)) {
            $firstName = strtoupper($row['user_type']);
            $lastName = '';
        }
        
        $isDeleted = false; // Don't mark as deleted anymore since we show role type

        $statusRaw = $row['user_status'] ?? 'N/A';
        $isBlocked = (strtolower($statusRaw) === 'blocked');
        
        // ROLE DISPLAY LOGIC - Detect from database or lookup by ID
        $rawUserType = trim($row['user_type'] ?? '');
        
        // If empty, try to detect from employees/students table
        if (empty($rawUserType)) {
            // Try to find in employees first (for admin/faculty)
            $empCheck = $conn->prepare("SELECT role FROM employees WHERE emplID = ? LIMIT 1");
            $empCheck->bind_param("s", $row['user_identifier']);
            $empCheck->execute();
            $empRes = $empCheck->get_result();
            
            if ($empRes->num_rows > 0) {
                $empRow = $empRes->fetch_assoc();
                $empRole = trim($empRow['role'] ?? '');
                if (stripos($empRole, 'Admin') !== false) {
                    $rawUserType = 'Admin';
                } else {
                    $rawUserType = 'Faculty';
                }
            } else {
                // Not in employees, must be student
                $rawUserType = 'Student';
            }
            $empCheck->close();
        }
        
        // Now map to display values
        $lowerType = strtolower($rawUserType);
        $roleDisplay = 'Unknown';
        $roleBadgeClass = 'role-faculty';
        
        if (strpos($lowerType, 'student') !== false) {
            $roleDisplay = 'Student';
            $roleBadgeClass = 'role-student';
        } elseif (strpos($lowerType, 'admin') !== false || stripos($rawUserType, 'Admin') !== false) {
            $roleDisplay = 'Admin';
            $roleBadgeClass = 'role-admin';
        } elseif (strpos($lowerType, 'faculty') !== false || strpos($lowerType, 'employee') !== false) {
            $roleDisplay = 'Faculty';
            $roleBadgeClass = 'role-faculty';
        } else {
            // Fallback: just show what we have
            $roleDisplay = !empty($rawUserType) ? ucfirst($rawUserType) : 'Unknown';
        }
        
        $uType = $lowerType;
        $pTypeFolder = (strpos($uType, 'student') !== false) ? 'student' : 'admin'; 
        
        $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
        $photoFilename = $row['user_pic'];
        $userPhotoUrl = "../profilepictures/" . $pTypeFolder . "/" . $photoFilename;
        $userPhotoFsPath = __DIR__ . "/../profilepictures/" . $pTypeFolder . "/" . $photoFilename;
        
        if (!$isDeleted && !empty($photoFilename) && $photoFilename !== 'default.png' && file_exists($userPhotoFsPath)) {
            $imgHtml = "<img src='" . htmlspecialchars($userPhotoUrl, ENT_QUOTES) . "' class='visitor-avatar me-3 shadow-sm' onerror=\"this.outerHTML='<div class=\\'initials-avatar me-3 shadow-sm\\'>$initials</div>'\">";
        } else {
            $imgHtml = "<div class='initials-avatar me-3 shadow-sm' style='background:#0038a8;'>$initials</div>";
        }

        $fullName = htmlspecialchars($firstName . ' ' . $lastName);
        
        // Logic for Action Button
        if ($isDeleted) {
            $actionBtn = "<span class='badge bg-secondary opacity-50'>System Record</span>";
            $statusBadge = "<span class='badge bg-secondary rounded-pill' style='font-size:10px;'>DELETED</span>";
        } else {
            $jsFriendlyName = addslashes($fullName);
            $actionVerb = $isBlocked ? 'unblock' : 'block';
            $btnClass = $isBlocked ? 'btn-unblock' : 'btn-block';
            $btnText = $isBlocked ? 'Unblock' : 'Block User';
            $toggleUrl = "toggle_status.php?user_id=" . urlencode($row['user_identifier']) . "&user_type=" . urlencode($uType) . "&current_status=" . urlencode($statusRaw);
            
            $actionBtn = "<button type='button' onclick=\"confirmStatusChange('$toggleUrl', '$actionVerb', '$jsFriendlyName')\" class='btn btn-status-action $btnClass shadow-sm'>$btnText</button>";
            $statusBadge = "<span class='badge ".($isBlocked ? 'bg-danger' : 'bg-success')." rounded-pill' style='font-size:10px;'>".strtoupper($statusRaw)."</span>";
        }

        $html .= "
        <tr>
            <td class='ps-4'>
                <div class='d-flex align-items-center'>
                    $imgHtml
                    <div>
                        <div class='fw-bold'>$fullName</div>
                        <div class='text-muted small'>ID: ".htmlspecialchars($row['user_identifier'])."</div>
                    </div>
                </div>
            </td>
            <td class='small text-muted'>".htmlspecialchars($row['program'] ?? 'N/A')."</td>
            <td><span class='badge-role $roleBadgeClass' style='display: inline-block; padding: 5px 12px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; background: rgba(43,111,255,0.15); color: #9cc1ff;'>" . strtoupper($roleDisplay) . "</span></td>
            <td>
                <div class='fw-bold small text-blue'>" . date('M d, Y', strtotime($row['date'])) . "</div>
                <div class='text-muted small'>" . date('h:i A', strtotime($row['time'])) . "</div>
            </td>
            <td>
                <span class='small text-dark fw-medium'>
                    <i class='bi bi-chat-left-text me-1 text-blue'></i> " . htmlspecialchars($row['reason'] ?: 'N/A') . "
                </span>
            </td>
            <td>$statusBadge</td>
            <td class='text-center action-col'>$actionBtn</td>
        </tr>";
    }
} else {
    $html = "<tr><td colspan='7' class='text-center py-5 text-muted'>No visitors logged today ($today).</td></tr>";
}

echo json_encode([
    'html' => $html,
    'todayCount' => (int)$countToday,
    'yesterdayCount' => (int)$countYesterday,
    'overallCount' => (int)$countOverall,
    'globalBlockedCount' => (int)$totalBlockedGlobal,
    'totalActiveCount' => (int)$totalActiveGlobal,
    'weekCount' => (int)$countWeek,
    'monthCount' => (int)$countMonth,
    'trendWeek' => (int)$trendWeek,
    'trendMonth' => (int)$trendMonth
]);