<?php
/**
 * Samrajya Mens Wear - Core System Bootstrap
 */

define('APP_ROOT', dirname(__DIR__));

// Timezone configuration
date_default_timezone_set('Asia/Kolkata');

// Error reporting for robust diagnosis
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/logs/php_errors.log');

// Ensure storage directories exist
$requiredDirs = [
    APP_ROOT . '/storage/invoices',
    APP_ROOT . '/storage/data',
    APP_ROOT . '/storage/logs'
];
foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Composer Autoloader
$autoloadPath = APP_ROOT . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// Load Application Configuration
$configFile = APP_ROOT . '/config/config.php';
if (file_exists($configFile)) {
    $config = require $configFile;
} else {
    $exampleConfigFile = APP_ROOT . '/config/config.example.php';
    if (file_exists($exampleConfigFile)) {
        $config = require $exampleConfigFile;
    } else {
        die("System configuration missing. Please create config/config.php.");
    }
}

// Core Modules
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/auth.php';

// Initialize session
init_session();

/**
 * Seed initial store settings and sample men's wear catalog if empty
 */
function seed_initial_data_if_needed(): void {
    $db = db();
    
    // Check if store settings exist
    $settings = $db->get('settings', 'store_config');
    if (!$settings) {
        global $config;
        $db->set('settings', 'store_config', [
            'shopName' => 'साम्राज्य.07',
            'englishName' => 'Samrajya.07',
            'tagline' => "The perfect men's shop",
            'phone' => '',
            'altPhone' => '',
            'email' => '',
            'addressLine1' => '',
            'addressLine2' => '',
            'city' => 'Tasgaon',
            'state' => 'Maharashtra',
            'stateCode' => '27',
            'pincode' => '',
            'isGstRegistered' => false,
            'gstin' => '',
            'defaultInvoiceType' => 'gst',
            'gstInvoicePrefix' => 'SMW-GST-',
            'nongstInvoicePrefix' => 'SMW-BILL-',
            'returnPolicy' => 'Returns or exchanges accepted within 7 days with original bill and tags intact.',
            'invoiceFooter' => 'Thank you for shopping with साम्राज्य.07 — The perfect men\'s shop. Visit again!',
            'upiId' => '',
            'createdAt' => date('c'),
            'updatedAt' => date('c'),
        ]);
    }

    // Check if default admin account exists in local store
    $defaultAdmin = $db->get('admins', 'admin_master');
    if (!$defaultAdmin) {
        $db->set('admins', 'admin_master', [
            'uid' => 'admin_master',
            'email' => 'admin@samrajyamenswear.com',
            'displayName' => 'Samrajya 07 Admin',
            'role' => 'superadmin',
            // Default secure hash for samrajyamenswea07
            'passwordHash' => password_hash('samrajyamenswea07', PASSWORD_BCRYPT),
            'isActive' => true,
            'createdAt' => date('c')
        ]);
    }
}

// Run seeder
seed_initial_data_if_needed();
