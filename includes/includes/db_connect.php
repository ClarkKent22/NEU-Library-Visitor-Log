<?php
$server_name = $_SERVER['SERVER_NAME'] ?? ($_SERVER['HTTP_HOST'] ?? '');
$remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';

$is_local = in_array($server_name, ['localhost', '127.0.0.1'], true)
    || in_array($remote_addr, ['127.0.0.1', '::1'], true)
    || str_ends_with($server_name, '.test')
    || str_ends_with($server_name, '.local')
    || str_ends_with($server_name, '.localhost');

if ($is_local) {
    $servername = "127.0.0.1";
    $username = "root";
    $password = ""; // Default Laragon password is empty
    $dbname = "librarylogs";
    $port = 3306;
} else {
    $servername = "sql309.infinityfree.com";
    $username = "if0_41414613";
    $password = "Mechigooo";
    $dbname = "if0_41414613_ClarkKent";
    $port = 3306;
}

date_default_timezone_set('Asia/Manila');

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

$conn->query("SET time_zone = '+08:00'");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
