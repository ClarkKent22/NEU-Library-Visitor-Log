<?php
session_start();
require_once '../includes/db_connect.php';

// Restrict visitor history export to logged-in admins
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    exit('Unauthorized access');
}

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$filterDate = isset($_GET['filterDate']) ? $conn->real_escape_string($_GET['filterDate']) : '';
$filterMonth = isset($_GET['filterMonth']) ? $conn->real_escape_string($_GET['filterMonth']) : '';
$filterYear = isset($_GET['filterYear']) ? $conn->real_escape_string($_GET['filterYear']) : '';

$sql = "SELECT h.*, 
        COALESCE(s.firstName, e.firstName, '') as fName,
        COALESCE(s.lastName, e.lastName, '') as lName,
        COALESCE(d1.departmentName, d2.departmentName) as deptName,
        COALESCE(e.role, '') as employeeRole
        FROM history_logs h
        LEFT JOIN students s ON h.user_identifier = s.studentID AND h.user_type = 'Student'
        LEFT JOIN employees e ON h.user_identifier = e.emplID AND h.user_type != 'Student'
        LEFT JOIN departments d1 ON s.departmentID = d1.departmentID
        LEFT JOIN departments d2 ON e.departmentID = d2.departmentID
        WHERE 1=1";

if (!empty($search)) {
    $sql .= " AND (s.firstName LIKE '%$search%' OR s.lastName LIKE '%$search%' OR e.firstName LIKE '%$search%' OR e.lastName LIKE '%$search%' OR h.user_identifier LIKE '%$search%')";
}
if (!empty($filterDate)) { $sql .= " AND h.date = '$filterDate'"; }
if (!empty($filterMonth)) { $sql .= " AND MONTH(h.date) = '$filterMonth'"; }
if (!empty($filterYear)) { $sql .= " AND YEAR(h.date) = '$filterYear'"; }

$sql .= " ORDER BY h.date DESC, h.time DESC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Determine role based on user_type and employeeRole
        $userTypeFromLog = trim($row['user_type'] ?? '');
        $employeeRole = trim($row['employeeRole'] ?? '');
        
        $roleDisplay = 'Unknown';
        $roleBadgeClass = 'role-faculty';
        
        // Check if it's a student
        if (strcasecmp($userTypeFromLog, 'Student') === 0) {
            $roleDisplay = 'STUDENT';
            $roleBadgeClass = 'role-student';
        } 
        // Check if it's Faculty/Admin (old admin role)
        elseif (strcasecmp($employeeRole, 'Faculty/Admin') === 0) {
            $roleDisplay = 'ADMIN';
            $roleBadgeClass = 'role-admin';
        }
        // Check if it's Employee / Faculty
        elseif (!empty($employeeRole) && (stripos($employeeRole, 'Employee') !== false || stripos($employeeRole, 'Faculty') !== false)) {
            $roleDisplay = 'FACULTY';
            $roleBadgeClass = 'role-faculty';
        }
        // Check for pure Admin
        elseif (!empty($employeeRole) && stripos($employeeRole, 'Admin') !== false) {
            $roleDisplay = 'ADMIN';
            $roleBadgeClass = 'role-admin';
        }
        // Check if user_type mentions Admin
        elseif (stripos($userTypeFromLog, 'Admin') !== false) {
            $roleDisplay = 'ADMIN';
            $roleBadgeClass = 'role-admin';
        }
        // Default to Faculty for other employees
        else {
            $roleDisplay = 'FACULTY';
            $roleBadgeClass = 'role-faculty';
        }
        
        $displayName = (!empty($row['fName']) || !empty($row['lName'])) ? htmlspecialchars($row['fName'].' '.$row['lName']) : htmlspecialchars($row['user_identifier']);
        echo "<tr>
                <td class='ps-4 fw-bold text-blue'>{$row['user_identifier']}</td>
                <td class='fw-semibold'>".$displayName."</td>
                <td><span class='badge-role $roleBadgeClass'>".strtoupper($roleDisplay)."</span></td>
                <td class='small text-muted'>".htmlspecialchars($row['deptName'] ?? 'N/A')."</td>
                <td class='small'><i>".htmlspecialchars($row['reason'])."</i></td>
                <td class='fw-bold'>".date('M d, Y', strtotime($row['date']))."</td>
                <td class='text-blue fw-bold'>".date('h:i A', strtotime($row['time']))."</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='7' class='text-center py-5 text-muted'>No records found.</td></tr>";
}
?>