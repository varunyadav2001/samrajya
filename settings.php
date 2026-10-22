<?php
/**
 * Samrajya Mens Wear - User Settings & Profile Management
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();

$pageTitle = 'User Settings';
$activePage = 'settings';

$currentUser = get_authenticated_user();
$db = db();
$adminDoc = $db->get('admins', $currentUser['uid'] ?? 'admin_master') ?? $db->get('admins', 'admin_master') ?? [];

$displayName = $adminDoc['displayName'] ?? ($currentUser['name'] ?? 'Samrajya 07 Admin');
$email = $adminDoc['email'] ?? ($currentUser['email'] ?? 'admin@samrajyamenswear.com');
$phone = $adminDoc['phone'] ?? '';
$role = $adminDoc['role'] ?? ($currentUser['role'] ?? 'superadmin');
$lastLogin = !empty($adminDoc['lastLoginAt']) ? date('d M Y, h:i A', strtotime($adminDoc['lastLoginAt'])) : 'Active Now';

require __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:22px; font-weight:800; color:var(--primary-900); margin:0;">
            User Profile & Settings
        </h2>
        <p style="font-size:13px; color:var(--slate-500); margin-top:2px;">
            तुमचे ॲडमिन प्रोफाइल, संपर्क माहिती आणि पासवर्ड येथे बदला.
        </p>
    </div>
</div>

<!-- User Overview Hero Banner -->
<div class="card" style="margin-bottom:24px; padding:20px; background:linear-gradient(135deg, var(--primary-900) 0%, var(--primary-800) 100%); color:#ffffff; border:none;">
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
        <div style="display:flex; align-items:center; gap:16px;">
            <div style="width:60px; height:60px; border-radius:50%; background:linear-gradient(135deg, var(--gold-400), var(--gold-600)); display:flex; align-items:center; justify-content:center; font-size:24px; font-weight:800; color:#ffffff; box-shadow:0 4px 12px rgba(0,0,0,0.25);">
                <?= strtoupper(substr($displayName, 0, 1)) ?>
            </div>
            <div>
                <h3 style="margin:0; font-size:18px; font-weight:800; color:#ffffff;"><?= htmlspecialchars($displayName) ?></h3>
                <div style="font-size:13px; color:var(--gold-400); margin-top:2px; display:flex; align-items:center; gap:8px;">
                    <span><?= htmlspecialchars($email) ?></span>
                    <span>•</span>
                    <span style="background:rgba(255,255,255,0.15); padding:2px 8px; border-radius:12px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">
                        <?= htmlspecialchars(strtoupper($role)) ?>
                    </span>
                </div>
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:20px; font-size:12px; color:rgba(255,255,255,0.85);">
            <div>
                <span style="opacity:0.7;">Account Status:</span><br>
                <strong style="color:#10b981;">● Active & Verified</strong>
            </div>
            <div>
                <span style="opacity:0.7;">Last Session:</span><br>
                <strong><?= htmlspecialchars($lastLogin) ?></strong>
            </div>
        </div>
    </div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px; align-items:start;">
    
    <!-- Left Column: User Profile Information -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                User Profile Details (वापरकर्ता माहिती)
            </div>
        </div>
        <div class="card-body">
            <form id="profileForm" onsubmit="saveUserProfile(event)">
                <div class="form-group">
                    <label class="form-label">Full Name / Display Name * (पूर्ण नाव)</label>
                    <input type="text" id="u_name" class="form-control" required value="<?= htmlspecialchars($displayName) ?>" placeholder="e.g. Samrajya 07 Admin">
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address * (लॉगिन ईमेल)</label>
                    <input type="email" id="u_email" class="form-control" required value="<?= htmlspecialchars($email) ?>" placeholder="admin@samrajyamenswear.com">
                    <small style="color:var(--slate-500); font-size:11.5px; margin-top:4px; display:block;">
                        हा ईमेल लॉगिन करताना वापरला जातो.
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label">Mobile Number (मोबाईल क्रमांक)</label>
                    <input type="tel" id="u_phone" class="form-control" value="<?= htmlspecialchars($phone) ?>" placeholder="e.g. 9876543210">
                </div>

                <div class="form-group">
                    <label class="form-label">User Role (भूमिका)</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars(ucfirst($role)) ?> (Full Access)" disabled style="background:#f8fafc; color:#64748b; cursor:not-allowed;">
                </div>

                <div style="display:flex; justify-content:flex-end; margin-top:24px;">
                    <button type="submit" id="saveProfileBtn" class="btn btn-primary" style="padding:11px 24px; font-weight:700;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:6px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Save Profile (प्रोफाइल सेव्ह करा)
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Right Column: Change Password -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Change Password (पासवर्ड बदला)
            </div>
        </div>
        <div class="card-body">
            <form id="passwordForm" onsubmit="changePassword(event)">
                <div class="form-group">
                    <label class="form-label">Current Password * (सध्याचा पासवर्ड)</label>
                    <input type="password" id="p_current" class="form-control" required placeholder="तुमचा आत्ताचा पासवर्ड टाका">
                </div>

                <div class="form-group">
                    <label class="form-label">New Password * (नवीन पासवर्ड)</label>
                    <input type="password" id="p_new" class="form-control" required minlength="6" placeholder="किमान ६ अक्षरे/नंबर टाका">
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm New Password * (नवीन पासवर्ड पुन्हा टाका)</label>
                    <input type="password" id="p_confirm" class="form-control" required minlength="6" placeholder="तोच पासवर्ड पुन्हा टाका">
                </div>

                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:12px; margin-top:16px; font-size:12px; color:#166534;">
                    <div style="font-weight:700; margin-bottom:2px;">💡 टीप:</div>
                    पासवर्ड बदलल्यानंतर पुढच्या वेळी नवीन पासवर्डने लॉगिन करावे लागेल.
                </div>

                <div style="display:flex; justify-content:flex-end; margin-top:24px;">
                    <button type="submit" id="savePasswordBtn" class="btn btn-primary" style="padding:11px 24px; font-weight:700;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:6px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Update Password (पासवर्ड अपडेट करा)
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
async function saveUserProfile(e) {
    e.preventDefault();
    const btn = document.getElementById('saveProfileBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<svg class="animate-spin" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="animation:spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> Saving Profile...`;

    const payload = {
        displayName: document.getElementById('u_name').value.trim(),
        email: document.getElementById('u_email').value.trim(),
        phone: document.getElementById('u_phone').value.trim()
    };

    try {
        const res = await App.api('api/settings.php?action=update_profile', {
            method: 'POST',
            body: payload
        });
        App.toast(res.message || 'Profile updated successfully! ✅', 'success');
        setTimeout(() => location.reload(), 800);
    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

async function changePassword(e) {
    e.preventDefault();
    const cur = document.getElementById('p_current').value;
    const n = document.getElementById('p_new').value;
    const c = document.getElementById('p_confirm').value;

    if (n.length < 6) {
        App.toast('New password must be at least 6 characters.', 'error');
        return;
    }

    if (n !== c) {
        App.toast('New password and confirm password do not match!', 'error');
        return;
    }

    const btn = document.getElementById('savePasswordBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<svg class="animate-spin" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="animation:spin 1s linear infinite;"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> Updating Password...`;

    try {
        const res = await App.api('api/settings.php?action=update_password', {
            method: 'POST',
            body: {
                currentPassword: cur,
                newPassword: n,
                confirmPassword: c
            }
        });
        App.toast(res.message || 'Password updated successfully! ✅', 'success');
        document.getElementById('passwordForm').reset();
    } catch (err) {
        // App.api handles error toast
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
