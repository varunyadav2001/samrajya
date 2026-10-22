<?php
/**
 * Samrajya Mens Wear - Print-friendly Invoice View
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/pdf-generator.php';
// Allows printing if valid invoice ID is provided

$id = trim($_GET['id'] ?? '');
if (!$id) {
    die('Invoice ID required');
}

$db = db();
$invoice = $db->get('invoices', $id);
if (!$invoice) {
    die('Invoice not found');
}

$settings = $db->get('settings', 'store_config') ?? [];
$html = InvoicePdfGenerator::generateHtml($invoice, $settings);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="format-detection" content="telephone=no">
    <title>Print <?= htmlspecialchars($invoice['invoiceNumber']) ?> — Samrajya Mens Wear</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <link rel="apple-touch-icon" href="assets/images/logo.png">
    <style>
        @media (max-width: 640px) {
            .print-toolbar {
                position: static !important;
                margin: 10px;
                justify-content: center;
            }
            .invoice-wrapper {
                margin: 10px !important;
                padding: 14px 10px !important;
                overflow-x: auto;
            }
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                margin: 0;
                padding: 0;
                background: #fff;
            }
        }
        .print-toolbar {
            position: fixed;
            top: 15px;
            right: 15px;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(4px);
            padding: 8px 14px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
            display: flex;
            gap: 10px;
            z-index: 9999;
        }
        .toolbar-btn {
            background: #0f392b;
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: sans-serif;
        }
        .toolbar-btn:hover {
            background: #164e3b;
        }
        .invoice-wrapper {
            max-width: 800px;
            margin: 40px auto;
            background: #fff;
            padding: 25px 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        @media print {
            .invoice-wrapper {
                margin: 0;
                padding: 0;
                box-shadow: none;
                border: none;
                max-width: 100%;
            }
        }
    </style>
</head>
<body style="background:#f1f5f9; font-family:sans-serif; margin:0; padding:0;">

<div class="print-toolbar no-print">
    <button class="toolbar-btn" onclick="window.print()">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Print Invoice
    </button>
    <a href="api/invoices.php?action=pdf&id=<?= urlencode($id) ?>&download=1" class="toolbar-btn">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Download PDF
    </a>
    <a href="billing.php" class="toolbar-btn" style="background:#475569;">
        Back to POS
    </a>
</div>

<div class="invoice-wrapper">
    <?= $html ?>
</div>

</body>
</html>
