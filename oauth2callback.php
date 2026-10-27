<?php
/**
 * EduMate - Google OAuth 2.0 Callback Handler
 *
 * Receives the authorization code from Google, verifies CSRF state token,
 * exchanges code for tokens, retrieves user profile, and authenticates the user into EduMate.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to user dashboard
if (is_logged_in()) {
    header('Location: ' . role_redirect($_SESSION['user']['role']));
    exit;
}

// Check for errors returned by Google
if (!empty($_GET['error'])) {
    $errorMsg = htmlspecialchars((string)$_GET['error']);
    flash_set('error', 'Google sign-in was cancelled or encountered an error: ' . $errorMsg);
    header('Location: login.php');
    exit;
}

$code = trim((string)($_GET['code'] ?? ''));
$state = trim((string)($_GET['state'] ?? ''));

if ($code === '') {
    flash_set('error', 'Authorization code not provided by Google.');
    header('Location: login.php');
    exit;
}

// Verify CSRF state token
$savedState = $_SESSION['oauth_state'] ?? '';
if ($savedState !== '' && !hash_equals($savedState, $state)) {
    error_log('OAuth state mismatch. Session state: ' . $savedState . ', Received: ' . $state);
    flash_set('error', 'Security state validation failed. Please try signing in again.');
    header('Location: login.php');
    exit;
}
unset($_SESSION['oauth_state']);

// Load OAuth configuration
$oauthConfig = [];
if (file_exists(__DIR__ . '/config/oauth.local.php')) {
    $oauthConfig = require __DIR__ . '/config/oauth.local.php';
} elseif (file_exists(__DIR__ . '/config/oauth.php')) {
    $oauthConfig = require __DIR__ . '/config/oauth.php';
} elseif (file_exists(__DIR__ . '/config/oauth.example.php')) {
    $oauthConfig = require __DIR__ . '/config/oauth.example.php';
}
$googleConfig = $oauthConfig['google'] ?? [];

$clientId = trim((string)($googleConfig['client_id'] ?? getenv('GOOGLE_CLIENT_ID') ?: ''));
$clientSecret = trim((string)($googleConfig['client_secret'] ?? getenv('GOOGLE_CLIENT_SECRET') ?: ''));

// Use the redirect_uri used during initialization or fallback to config
$redirectUri = $_SESSION['oauth_redirect_uri'] ?? ($googleConfig['redirect_uri'] ?? 'http://localhost/WEBPAGE/edumate/oauth2callback.php');

if ($clientId === '' || $clientSecret === '') {
    flash_set('error', 'OAuth client credentials are not configured.');
    header('Location: login.php');
    exit;
}

// Exchange authorization code for access token via cURL
$tokenUrl = 'https://oauth2.googleapis.com/token';
$postData = [
    'code'          => $code,
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri'  => $redirectUri,
    'grant_type'    => 'authorization_code',
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $tokenUrl,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($postData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_SSL_VERIFYPEER => false, // Ensure local dev compatibility on Windows/XAMPP
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_TIMEOUT        => 20,
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    error_log('Google token exchange error (HTTP ' . $httpCode . '): ' . $curlError . ' Response: ' . $response);
    $decoded = json_decode((string)$response, true);
    $errDesc = $decoded['error_description'] ?? $decoded['error'] ?? 'Could not exchange authorization code.';
    flash_set('error', 'Google authentication failed: ' . htmlspecialchars($errDesc));
    header('Location: login.php');
    exit;
}

$tokenData = json_decode($response, true);
$accessToken = $tokenData['access_token'] ?? '';

if (empty($accessToken)) {
    flash_set('error', 'Failed to retrieve access token from Google.');
    header('Location: login.php');
    exit;
}

// Fetch Google User Profile
$userInfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $userInfoUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_TIMEOUT        => 20,
]);

$userResponse = curl_exec($ch);
$userHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($userResponse === false || $userHttpCode !== 200) {
    flash_set('error', 'Failed to retrieve user profile from Google.');
    header('Location: login.php');
    exit;
}

$profile = json_decode($userResponse, true);
$email = trim((string)($profile['email'] ?? ''));
$fullName = trim((string)($profile['name'] ?? ''));
$avatar = trim((string)($profile['picture'] ?? ''));

if ($email === '') {
    flash_set('error', 'Google did not return an email address.');
    header('Location: login.php');
    exit;
}

if ($fullName === '') {
    $fullName = explode('@', $email)[0];
}

$requestedRole = in_array(($_SESSION['oauth_role'] ?? ''), ['student', 'teacher'], true)
    ? $_SESSION['oauth_role']
    : 'student';
unset($_SESSION['oauth_role'], $_SESSION['oauth_redirect_uri'], $_SESSION['oauth_provider']);

// Complete user login / registration
try {
    $stmt = db()->prepare('SELECT id, full_name, email, role, is_active FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user) {
        if ((int)$user['is_active'] !== 1) {
            flash_set('error', 'Your account has been deactivated. Please contact support.');
            header('Location: login.php');
            exit;
        }
        // Update avatar if not already set or changed
        if (!empty($avatar)) {
            try {
                db()->prepare('UPDATE users SET avatar = :avatar WHERE id = :id')->execute([
                    ':avatar' => $avatar,
                    ':id'     => $user['id'],
                ]);
            } catch (Throwable $e) {}
        }
    } else {
        // Register new user via Google OAuth
        $randomPassword = bin2hex(random_bytes(16));
        $passwordHash = password_hash($randomPassword, PASSWORD_DEFAULT);
        $defaultClass = ($requestedRole === 'student') ? 10 : null;
        $defaultBoard = ($requestedRole === 'student') ? 'CBSE' : null;

        $ins = db()->prepare(
            'INSERT INTO users (full_name, email, password_hash, role, avatar, class, board, is_active)
             VALUES (:name, :email, :hash, :role, :avatar, :class, :board, 1)'
        );
        $ins->execute([
            ':name'   => $fullName,
            ':email'  => $email,
            ':hash'   => $passwordHash,
            ':role'   => $requestedRole,
            ':avatar' => $avatar ?: null,
            ':class'  => $defaultClass,
            ':board'  => $defaultBoard,
        ]);

        $newId = (int)db()->lastInsertId();
        $user = [
            'id'        => $newId,
            'full_name' => $fullName,
            'email'     => $email,
            'role'      => $requestedRole,
            'is_active' => 1,
        ];
    }

    login_user($user, false);
    flash_set('success', 'Successfully signed in with Google! Welcome, ' . htmlspecialchars($user['full_name']) . '.');
    header('Location: ' . role_redirect((string)$user['role']));
    exit;
} catch (Throwable $e) {
    error_log('OAuth login error: ' . $e->getMessage());
    flash_set('error', 'Database error during Google authentication. Please try again.');
    header('Location: login.php');
    exit;
}
