<?php
session_start();
require_once '../includes/db_connect.php';

// Only logged-in admins can update user records
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    exit('Unauthorized access');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit('Direct access denied');

$id = $conn->real_escape_string($_POST['id']);
$type = $_POST['type']; // 'Student' or 'Employee'
$firstName = $conn->real_escape_string($_POST['firstName']);
$lastName = $conn->real_escape_string($_POST['lastName']);
$email = $conn->real_escape_string($_POST['email']);
$deptID = $conn->real_escape_string($_POST['departmentID']);
$role = ($type === 'Employee' && !empty($_POST['role'])) ? $conn->real_escape_string($_POST['role']) : '';

// --- NEW DOMAIN VALIDATION ---
$allowedDomain = "@neu.edu.ph";
if (!str_ends_with(strtolower($email), $allowedDomain)) {
    exit("Invalid Email: Only $allowedDomain addresses are permitted.");
}

$table = ($type === 'Student') ? 'students' : 'employees';
$idCol = ($type === 'Student') ? 'studentID' : 'emplID';
$folder = ($type === 'Student') ? 'student' : 'admin';

// 1. File Upload Logic
$imgUpdateSql = "";
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
    // Validate file extension
    $allowedExts = array("jpg", "jpeg", "png", "gif", "webp");
    $fileInfo = pathinfo($_FILES['profile_image']['name']);
    $extension = strtolower($fileInfo['extension'] ?? '');

    if (!in_array($extension, $allowedExts)) {
        exit("Invalid image format. Only JPG, JPEG, PNG, WEBP, and GIF are allowed.");
    }

    $targetDir = "C:\\laragon\\www\\crud\\profilepictures\\" . $folder . "\\";
    
    // Ensure directory exists
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $newFileName = $id . ".png"; 
    $uploadPath = $targetDir . $newFileName;

    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadPath)) {
        $imgUpdateSql = ", profile_image = '$newFileName'";
    }
}

// 2. Password Logic (PLAIN TEXT as requested)
$passUpdateSql = "";
if ($type === 'Employee' && !empty($_POST['new_password'])) {
    $plainPassword = $conn->real_escape_string($_POST['new_password']);
    $passUpdateSql = ", password = '$plainPassword'";
}

// 3. Role Logic (for employees only)
$roleUpdateSql = "";
if ($type === 'Employee' && !empty($role)) {
    $roleUpdateSql = ", role = '$role'";
}

// 4. Database Update
$sql = "UPDATE $table SET 
        firstName = '$firstName', 
        lastName = '$lastName', 
        institutionalEmail = '$email', 
        departmentID = '$deptID' 
        $imgUpdateSql 
        $passUpdateSql 
        $roleUpdateSql 
        WHERE $idCol = '$id'";

if ($conn->query($sql)) {
    echo "success"; 
} else {
    echo "Error: " . $conn->error;
}
?>