<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/google_oauth.php';

function redirectWithError($code) {
    header("Location: ../index.php?error=" . urlencode($code));
    exit();
}

// Validate state
if (empty($_GET['state']) || empty($_SESSION['google_oauth_state']) || $_GET['state'] !== $_SESSION['google_oauth_state']) {
    redirectWithError('invalid_state');
}
unset($_SESSION['google_oauth_state']);

if (empty($_GET['code'])) {
    redirectWithError('missing_code');
}

// Use the public redirect URI for home Google login flow
$tokenUrl = 'https://oauth2.googleapis.com/token';
$postData = http_build_query([
    'code' => $_GET['code'],
    'client_id' => $googleClientId,
    'client_secret' => $googleClientSecret,
    'redirect_uri' => $googlePublicRedirectUri,
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
$firstName = $tokenInfo['given_name'] ?? '';
$lastName = $tokenInfo['family_name'] ?? '';

if (!str_ends_with($email, '@neu.edu.ph')) {
    redirectWithError('invalid_domain');
}

// Search for user in Students table first
$stmt = $conn->prepare("SELECT studentID, firstName, lastName, status FROM students WHERE institutionalEmail = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$userType = 'student';

// If not found, search in Employees table
if (!$user) {
    $stmt = $conn->prepare("SELECT emplID, firstName, lastName, status FROM employees WHERE institutionalEmail = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $userType = 'employee';
}

if ($user) {
    if (strcasecmp($user['status'], 'Blocked') === 0) {
        redirectWithError('blocked');
    }

    // Existing student/employee: continue with normal entry flow.
    $found_id = ($userType === 'student') ? $user['studentID'] : $user['emplID'];
    $_SESSION['user_id'] = $found_id;
    $_SESSION['role'] = $userType;
    header("Location: Visitorentryform.php");
    exit();
}

// New user: prefill registration form and send to Add User.
$_SESSION['google_reg_info'] = [
    'firstName' => $firstName,
    'lastName' => $lastName,
    'email' => $email,
];

header("Location: ../index.php?autoAddUser=1");
exit();
