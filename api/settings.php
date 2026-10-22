<?php
/**
 * Samrajya Mens Wear - Settings & Firebase Operations API
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_auth(true);

$action = $_GET['action'] ?? $_POST['action'] ?? 'get';
$db = db();

switch ($action) {
    case 'get_profile':
        $user = get_authenticated_user();
        $adminDoc = $db->get('admins', $user['uid'] ?? 'admin_master') ?? $db->get('admins', 'admin_master') ?? [];

        json_response([
            'success' => true,
            'user' => [
                'uid' => $adminDoc['uid'] ?? ($user['uid'] ?? 'admin_master'),
                'displayName' => $adminDoc['displayName'] ?? ($user['name'] ?? 'Samrajya Admin'),
                'email' => $adminDoc['email'] ?? ($user['email'] ?? 'admin@samrajyamenswear.com'),
                'phone' => $adminDoc['phone'] ?? '',
                'role' => $adminDoc['role'] ?? ($user['role'] ?? 'superadmin'),
                'lastLoginAt' => $adminDoc['lastLoginAt'] ?? ''
            ]
        ]);
        break;

    case 'update_profile':
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?? $_POST;

        $displayName = trim($data['displayName'] ?? '');
        $email = strtolower(trim($data['email'] ?? ''));
        $phone = trim($data['phone'] ?? '');

        if (empty($displayName)) {
            json_error('Full name is required.');
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_error('A valid email address is required.');
        }

        $user = get_authenticated_user();
        $uid = $user['uid'] ?? 'admin_master';
        $adminDoc = $db->get('admins', $uid) ?? $db->get('admins', 'admin_master') ?? [];

        $adminDoc['displayName'] = $displayName;
        $adminDoc['email'] = $email;
        $adminDoc['phone'] = $phone;
        $adminDoc['updatedAt'] = date('c');

        $db->set('admins', $uid, $adminDoc, true);

        // Update active session values
        $_SESSION['admin_user']['name'] = $displayName;
        $_SESSION['admin_user']['email'] = $email;

        json_response([
            'success' => true,
            'message' => 'User profile updated successfully! ✅',
            'user' => [
                'displayName' => $displayName,
                'email' => $email,
                'phone' => $phone
            ]
        ]);
        break;

    case 'update_password':
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?? $_POST;

        $currentPassword = (string)($data['currentPassword'] ?? '');
        $newPassword = (string)($data['newPassword'] ?? '');
        $confirmPassword = (string)($data['confirmPassword'] ?? '');

        if (empty($currentPassword)) {
            json_error('Current password is required.');
        }
        if (strlen($newPassword) < 6) {
            json_error('New password must be at least 6 characters long.');
        }
        if ($newPassword !== $confirmPassword) {
            json_error('New password and confirm password do not match.');
        }

        $user = get_authenticated_user();
        $uid = $user['uid'] ?? 'admin_master';
        $adminDoc = $db->get('admins', $uid) ?? $db->get('admins', 'admin_master') ?? [];

        // Verify current password against hash or seed credentials
        $isValidCurrent = false;
        if (!empty($adminDoc['passwordHash']) && password_verify($currentPassword, $adminDoc['passwordHash'])) {
            $isValidCurrent = true;
        } elseif ($currentPassword === 'samrajyamenswea07' || $currentPassword === 'Samrajya@123') {
            $isValidCurrent = true;
        }

        if (!$isValidCurrent) {
            json_error('Incorrect current password. Please enter your existing password correctly.');
        }

        // Hash and store new password
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $adminDoc['passwordHash'] = $newHash;
        $adminDoc['updatedAt'] = date('c');

        $db->set('admins', $uid, $adminDoc, true);

        json_response([
            'success' => true,
            'message' => 'Password changed successfully! ✅'
        ]);
        break;

    case 'get':
        $settings = $db->get('settings', 'store_config') ?? [];

        json_response([
            'success' => true,
            'settings' => $settings
        ]);
        break;

    case 'save':
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?? $_POST;

        $shopName = trim($data['shopName'] ?? '');
        $tagline = trim($data['tagline'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $altPhone = trim($data['altPhone'] ?? '');
        $email = trim($data['email'] ?? '');
        $addressLine1 = trim($data['addressLine1'] ?? '');
        $addressLine2 = trim($data['addressLine2'] ?? '');
        $city = trim($data['city'] ?? '');
        $state = trim($data['state'] ?? '');
        $stateCode = trim($data['stateCode'] ?? '');
        $pincode = trim($data['pincode'] ?? '');
        $isGstRegistered = !empty($data['isGstRegistered']);
        $gstin = strtoupper(trim($data['gstin'] ?? ''));
        $defaultInvoiceType = ($data['defaultInvoiceType'] ?? 'nongst') === 'gst' ? 'gst' : 'nongst';
        $gstInvoicePrefix = trim($data['gstInvoicePrefix'] ?? 'SMW-GST-');
        $nongstInvoicePrefix = trim($data['nongstInvoicePrefix'] ?? 'SMW-BILL-');
        $returnPolicy = trim($data['returnPolicy'] ?? '');
        $invoiceFooter = trim($data['invoiceFooter'] ?? '');
        $upiId = trim($data['upiId'] ?? '');

        if (empty($shopName)) {
            json_error('Shop name is required.');
        }

        $now = date('c');
        $settingsDoc = [
            'shopName' => $shopName,
            'tagline' => $tagline,
            'phone' => $phone,
            'altPhone' => $altPhone,
            'email' => $email,
            'addressLine1' => $addressLine1,
            'addressLine2' => $addressLine2,
            'city' => $city,
            'state' => $state,
            'stateCode' => $stateCode,
            'pincode' => $pincode,
            'isGstRegistered' => $isGstRegistered,
            'gstin' => $gstin,
            'defaultInvoiceType' => $defaultInvoiceType,
            'gstInvoicePrefix' => $gstInvoicePrefix,
            'nongstInvoicePrefix' => $nongstInvoicePrefix,
            'returnPolicy' => $returnPolicy,
            'invoiceFooter' => $invoiceFooter,
            'upiId' => $upiId,
            'updatedAt' => $now
        ];

        $db->set('settings', 'store_config', $settingsDoc, true);

        json_response([
            'success' => true,
            'message' => 'Store settings saved successfully.',
            'settings' => $settingsDoc
        ]);
        break;

    case 'test_firebase':
        if (!$db->isFirestore()) {
            global $config;
            $path = $config['firebase_admin']['service_account_path'] ?? '';
            json_error("Firebase Service Account JSON was not found at: {$path}. Please upload 'firebase-service-account.json' into the config/ folder.", 400, [
                'configuredPath' => $path,
                'exists' => file_exists($path)
            ]);
        }

        try {
            $client = $db->getFirestoreClient();
            // Ping Firestore by getting settings document
            $doc = $client->getDocument('settings', 'store_config');
            json_response([
                'success' => true,
                'message' => 'Cloud Firestore connection verified successfully! Connected to Google Cloud project: ' . $client->getProjectId(),
                'projectId' => $client->getProjectId()
            ]);
        } catch (Exception $e) {
            json_error("Cloud Firestore connection test failed: " . $e->getMessage());
        }
        break;

    case 'sync_firestore':
        if (!$db->isFirestore()) {
            json_error('Cannot sync: Cloud Firestore is not currently connected. Please configure service account first.');
        }

        try {
            $result = $db->syncLocalToFirestore();
            json_response([
                'success' => true,
                'message' => "Successfully synced {$result['synced']} documents to Cloud Firestore.",
                'details' => $result
            ]);
        } catch (Exception $e) {
            json_error("Sync failed: " . $e->getMessage());
        }
        break;

    default:
        json_error('Invalid settings action.');
}
