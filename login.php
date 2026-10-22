<?php
/**
 * Samrajya Mens Wear - Admin Authentication Portal
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Redirect if already logged in
if (is_authenticated()) {
    header('Location: dashboard.php');
    exit;
}

global $config;
$fbClient = $config['firebase_client'] ?? [];
$shop = $config['shop'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Samrajya Mens Wear</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        body {
            background: radial-gradient(circle at 50% 30%, #164e3b 0%, #09261b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .login-card {
            background: #ffffff;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 440px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45);
            overflow: hidden;
            animation: cardSlideUp 0.35s ease-out;
        }
        @keyframes cardSlideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-header {
            background: linear-gradient(135deg, var(--primary-800), var(--primary-900));
            padding: 36px 30px 28px;
            text-align: center;
            color: #fff;
            position: relative;
        }
        .login-crest {
            width: 58px;
            height: 58px;
            background: linear-gradient(135deg, var(--gold-400), var(--gold-600));
            border-radius: var(--radius-md);
            margin: 0 auto 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 800;
            color: #fff;
            box-shadow: 0 4px 14px rgba(217, 119, 6, 0.4);
            letter-spacing: 0.5px;
        }
        .login-title {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .login-subtitle {
            font-size: 12px;
            color: var(--gold-400);
            text-transform: uppercase;
            letter-spacing: 1.2px;
            font-weight: 600;
        }
        .login-body {
            padding: 32px 30px;
        }
        .login-btn {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, var(--primary-700), var(--primary-900));
            color: #fff;
            font-size: 14.5px;
            font-weight: 700;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(15, 57, 43, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
            margin-top: 24px;
        }
        .login-btn:hover {
            background: linear-gradient(135deg, var(--primary-600), var(--primary-800));
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(15, 57, 43, 0.4);
        }
        .login-btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }
        .login-footer {
            text-align: center;
            padding: 14px 20px;
            background-color: #fafbfc;
            border-top: 1px solid var(--slate-200);
            font-size: 11px;
            color: var(--slate-500);
        }
    </style>

    <!-- Firebase App, Auth & Analytics Scripts -->
    <script src="https://www.gstatic.com/firebasejs/10.9.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.9.0/firebase-auth-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.9.0/firebase-analytics-compat.js"></script>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <img src="assets/images/logo.png" alt="साम्राज्य.07" style="width: 88px; height: 88px; border-radius: 50%; object-fit: contain; box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4); margin: 0 auto 14px; display: block; border: 2px solid var(--gold-400); background: #000;">
        <div class="login-title" style="font-size: 22px; font-weight: 800;">साम्राज्य.07</div>
        <div class="login-subtitle" style="font-size: 13px; color: var(--gold-400); letter-spacing: 0.5px; text-transform: none;">The perfect men's shop</div>
    </div>

    <div class="login-body">
        <form id="loginForm" onsubmit="handleLogin(event)">
            <div class="form-group">
                <label class="form-label" for="email">Administrator Email</label>
                <input type="email" id="email" class="form-control" placeholder="Enter administrator email" required autofocus autocomplete="email">
            </div>

            <div class="form-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                    <label class="form-label" for="password" style="margin-bottom:0;">Password</label>
                </div>
                <input type="password" id="password" class="form-control" placeholder="Enter password" required autocomplete="current-password">
            </div>

            <button type="submit" id="submitBtn" class="login-btn">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                Secure Sign In
            </button>
        </form>
    </div>

    <div class="login-footer">
        Samrajya Mens Wear &copy; <?= date('Y') ?> &bull; Internal Retail Staff Only
    </div>
</div>

<div id="toastContainer"></div>

<script src="assets/js/app.js"></script>
<script>
    // Initialize Firebase Client SDK if configured
    const firebaseConfig = <?= json_encode($fbClient) ?>;
    let isFirebaseReady = false;

    try {
        if (firebaseConfig && firebaseConfig.apiKey && !firebaseConfig.apiKey.includes('DEMO')) {
            const app = firebase.initializeApp(firebaseConfig);
            if (typeof firebase.analytics === 'function') {
                firebase.analytics();
            }
            isFirebaseReady = true;
        }
    } catch (err) {
        console.warn('Firebase client initialization note:', err);
    }

    async function handleLogin(e) {
        e.preventDefault();
        const btn = document.getElementById('submitBtn');
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value.trim();

        btn.disabled = true;
        btn.innerHTML = `<svg class="animate-spin" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="animation:spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> Authenticating...`;

        let idToken = '';

        // Attempt client-side Firebase Auth sign-in if initialized
        if (isFirebaseReady) {
            try {
                const userCredential = await firebase.auth().signInWithEmailAndPassword(email, password);
                idToken = await userCredential.user.getIdToken();
            } catch (fbErr) {
                console.warn('Firebase client sign-in failed, checking backend authorization directly:', fbErr.message);
            }
        }

        try {
            // Send to server-side auth verification endpoint
            const res = await App.api('api/auth.php?action=login', {
                method: 'POST',
                body: {
                    email: email,
                    password: password,
                    idToken: idToken
                }
            });

            App.toast('Welcome back! Redirecting to dashboard...', 'success');
            setTimeout(() => {
                window.location.href = res.redirect || 'dashboard.php';
            }, 600);
        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = `<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg> Secure Sign In`;
        }
    }
</script>
</body>
</html>
