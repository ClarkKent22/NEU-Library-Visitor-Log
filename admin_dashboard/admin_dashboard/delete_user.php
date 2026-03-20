<?php
session_start();
require_once '../includes/db_connect.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$id = $_GET['id'] ?? '';
$type = strtolower($_GET['type'] ?? '');
$adminID = $_SESSION['emplID']; 

if (!empty($id) && !empty($type)) {
    $table = ($type === 'student') ? 'students' : 'employees';
    $idColumn = ($type === 'student') ? 'studentID' : 'emplID';

    $userQuery = $conn->prepare("SELECT firstName, lastName, profile_image FROM $table WHERE $idColumn = ?");
    $userQuery->bind_param("i", $id); 
    $userQuery->execute();
    $userData = $userQuery->get_result()->fetch_assoc();

    if ($userData) {
        $fullName = $userData['firstName'] . ' ' . $userData['lastName'];

        // Image Cleanup
        if (!empty($userData['profile_image']) && $userData['profile_image'] !== 'default.png') {
            $folder = ($type === 'student') ? 'student' : 'admin';
            $filePath = "../profilepictures/$folder/" . $userData['profile_image'];
            if (file_exists($filePath)) unlink($filePath);
        }
        
        // DELETE ALL HISTORY LOGS BELONGING TO THIS USER
        $uTypeFormatted = ucfirst($type); // 'Student' or 'Employee'
        $deleteLogs = $conn->prepare("DELETE FROM history_logs WHERE user_identifier = ? AND user_type = ?");
        $deleteLogs->bind_param("is", $id, $uTypeFormatted);
        $deleteLogs->execute();

        // DELETE THE RECORD
        $deleteSql = "DELETE FROM $table WHERE $idColumn = ?";
        $stmt = $conn->prepare($deleteSql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            header("Location: user_management.php?msg=UserDeleted");
        } else {
            header("Location: user_management.php?msg=DependencyError");
        }
    } else {
        header("Location: user_management.php?msg=UserNotFound");
    }
} else {
    header("Location: user_management.php");
}
exit();