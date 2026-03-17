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
        s.firstName as sFN, s.lastName as sLN, e.firstName as eFN, e.lastName as eLN,
        COALESCE(s.profile_image, e.profile_image) as user_pic,
        COALESCE(s.status, e.status) as user_status,
        d.departmentName as program
    FROM history_logs h
    LEFT JOIN students s ON h.user_identifier = s.studentID AND h.user_type = 'Student'
    LEFT JOIN employees e ON h.user_identifier = e.emplID AND h.user_type = 'Employee'
    LEFT JOIN departments d ON d.departmentID = COALESCE(s.departmentID, e.departmentID)
    WHERE h.date = '$today' 
    ORDER BY h.time DESC";

$logsResult = $conn->query($logQuery);

$html = "";
if($logsResult && $logsResult->num_rows > 0) {
    while($row = $logsResult->fetch_assoc()) {
        // Handle names carefully for deleted users
        $firstName = $row['sFN'] ?? $row['eFN'] ?? 'Unknown';
        $lastName = $row['sLN'] ?? $row['eLN'] ?? 'User';
        $isDeleted = ($firstName === 'Unknown');

        $statusRaw = $row['user_status'] ?? 'N/A';
        $isBlocked = (strtolower($statusRaw) === 'blocked');
        $uType = strtolower($row['user_type']);
        $pTypeFolder = ($uType === 'student') ? 'student' : 'admin'; 
        
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
            <td><span class='badge-role ".($uType === 'student' ? 'role-student' : 'role-employee')."'>" . strtoupper($row['user_type']) . "</span></td>
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