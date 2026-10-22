<?php
/**
 * Samrajya Mens Wear - Configuration Example
 * Copy this file to config.php and update with your actual credentials.
 */

return [
    'app' => [
        'name' => 'Samrajya Mens Wear',
        'short_name' => 'SMW',
        'tagline' => "The Royal Destination for Men's Fashion",
        'env' => 'development', // 'development' or 'production'
        'url' => 'http://localhost/samrajya',
        'session_lifetime' => 86400, // 24 hours
        'timezone' => 'Asia/Kolkata',
    ],

    // Shop and Invoice Details (Configurable, verify before production)
    'shop' => [
        'name' => 'Samrajya Mens Wear',
        'tagline' => "The Royal Destination for Men's Fashion",
        'phone' => '+91 98765 43210',
        'alt_phone' => '+91 91234 56789',
        'email' => 'sales@samrajyamenswear.com',
        'address_line1' => 'Shop No. 4 & 5, Royal Palace Commercial Hub',
        'address_line2' => 'Station Road, Near Central Plaza',
        'city' => 'Kolhapur',
        'state' => 'Maharashtra',
        'state_code' => '27',
        'pincode' => '416001',
        
        // GST Details (Set is_gst_registered to false if shop is unregistered)
        'is_gst_registered' => true,
        'gstin' => '27AAAAA0000A1Z5', // Replace with verified GSTIN
        'default_invoice_type' => 'gst', // 'gst' or 'nongst'
        
        // Invoice numbering prefixes
        'gst_invoice_prefix' => 'SMW-GST-',
        'nongst_invoice_prefix' => 'SMW-BILL-',
        'return_prefix' => 'SMW-RET-',
        
        // Payment & Terms
        'upi_id' => 'samrajya@okhdfcbank',
        'currency_symbol' => '₹',
        'currency_code' => 'INR',
        'return_policy' => 'Returns or exchanges accepted within 7 days with original bill and tags intact. No exchange on altered garments.',
        'invoice_footer' => 'Thank you for shopping with Samrajya Mens Wear. Visit again!',
    ],

    // Firebase Web Client Configuration (Used for Firebase Auth on login.php)
    'firebase_client' => [
        'apiKey' => 'AIzaSyExamplePlaceholderKeyHere',
        'authDomain' => 'samrajya-mens-wear.firebaseapp.com',
        'projectId' => 'samrajya-mens-wear',
        'storageBucket' => 'samrajya-mens-wear.appspot.com',
        'messagingSenderId' => '123456789012',
        'appId' => '1:123456789012:web:abcdef1234567890',
    ],

    // Server-Side Firebase Admin Configuration
    'firebase_admin' => [
        // Path to your downloaded service account JSON file from Firebase Console
        'service_account_path' => __DIR__ . '/firebase-service-account.json',
        'project_id' => 'samrajya-mens-wear',
    ],

    // Admin Access Control (Server-side authorization check)
    'admin_auth' => [
        // Allowed admin emails (case-insensitive)
        'allowed_emails' => [
            'admin@samrajyamenswear.com',
            'owner@samrajya.com',
        ],
        // Allowed admin UIDs (optional, matches Firebase Auth UID)
        'allowed_uids' => [],
    ],
];
