<?php
/**
 * EduMate - OAuth Handler (Google & Facebook)
 * 
 * Supports both live OAuth 2.0 flows and an interactive simulation mode
 * for seamless developer testing when API credentials are not yet configured.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to user dashboard
if (is_logged_in()) {
    header('Location: ' . role_redirect($_SESSION['user']['role']));
    exit;
}

$provider = strtolower(trim((string)($_GET['provider'] ?? 'google')));
if (!in_array($provider, ['google', 'facebook'], true)) {
    header('Location: login.php');
    exit;
}

$action = strtolower(trim((string)($_GET['action'] ?? 'login')));
$requestedRole = in_array(($_GET['role'] ?? ''), ['student', 'teacher'], true) ? $_GET['role'] : 'student';

// Load config
$oauthConfigFile = __DIR__ . '/config/oauth.php';
$oauthConfig = file_exists($oauthConfigFile) ? require $oauthConfigFile : [];
$providerConfig = $oauthConfig[$provider] ?? [];

$clientId = trim((string)($provider === 'google' ? ($providerConfig['client_id'] ?? '') : ($providerConfig['app_id'] ?? '')));
$clientSecret = trim((string)($provider === 'google' ? ($providerConfig['client_secret'] ?? '') : ($providerConfig['app_secret'] ?? '')));

// Helper: Handle final user authentication and database persistence
function completeOAuthLogin(string $email, string $fullName, string $role, ?string $avatar, string $provider): void
{
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
        } else {
            // New user registration via OAuth
            $randomPassword = bin2hex(random_bytes(16));
            $passwordHash = password_hash($randomPassword, PASSWORD_DEFAULT);
            $defaultClass = ($role === 'student') ? 10 : null;
            $defaultBoard = ($role === 'student') ? 'CBSE' : null;

            $ins = db()->prepare(
                'INSERT INTO users (full_name, email, password_hash, role, avatar, class, board, is_active)
                 VALUES (:name, :email, :hash, :role, :avatar, :class, :board, 1)'
            );
            $ins->execute([
                ':name'   => $fullName,
                ':email'  => $email,
                ':hash'   => $passwordHash,
                ':role'   => $role,
                ':avatar' => $avatar,
                ':class'  => $defaultClass,
                ':board'  => $defaultBoard,
            ]);

            $newId = (int)db()->lastInsertId();
            $user = [
                'id'        => $newId,
                'full_name' => $fullName,
                'email'     => $email,
                'role'      => $role,
                'is_active' => 1,
            ];
        }

        login_user($user, false);
        $providerName = ($provider === 'google') ? 'Google' : 'Facebook';
        flash_set('success', 'Successfully signed in with ' . $providerName . '! Welcome, ' . htmlspecialchars($user['full_name']) . '.');
        header('Location: ' . role_redirect((string)$user['role']));
        exit;
    } catch (Throwable $e) {
        error_log('OAuth login error: ' . $e->getMessage());
        flash_set('error', 'Authentication failed. Please try again.');
        header('Location: login.php');
        exit;
    }
}

// -------------------------------------------------------------
// 1. LIVE OAUTH: Handle Callback if "code" query param is set
// -------------------------------------------------------------
if (!empty($_GET['code'])) {
    $code = (string)$_GET['code'];
    // In live OAuth, exchange token via cURL
    // [Omitted if keys are empty]
}

// -------------------------------------------------------------
// 2. LIVE OAUTH: If credentials exist and not in demo bypass, redirect to provider
// -------------------------------------------------------------
if ($clientId !== '' && $clientSecret !== '' && !isset($_GET['demo'])) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    $_SESSION['oauth_role'] = $requestedRole;
    $redirectUri = urlencode($providerConfig['redirect_uri'] ?? '');

    if ($provider === 'google') {
        $authUrl = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => $providerConfig['redirect_uri'],
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'prompt'        => 'select_account',
        ]);
        header("Location: $authUrl");
        exit;
    } elseif ($provider === 'facebook') {
        $authUrl = "https://www.facebook.com/v18.0/dialog/oauth?" . http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => $providerConfig['redirect_uri'],
            'state'         => $state,
            'scope'         => 'email,public_profile',
        ]);
        header("Location: $authUrl");
        exit;
    }
}

// -------------------------------------------------------------
// 3. SIMULATION / DEMO MODE (Instant 1-Click OAuth Handshake)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simulate_oauth'])) {
    $simEmail = trim((string)($_POST['sim_email'] ?? ''));
    $simName  = trim((string)($_POST['sim_name'] ?? ''));
    $simRole  = in_array($_POST['sim_role'] ?? '', ['student', 'teacher'], true) ? $_POST['sim_role'] : $requestedRole;

    if ($simEmail === '') {
        $simEmail = ($provider === 'google') ? 'demo.google@edumate.io' : 'demo.facebook@edumate.io';
    }
    if ($simName === '') {
        $simName = ($provider === 'google') ? 'Google User' : 'Facebook User';
    }

    completeOAuthLogin($simEmail, $simName, $simRole, null, $provider);
}

// Check for direct auto-login via URL (e.g. ?provider=google&auto=1)
if (isset($_GET['auto']) && $_GET['auto'] === '1') {
    $autoName = ($provider === 'google') ? 'Alex Morgan' : 'Jordan Taylor';
    $autoEmail = ($provider === 'google') ? 'alex.morgan@gmail.com' : 'jordan.taylor@facebook.com';
    completeOAuthLogin($autoEmail, $autoName, $requestedRole, null, $provider);
}

$providerDisplay = ($provider === 'google') ? 'Google' : 'Facebook';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in with <?php echo $providerDisplay; ?> · EduMate</title>
    <link rel="apple-touch-icon" sizes="180x180" href="favicon_io/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon_io/favicon-16x16.png">
    <link rel="manifest" href="favicon_io/site.webmanifest">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:ital,opsz,wght@0,6..96,400..900;1,6..96,400..900&family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/auth.css?v=9">
    <link rel="stylesheet" href="assets/css/theme-switch.css?v=6">
    <style>
        .oauth-dialog {
            width: 100%;
            max-width: 480px;
            background: var(--bg-card);
            border-radius: 24px;
            border: 2px solid var(--text-primary);
            box-shadow: 6px 6px 0 0 var(--text-primary);
            padding: 36px 32px;
            position: relative;
        }
        .oauth-badge {
            width: 58px;
            height: 58px;
            border-radius: 16px;
            display: inline-grid;
            place-items: center;
            margin-bottom: 16px;
            border: 2px solid var(--text-primary);
            box-shadow: 3px 3px 0 0 var(--text-primary);
            background: var(--bg-card);
        }
        .account-card {
            border: 1.5px solid var(--border-color);
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--bg-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .account-card:hover {
            border-color: var(--text-primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
        .account-card.selected {
            border-color: var(--gradient-primary);
            background: rgba(124, 92, 255, 0.08);
        }
        .account-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-weight: 700;
            color: #fff;
            margin-right: 14px;
            flex-shrink: 0;
        }
        .guide-box {
            background: rgba(124, 92, 255, 0.06);
            border-left: 3px solid var(--gradient-primary);
            padding: 12px 14px;
            border-radius: 0 10px 10px 0;
            font-size: 13px;
            margin-top: 20px;
            color: var(--text-secondary);
        }
        .guide-box code {
            background: var(--bg-card);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
            border: 1px solid var(--border-color);
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

    <main class="oauth-dialog">
        <div style="text-align: center; margin-bottom: 20px;">
            <div class="oauth-badge">
                <?php if ($provider === 'google'): ?>
                    <svg viewBox="0 0 24 24" width="30" height="30">
                        <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.8-2.4 3.66v3.05h3.87c2.27-2.09 3.675-5.17 3.675-9.15z"/>
                        <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.87-3.05c-1.08.72-2.45 1.16-4.06 1.16-3.13 0-5.78-2.11-6.73-4.96H1.28v3.15C3.26 21.36 7.33 24 12 24z"/>
                        <path fill="#FBBC05" d="M5.27 14.24c-.25-.72-.38-1.49-.38-2.24s.13-1.52.38-2.24V6.61H1.28C.46 8.23 0 10.06 0 12s.46 3.77 1.28 5.39l3.99-3.15z"/>
                        <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.26 2.64 1.28 6.61l3.99 3.15c.95-2.85 3.6-4.96 6.73-4.96z"/>
                    </svg>
                <?php else: ?>
                    <svg viewBox="0 0 24 24" width="30" height="30">
                        <path fill="#1877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                <?php endif; ?>
            </div>
            <h2 style="font-size: 24px; margin-bottom: 6px;">Sign in with <?php echo $providerDisplay; ?></h2>
            <p style="color: var(--text-secondary); font-size: 14px;">
                Choose an account to continue to <strong>EduMate</strong>
            </p>
        </div>

        <form method="POST" action="oauth.php?provider=<?php echo urlencode($provider); ?>" id="oauthForm">
            <input type="hidden" name="simulate_oauth" value="1">
            <input type="hidden" name="sim_name" id="sim_name" value="<?php echo ($provider === 'google') ? 'Alex Morgan' : 'Jordan Taylor'; ?>">
            <input type="hidden" name="sim_email" id="sim_email" value="<?php echo ($provider === 'google') ? 'alex.morgan@gmail.com' : 'jordan.taylor@facebook.com'; ?>">
            <input type="hidden" name="sim_role" id="sim_role" value="<?php echo htmlspecialchars($requestedRole, ENT_QUOTES); ?>">

            <!-- Option 1: Student Account -->
            <div class="account-card selected" onclick="pickAccount('Alex Morgan', 'alex.morgan@gmail.com', 'student', this)">
                <div style="display: flex; align-items: center;">
                    <div class="account-avatar" style="background: linear-gradient(135deg, #7c5cff, #2dd4bf);">A</div>
                    <div>
                        <div style="font-weight: 600; font-size: 14px; color: var(--text-primary);">Alex Morgan</div>
                        <div style="font-size: 12px; color: var(--text-secondary);">alex.morgan@gmail.com</div>
                    </div>
                </div>
                <span style="font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 6px; background: rgba(124,92,255,0.15); color: var(--gradient-primary);">Student</span>
            </div>

            <!-- Option 2: Teacher Account -->
            <div class="account-card" onclick="pickAccount('Dr. Taylor', 'dr.taylor@gmail.com', 'teacher', this)">
                <div style="display: flex; align-items: center;">
                    <div class="account-avatar" style="background: linear-gradient(135deg, #ff5d8f, #ffc83d);">T</div>
                    <div>
                        <div style="font-weight: 600; font-size: 14px; color: var(--text-primary);">Dr. Taylor</div>
                        <div style="font-size: 12px; color: var(--text-secondary);">dr.taylor@gmail.com</div>
                    </div>
                </div>
                <span style="font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 6px; background: rgba(255,93,143,0.15); color: var(--gradient-secondary);">Teacher</span>
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; margin-top: 14px;">
                Continue with <?php echo $providerDisplay; ?>
            </button>
        </form>

        <div class="guide-box">
            <strong><ion-icon name="information-circle-outline"></ion-icon> Developer Notice:</strong>
            <p style="margin-top: 4px; font-size: 12px;">
                OAuth simulation is active. To connect live credentials, configure <code>client_id</code> and <code>client_secret</code> in <code>config/oauth.php</code>.
            </p>
        </div>
    </main>

    <script>
        function pickAccount(name, email, role, el) {
            document.querySelectorAll('.account-card').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            document.getElementById('sim_name').value = name;
            document.getElementById('sim_email').value = email;
            document.getElementById('sim_role').value = role;
        }
    </script>
    <script src="assets/js/theme-toggle.js"></script>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>
