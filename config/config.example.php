<?php
/**
 * Samrajya Mens Wear - Configuration
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

    // Shop and Invoice Details
    'shop' => [
        'name' => 'साम्राज्य.07',
        'english_name' => 'Samrajya.07',
        'tagline' => "The perfect men's shop",
        'phone' => '+91 98765 43210',
        'alt_phone' => '',
        'email' => 'contact@samrajya07.com',
        'address_line1' => 'Shop No. 07, Main Market Road',
        'address_line2' => '',
        'city' => 'Kolhapur',
        'state' => 'Maharashtra',
        'state_code' => '27',
        'pincode' => '416001',
        
        // GST Details
        'is_gst_registered' => true,
        'gstin' => '27AAAAA0000A1Z5',
        'default_invoice_type' => 'gst',
        
        // Invoice numbering prefixes
        'gst_invoice_prefix' => 'SMW-GST-',
        'nongst_invoice_prefix' => 'SMW-BILL-',
        'return_prefix' => 'SMW-RET-',
        
        // Payment & Terms
        'upi_id' => 'samrajya07@upi',
        'currency_symbol' => '₹',
        'currency_code' => 'INR',
        'return_policy' => 'Returns or exchanges accepted within 7 days with original bill and tags intact. No exchange on altered garments.',
        'invoice_footer' => "Thank you for shopping with साम्राज्य.07 — The perfect men's shop. Visit again!",
    ],

    // Firebase Web Client Configuration (Used for Firebase Auth on client login & Analytics)
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

    // Admin Access Control
    'admin_auth' => [
        'allowed_emails' => [
            'admin@samrajyamenswear.com',
            'owner@samrajya.com',
        ],
        'allowed_uids' => [],
    ],
];
