<?php
session_start();
require_once '../includes/db_connect.php';

// Initialize variables
$userData = null;
$errorMsg = "";

// 1. Handle the POST request (The "Post" in PRG)
if (isset($_POST['search_id'])) {
    $searchID = trim($_POST['userID'] ?? '');

    // Check Student Table
    $studentStmt = $conn->prepare("SELECT studentID as id, firstName, lastName, profile_image, 'Student' as type FROM students WHERE studentID = ?");
    $studentStmt->bind_param("s", $searchID);
    $studentStmt->execute();
    $studentRes = $studentStmt->get_result();

    if (mysqli_num_rows($studentRes) > 0) {
        $_SESSION['qr_user_data'] = mysqli_fetch_assoc($studentRes);
        unset($_SESSION['qr_error']);
    } else {
        // Check Employee Table
        $empStmt = $conn->prepare("SELECT emplID as id, firstName, lastName, profile_image, role as type FROM employees WHERE emplID = ?");
        $empStmt->bind_param("s", $searchID);
        $empStmt->execute();
        $empRes = $empStmt->get_result();
        
        if (mysqli_num_rows($empRes) > 0) {
            $_SESSION['qr_user_data'] = mysqli_fetch_assoc($empRes);
            unset($_SESSION['qr_error']);
        } else {
            $_SESSION['qr_error'] = "ID not found in our records.";
            unset($_SESSION['qr_user_data']);
        }
    }

    if (isset($studentStmt)) {
        $studentStmt->close();
    }
    if (isset($empStmt)) {
        $empStmt->close();
    }
    
    // Redirect to the same page using GET (The "Redirect" in PRG)
    header("Location: qrmaker.php");
    exit();
}

// 2. Retrieve data from Session (The "Get" in PRG)
if (isset($_SESSION['qr_user_data'])) {
    $userData = $_SESSION['qr_user_data'];
}
if (isset($_SESSION['qr_error'])) {
    $errorMsg = $_SESSION['qr_error'];
}

// Clear session data after retrieving so it doesn't persist forever 
// (unless you want the QR to stay there even if they leave and come back)
unset($_SESSION['qr_user_data']);
unset($_SESSION['qr_error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My QR Code | NEU Library</title>
    <link rel="icon" type="image/png" href="../assets/neu.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <style>
        :root { --neu-blue: #0038a8; --bg-body: #eeeeee; --card-bg: #ffffff; --border-color: #e2e8f0; }
        html, body { height: 100%; margin: 0; }
        body { background-color: var(--bg-body); font-family: 'Plus Jakarta Sans', sans-serif; display: flex; flex-direction: column; }
        
        .navbar { background: var(--card-bg); border-bottom: 1px solid var(--border-color); padding: 0.8rem 2rem; position: sticky; top: 0; z-index: 1000; }
        .nav-link { color: #6c757d !important; font-weight: 600; transition: 0.2s; }
        .nav-link:hover { color: var(--neu-blue) !important; }
        .nav-link.active { color: var(--neu-blue) !important; opacity: 1; } 
        .btn-admin { background-color: var(--neu-blue) !important; color: #fff !important; border-radius: 8px; font-weight: 600; padding: 8px 20px; text-decoration: none; }

        .main-wrapper { flex: 1 0 auto; display: flex; justify-content: center; align-items: center; padding: 40px 20px; }
        .qrmaker-card { width: 100%; max-width: 450px; background: var(--card-bg); border-radius: 30px; padding: 35px; border: 1px solid var(--border-color); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05); }
        
        .profile-img-container {
            width: 110px;
            height: 110px;
            margin: 0 auto 15px auto;
            border-radius: 50%;
            border: 2px solid var(--neu-blue);
            padding: 3px; 
            background-color: white;
            overflow: hidden; 
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .profile-img-qr {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%; 
            display: block;
        }

        .qr-display-area { background: white; padding: 20px; border-radius: 20px; border: 2px dashed #cbd5e1; }
        #qrcode img { margin: 0 auto; }
        
        .btn-primary { background-color: var(--neu-blue); border: none; border-radius: 12px; padding: 12px; font-weight: 700; }
        footer { padding: 25px; text-align: center; color: #6c757d; font-size: 0.85rem; border-top: 1px solid var(--border-color); background: #f8f9fa; }

        @media (max-width: 768px) {
            .navbar { padding: 0.8rem 1rem; }
            .btn-admin { margin-top: 10px; width: 100%; text-align: center; }
        }
    </style>
    <link rel="stylesheet" href="../assets/theme.css">
</head>
<body class="theme-dark">

    <div class="support-shell">
        <div class="support-topbar">
            <div class="d-flex align-items-center gap-2">
                <img src="../assets/neu.png" alt="NEU" width="36">
                <div>
                    <div class="fw-bold">NEU Library Pass</div>
                    <div class="small text-muted">Generate your QR Code</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="../index.php" class="btn btn-secondary">Home</a>
                <a href="qrmaker.php" class="btn btn-blue">My QR Code</a>
                <a href="help.php" class="btn btn-secondary">Help</a>
                <a href="../admin_dashboard/login.php" class="btn btn-admin">Admin Login</a>
            </div>
        </div>
    </div>
</div>

    <div class="kiosk-shell">
        <div class="kiosk-hero">
            <h2 class="fw-800 mb-2">Create Your Library Pass</h2>
            <p class="text-muted mb-3">Enter your 7-digit ID to generate a scannable QR code for quick access.</p>
            <div class="kiosk-metrics">
                <div class="metric">
                    <div class="label">Step 1</div>
                    <div class="value">Verify ID</div>
                </div>
                <div class="metric">
                    <div class="label">Step 2</div>
                    <div class="value">Generate QR Code</div>
                </div>
                <div class="metric">
                    <div class="label">Step 3</div>
                    <div class="value">Download</div>
                </div>
            </div>
            <div class="mt-3 text-muted small">Print your QR code and keep it with your ID for faster scanning.</div>
        </div>
        <div class="kiosk-card">
            <div class="qrmaker-card" style="box-shadow:none; border:none; padding:0; background:transparent;">
            <h2 class="fw-800 h4 mb-1">QR Pass Generator</h2>
            <p class="text-muted small mb-4">Enter your 7-digit ID to get your library pass</p>

            <form method="POST" id="searchForm">
                <div class="mb-3">
                    <label class="small fw-bold text-muted mb-1 d-block">Student / Employee ID</label>
                    <input type="text" name="userID" id="userID" 
                           class="form-control border-2" placeholder="Enter your 7-digit ID" 
                           inputmode="numeric" maxlength="7" required style="border-radius: 10px;">
                </div>
                <button type="submit" name="search_id" class="btn btn-primary w-100 shadow-sm mb-3">VERIFY ID</button>
            </form>

            <?php if($errorMsg): ?>
                <div class="alert alert-danger py-2 small text-center" style="border-radius: 10px;"><?php echo $errorMsg; ?></div>
            <?php endif; ?>

            <?php if($userData): ?>
                <?php 
                    // 1. Determine folder path based on user type
                    $folder = (strtolower($userData['type']) == 'student') ? 'student' : 'admin';
                    $localPath = "../profilepictures/" . $folder . "/" . $userData['profile_image'];
                    
                    // 2. Check if image exists, otherwise generate initials avatar
                    if (!empty($userData['profile_image']) && $userData['profile_image'] !== 'default.png' && file_exists($localPath)) {
                        $type = pathinfo($localPath, PATHINFO_EXTENSION);
                        $data = file_get_contents($localPath);
                        $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
                    } else {
                        // Generate initials using BOTH First and Last name
                        $fullName = $userData['firstName'] . ' ' . $userData['lastName'];
                        $avatarUrl = 'https://ui-avatars.com/api/?name=' . urlencode($fullName) . '&background=0038a8&color=fff&size=128&font-size=0.35&bold=true';
                        
                        // Fetch the image content and convert to base64 to prevent CORS issues with html2canvas
                        $avatarData = file_get_contents($avatarUrl);
                        $base64 = 'data:image/png;base64,' . base64_encode($avatarData);
                    }
                ?>
                <div class="mt-4 text-center">
                    <div id="qr-export-area" class="qr-display-area">
                        <img src="../assets/neu.png" width="30" class="mb-2">
                        <p class="text-primary fw-800 mb-2 small text-uppercase"><?php echo $userData['type']; ?> Pass</p>
                        
                        <div class="profile-img-container">
                            <img src="<?php echo $base64; ?>" class="profile-img-qr">
                        </div>
                                                
                        <h5 class="fw-bold text-dark mb-1"><?php echo $userData['firstName'] . ' ' . $userData['lastName']; ?></h5>
                        <div id="qrcode" class="d-flex justify-content-center mt-2"></div>
                        <h4 class="mt-3 fw-bold text-dark" style="font-family: 'JetBrains Mono';"><?php echo $userData['id']; ?></h4>
                    </div>
                    
                    <div class="mt-4 d-grid gap-2">
                        <button id="downloadBtn" onclick="downloadPNG()" class="btn btn-dark fw-bold py-3" style="border-radius: 12px;">
                            <i class="bi bi-download me-2"></i> DOWNLOAD PASS
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer>&copy; 2026 NEU Library Visitor Log System. All rights reserved.</footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const idInput = document.getElementById('userID');
        if(idInput) {
            idInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        }

        <?php if($userData): ?>
        new QRCode(document.getElementById("qrcode"), {
            text: "<?php echo $userData['id']; ?>",
            width: 160,
            height: 160,
            colorDark : "#0038a8",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });

        function downloadPNG() {
            const btn = document.getElementById('downloadBtn');
            const area = document.getElementById("qr-export-area");
            
            btn.innerHTML = 'PROCESSING...';
            btn.disabled = true;

            setTimeout(() => {
                html2canvas(area, { 
                    useCORS: true, 
                    allowTaint: false, 
                    scale: 3,
                    backgroundColor: "#ffffff",
                    logging: false
                }).then(canvas => {
                    const link = document.createElement('a');
                    link.download = 'NEU_PASS_<?php echo $userData['id']; ?>.png';
                    link.href = canvas.toDataURL("image/png");
                    link.click();
                    
                    btn.innerHTML = '<i class="bi bi-download me-2"></i> DOWNLOAD PASS';
                    btn.disabled = false;
                });
            }, 300);
        }
        <?php endif; ?>
    </script>
</body>
</html>
