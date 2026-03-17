<?php
session_start();
// FIX: Back out of entrynavpages to find includes
require_once '../includes/db_connect.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['reason'])) {
    // FIX: Back out to root index
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$reason = $_POST['reason'];
$specific_details = isset($_POST['specific_reason']) ? trim($_POST['specific_reason']) : '';

// --- SAFETY RE-CHECK: ensure user is still not blocked ---
$blockCheckSql = "
    SELECT 'student' AS src, firstName, lastName, profile_image, departmentID, status, studentID AS id
    FROM students WHERE studentID = ?
    UNION ALL
    SELECT 'employee' AS src, firstName, lastName, profile_image, departmentID, status, emplID AS id
    FROM employees WHERE emplID = ?
    LIMIT 1
";
$blockStmt = $conn->prepare($blockCheckSql);
$blockStmt->bind_param("ss", $user_id, $user_id);
$blockStmt->execute();
$blockRes = $blockStmt->get_result();
if ($u = $blockRes->fetch_assoc()) {
    if (strtolower($u['status']) === 'blocked') {
        $_SESSION['blocked_user'] = [
            'firstName'    => $u['firstName'],
            'lastName'     => $u['lastName'],
            'profile_image'=> $u['profile_image'],
            'departmentID' => $u['departmentID'],
            'type'         => $u['src'] === 'student' ? 'student' : 'admin',
            'studentID'    => $u['src'] === 'student' ? $u['id'] : null,
            'emplID'       => $u['src'] === 'employee' ? $u['id'] : null,
        ];
        unset($_SESSION['user_id']);
        header("Location: ../index.php?error=blocked");
        exit();
    }
}

// --- CLEAN DATA LOGIC ---
// If 'Others' is selected, we discard the word 'Others' and use the custom text only.
if ($reason == "Others" && !empty($specific_details)) {
    $reason = $specific_details; 
}
// ------------------------

// 1. DUPLICATE CHECK (Prevent multiple entries per day)
$check_sql = "SELECT logID FROM history_logs WHERE user_identifier = ? AND date = CURDATE()";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("s", $user_id);
$check_stmt->execute();
if ($check_stmt->get_result()->num_rows > 0) {
    unset($_SESSION['user_id']);
    // FIX: Back out to root index
    header("Location: ../index.php?error=already_logged");
    exit();
}

// 2. ROLE DETECTION
$user_type = null;

// Check Students table
$stmt_stu = $conn->prepare("SELECT studentID FROM students WHERE studentID = ?");
$stmt_stu->bind_param("s", $user_id);
$stmt_stu->execute();
if ($stmt_stu->get_result()->num_rows > 0) {
    $user_type = 'Student';
} else {
    // Check Employees table
    $stmt_emp = $conn->prepare("SELECT emplID FROM employees WHERE emplID = ?");
    $stmt_emp->bind_param("s", $user_id);
    $stmt_emp->execute();
    if ($stmt_emp->get_result()->num_rows > 0) {
        $user_type = 'Employee';
    }
}

if (is_null($user_type)) {
    die("Error: User type could not be determined for ID: " . htmlspecialchars($user_id));
}

// 3. INSERT INTO HISTORY_LOGS
$sql = "INSERT INTO history_logs (user_identifier, user_type, date, time, reason) 
        VALUES (?, ?, CURDATE(), CURTIME(), ?)";

$stmt_insert = $conn->prepare($sql);
$stmt_insert->bind_param("sss", $user_id, $user_type, $reason);

if ($stmt_insert->execute()) {
    unset($_SESSION['user_id']);
    // FIX: Back out to root index
    header("Location: ../index.php?status=success");
    exit();
} else {
    echo "Database Error: " . $conn->error;
}
?>