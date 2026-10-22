<?php
/**
 * Samrajya.07 - Public Customer Digital Bill View
 * Authentic Retail GST Tax Invoice & POS Receipt
 * Accessible to customers via WhatsApp or SMS share link (No login required).
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';

$id = trim($_GET['id'] ?? '');
$no = trim($_GET['no'] ?? '');

$db = db();
$invoice = null;

if (!empty($id)) {
    $invoice = $db->get('invoices', $id);
} elseif (!empty($no)) {
    $found = $db->query('invoices', function($inv) use ($no) {
        return ($inv['invoiceNumber'] ?? '') === $no;
    });
    if (!empty($found)) {
        $invoice = $found[0];
    }
}

if (!$invoice) {
    ?>
    <!DOCTYPE html>
    <html lang="mr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Invoice Not Found — साम्राज्य.07</title>
        <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body style="background:#f1f5f9; display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; font-family:sans-serif;">
        <div style="background:#fff; border-radius:12px; padding:40px; text-align:center; max-width:440px; box-shadow:0 4px 20px rgba(0,0,0,0.08); border:1px solid #e2e8f0;">
            <img src="assets/images/logo.png" alt="साम्राज्य.07" style="width:76px; height:76px; border-radius:50%; margin:0 auto 16px; border:2px solid #f59e0b;">
            <h3 style="color:#0f392b; font-size:18px; margin-bottom:8px;">बिल सापडले नाही (Invoice Not Found)</h3>
            <p style="color:#64748b; font-size:13px; line-height:1.5;">आपण दिलेली लिंक चुकीची असू शकते किंवा बिल क्रमांक अस्तित्वात नाही. कृपया दुकानदाराशी संपर्क साधा.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$settings = $db->get('settings', 'store_config') ?? [];
$isGst = ($invoice['invoiceType'] ?? 'gst') === 'gst';
$customer = $invoice['customer'] ?? [];
$items = $invoice['items'] ?? [];
$grandTotalWords = number_to_indian_words((float)($invoice['grandTotal'] ?? 0));

$shopName = !empty($settings['shopName']) ? $settings['shopName'] : 'साम्राज्य.07';
$tagline = $settings['tagline'] ?? "";
$address1 = $settings['addressLine1'] ?? '';
$city = $settings['city'] ?? '';
$state = $settings['state'] ?? '';
$stateCode = $settings['stateCode'] ?? '27';
$pincode = $settings['pincode'] ?? '';
$phone = $settings['phone'] ?? '';
$email = $settings['email'] ?? '';
$gstin = $settings['gstin'] ?? '';
$upiId = $settings['upiId'] ?? '';
$returnPolicy = $settings['returnPolicy'] ?? 'Returns or exchanges accepted within 7 days with original bill and tags intact.';

$addrParts = array_filter([$address1, $city, ($state ? "{$state}" . ($pincode ? " - {$pincode}" : "") : $pincode)]);
$fullAddress = implode(', ', $addrParts);

// Base public URL for sharing (auto-detects LAN Wi-Fi IP for seamless mobile access)
$publicBase = get_public_base_url();
$publicBillUrl = "{$publicBase}/bill.php?id=" . urlencode($invoice['invoiceId'] ?? '');

// Build full itemized WhatsApp bill message
$custName = !empty($customer['name']) ? $customer['name'] : 'Walk-in Customer';
$invDateFormatted = date('d-m-Y, h:i A', strtotime($invoice['invoiceDate']));
$grandTotalFormatted = number_format((float)($invoice['grandTotal'] ?? 0), 2);
$subtotalFormatted = number_format((float)($invoice['subtotal'] ?? 0), 2);
$discountFormatted = number_format((float)($invoice['discountTotal'] ?? 0), 2);
$taxFormatted = number_format((float)($invoice['taxTotal'] ?? 0), 2);

$waMessage = "👑 *{$shopName} — {$tagline}*\n";
$waMessage .= "📍 {$fullAddress}\n";
$waMessage .= "📞 मो.: {$phone}\n";
if ($isGst && !empty($gstin)) {
    $waMessage .= "🏷️ GSTIN: {$gstin} | State Code: {$stateCode}\n";
}
$waMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
$waMessage .= "🧾 *" . ($isGst ? "टॅक्स इनव्हॉइस (TAX INVOICE)" : "सेल्स बिल (RETAIL SALES BILL)") . "*\n";
$waMessage .= "बिल क्र.: *{$invoice['invoiceNumber']}*\n";
$waMessage .= "दिनांक: {$invDateFormatted}\n";
$waMessage .= "ग्राहक: *{$custName}*" . (!empty($customer['phone']) ? " ({$customer['phone']})" : "") . "\n";
$waMessage .= "पेमेंट: *" . strtoupper($invoice['paymentMethod'] ?? 'CASH') . " (PAID)*\n";
$waMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
$waMessage .= "🛍️ *खरेदी तपशील (Items):*\n";

$i = 1;
foreach ($items as $item) {
    $pName = $item['productName'] ?? 'Item';
    $size = !empty($item['size']) ? " [{$item['size']}]" : "";
    $qty = (int)($item['quantity'] ?? 1);
    $rate = number_format((float)($item['unitPrice'] ?? 0), 2);
    $tot = number_format((float)($item['totalAmount'] ?? 0), 2);
    $waMessage .= "{$i}. *{$pName}*{$size}\n";
    $waMessage .= "   {$qty} pcs × ₹{$rate} = *₹{$tot}*\n";
    $i++;
}

$waMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
$waMessage .= "सबटोटल (Subtotal): ₹{$subtotalFormatted}\n";
if ((float)($invoice['discountTotal'] ?? 0) > 0) {
    $waMessage .= "सूट (Discount): - ₹{$discountFormatted}\n";
}
if ($isGst && (float)($invoice['taxTotal'] ?? 0) > 0) {
    $waMessage .= "जीएसटी (CGST + SGST): ₹{$taxFormatted}\n";
}
$waMessage .= "💰 *एकूण देय रक्कम: ₹{$grandTotalFormatted}*\n";
$waMessage .= "अक्षरी: *{$grandTotalWords}*\n";
$waMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
$waMessage .= "🌐 *आपले मूळ डिजिटल बिल पाहण्यासाठी खालील लिंक उघडा:*\n";
$waMessage .= "👉 {$publicBillUrl}\n\n";
$waMessage .= "🙏 साम्राज्य.07 मध्ये खरेदी केल्याबद्दल धन्यवाद! पुन्हा नक्की भेट द्या! ✨";

$cleanPhone = preg_replace('/[^0-9]/', '', $customer['phone'] ?? '');
if (strlen($cleanPhone) === 10) {
    $cleanPhone = '91' . $cleanPhone;
}
$waDirectUrl = $cleanPhone
    ? "https://wa.me/{$cleanPhone}?text=" . rawurlencode($waMessage)
    : "https://api.whatsapp.com/send?text=" . rawurlencode($waMessage);
?>
<!DOCTYPE html>
<html lang="mr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill <?= htmlspecialchars($invoice['invoiceNumber']) ?> — <?= htmlspecialchars($shopName) ?></title>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Yantramanav:wght@500;700;900&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Scripts for QR code and High-Res Image Snapshot -->
    <script src="assets/js/qrcode.min.js"></script>
    <script src="assets/js/html2canvas.min.js"></script>
    
    <style>
        :root {
            --brand-green: #0f392b;
            --brand-gold: #f59e0b;
            --slate-border: #cbd5e1;
            --slate-header: #f8fafc;
        }
        body {
            background-color: #eef2f6;
            margin: 0;
            padding: 20px 10px 40px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #0f172a;
        }
        .bill-container {
            max-width: 820px;
            margin: 0 auto;
        }
        
        /* Floating / Sticky Actions Bar */
        .actions-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .actions-btn-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn-wa-share {
            background-color: #25D366;
            color: #ffffff;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13.5px;
            box-shadow: 0 2px 8px rgba(37, 211, 102, 0.35);
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-wa-share:hover {
            background-color: #1eb857;
            transform: translateY(-1px);
            color: #ffffff;
        }
        .btn-action {
            background-color: #ffffff;
            color: #334155;
            border: 1px solid #cbd5e1;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 14px;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-action:hover {
            background-color: #f8fafc;
            border-color: #94a3b8;
            color: #0f172a;
        }
        .btn-image-share {
            background-color: #0f392b;
            color: #ffffff;
            border: 1px solid #0f392b;
        }
        .btn-image-share:hover {
            background-color: #1b5e48;
            color: #ffffff;
        }

        /* Printable Invoice Card */
        .invoice-card {
            background: #ffffff;
            border: 2px solid #0f392b;
            border-radius: 8px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            padding: 28px 32px;
            margin: 0 auto;
            position: relative;
            background-color: #ffffff;
        }

        /* Invoice Header */
        .inv-top-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .shop-brand-name {
            font-family: 'Yantramanav', 'Plus Jakarta Sans', sans-serif;
            font-size: 26px;
            font-weight: 900;
            color: #0f392b;
            margin: 0;
            line-height: 1.1;
            letter-spacing: 0.5px;
        }
        .shop-sub-title {
            font-size: 13px;
            font-weight: 700;
            color: #b45309;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin: 2px 0 6px;
        }
        .shop-info-text {
            font-size: 11px;
            color: #475569;
            line-height: 1.45;
        }
        .gst-badge-pill {
            display: inline-block;
            background: #0f392b;
            color: #ffffff;
            font-weight: 800;
            font-size: 12px;
            padding: 4px 12px;
            border-radius: 4px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .invoice-title-band {
            background: #f8faf9;
            border-top: 1px solid #0f392b;
            border-bottom: 1px solid #0f392b;
            padding: 6px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .invoice-title-band h2 {
            margin: 0;
            font-size: 14px;
            font-weight: 800;
            color: #0f392b;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .invoice-title-band span {
            font-size: 11px;
            color: #64748b;
            font-weight: 600;
        }

        /* Party Details Grid */
        .party-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            margin-bottom: 16px;
            overflow: hidden;
        }
        .party-col {
            padding: 10px 14px;
        }
        .party-col:first-child {
            border-right: 1px solid #cbd5e1;
            background: #fdfdfd;
        }
        .party-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 800;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .party-name {
            font-size: 14.5px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 2px;
        }
        .party-sub {
            font-size: 11.5px;
            color: #475569;
            line-height: 1.4;
        }

        /* Line Items Table */
        .inv-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #cbd5e1;
            margin-bottom: 16px;
        }
        .inv-table th {
            background: #0f392b;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            text-align: left;
            padding: 8px 8px;
            text-transform: uppercase;
            border: 1px solid #0f392b;
        }
        .inv-table td {
            padding: 9px 8px;
            border: 1px solid #e2e8f0;
            font-size: 11.5px;
            vertical-align: middle;
        }
        .inv-table tbody tr:nth-child(even) td {
            background-color: #fbfcfd;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }

        /* Accounting & Totals Section */
        .summary-grid {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 16px;
            margin-bottom: 18px;
        }
        .words-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 12px;
            color: #166534;
            margin-bottom: 12px;
        }
        .qr-pay-box {
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 12px;
            background: #fafbfb;
        }
        .calc-box {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: #fafbfb;
            overflow: hidden;
        }
        .calc-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 12px;
            font-size: 11.5px;
            color: #334155;
            border-bottom: 1px dashed #e2e8f0;
        }
        .calc-row.grand-row {
            background: #0f392b;
            color: #ffffff;
            font-size: 15px;
            font-weight: 800;
            padding: 9px 12px;
            border: none;
        }

        /* Policy & Signatory */
        .footer-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 20px;
            padding-top: 14px;
            border-top: 1px solid #cbd5e1;
        }
        .terms-text {
            font-size: 10px;
            color: #64748b;
            line-height: 1.5;
        }
        .sign-box {
            text-align: right;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: flex-end;
        }
        .sign-stamp {
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: cursive;
            font-size: 16px;
            color: #0f392b;
            opacity: 0.85;
            margin-bottom: 4px;
        }
        .sign-title {
            font-size: 11px;
            font-weight: 700;
            color: #0f392b;
            border-top: 1px solid #94a3b8;
            padding-top: 4px;
            min-width: 170px;
            text-align: center;
        }

        /* Mobile Responsiveness for Smart Phones */
        @media (max-width: 640px) {
            body {
                padding: 10px 8px 30px;
            }
            .actions-card {
                padding: 12px;
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            .actions-btn-group {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }
            .btn-wa-share {
                grid-column: 1 / -1;
                justify-content: center;
                padding: 11px;
            }
            .btn-action {
                justify-content: center;
                padding: 9px;
            }
            .invoice-card {
                padding: 16px 12px;
                border-radius: 8px;
                border-width: 1px;
            }
            .inv-top-table,
            .inv-top-table tbody,
            .inv-top-table tr,
            .inv-top-table td {
                display: block !important;
                width: 100% !important;
                text-align: left !important;
                padding: 0 !important;
            }
            .inv-top-table tr {
                display: flex !important;
                flex-direction: column !important;
                gap: 10px !important;
            }
            .inv-top-table td:first-child {
                text-align: center !important;
            }
            .inv-top-table td:first-child img {
                margin: 0 auto;
            }
            .inv-top-table td:nth-child(2) {
                text-align: center !important;
                padding-left: 0 !important;
            }
            .inv-top-table td:last-child {
                text-align: center !important;
                border-top: 1px dashed #cbd5e1;
                padding-top: 8px !important;
            }
            .invoice-title-band {
                flex-direction: column;
                gap: 4px;
                text-align: center;
            }
            .party-grid {
                grid-template-columns: 1fr !important;
            }
            .party-col:first-child {
                border-right: none !important;
                border-bottom: 1px solid #cbd5e1 !important;
            }
            .summary-grid {
                grid-template-columns: 1fr !important;
                gap: 12px !important;
            }
            .footer-grid {
                grid-template-columns: 1fr !important;
                gap: 14px !important;
            }
            .sign-box {
                align-items: center !important;
                text-align: center !important;
            }
        }

        /* Print Mode */
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; background: #fff; }
            .invoice-card {
                border: 1px solid #000;
                box-shadow: none;
                padding: 15px;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="bill-container">

    <!-- Top Action Toolbar (No-Print) -->
    <div class="actions-card no-print">
        <div style="display:flex; align-items:center; gap:10px;">
            <img src="assets/images/logo.png" alt="Logo" style="width:36px; height:36px; border-radius:50%; border:1px solid #f59e0b;">
            <div>
                <div style="font-weight:800; font-size:14px; color:#0f392b;"><?= htmlspecialchars($invoice['invoiceNumber']) ?></div>
                <div style="font-size:11px; color:#64748b;">डिजिटल बिल पावती (Digital Invoice)</div>
            </div>
        </div>

        <div class="actions-btn-group">
            <!-- 1. Send Full Bill directly to WhatsApp -->
            <button type="button" class="btn-wa-share" onclick="shareOnWhatsApp()">
                <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                <span>WhatsApp वर पाठवा</span>
            </button>

            <!-- 2. Download / Share Digital Bill as Image (PNG) -->
            <button type="button" class="btn-action btn-image-share" onclick="downloadBillImage()" id="btnBillImg">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span>बिल फोटो (PNG)</span>
            </button>

            <!-- 3. Official PDF Download -->
            <a href="api/invoices.php?action=pdf&id=<?= urlencode($invoice['invoiceId']) ?>&download=1" class="btn-action">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                PDF
            </a>

            <!-- 4. Direct Print -->
            <button type="button" class="btn-action" onclick="window.print()">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print
            </button>

            <!-- 5. Copy Link -->
            <button type="button" class="btn-action" onclick="copyBillLink()">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                Copy
            </button>
        </div>
    </div>

    <!-- The Complete Proper Invoice Document -->
    <div class="invoice-card" id="invoiceCard">
        
        <!-- Store Header Table -->
        <table class="inv-top-table">
            <tr>
                <td style="width: 80px; vertical-align: middle;">
                    <img src="assets/images/logo.png" alt="साम्राज्य.07" style="width:74px; height:74px; border-radius:50%; border:2px solid #f59e0b; background:#000;">
                </td>
                <td style="vertical-align: middle; padding-left: 14px;">
                    <h1 class="shop-brand-name"><?= htmlspecialchars($shopName) ?></h1>
                    <div class="shop-sub-title"><?= htmlspecialchars($tagline) ?></div>
                    <div class="shop-info-text">
                        <?= htmlspecialchars($fullAddress) ?><br>
                        <strong>मोबाईल:</strong> <?= htmlspecialchars($phone) ?> &bull; <strong>ईमेल:</strong> <?= htmlspecialchars($email) ?><br>
                        <?php if ($isGst && !empty($gstin)): ?>
                            <strong>GSTIN:</strong> <?= htmlspecialchars($gstin) ?> &bull; <strong>State Code:</strong> <?= htmlspecialchars($stateCode) ?> (Maharashtra)
                        <?php endif; ?>
                    </div>
                </td>
                <td style="text-align: right; vertical-align: top; width: 220px;">
                    <div class="gst-badge-pill"><?= $isGst ? 'GST TAX INVOICE' : 'RETAIL SALES BILL' ?></div>
                    <div style="font-size: 10px; color: #64748b; margin-top: 4px; font-weight: 600;">
                        <?= $isGst ? '(Original for Recipient)' : '(Retail Cash Memo)' ?>
                    </div>
                    <div style="margin-top: 8px;">
                        <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700;">Invoice Number</div>
                        <div style="font-size: 16px; font-weight: 800; color: #0f392b;"><?= htmlspecialchars($invoice['invoiceNumber']) ?></div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Invoice Title Band -->
        <div class="invoice-title-band">
            <h2><?= $isGst ? 'Tax Invoice' : 'Retail Sales Receipt' ?></h2>
            <span>Date & Time: <strong><?= date('d-m-Y, h:i A', strtotime($invoice['invoiceDate'])) ?></strong></span>
        </div>

        <!-- Party & Bill Details -->
        <div class="party-grid">
            <div class="party-col">
                <div class="party-label">Billed To / ग्राहक माहिती:</div>
                <div class="party-name"><?= htmlspecialchars($customer['name'] ?? 'Walk-in Customer') ?></div>
                <div class="party-sub">
                    <strong>मोबाईल:</strong> <?= htmlspecialchars($customer['phone'] ?? '-') ?><br>
                    <strong>पत्ता:</strong> <?= htmlspecialchars($customer['address'] ?? 'Counter Sale, Store Pickup') ?><br>
                    <?php if (!empty($customer['gstin'])): ?>
                        <strong>ग्राहक GSTIN:</strong> <?= htmlspecialchars($customer['gstin']) ?><br>
                    <?php endif; ?>
                    <strong>Place of Supply:</strong> Maharashtra (27)
                </div>
            </div>
            <div class="party-col" style="background:#fafbfb;">
                <div class="party-label">Invoice & Payment Summary / बिल तपशील:</div>
                <div class="party-sub" style="font-size: 12px; line-height: 1.6;">
                    <strong>बिल क्र.:</strong> <?= htmlspecialchars($invoice['invoiceNumber']) ?><br>
                    <strong>दिनांक:</strong> <?= date('d M Y, h:i A', strtotime($invoice['invoiceDate'])) ?><br>
                    <strong>पेमेंट मोड:</strong> <span style="font-weight:700; color:#059669;"><?= strtoupper(htmlspecialchars($invoice['paymentMethod'] ?? 'CASH')) ?> (PAID)</span><br>
                    <strong>Billed By:</strong> <?= htmlspecialchars($invoice['createdBy'] ?? 'Samrajya Admin') ?>
                </div>
            </div>
        </div>

        <!-- Line Items Table -->
        <div style="width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; margin-bottom:16px;">
        <table class="inv-table" style="min-width: 560px; margin-bottom: 0;">
            <thead>
                <tr>
                    <th style="width:30px;" class="text-center">#</th>
                    <th>वस्तूचे नाव व तपशील (Item Description)</th>
                    <?php if ($isGst): ?><th style="width:65px;" class="text-center">HSN</th><?php endif; ?>
                    <th style="width:55px;" class="text-center">Size</th>
                    <th style="width:45px;" class="text-center">Qty</th>
                    <th style="width:75px;" class="text-right">दर (Rate)</th>
                    <th style="width:65px;" class="text-right">सूट (Disc)</th>
                    <?php if ($isGst): ?>
                        <th style="width:75px;" class="text-right">Taxable</th>
                        <th style="width:50px;" class="text-center">GST</th>
                    <?php endif; ?>
                    <th style="width:85px;" class="text-right">एकूण (Total)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sr = 1;
                $totalPieces = 0;
                foreach ($items as $item): 
                    $qty = (int)($item['quantity'] ?? 1);
                    $totalPieces += $qty;
                ?>
                    <tr>
                        <td class="text-center" style="font-weight:600; color:#64748b;"><?= $sr++ ?></td>
                        <td>
                            <strong style="color:#0f392b; font-size:12.5px;"><?= htmlspecialchars($item['productName']) ?></strong>
                            <div style="font-size:10px; color:#64748b; margin-top:1px;">
                                SKU: <?= htmlspecialchars($item['sku'] ?? '-') ?> &bull; 
                                Color: <?= htmlspecialchars($item['color'] ?? '-') ?>
                                <?php if (!empty($item['brand'])): ?> &bull; Brand: <?= htmlspecialchars($item['brand']) ?><?php endif; ?>
                            </div>
                        </td>
                        <?php if ($isGst): ?>
                            <td class="text-center" style="font-family:monospace;"><?= htmlspecialchars($item['hsnCode'] ?? '6205') ?></td>
                        <?php endif; ?>
                        <td class="text-center">
                            <span style="display:inline-block; padding:1px 6px; background:#f1f5f9; border-radius:3px; font-weight:700; font-size:10.5px;">
                                <?= htmlspecialchars($item['size'] ?? 'Free') ?>
                            </span>
                        </td>
                        <td class="text-center" style="font-weight:700; font-size:12px;"><?= $qty ?></td>
                        <td class="text-right"><?= format_currency($item['unitPrice']) ?></td>
                        <td class="text-right" style="color:<?= ($item['discountAmount'] ?? 0) > 0 ? '#059669' : '#94a3b8' ?>;">
                            <?= ($item['discountAmount'] ?? 0) > 0 ? ('- ' . format_currency($item['discountAmount'])) : '0.00' ?>
                        </td>
                        <?php if ($isGst): ?>
                            <td class="text-right"><?= format_currency($item['taxableValue'] ?? $item['totalAmount']) ?></td>
                            <td class="text-center" style="font-size:10.5px;"><?= (int)($item['gstRate'] ?? 0) ?>%</td>
                        <?php endif; ?>
                        <td class="text-right" style="font-weight:800; color:#0f392b; font-size:12.5px;">
                            <?= format_currency($item['totalAmount']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Calculations & Summary Grid -->
        <div class="summary-grid">
            <div>
                <!-- Amount in Words -->
                <div class="words-box">
                    <span style="font-size:10.5px; font-weight:700; text-transform:uppercase; color:#166534; display:block;">Amount in Words / अक्षरी रक्कम:</span>
                    <span style="font-size:12.5px; font-weight:800;">INR <?= htmlspecialchars($grandTotalWords) ?></span>
                </div>

                <!-- UPI QR Code for instant payment verification -->
                <div class="qr-pay-box">
                    <div id="billQrCode" style="width:72px; height:72px; background:#fff; border:1px solid #cbd5e1; border-radius:4px; padding:2px; display:flex; align-items:center; justify-content:center;"></div>
                    <div>
                        <div style="font-size:11.5px; font-weight:800; color:#0f392b;">UPI Payment & Verification QR</div>
                        <div style="font-size:10px; color:#64748b; line-height:1.35; margin-top:2px;">
                            UPI: <strong><?= htmlspecialchars($upiId) ?></strong><br>
                            कोणत्याही UPI अ‍ॅपवरून (GPay, PhonePe, Paytm) स्कॅन करा व पावती तपासा.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Totals Box -->
            <div class="calc-box">
                <div class="calc-row">
                    <span>एकूण वस्तू (Items & Qty):</span>
                    <strong><?= count($items) ?> items (<?= $totalPieces ?> pcs)</strong>
                </div>
                <div class="calc-row">
                    <span>सबटोटल (Subtotal):</span>
                    <span><?= format_currency($invoice['subtotal']) ?></span>
                </div>
                <?php if ((float)($invoice['discountTotal'] ?? 0) > 0): ?>
                    <div class="calc-row" style="color:#059669;">
                        <span>सूट (Total Discount):</span>
                        <span>- <?= format_currency($invoice['discountTotal']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($isGst): ?>
                    <div class="calc-row">
                        <span>करपात्र मूल्य (Taxable Value):</span>
                        <span><?= format_currency($invoice['taxableTotal'] ?? $invoice['subtotal']) ?></span>
                    </div>
                    <?php if ((float)($invoice['cgstTotal'] ?? 0) > 0): ?>
                        <div class="calc-row">
                            <span>CGST:</span>
                            <span><?= format_currency($invoice['cgstTotal']) ?></span>
                        </div>
                        <div class="calc-row">
                            <span>SGST:</span>
                            <span><?= format_currency($invoice['sgstTotal']) ?></span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ((float)($invoice['roundOff'] ?? 0) != 0): ?>
                    <div class="calc-row">
                        <span>Round Off:</span>
                        <span><?= ((float)$invoice['roundOff'] > 0 ? '+ ' : '') . format_currency($invoice['roundOff']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="calc-row grand-row">
                    <span>एकूण देय रक्कम (Grand Total):</span>
                    <span><?= format_currency($invoice['grandTotal']) ?></span>
                </div>
            </div>
        </div>

        <!-- Terms & Conditions + Authorized Signatory -->
        <div class="footer-grid">
            <div>
                <div style="font-size:11px; font-weight:800; color:#0f392b; margin-bottom:4px;">नियम व अटी (Terms & Conditions):</div>
                <div class="terms-text">
                    1. खरेदी केलेले कपडे <strong>७ दिवसांच्या आत</strong> ओरिजिनल बिल व टॅगसह बदलून मिळतील (Exchange only).<br>
                    2. अल्टर केलेले, वापरलेले किंवा धुतलेले कपडे बदलून मिळणार नाहीत.<br>
                    3. कोणत्याही परिस्थितीत रोख परतावा (No Cash Refund) दिला जाणार नाही.
                </div>
            </div>
            <div class="sign-box">
                <div style="font-size:11.5px; font-weight:800; color:#0f392b;">For <?= htmlspecialchars($shopName) ?></div>
                <div class="sign-stamp">
                    <span style="transform:rotate(-4deg); display:inline-block; border:1.5px dashed #0f392b; padding:2px 10px; border-radius:4px; font-size:11px; font-weight:bold; letter-spacing:1px;">
                        ✓ VERIFIED BILL
                    </span>
                </div>
                <div class="sign-title">Authorized Signatory</div>
            </div>
        </div>

        <div style="text-align:center; font-size:11px; color:#64748b; margin-top:20px; padding-top:12px; border-top:1px dashed #cbd5e1; font-weight:600;">
            <?= htmlspecialchars($settings['invoiceFooter'] ?? 'साम्राज्य.07 — The perfect men\'s shop मध्ये खरेदी केल्याबद्दल धन्यवाद! पुन्हा नक्की भेट द्या! 🙏') ?>
        </div>
    </div>
</div>

<div id="toastContainer" style="position:fixed; bottom:20px; right:20px; z-index:99999;"></div>

<script>
// Generate dynamic UPI QR Code on load
document.addEventListener('DOMContentLoaded', () => {
    try {
        const upiString = "upi://pay?pa=<?= urlencode($upiId) ?>&pn=<?= urlencode($shopName) ?>&am=<?= number_format((float)($invoice['grandTotal'] ?? 0), 2, '.', '') ?>&cu=INR&tr=<?= urlencode($invoice['invoiceNumber']) ?>";
        const qrContainer = document.getElementById('billQrCode');
        if (qrContainer && typeof QRCode !== 'undefined') {
            new QRCode(qrContainer, {
                text: upiString,
                width: 66,
                height: 66,
                colorDark: "#0f392b",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.M
            });
        }
    } catch(e) {
        console.warn('QR Code generation error:', e);
    }
});

// Toast notification helper
function showToast(msg, type = 'success') {
    const box = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.style.cssText = `background:${type === 'success' ? '#0f392b' : '#dc2626'}; color:#fff; padding:12px 20px; border-radius:8px; margin-top:8px; box-shadow:0 4px 12px rgba(0,0,0,0.2); font-size:13px; font-weight:700; transition:all 0.3s;`;
    toast.innerText = msg;
    box.appendChild(toast);
    setTimeout(() => { toast.remove(); }, 4000);
}

// 1. WhatsApp Full Digital Bill Share
function shareOnWhatsApp() {
    let customerPhone = '<?= htmlspecialchars($customer['phone'] ?? '') ?>';
    const waText = <?= json_encode($waMessage) ?>;
    
    // If no phone exists, prompt user or open standard WhatsApp picker
    let targetUrl = '<?= $waDirectUrl ?>';
    
    if (!customerPhone || customerPhone.trim() === '') {
        const inputPhone = prompt('ग्राहकाचा १० अंकी व्हॉट्सअ‍ॅप नंबर प्रविष्ट करा (किंवा थेट शेअर करण्यासाठी ओके करा):', '');
        if (inputPhone !== null) {
            const clean = inputPhone.replace(/[^0-9]/g, '');
            if (clean.length === 10) {
                targetUrl = 'https://wa.me/91' + clean + '?text=' + encodeURIComponent(waText);
            } else if (clean.length > 10) {
                targetUrl = 'https://wa.me/' + clean + '?text=' + encodeURIComponent(waText);
            } else {
                targetUrl = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(waText);
            }
        } else {
            return;
        }
    }
    
    window.open(targetUrl, '_blank');
}

// 2. Download or Share Bill as High-Resolution PNG Image
async function downloadBillImage() {
    const btn = document.getElementById('btnBillImg');
    const originalText = btn.innerHTML;
    btn.innerHTML = `<span style="display:inline-block; animation:spin 1s linear infinite;">⏳</span> इमेज तयार होत आहे...`;
    btn.disabled = true;

    try {
        const card = document.getElementById('invoiceCard');
        
        // Render with html2canvas at 2x scale for ultra crisp text
        const canvas = await html2canvas(card, {
            scale: 2,
            useCORS: true,
            backgroundColor: '#ffffff',
            logging: false
        });

        // Check if Web Share API with files is supported (e.g. mobile Chrome)
        canvas.toBlob(async (blob) => {
            const invNo = '<?= htmlspecialchars($invoice['invoiceNumber']) ?>';
            const fileName = `Bill-${invNo}.png`;
            const file = new File([blob], fileName, { type: 'image/png' });

            if (navigator.canShare && navigator.canShare({ files: [file] })) {
                try {
                    await navigator.share({
                        files: [file],
                        title: `साम्राज्य.07 — बिल ${invNo}`,
                        text: `👑 साम्राज्य.07 — The perfect men's shop\nबिल क्र.: ${invNo}`
                    });
                    showToast('बिल इमेज थेट शेअर केली!');
                    return;
                } catch (shareErr) {
                    // Fallback to direct download if user cancelled share sheet
                }
            }

            // Fallback: Trigger direct PNG download
            const imageURL = canvas.toDataURL('image/png');
            const link = document.createElement('a');
            link.href = imageURL;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            showToast('बिल फोटो डाऊनलोड झाला! आपण हा फोटो थेट व्हॉट्सअ‍ॅपवर पाठवू शकता. 📸');
        }, 'image/png');

    } catch (err) {
        console.error('Image capture failed:', err);
        showToast('इमेज तयार करताना त्रुटी आली.', 'error');
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

// 3. Copy Link
function copyBillLink() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        showToast('डिजिटल बिल लिंक कॉपी झाली! 📋');
    }).catch(() => {
        prompt('Copy bill link:', window.location.href);
    });
}
</script>

</body>
</html>
