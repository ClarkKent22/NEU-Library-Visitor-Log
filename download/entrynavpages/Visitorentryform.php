<?php
session_start();
// STEP 1: Fix path to db_connect
require_once '../includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Default values if user is not found at all
$full_name = "User Not Found";
$dept_display = "N/A";
$role_display = "N/A";
$base64_img = ""; 

$sql = "SELECT firstName, lastName, full_name, deptName as dept, p_img, role FROM (
            SELECT s.firstName, s.lastName, CONCAT(s.firstName, ' ', s.lastName) as full_name, 
                   d.departmentName as deptName, 
                   s.profile_image as p_img, 
                   s.studentID as id, 
                   'Student' as role 
            FROM students s
            LEFT JOIN departments d ON s.departmentID = d.departmentID
            UNION 
            SELECT e.firstName, e.lastName, CONCAT(e.firstName, ' ', e.lastName) as full_name, 
                   d.departmentName as deptName, 
                   e.profile_image as p_img, 
                   e.emplID as id, 
                   e.role as role 
            FROM employees e
            LEFT JOIN departments d ON e.departmentID = d.departmentID
        ) AS users WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // FIX: Using ?? "" inside trim() prevents the Deprecated error in PHP 8.1+
    $full_name = !empty(trim($row['full_name'] ?? "")) ? $row['full_name'] : "N/A";
    $dept_display = !empty(trim($row['dept'] ?? "")) ? $row['dept'] : "N/A";
    $role_display = !empty(trim($row['role'] ?? "")) ? $row['role'] : "N/A";

    // 2. Folder Mapping
    $role_lower = strtolower($role_display);
    if ($role_lower === 'student') {
        $folder = 'student';
    } else {
        // Employees map to the admin folder (no "employee" folder exists).
        $folder = 'admin';
    }

    // 3. Resolve Image Path
    $localPath = "../profilepictures/" . $folder . "/" . ($row['p_img'] ?? "");
    
    if (!empty($row['p_img']) && file_exists($localPath)) {
        $type = pathinfo($localPath, PATHINFO_EXTENSION);
        $data = @file_get_contents($localPath);
        $base64_img = 'data:image/' . $type . ';base64,' . base64_encode($data);
    } else {
        // Initials fallback if no image exists or file is missing
        $fName = $row['firstName'] ?? "U";
        $lName = $row['lastName'] ?? "N";
        $initialsName = urlencode($fName . ' ' . $lName);
        $base64_img = 'https://ui-avatars.com/api/?name=' . $initialsName . '&background=0038a8&color=fff&bold=true';
    }
}

header("Cache-Control: no-cache, no-store, must-revalidate");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Entry Form - NEU Library</title>
    <link rel="icon" type="image/png" href="../assets/neu.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root { --neu-blue: #0038a8; --bg-gray: #f0f2f5; --text-dark: #1a1a1a; }
        body { background-color: var(--bg-gray); font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-dark); margin: 0; }
        .navbar { background: white; border-bottom: 2px solid var(--neu-blue); padding: 0.6rem 2rem; }
        .logo-img { height: 45px; width: auto; }
        .user-profile { display: flex; align-items: center; gap: 12px; }
        .avatar-circle { width: 40px; height: 40px; border-radius: 50%; border: 2px solid #e2e8f0; overflow: hidden; background: #eee; }
        .avatar-circle img { width: 100%; height: 100%; object-fit: cover; }
        .btn-logout { background: #f0f4ff; color: var(--neu-blue); border: 1px solid #dbeafe; border-radius: 8px; font-weight: 700; padding: 6px 16px; font-size: 0.85rem; text-decoration: none; transition: 0.2s; }
        .btn-logout:hover { background: var(--neu-blue); color: white; }
        .main-container { max-width: 800px; margin: 40px auto; padding: 0 20px; }
        .section-card { background: white; border-radius: 20px; border: 1px solid #e2e8f0; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow: hidden; }
        .card-header-custom { background: #f8faff; padding: 15px 24px; border-bottom: 1px solid #edf2f7; display: flex; align-items: center; gap: 10px; }
        .card-header-custom i { color: var(--neu-blue); }
        .label-title { font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 4px; }
        .data-text { font-size: 1.1rem; font-weight: 700; color: #0f172a; }
        .form-select, .form-control { border: 2px solid #e2e8f0; border-radius: 12px; padding: 12px; transition: 0.3s; }
        .submit-btn { background: var(--neu-blue); color: white; border: none; border-radius: 12px; padding: 16px 32px; font-weight: 700; display: inline-flex; align-items: center; gap: 10px; transition: 0.3s; width: 100%; justify-content: center; }
        footer { text-align: center;  color: #94a3b8; font-size: 0.8rem; margin-top: 40px; padding-bottom: 30px; }
    </style>
    <link rel="stylesheet" href="../assets/theme.css">
</head>
<body class="theme-dark">

    <div class="support-shell">
        <div class="support-topbar">
            <div class="d-flex align-items-center gap-2">
                <img src="../assets/neu.png" alt="NEU Logo" width="34">
                <div>
                    <div class="fw-bold">Entry Confirmation</div>
                    <div class="small text-muted">Verify your details and submit your visit purpose</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="d-none d-md-flex align-items-center gap-2">
                    <div class="avatar-circle">
                        <img src="<?php echo $base64_img; ?>" alt="Profile">
                    </div>
                    <div class="text-end">
                        <div class="fw-bold lh-1" style="font-size: 0.9rem;"><?php echo htmlspecialchars($full_name ?: 'N/A'); ?></div>
                        <small class="text-muted" style="font-size: 0.75rem;">Active Session</small>
                    </div>
                </div>
                <a href="../index.php" class="btn btn-logout">Cancel</a>
            </div>
        </div>

        <div class="support-grid">
            <section class="support-panel">
                <h3 class="fw-800 mb-3">Your Profile</h3>
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="avatar-circle" style="width:70px;height:70px;">
                        <img src="<?php echo $base64_img; ?>" alt="Profile">
                    </div>
                    <div>
                        <div class="fw-bold h5 mb-1"><?php echo htmlspecialchars($full_name ?: 'N/A'); ?></div>
                        <div class="text-muted small"><?php echo htmlspecialchars($role_display ?: 'N/A'); ?> · <?php echo htmlspecialchars($dept_display ?: 'N/A'); ?></div>
                    </div>
                </div>
                <div class="stat-grid">
                    <div class="panel-card">
                        <div class="panel-label">Role</div>
                        <div class="panel-value" style="font-size:1.1rem;"><?php echo htmlspecialchars($role_display ?: 'N/A'); ?></div>
                    </div>
                    <div class="panel-card">
                        <div class="panel-label">Department</div>
                        <div class="panel-value" style="font-size:1.1rem;"><?php echo htmlspecialchars($dept_display ?: 'N/A'); ?></div>
                    </div>
                </div>
            </section>

            <section class="support-panel">
                <h3 class="fw-800 mb-3">Visit Details</h3>
                <form action="process_entry.php" method="POST">
                    <div class="mb-4">
                        <label class="fw-bold mb-2 d-flex align-items-center gap-2">
                            <i class="bi bi-ui-checks text-primary"></i> Visit Purpose
                        </label>
                        <select name="reason" id="reasonSelect" class="form-select" required>
                            <option value="" selected disabled>Select a reason...</option>
                            <option value="Research">Research / Thesis Work</option>
                            <option value="Study">Quiet Study</option>
                            <option value="Group Study">Group Study / Collaboration</option>
                            <option value="Borrowing">Borrowing/Returning Books</option>
                            <option value="Clearance">Clearance Signing</option>
                            <option value="ID Validation">Library Card / ID Validation</option>
                            <option value="Resting">Resting / Between Classes</option>
                            <option value="Computer Use">Computer / Internet Access</option>
                            <option value="Printing">Printing / Photocopying Services</option>
                            <option value="E-Resources">Accessing Online Databases/E-Books</option>
                            <option value="Event">School Event / Seminar / Orientation</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <label class="fw-bold d-flex align-items-center gap-2">
                                <i class="bi bi-chat-left-text text-primary"></i> Other Reason
                            </label>
                            <span class="text-muted small" id="counterWrapper" style="display:none;">
                                <span id="charCount" class="fw-bold">255</span> left
                            </span>
                        </div>
                        <textarea name="specific_reason" id="specificReason" class="form-control" rows="3" 
                                  placeholder="Type your specific reason here..." 
                                  maxlength="255" disabled></textarea>
                    </div>

                    <button type="submit" class="submit-btn">
                        Confirm & Enter Library <i class="bi bi-check2-circle"></i>
                    </button>
                </form>
            </section>
        </div>

        <footer>&copy; 2026 New Era University Library.</footer>
    </div>

    <script>
        const reasonSelect = document.getElementById('reasonSelect');
        const specificReason = document.getElementById('specificReason');
        const counterWrapper = document.getElementById('counterWrapper');

        reasonSelect.addEventListener('change', function() {
            if (this.value === 'Others') {
                specificReason.disabled = false;
                specificReason.required = true;
                counterWrapper.style.display = 'inline';
                specificReason.focus();
            } else {
                specificReason.disabled = true;
                specificReason.required = false;
                specificReason.value = '';
                counterWrapper.style.display = 'none';
            }
        });
    </script>
</body>
</html>
