<?php
session_start();
require_once '../includes/db_connect.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo "Unauthorized access.";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userType = $_POST['user_type'] ?? 'Student';
    $firstName = $conn->real_escape_string(trim($_POST['firstName'] ?? ''));
    $lastName = $conn->real_escape_string(trim($_POST['lastName'] ?? ''));
    $email = $conn->real_escape_string(trim($_POST['institutionalEmail'] ?? ''));
    $departmentID = $conn->real_escape_string(trim($_POST['departmentID'] ?? ''));
    $password = $_POST['password'] ?? ''; // PLAIN TEXT AS REQUESTED
    
    // Domain validation backend check
    if (!str_ends_with(strtolower($email), '@neu.edu.ph')) {
        echo "Only @neu.edu.ph emails are allowed.";
        exit();
    }

    // Default image
    $imageName = 'default.png';
    $uploadTmpPath = '';
    $uploadFolder = ($userType === 'Employee') ? 'admin' : 'student';

    // Handle File Upload if provided
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowedExts = array("jpg", "jpeg", "png", "gif", "webp");
        $fileInfo = pathinfo($_FILES["profile_image"]["name"]);
        $extension = strtolower($fileInfo['extension']);
        
        if (in_array($extension, $allowedExts)) {
            $uploadTmpPath = $_FILES["profile_image"]["tmp_name"];
        } else {
            echo "Invalid image format. Only JPG, JPEG, PNG, WEBP, and GIF are allowed.";
            exit();
        }
    }

    // Insert based on User Type
    if ($userType === 'Student') {
        // Check duplicate email
        $check = $conn->query("SELECT institutionalEmail FROM students WHERE institutionalEmail = '$email'");
        if ($check->num_rows > 0) {
            echo "Email is already registered as a Student.";
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO students (firstName, lastName, institutionalEmail, departmentID, profile_image) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $firstName, $lastName, $email, $departmentID, $imageName);
        
        if ($stmt->execute()) {
            $newId = $conn->insert_id;

            // If an image was uploaded earlier, save it using the new user ID
            if (!empty($uploadTmpPath)) {
                $targetDir = "../profilepictures/$uploadFolder/";
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }

                $newFileName = $newId . '.png';
                $targetFilePath = $targetDir . $newFileName;
                if (move_uploaded_file($uploadTmpPath, $targetFilePath)) {
                    $updateStmt = $conn->prepare("UPDATE students SET profile_image = ? WHERE studentID = ?");
                    $updateStmt->bind_param("si", $newFileName, $newId);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
            }

            echo "success";
        } else {
            echo "Database error: " . $conn->error;
        }
        $stmt->close();
        
    } else if ($userType === 'Employee') {
        // Check duplicate email
        $check = $conn->query("SELECT institutionalEmail FROM employees WHERE institutionalEmail = '$email'");
        if ($check->num_rows > 0) {
            echo "Email is already registered as an Employee.";
            exit();
        }

        // Password is optional for Faculty/Staff in public registration
        $role = 'Employee / Faculty';
        $stmt = $conn->prepare("INSERT INTO employees (firstName, lastName, institutionalEmail, password, departmentID, profile_image, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')");
        // Binding plain text password (can be empty string as per current design)
        $stmt->bind_param("sssssss", $firstName, $lastName, $email, $password, $departmentID, $imageName, $role);
        
        if ($stmt->execute()) {
            $newId = $conn->insert_id;

            // If an image was uploaded earlier, save it using the new user ID
            if (!empty($uploadTmpPath)) {
                $targetDir = "../profilepictures/$uploadFolder/";
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }

                $newFileName = $newId . '.png';
                $targetFilePath = $targetDir . $newFileName;
                if (move_uploaded_file($uploadTmpPath, $targetFilePath)) {
                    $updateStmt = $conn->prepare("UPDATE employees SET profile_image = ? WHERE emplID = ?");
                    $updateStmt->bind_param("si", $newFileName, $newId);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
            }

            echo "success";
        } else {
            echo "Database error: " . $conn->error;
        }
        $stmt->close();
    } else {
        echo "Invalid User Type.";
    }
} else {
    echo "Invalid request method.";
}
?>