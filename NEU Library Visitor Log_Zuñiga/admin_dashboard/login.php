<?php
session_start();
require_once '../includes/db_connect.php'; 

$error = "";
$entered_identifier = ""; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $identifier = trim($_POST['identifier']); 
    $password = $_POST['password'];
    
    // Keep identifier for field persistence
    $entered_identifier = htmlspecialchars($identifier); 

    // 1. Check if account exists in employees table
    $sql = "SELECT emplID, firstName, lastName, password, role, status FROM employees 
            WHERE institutionalEmail = ? OR emplID = ? LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        
        // 2. STATUS CHECK: Block access if status is 'Blocked'
        if (strcasecmp($user['status'], 'Blocked') === 0) {
            $error = "Your account is currently blocked. Please contact the Super Admin.";
            $entered_identifier = ""; 
        } 
        // 3. Role Check (Admin-only)
        else if (strcasecmp($user['role'], 'Faculty/Admin') === 0) {
            
            // 4. Password Check (PLAIN TEXT comparison)
            if ($password === $user['password']) {
                
                // Set Session Variables
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['emplID'] = $user['emplID']; 
                $_SESSION['admin_name'] = $user['firstName'] . ' ' . $user['lastName'];
                
                // --- THE ONE-TIME WELCOME FLAG ---
                $_SESSION['show_welcome'] = true; 
                
                // Redirect to dashboard
                header("Location: index.php"); 
                exit();
            } else {
                $error = "Incorrect password. Please try again.";
            }
        } else {
            $error = "Access Denied. This portal is for Administrators only.";
            $entered_identifier = ""; 
        }
    } else {
        // 5. Check if student (to provide helpful error message)
        $checkStudent = "SELECT studentID FROM students WHERE institutionalEmail = ? OR studentID = ? LIMIT 1";
        $stmt2 = $conn->prepare($checkStudent);
        $stmt2->bind_param("ss", $identifier, $identifier);
        $stmt2->execute();
        $res2 = $stmt2->get_result();

        if ($res2->num_rows > 0) {
            $error = "Access Denied. Student accounts cannot access the Admin Portal.";
            $entered_identifier = ""; 
        } else {
            $error = "Account not recognized. Please check your ID or Email.";
            $entered_identifier = ""; 
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | NEU Library</title>
    <link rel="icon" type="image/png" href="../assets/neu.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root { --neu-blue: #0038a8; --neu-hover: #002a80; }
        .auth-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            background:
                radial-gradient(900px 500px at 10% 10%, rgba(43, 111, 255, 0.2), transparent 60%),
                radial-gradient(700px 400px at 90% 90%, rgba(16, 185, 129, 0.18), transparent 55%),
                #0b0f17;
            padding: 24px;
            gap: 20px;
        }
        .auth-panel {
            background: #121826;
            border: 1px solid #1f2a44;
            border-radius: 22px;
            box-shadow: 0 20px 60px rgba(4, 10, 24, 0.7);
            overflow: hidden;
        }
        .auth-hero {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 28px;
            background-image: linear-gradient(rgba(10,16,30,0.45), rgba(10,16,30,0.7)), url('../assets/banner.png');
            background-size: cover;
            background-position: center;
            color: #fff;
        }
        .auth-form {
            padding: 40px 46px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .logo-box { background-color: #fff; width: 70px; height: 70px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
        .header-title { color: var(--neu-blue); font-weight: 800; font-size: 1.75rem; }
        .input-group { background-color: #f1f5f9; border-radius: 10px; padding: 2px 10px; border: 2px solid transparent; transition: 0.2s; }
        .input-group:focus-within { border-color: var(--neu-blue); background-color: #fff; }
        .form-control { background: transparent; border: none; padding: 10px; font-weight: 500; }
        .form-control:focus { box-shadow: none; background: transparent; }
        .btn-login { background-color: var(--neu-blue); color: white; border: none; padding: 14px; border-radius: 10px; font-weight: 700; transition: 0.3s; margin-top: 10px; }
        .btn-login:hover { background-color: var(--neu-hover); transform: translateY(-2px); box-shadow: 0 8px 15px rgba(0, 56, 168, 0.3); }
        .toggle-password { border: none; background: transparent; color: #64748b; }
        #radius-shape-1, #radius-shape-2 { display: none; }
        @media (max-width: 900px) {
            .auth-shell { grid-template-columns: 1fr; }
        }
    </style>
    <link rel="stylesheet" href="../assets/theme.css">
</head>
<body class="theme-dark">

<section class="auth-shell">
    <div class="auth-panel auth-hero d-none d-md-flex">
        <div class="d-flex align-items-center gap-2">
            <img src="../assets/neu.png" alt="NEU" width="40">
            <div class="fw-bold">NEU Library Admin</div>
        </div>
        <div>
            <h1 class="fw-bold mb-2">Secure Administrative Access</h1>
            <p class="text-white-50">Manage library operations, reports, and system activity in one place.</p>
        </div>
        <div class="text-white-50 small">Authorized personnel only</div>
    </div>

    <div class="auth-panel auth-form text-center">
            <div class="logo-box">
                <img src="../assets/neu.png" alt="NEU Logo" style="height: 45px;">
            </div>

            <div class="mb-4">
                <h2 class="header-title">Admin Portal</h2>
                <p class="text-muted small">Library Management System</p>
            </div>

            <?php if(!empty($error)): ?>
                <div class="alert alert-danger py-2 border-0 small mb-3">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="text-start">
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase" style="color: #4a5568;">Admin ID or Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-0"><i class="bi bi-person-fill text-primary"></i></span>
                        <input type="text" name="identifier" class="form-control" placeholder="Institutional ID" value="<?php echo $entered_identifier; ?>" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold small text-uppercase" style="color: #4a5568;">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-0"><i class="bi bi-lock-fill text-primary"></i></span>
                        <input type="password" name="password" id="passwordField" class="form-control" placeholder="Enter Your Password" required>
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility()">
                            <i class="bi bi-eye-slash" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login w-100">
                    Sign In <i class="bi bi-box-arrow-in-right ms-2"></i>
                </button>
            </form>

            <a href="../index.php" class="text-decoration-none mt-4 small fw-bold text-muted">
                <i class="bi bi-arrow-left me-1"></i> Return to Main Page
            </a>
        </div>
    </div>
</section>

<script>
    function togglePasswordVisibility() {
        const passwordField = document.getElementById('passwordField');
        const toggleIcon = document.getElementById('toggleIcon');
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            toggleIcon.classList.replace('bi-eye-slash', 'bi-eye');
        } else {
            passwordField.type = 'password';
            toggleIcon.classList.replace('bi-eye', 'bi-eye-slash');
        }
    }
</script>

</body>
</html>
