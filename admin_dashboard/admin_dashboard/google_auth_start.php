<?php
session_start();

$configPath = __DIR__ . '/../includes/google_oauth.php';
if (!file_exists($configPath)) {
    header("Location: login.php?error=google_config");
    exit();
}
require_once $configPath;

if (empty($googleClientId) || empty($googleAdminRedirectUri)) {
    header("Location: login.php?error=google_config");
    exit();
}

// Generate state with a compatible fallback for hosting environments
try {
    $state = bin2hex(random_bytes(16));
} catch (Throwable $e) {
    $state = bin2hex(openssl_random_pseudo_bytes(16));
}
$_SESSION['google_oauth_state'] = $state;

$authParams = [
    'client_id' => $googleClientId,
    'redirect_uri' => $googleAdminRedirectUri,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'access_type' => 'online',
    'prompt' => 'select_account',
    'hd' => 'neu.edu.ph',
    'state' => $state,
];

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($authParams);
header("Location: $authUrl");
exit();
