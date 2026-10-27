<?php
/**
 * EduMate - Google OAuth 2.0 Callback Handler
 *
 * 1. Exchanges Google authorization code for access token.
 * 2. Fetches verified user profile (email, name, picture).
 * 3. If user already exists -> logs in immediately to user dashboard.
 * 4. If user is new -> lets user explicitly choose their role (Student or Teacher)
 *    and customize class/board before creating their account.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to user dashboard
if (is_logged_in()) {
    header('Location: ' . role_redirect($_SESSION['user']['role']));
    exit;
}

// -------------------------------------------------------------
// POST HANDLER: Complete registration with chosen role
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_google_signup'])) {
    if (empty($_SESSION['pending_google_user']['email'])) {
        flash_set('error', 'Session expired. Please sign in again.');
        header('Location: login.php');
        exit;
    }

    $pending = $_SESSION['pending_google_user'];
    $role = in_array(($_POST['role'] ?? ''), ['student', 'teacher'], true) ? (string)$_POST['role'] : 'student';
    $fullName = trim((string)($_POST['full_name'] ?? $pending['full_name']));
    if ($fullName === '') {
        $fullName = $pending['full_name'];
    }
    $email = $pending['email'];
    $avatar = $pending['avatar'] ?? null;

    $class = null;
    $board = null;
    if ($role === 'student') {
        $class = isset($_POST['class']) ? max(5, min(10, (int)$_POST['class'])) : 10;
        $board = trim((string)($_POST['board'] ?? 'CBSE'));
        if (empty($board)) {
            $board = 'CBSE';
        }
    }

    try {
        $stmt = db()->prepare('SELECT id, full_name, email, role, is_active FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ((int)$existing['is_active'] !== 1) {
                flash_set('error', 'Your account has been deactivated. Please contact support.');
                header('Location: login.php');
                exit;
            }
            login_user($existing, false);
            unset($_SESSION['pending_google_user'], $_SESSION['oauth_role']);
            header('Location: ' . role_redirect((string)$existing['role']));
            exit;
        }

        $randomPassword = bin2hex(random_bytes(16));
        $passwordHash = password_hash($randomPassword, PASSWORD_DEFAULT);

        $ins = db()->prepare(
            'INSERT INTO users (full_name, email, password_hash, role, avatar, class, board, is_active)
             VALUES (:name, :email, :hash, :role, :avatar, :class, :board, 1)'
        );
        $ins->execute([
            ':name'   => $fullName,
            ':email'  => $email,
            ':hash'   => $passwordHash,
            ':role'   => $role,
            ':avatar' => $avatar ?: null,
            ':class'  => $class,
            ':board'  => $board,
        ]);

        $newId = (int)db()->lastInsertId();
        $user = [
            'id'        => $newId,
            'full_name' => $fullName,
            'email'     => $email,
            'role'      => $role,
            'is_active' => 1,
        ];

        login_user($user, false);
        unset($_SESSION['pending_google_user'], $_SESSION['oauth_role']);
        flash_set('success', 'Successfully registered with Google as a ' . ucfirst($role) . '! Welcome, ' . htmlspecialchars($fullName) . '.');
        header('Location: ' . role_redirect($role));
        exit;
    } catch (Throwable $e) {
        error_log('OAuth registration error: ' . $e->getMessage());
        flash_set('error', 'Registration failed. Please try again.');
        header('Location: login.php');
        exit;
    }
}

// -------------------------------------------------------------
// OAUTH EXCHANGE: If returning from Google with code
// -------------------------------------------------------------
if (!empty($_GET['error'])) {
    $errorMsg = htmlspecialchars((string)$_GET['error']);
    flash_set('error', 'Google sign-in was cancelled or encountered an error: ' . $errorMsg);
    header('Location: login.php');
    exit;
}

$code = trim((string)($_GET['code'] ?? ''));
$state = trim((string)($_GET['state'] ?? ''));

// If no code and no pending session, return to login
if ($code === '' && empty($_SESSION['pending_google_user'])) {
    flash_set('error', 'Authorization code not provided by Google.');
    header('Location: login.php');
    exit;
}

if ($code !== '') {
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
    $redirectUri = $_SESSION['oauth_redirect_uri'] ?? ($googleConfig['redirect_uri'] ?? 'http://localhost/WEBPAGE/eduMate/oauth2callback.php');

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
        CURLOPT_SSL_VERIFYPEER => false,
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

    $tokenData = json_decode((string)$response, true);
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

    $profile = json_decode((string)$userResponse, true);
    $email = trim((string)($profile['email'] ?? ''));
    $fullName = trim((string)($profile['name'] ?? ''));
    $avatar = trim((string)($profile['picture'] ?? ''));

    if ($email === '') {
        flash_set('error', 'Google did not provide an email address.');
        header('Location: login.php');
        exit;
    }

    if ($fullName === '') {
        $fullName = explode('@', $email)[0];
    }

    // Check if user already exists in database
    try {
        $stmt = db()->prepare('SELECT id, full_name, email, role, is_active FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ((int)$existing['is_active'] !== 1) {
                flash_set('error', 'Your account has been deactivated. Please contact support.');
                header('Location: login.php');
                exit;
            }
            if (!empty($avatar)) {
                try {
                    db()->prepare('UPDATE users SET avatar = :avatar WHERE id = :id')->execute([
                        ':avatar' => $avatar,
                        ':id'     => $existing['id'],
                    ]);
                } catch (Throwable $e) {}
            }
            login_user($existing, false);
            flash_set('success', 'Successfully signed in with Google! Welcome back, ' . htmlspecialchars($existing['full_name']) . '.');
            header('Location: ' . role_redirect((string)$existing['role']));
            exit;
        }
    } catch (Throwable $e) {
        error_log('Database check error: ' . $e->getMessage());
    }

    // User is new -> Store Google user info in session for role selection
    $_SESSION['pending_google_user'] = [
        'email'     => $email,
        'full_name' => $fullName,
        'avatar'    => $avatar,
    ];
}

$pending = $_SESSION['pending_google_user'] ?? [
    'email'     => '',
    'full_name' => '',
    'avatar'    => '',
];

$preselectedRole = ($_SESSION['oauth_role'] === 'teacher') ? 'teacher' : 'student';
$boards = get_all_boards();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Your Account Type · EduMate</title>
    <link rel="apple-touch-icon" sizes="180x180" href="favicon_io/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon_io/favicon-16x16.png">
    <link rel="manifest" href="favicon_io/site.webmanifest">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:ital,opsz,wght@0,6..96,400..900;1,6..96,400..900&family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/auth.css?v=10">
    <link rel="stylesheet" href="assets/css/theme-switch.css?v=6">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .role-modal {
            width: 100%;
            max-width: 540px;
            background: var(--bg-card);
            border-radius: 24px;
            border: 2px solid var(--text-primary);
            box-shadow: 6px 6px 0 0 var(--text-primary);
            padding: 36px 32px;
            position: relative;
        }
        .google-pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            background: rgba(124, 92, 255, 0.08);
            border: 1.5px solid var(--border-color);
            border-radius: 100px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .google-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
        }
        .role-cards-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin: 20px 0;
        }
        .role-option-card {
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 20px 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: var(--bg-secondary);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .role-option-card:hover {
            border-color: var(--text-primary);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.06);
        }
        .role-option-card.selected {
            border-color: var(--gradient-primary);
            background: rgba(124, 92, 255, 0.08);
            box-shadow: 0 0 0 2px var(--gradient-primary);
        }
        .role-card-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            font-size: 24px;
            margin-bottom: 12px;
            border: 1.5px solid var(--text-primary);
        }
        .role-option-card.student .role-card-icon {
            background: linear-gradient(135deg, #7c5cff, #2dd4bf);
            color: #fff;
        }
        .role-option-card.teacher .role-card-icon {
            background: linear-gradient(135deg, #ff5d8f, #ffc83d);
            color: #fff;
        }
        .role-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        .role-desc {
            font-size: 12px;
            color: var(--text-secondary);
            line-height: 1.4;
        }
        .student-custom-fields {
            margin-top: 16px;
            padding: 16px;
            background: var(--bg-secondary);
            border-radius: 14px;
            border: 1.5px solid var(--border-color);
        }
    </style>
</head>
<body>
    <a class="back-home" href="login.php"><ion-icon name="arrow-back-outline"></ion-icon> Cancel & Return</a>
    <div style="position: fixed; top: 18px; right: 18px; z-index: 100;">
        <label class="theme-switch" aria-label="Toggle dark mode">
            <input type="checkbox" class="theme-switch__checkbox">
            <div class="theme-switch__container">
                <div class="theme-switch__clouds"></div>
                <div class="theme-switch__stars-container"><svg viewBox="0 0 144 55" fill="none"><path d="M135.831 3.00688C135.055 3.85027 134.111 4.29946 133 4.35447C134.111 4.40947 135.055 4.85867 135.831 5.71123C136.607 6.55462 136.996 7.56303 136.996 8.72727C136.996 7.95722 137.172 7.25134 137.525 6.59129C137.886 5.93124 138.372 5.39954 138.98 5.00535C139.598 4.60199 140.268 4.39114 141 4.35447C139.88 4.2903 138.936 3.85027 138.16 3.00688C137.384 2.16348 136.996 1.16425 136.996 0C136.996 1.16425 136.607 2.16348 135.831 3.00688Z" fill="currentColor"/></svg></div>
                <div class="theme-switch__circle-container"><div class="theme-switch__sun-moon-container"><div class="theme-switch__moon"><div class="theme-switch__spot"></div><div class="theme-switch__spot"></div><div class="theme-switch__spot"></div></div></div></div>
            </div>
        </label>
    </div>

    <main class="role-modal">
        <div style="text-align: center;">
            <div class="google-pill">
                <?php if (!empty($pending['avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($pending['avatar']); ?>" class="google-avatar" alt="Avatar">
                <?php else: ?>
                    <i class="fa-brands fa-google" style="color: #4285F4;"></i>
                <?php endif; ?>
                <span>Signing up as <strong><?php echo htmlspecialchars($pending['full_name']); ?></strong></span>
            </div>

            <h2 style="font-size: 24px; margin-bottom: 6px;">Choose Your Account Type</h2>
            <p style="color: var(--text-secondary); font-size: 14px;">
                Are you joining EduMate as a <strong>Student</strong> or a <strong>Teacher</strong>?
            </p>
        </div>

        <form method="POST" action="oauth2callback.php" id="roleForm" style="margin-top: 20px;">
            <input type="hidden" name="complete_google_signup" value="1">
            <input type="hidden" name="role" id="selectedRoleInput" value="<?php echo htmlspecialchars($preselectedRole); ?>">

            <div class="role-cards-grid">
                <!-- Option 1: Student -->
                <div class="role-option-card student <?php echo ($preselectedRole === 'student') ? 'selected' : ''; ?>" id="card-student" onclick="selectRole('student')">
                    <div class="role-card-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="role-title">Student</div>
                    <div class="role-desc">Access study courses, flashcards, AI tutor, DPPs, and track streaks.</div>
                </div>

                <!-- Option 2: Teacher -->
                <div class="role-option-card teacher <?php echo ($preselectedRole === 'teacher') ? 'selected' : ''; ?>" id="card-teacher" onclick="selectRole('teacher')">
                    <div class="role-card-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="role-title">Teacher</div>
                    <div class="role-desc">Create courses, assign homework, host live sessions, and track students.</div>
                </div>
            </div>

            <!-- Student specific fields -->
            <div class="student-custom-fields" id="studentFields" style="<?php echo ($preselectedRole === 'student') ? 'display: block;' : 'display: none;'; ?>">
                <div style="font-size: 13px; font-weight: 600; margin-bottom: 12px; color: var(--text-primary);">
                    <i class="fas fa-sliders" style="margin-right: 6px; color: var(--gradient-primary);"></i> Student Details
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px; color: var(--text-secondary);">Class</label>
                        <select name="class" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); font-family: inherit;">
                            <?php for ($c = 5; $c <= 10; $c++): ?>
                                <option value="<?php echo $c; ?>" <?php echo ($c === 10) ? 'selected' : ''; ?>>Class <?php echo $c; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px; color: var(--text-secondary);">Board</label>
                        <select name="board" style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1.5px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); font-family: inherit;">
                            <?php foreach ($boards as $b): ?>
                                <option value="<?php echo htmlspecialchars($b); ?>" <?php echo ($b === 'CBSE') ? 'selected' : ''; ?>><?php echo htmlspecialchars($b); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-primary" id="btnSubmitRole" style="width: 100%; margin-top: 20px; padding: 14px; font-size: 15px; font-weight: 700;">
                <i class="fas fa-arrow-right" style="margin-right: 6px;"></i> Continue as <span id="btnRoleText"><?php echo ucfirst($preselectedRole); ?></span>
            </button>
        </form>
    </main>

    <script>
        function selectRole(role) {
            document.getElementById('selectedRoleInput').value = role;
            document.getElementById('card-student').classList.toggle('selected', role === 'student');
            document.getElementById('card-teacher').classList.toggle('selected', role === 'teacher');
            document.getElementById('studentFields').style.display = (role === 'student') ? 'block' : 'none';
            document.getElementById('btnRoleText').textContent = role.charAt(0).toUpperCase() + role.slice(1);
        }
    </script>
    <script src="assets/js/theme-toggle.js"></script>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>
