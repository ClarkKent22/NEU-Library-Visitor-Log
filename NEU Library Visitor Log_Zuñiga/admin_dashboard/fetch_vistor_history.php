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
        CASE WHEN h.user_type = 'Student' THEN s.firstName ELSE e.firstName END as fName,
        CASE WHEN h.user_type = 'Student' THEN s.lastName ELSE e.lastName END as lName,
        CASE WHEN h.user_type = 'Student' THEN d1.departmentName ELSE d2.departmentName END as deptName
        FROM history_logs h
        LEFT JOIN students s ON h.user_identifier = s.studentID AND h.user_type = 'Student'
        LEFT JOIN employees e ON h.user_identifier = e.emplID AND h.user_type = 'Employee'
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
        $roleClass = ($row['user_type'] == 'Student') ? 'role-student' : 'role-employee';
        echo "<tr>
                <td class='ps-4 fw-bold text-blue'>{$row['user_identifier']}</td>
                <td class='fw-semibold'>".htmlspecialchars($row['fName'].' '.$row['lName'])."</td>
                <td><span class='badge-role $roleClass'>".strtoupper($row['user_type'])."</span></td>
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