<?php
/**
 * Samrajya Mens Wear - Authentication & Authorization Engine
 * Handles session integrity, server-side Firebase ID token verification, and RBAC.
 */

if (!defined('APP_ROOT')) {
    exit('Direct access not permitted');
}

/**
 * Initializes secure PHP session
 */
function init_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $lifetime = 86400; // 24 hours
        ini_set('session.gc_maxlifetime', $lifetime);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ]);
        
        session_start();
    }
}

/**
 * Check if current user is logged in
 */
function is_authenticated(): bool {
    init_session();
    if (empty($_SESSION['admin_user']) || !is_array($_SESSION['admin_user'])) {
        return false;
    }

    // Check session timeout
    $loginTime = $_SESSION['admin_user']['login_time'] ?? 0;
    global $config;
    $maxLife = $config['app']['session_lifetime'] ?? 86400;
    if (time() - $loginTime > $maxLife) {
        logout();
        return false;
    }

    return true;
}

/**
 * Get current authenticated user details
 */
function get_authenticated_user(): ?array {
    return is_authenticated() ? $_SESSION['admin_user'] : null;
}

/**
 * Enforce authentication on protected pages or APIs
 */
function require_auth(bool $isApi = false): void {
    if (!is_authenticated()) {
        if ($isApi || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            json_error('Unauthorized. Session expired or missing.', 401);
        } else {
            $redirect = urlencode($_SERVER['REQUEST_URI'] ?? 'dashboard.php');
            header("Location: login.php?redirect={$redirect}");
            exit;
        }
    }
}

/**
 * Check if email or UID is in the approved admin whitelist
 */
function is_authorized_admin(string $email, ?string $uid = null): bool {
    global $config;
    $allowedEmails = array_map('strtolower', $config['admin_auth']['allowed_emails'] ?? []);
    $allowedUids = $config['admin_auth']['allowed_uids'] ?? [];

    if (in_array(strtolower(trim($email)), $allowedEmails, true)) {
        return true;
    }

    if (!empty($uid) && in_array(trim($uid), $allowedUids, true)) {
        return true;
    }

    // Also check admins collection in database
    try {
        $adminMaster = db()->get('admins', 'admin_master');
        if ($adminMaster && !empty($adminMaster['isActive']) && strtolower(trim($adminMaster['email'] ?? '')) === strtolower(trim($email))) {
            return true;
        }
        $adminDoc = db()->get('admins', $uid ?: md5(strtolower($email)));
        if ($adminDoc && !empty($adminDoc['isActive']) && in_array($adminDoc['role'] ?? '', ['superadmin', 'admin'])) {
            return true;
        }
    } catch (Exception $e) {
        error_log("Admin authorization check error: " . $e->getMessage());
    }

    return false;
}

/**
 * Verify Firebase ID Token via Google's token verification endpoint
 */
function verify_firebase_id_token(string $idToken): ?array {
    global $config;
    $projectId = $config['firebase_client']['projectId'] ?? '';

    // Verify via Google OAuth2 TokenInfo endpoint
    $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($idToken);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $info = json_decode($res, true);
        if (is_array($info)) {
            $aud = $info['aud'] ?? '';
            $email = $info['email'] ?? '';
            $uid = $info['sub'] ?? ($info['user_id'] ?? '');

            // Verify audience matches project ID if configured
            if (!empty($projectId) && $aud !== $projectId) {
                error_log("Token audience mismatch: got $aud, expected $projectId");
                return null;
            }

            return [
                'uid' => $uid,
                'email' => $email,
                'email_verified' => (bool)($info['email_verified'] ?? false),
                'name' => $info['name'] ?? ($info['displayName'] ?? explode('@', $email)[0]),
                'provider' => 'firebase'
            ];
        }
    }

    // Alternative: Verify with Firebase Identity Toolkit endpoint
    $apiKey = $config['firebase_client']['apiKey'] ?? '';
    if (!empty($apiKey) && strpos($apiKey, 'DEMO') === false) {
        $lookupUrl = "https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=" . urlencode($apiKey);
        $ch = curl_init($lookupUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['idToken' => $idToken]),
            CURLOPT_TIMEOUT => 10
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200) {
            $data = json_decode($res, true);
            if (!empty($data['users'][0])) {
                $u = $data['users'][0];
                return [
                    'uid' => $u['localId'],
                    'email' => $u['email'],
                    'email_verified' => (bool)($u['emailVerified'] ?? false),
                    'name' => $u['displayName'] ?? explode('@', $u['email'])[0],
                    'provider' => 'firebase'
                ];
            }
        }
    }

    return null;
}

/**
 * Log out user and destroy session
 */
function logout(): void {
    init_session();
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
