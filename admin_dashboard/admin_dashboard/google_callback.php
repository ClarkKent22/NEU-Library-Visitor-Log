<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/google_oauth.php';

function redirectWithError($code) {
    header("Location: login.php?error=" . urlencode($code));
    exit();
}

if (empty($_GET['state']) || empty($_SESSION['google_oauth_state']) || $_GET['state'] !== $_SESSION['google_oauth_state']) {
    redirectWithError('invalid_state');
}
unset($_SESSION['google_oauth_state']);

if (empty($_GET['code'])) {
    redirectWithError('missing_code');
}

$tokenUrl = 'https://oauth2.googleapis.com/token';
$postData = http_build_query([
    'code' => $_GET['code'],
    'client_id' => $googleClientId,
    'client_secret' => $googleClientSecret,
    'redirect_uri' => $googleAdminRedirectUri,
    'grant_type' => 'authorization_code',
]);

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
$tokenResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$tokenResponse) {
    redirectWithError('token_error');
}

$tokenData = json_decode($tokenResponse, true);
if (empty($tokenData['id_token'])) {
    redirectWithError('missing_id_token');
}

$idToken = $tokenData['id_token'];
$tokenInfoUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
$tokenInfo = json_decode(@file_get_contents($tokenInfoUrl), true);

if (empty($tokenInfo['aud']) || $tokenInfo['aud'] !== $googleClientId) {
    redirectWithError('invalid_audience');
}

if (empty($tokenInfo['email']) || empty($tokenInfo['email_verified']) || $tokenInfo['email_verified'] !== 'true') {
    redirectWithError('unverified_email');
}

$email = strtolower($tokenInfo['email']);
if (!in_array($email, $googleAllowedAdmins, true)) {
    redirectWithError('not_allowed');
}

// Confirm admin account exists and is active
$stmt = $conn->prepare("SELECT emplID, firstName, lastName, role, status FROM employees WHERE institutionalEmail = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if (!$user) {
    redirectWithError('no_account');
}

if (strcasecmp($user['status'], 'Blocked') === 0) {
    redirectWithError('blocked');
}

if (strcasecmp($user['role'], 'Faculty/Admin') !== 0) {
    redirectWithError('not_admin');
}

$_SESSION['admin_logged_in'] = true;
$_SESSION['emplID'] = $user['emplID'];
$_SESSION['admin_name'] = $user['firstName'] . ' ' . $user['lastName'];
$_SESSION['show_welcome'] = true;

header("Location: index.php");
exit();
