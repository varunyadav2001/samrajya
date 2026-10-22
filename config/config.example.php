<?php
/**
 * Samrajya Mens Wear - Configuration Example
 * Copy this file to config.php and update with your actual credentials.
 */

return [
    'app' => [
        'name' => 'साम्राज्य.07 — The perfect men\'s shop',
        'short_name' => 'साम्राज्य.07',
        'tagline' => "The perfect men's shop",
        'env' => 'production',
        'url' => 'https://samrajya-bice.vercel.app',
        'session_lifetime' => 86400, // 24 hours
        'timezone' => 'Asia/Kolkata',
    ],

    // Shop and Invoice Details (Configurable, verify before production)
    'shop' => [
        'name' => 'साम्राज्य.07',
        'english_name' => 'Samrajya.07',
        'tagline' => "The perfect men's shop",
        'phone' => '',
        'alt_phone' => '',
        'email' => 'admin@samrajyamenswear.com',
        'address_line1' => '',
        'address_line2' => '',
        'city' => 'Tasgaon',
        'state' => 'Maharashtra',
        'state_code' => '27',
        'pincode' => '',
        
        // GST Details
        'is_gst_registered' => false,
        'gstin' => '',
        'default_invoice_type' => 'nongst',
        
        // Invoice numbering prefixes
        'gst_invoice_prefix' => 'SMW-GST-',
        'nongst_invoice_prefix' => 'SMW-BILL-',
        'return_prefix' => 'SMW-RET-',
        
        // Payment & Terms
        'upi_id' => '',
        'currency_symbol' => '₹',
        'currency_code' => 'INR',
        'return_policy' => 'Returns or exchanges accepted within 7 days with original bill and tags intact.',
        'invoice_footer' => 'Thank you for shopping with साम्राज्य.07. Visit again!',
    ],

    // Firebase Web Client Configuration (Used for Firebase Auth on login.php)
    'firebase_client' => [
        'apiKey' => 'AIzaSyDZgH-8ZNLuN1k3iosntJsCNogo5zjQ93w',
        'authDomain' => 'samrajya-f81ae.firebaseapp.com',
        'projectId' => 'samrajya-f81ae',
        'storageBucket' => 'samrajya-f81ae.firebasestorage.app',
        'messagingSenderId' => '1073715276397',
        'appId' => '1:1073715276397:web:c1024911c9335c50032d64',
        'measurementId' => 'G-K24C07FJBM',
    ],

    // Server-Side Firebase Admin Configuration
    'firebase_admin' => [
        'service_account_path' => __DIR__ . '/firebase-service-account.json',
        'project_id' => 'samrajya-f81ae',
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
