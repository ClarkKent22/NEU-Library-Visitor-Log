<?php
session_start();
require_once '../includes/db_connect.php';

// 1. SECURITY: Only logged-in admins can toggle status
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Support both Form POST and JavaScript URL GET (via $_REQUEST)
$userId = $_REQUEST['user_id'] ?? '';
$userType = strtolower(trim($_REQUEST['user_type'] ?? '')); 
$currentStatus = strtolower(trim($_REQUEST['current_status'] ?? ''));

// Get reason from the request, or use a default if blocking
$reason = $_REQUEST['reason'] ?? 'Violation of Library Policy';

if (!empty($userId) && !empty($userType)) {
    
    // 2. Determine new status
    $newStatus = ($currentStatus === 'blocked') ? 'Active' : 'Blocked';
    
    // 3. Routing: Determine table and ID column
    if ($userType === 'student') {
        $table = 'students';
        $idColumn = 'studentID';
    } else {
        $table = 'employees';
        $idColumn = 'emplID';
    }

    // 4. Update the database using Prepared Statements
    if ($newStatus === 'Blocked') {
        // When blocking: Update status, save the reason, and set current time
        $sql = "UPDATE $table SET status = ?, block_reason = ?, date_blocked = NOW() WHERE $idColumn = ?";
        $stmt = $conn->prepare($sql);
        // FIXED: Changed "ssss" to "sss" because there are 3 variables
        $stmt->bind_param("sss", $newStatus, $reason, $userId);
    } else {
        // When unblocking: Set status to Active and CLEAR the reason/date
        $sql = "UPDATE $table SET status = ?, block_reason = NULL, date_blocked = NULL WHERE $idColumn = ?";
        $stmt = $conn->prepare($sql);
        // Corrected: 2 placeholders (?) need 2 variables
        $stmt->bind_param("ss", $newStatus, $userId);
    }

    if ($stmt->execute()) {
        // 5. SMART REDIRECT (keep user on the originating page)
        $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';

        if (strpos($referer, 'block.php') !== false) {
            header("Location: block.php?msg=StatusUpdated");
        } elseif (strpos($referer, 'user_management.php') !== false) {
            header("Location: user_management.php?msg=StatusUpdated");
        } elseif (strpos($referer, 'visitor_history.php') !== false) {
            header("Location: visitor_history.php?msg=StatusUpdated");
        } else {
            header("Location: index.php?msg=StatusUpdated");
        }
    } else {
        header("Location: index.php?msg=Error");
    }
    
    $stmt->close();
    exit();
} else {
    header("Location: index.php");
    exit();
}
?>
