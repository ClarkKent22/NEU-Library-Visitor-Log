<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Invalid request method.";
    exit();
}

$userType = trim($_POST['user_type'] ?? 'Student');
$identifier = trim($_POST['identifier'] ?? '');
$firstName = trim($_POST['firstName'] ?? '');
$lastName = trim($_POST['lastName'] ?? '');
$email = trim($_POST['institutionalEmail'] ?? '');
$departmentID = trim($_POST['departmentID'] ?? '');

if ($identifier === '' || $firstName === '' || $lastName === '' || $email === '' || $departmentID === '') {
    echo "All fields except profile image are required.";
    exit();
}

if (!preg_match('/^[0-9]{4,20}$/', $identifier)) {
    echo "ID number must be 4-20 digits.";
    exit();
}

$emailLower = strtolower($email);
$domain = '@neu.edu.ph';
if (substr($emailLower, -strlen($domain)) !== $domain) {
    echo "Only @neu.edu.ph emails are allowed.";
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Invalid email format.";
    exit();
}

// Check duplicates in students and employees by email
$checkStudent = $conn->prepare("SELECT 1 FROM students WHERE institutionalEmail = ? LIMIT 1");
$checkStudent->bind_param("s", $email);
$checkStudent->execute();
if ($checkStudent->get_result()->num_rows > 0) {
    echo "Email is already registered as a Student.";
    exit();
}
$checkStudent->close();

$checkEmployee = $conn->prepare("SELECT 1 FROM employees WHERE institutionalEmail = ? LIMIT 1");
$checkEmployee->bind_param("s", $email);
$checkEmployee->execute();
if ($checkEmployee->get_result()->num_rows > 0) {
    echo "Email is already registered as an Employee.";
    exit();
}
$checkEmployee->close();

// Check duplicate identifier across both tables
$checkID1 = $conn->prepare("SELECT 1 FROM students WHERE studentID = ? LIMIT 1");
$checkID1->bind_param("s", $identifier);
$checkID1->execute();
if ($checkID1->get_result()->num_rows > 0) {
    echo "ID is already registered as a Student.";
    exit();
}
$checkID1->close();

$checkID2 = $conn->prepare("SELECT 1 FROM employees WHERE emplID = ? LIMIT 1");
$checkID2->bind_param("s", $identifier);
$checkID2->execute();
if ($checkID2->get_result()->num_rows > 0) {
    echo "ID is already registered as an Employee.";
    exit();
}
$checkID2->close();

$imageName = 'default.png';
$uploadTmpPath = '';

if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
    $allowedExts = ["jpg", "jpeg", "png", "gif", "webp"];
    $fileInfo = pathinfo($_FILES["profile_image"]["name"]);
    $extension = strtolower($fileInfo['extension'] ?? '');

    if (!in_array($extension, $allowedExts, true)) {
        echo "Invalid image format. Only JPG, JPEG, PNG, WEBP, and GIF are allowed.";
        exit();
    }

    if ($_FILES['profile_image']['size'] > 2 * 1024 * 1024) {
        echo "Image file is too large. Max size is 2MB.";
        exit();
    }

    $uploadTmpPath = $_FILES["profile_image"]["tmp_name"];
}

$newId = $identifier;
$targetDir = null;

if (strtolower($userType) === 'employee') {
    // Password is optional for employees
    $password = trim($_POST['password'] ?? '');

    $stmt = $conn->prepare("INSERT INTO employees (emplID, firstName, lastName, institutionalEmail, password, departmentID, profile_image, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Faculty/Admin', 'Active')");
    if (!$stmt) {
        echo "Error preparing employee insert: " . $conn->error;
        exit();
    }
    $stmt->bind_param("sssssss", $newId, $firstName, $lastName, $email, $password, $departmentID, $imageName);
    $targetDir = __DIR__ . '/../profilepictures/admin/';
} else {
    // Students - no password field in students table
    $stmt = $conn->prepare("INSERT INTO students (studentID, firstName, lastName, institutionalEmail, departmentID, profile_image, status) VALUES (?, ?, ?, ?, ?, ?, 'Active')");
    if (!$stmt) {
        echo "Error preparing student insert: " . $conn->error;
        exit();
    }
    $stmt->bind_param("ssssss", $newId, $firstName, $lastName, $email, $departmentID, $imageName);
    $targetDir = __DIR__ . '/../profilepictures/student/';
}

if (!$stmt->execute()) {
    echo "Database execute error: " . $stmt->error;
    $stmt->close();
    exit();
}

$stmt->close();

if (!empty($uploadTmpPath)) {
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $newFileName = $newId . '.png';
    $targetFilePath = $targetDir . $newFileName;
    if (move_uploaded_file($uploadTmpPath, $targetFilePath)) {
        if (strtolower($userType) === 'employee') {
            $updateStmt = $conn->prepare("UPDATE employees SET profile_image = ? WHERE emplID = ?");
        } else {
            $updateStmt = $conn->prepare("UPDATE students SET profile_image = ? WHERE studentID = ?");
        }
        $updateStmt->bind_param("ss", $newFileName, $newId);
        $updateStmt->execute();
        $updateStmt->close();
    }
}

echo "success";
