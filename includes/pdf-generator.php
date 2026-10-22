<?php
/**
 * Samrajya Mens Wear - Professional PDF Invoice Generator
 * Powered by Dompdf with crisp typography and Indian GST compliance.
 */

if (!defined('APP_ROOT')) {
    exit('Direct access not permitted');
}

use Dompdf\Dompdf;
use Dompdf\Options;

class InvoicePdfGenerator {
    
    /**
     * Map Marathi/Devanagari characters to clean Latin/English equivalents for flawless PDF rendering
     */
    public static function sanitizeForPdf(?string $text): string {
        if ($text === null || $text === '') return '';

        $marathiNumbers = ['०', '१', '२', '३', '४', '५', '६', '७', '८', '९'];
        $englishNumbers = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $text = str_replace($marathiNumbers, $englishNumbers, $text);

        $replacements = [
            'साम्राज्य.07' => 'Samrajya.07',
            'साम्राज्य' => 'Samrajya',
            'तासगाव' => 'Tasgaon',
            'महाराष्ट्र' => 'Maharashtra',
            'कोल्हापूर' => 'Kolhapur',
            'सांगली' => 'Sangli',
            'पुणे' => 'Pune',
            'मुंबई' => 'Mumbai',
            '₹' => 'Rs. '
        ];
        $text = str_replace(array_keys($replacements), array_values($replacements), $text);

        return trim($text);
    }

    /**
     * Generate HTML representation of invoice
     */
    public static function generateHtml(array $invoice, array $settings): string {
        $isGst = ($invoice['invoiceType'] ?? 'gst') === 'gst';
        $items = $invoice['items'] ?? [];
        $customer = $invoice['customer'] ?? [];
        $isInterstate = !empty($invoice['isInterstate']);

        $shopName = !empty($settings['englishName']) ? $settings['englishName'] : self::sanitizeForPdf($settings['shopName'] ?? 'Samrajya.07');
        if (empty($shopName)) $shopName = 'Samrajya.07';

        $tagline = !empty($settings['tagline']) ? $settings['tagline'] : "The perfect men's shop";
        $address1 = self::sanitizeForPdf($settings['addressLine1'] ?? '');
        $address2 = self::sanitizeForPdf($settings['addressLine2'] ?? '');
        $city = !empty($settings['city']) ? self::sanitizeForPdf($settings['city']) : 'Tasgaon';
        $state = !empty($settings['state']) ? self::sanitizeForPdf($settings['state']) : 'Maharashtra';
        $stateCode = $settings['stateCode'] ?? '27';
        $pincode = !empty($settings['pincode']) ? self::sanitizeForPdf($settings['pincode']) : '416312';
        $phone = $settings['phone'] ?? '';
        $email = $settings['email'] ?? '';
        $gstin = $settings['gstin'] ?? '';
        $returnPolicy = $settings['returnPolicy'] ?? 'Exchanges accepted within 7 days with original invoice & tags intact.';
        $footerNote = self::sanitizeForPdf($settings['invoiceFooter'] ?? "Thank you for shopping with Samrajya.07 — The perfect men's shop. Visit again!");

        $fullAddrStr = $city . ' (' . $state . ($pincode ? ' - ' . $pincode : '') . ')';
        if (!empty($address1) && stripos($address1, $city) === false && stripos($address1, 'Tasgaon') === false) {
            $fullAddrStr = $address1 . ', ' . $fullAddrStr;
        }

        $invoiceTitle = $isGst ? 'TAX INVOICE' : 'RETAIL SALES BILL';
        $subTitle = $isGst ? '(Original for Recipient — Rule 46 of CGST Rules)' : '(Retail Cash Memo)';

        $grandTotalWords = number_to_indian_words((float)($invoice['grandTotal'] ?? 0));
        $logoPath = APP_ROOT . '/assets/images/logo.png';
        $logoBase64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : '';

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title><?= htmlspecialchars($invoice['invoiceNumber'] ?? 'Invoice') ?></title>
<style>
    @page {
        margin: 20px 25px;
        size: A4 portrait;
    }
    body {
        font-family: 'DejaVu Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
        color: #1e293b;
        font-size: 11px;
        line-height: 1.4;
        margin: 0;
        padding: 0;
    }
    .header-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
        border-bottom: 2px solid #0f392b;
        padding-bottom: 10px;
    }
    .brand-title {
        font-size: 20px;
        font-weight: bold;
        color: #0f392b;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin: 0;
    }
    .brand-tagline {
        font-size: 10px;
        color: #1b5e48;
        font-style: italic;
        margin-top: 2px;
        margin-bottom: 4px;
    }
    .shop-details {
        font-size: 9.5px;
        color: #475569;
        line-height: 1.35;
    }
    .invoice-badge-box {
        text-align: right;
        vertical-align: top;
    }
    .invoice-title {
        font-size: 15px;
        font-weight: bold;
        color: #0f392b;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }
    .invoice-subtitle {
        font-size: 8.5px;
        color: #64748b;
        margin-bottom: 6px;
    }
    .meta-box {
        background-color: #f8faf9;
        border: 1px solid #d1fae5;
        border-radius: 4px;
        padding: 6px 10px;
        display: inline-block;
        text-align: left;
        margin-top: 4px;
    }
    .meta-item {
        font-size: 9.5px;
        margin-bottom: 2px;
    }
    .meta-label {
        font-weight: bold;
        color: #1e293b;
        display: inline-block;
        width: 90px;
    }
    .meta-val {
        color: #0f392b;
        font-weight: 600;
    }

    /* Customer and Seller Info bar */
    .party-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
        background: #fafbfb;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
    }
    .party-td {
        width: 50%;
        padding: 8px 12px;
        vertical-align: top;
    }
    .party-title {
        font-size: 10px;
        font-weight: bold;
        text-transform: uppercase;
        color: #0f392b;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 3px;
        margin-bottom: 5px;
    }

    /* Items table */
    .items-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
    }
    .items-table th {
        background-color: #0f392b;
        color: #ffffff;
        font-size: 9.5px;
        font-weight: 600;
        text-align: left;
        padding: 6px 6px;
        text-transform: uppercase;
        border: 1px solid #0f392b;
    }
    .items-table td {
        padding: 6px 6px;
        border: 1px solid #e2e8f0;
        font-size: 9.5px;
        vertical-align: middle;
    }
    .items-table tr:nth-child(even) td {
        background-color: #fbfcfc;
    }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .font-bold { font-weight: bold; }

    /* Summary section */
    .summary-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 14px;
    }
    .summary-left {
        width: 58%;
        vertical-align: top;
        padding-right: 15px;
    }
    .summary-right {
        width: 42%;
        vertical-align: top;
    }
    .calc-table {
        width: 100%;
        border-collapse: collapse;
    }
    .calc-table td {
        padding: 4px 6px;
        font-size: 10px;
        border-bottom: 1px dashed #e2e8f0;
    }
    .calc-label {
        color: #475569;
    }
    .calc-val {
        text-align: right;
        font-weight: 600;
        color: #1e293b;
    }
    .grand-total-row td {
        background: #0f392b;
        color: #ffffff;
        font-size: 12px;
        font-weight: bold;
        padding: 7px 8px;
        border: none;
    }
    .grand-total-row .calc-val {
        color: #ffffff;
    }

    /* Policy and Signatory */
    .policy-box {
        background: #f8faf9;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        padding: 8px;
        font-size: 8.5px;
        color: #475569;
        line-height: 1.35;
        margin-bottom: 8px;
    }
    .words-box {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        padding: 6px 8px;
        font-size: 9.5px;
        color: #166534;
        font-weight: bold;
        border-radius: 3px;
        margin-bottom: 8px;
    }
    .sign-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    .sign-box {
        text-align: right;
        font-size: 9.5px;
    }
    .footer-bar {
        text-align: center;
        font-size: 9px;
        color: #64748b;
        margin-top: 15px;
        border-top: 1px solid #e2e8f0;
        padding-top: 8px;
    }
</style>
</head>
<body>

<!-- Header -->
<table class="header-table">
    <tr>
        <td style="width: 58%; vertical-align: top;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <?php if (!empty($logoBase64)): ?>
                    <td style="width: 66px; vertical-align: middle; padding-right: 10px;">
                        <img src="data:image/png;base64,<?= $logoBase64 ?>" style="width: 58px; height: 58px; border-radius: 50%;" alt="Logo">
                    </td>
                    <?php endif; ?>
                    <td style="vertical-align: top;">
                        <div class="brand-title"><?= htmlspecialchars($shopName) ?></div>
                        <div class="brand-tagline"><?= htmlspecialchars($tagline) ?></div>
                        <div class="shop-details">
                            <?= htmlspecialchars($fullAddrStr) ?><br>
                            <strong>State:</strong> <?= htmlspecialchars($state) ?> (Code: <?= htmlspecialchars($stateCode) ?>)<br>
                            <strong>Phone:</strong> <?= htmlspecialchars($phone) ?><?php if (!empty($email)): ?> | <strong>Email:</strong> <?= htmlspecialchars($email) ?><?php endif; ?><br>
                            <?php if ($isGst && !empty($gstin)): ?>
                                <strong>GSTIN:</strong> <span style="color:#0f392b; font-weight:bold;"><?= htmlspecialchars($gstin) ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            </table>
        </td>
        <td class="invoice-badge-box">
            <div class="invoice-title"><?= $invoiceTitle ?></div>
            <div class="invoice-subtitle"><?= $subTitle ?></div>
            <div class="meta-box">
                <div class="meta-item"><span class="meta-label">Invoice No:</span> <span class="meta-val"><?= htmlspecialchars($invoice['invoiceNumber'] ?? 'N/A') ?></span></div>
                <div class="meta-item"><span class="meta-label">Date & Time:</span> <span class="meta-val"><?= format_datetime($invoice['invoiceDate'] ?? time(), 'd-m-Y h:i A') ?></span></div>
                <?php if ($isGst): ?>
                    <div class="meta-item"><span class="meta-label">Place of Supply:</span> <span class="meta-val"><?= htmlspecialchars($stateCode) ?> - <?= htmlspecialchars($state) ?></span></div>
                    <div class="meta-item"><span class="meta-label">Reverse Charge:</span> <span class="meta-val">No</span></div>
                <?php endif; ?>
                <div class="meta-item"><span class="meta-label">Payment Mode:</span> <span class="meta-val"><?= strtoupper(htmlspecialchars($invoice['paymentMethod'] ?? 'Cash')) ?> (PAID)</span></div>
            </div>
        </td>
    </tr>
</table>

<!-- Customer & Details Bar -->
<table class="party-table">
    <tr>
        <td class="party-td" style="border-right: 1px solid #e2e8f0;">
            <div class="party-title">Billed To (Customer Details)</div>
            <div><strong>Name:</strong> <?= htmlspecialchars($customer['name'] ?? 'Walk-in Customer') ?></div>
            <div><strong>Mobile:</strong> <?= htmlspecialchars($customer['phone'] ?? 'N/A') ?></div>
            <?php if (!empty($customer['address'])): ?>
                <div><strong>Address:</strong> <?= htmlspecialchars($customer['address']) ?></div>
            <?php endif; ?>
            <?php if (!empty($customer['gstin'])): ?>
                <div><strong>Customer GSTIN:</strong> <?= htmlspecialchars($customer['gstin']) ?></div>
            <?php endif; ?>
        </td>
        <td class="party-td">
            <div class="party-title">Sale & Dispatch Details</div>
            <div><strong>Store:</strong> <?= htmlspecialchars($shopName) ?><?= !empty($city) ? ' (' . htmlspecialchars($city) . ')' : '' ?></div>
            <div><strong>Billed By:</strong> <?= htmlspecialchars($invoice['createdBy'] ?? 'Store Cashier') ?></div>
            <div><strong>Invoice Mode:</strong> <?= $isGst ? 'Regular GST Tax Invoice' : 'Non-GST Retail Bill' ?></div>
            <div><strong>Status:</strong> <span style="color:#059669; font-weight:bold;"><?= strtoupper(htmlspecialchars($invoice['status'] ?? 'COMPLETED')) ?></span></div>
        </td>
    </tr>
</table>

<!-- Items Table -->
<table class="items-table">
    <thead>
        <tr>
            <th class="text-center" style="width: 25px;">#</th>
            <th>Item Description</th>
            <?php if ($isGst): ?><th class="text-center" style="width: 45px;">HSN</th><?php endif; ?>
            <th class="text-center" style="width: 35px;">Size</th>
            <th class="text-center" style="width: 45px;">Color</th>
            <th class="text-center" style="width: 30px;">Qty</th>
            <th class="text-right" style="width: 55px;">Rate (Rs.)</th>
            <th class="text-right" style="width: 50px;">Disc (Rs.)</th>
            <?php if ($isGst): ?>
                <th class="text-right" style="width: 60px;">Taxable (Rs.)</th>
                <th class="text-center" style="width: 45px;">GST %</th>
                <th class="text-right" style="width: 50px;">GST (Rs.)</th>
            <?php endif; ?>
            <th class="text-right" style="width: 65px;">Total (Rs.)</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $idx => $item): ?>
            <tr>
                <td class="text-center"><?= $idx + 1 ?></td>
                <td>
                    <div style="font-weight:600; color:#0f392b;"><?= htmlspecialchars($item['productName'] ?? 'Garment') ?></div>
                    <div style="font-size: 8px; color: #64748b;">SKU: <?= htmlspecialchars($item['sku'] ?? 'N/A') ?></div>
                </td>
                <?php if ($isGst): ?>
                    <td class="text-center"><?= htmlspecialchars($item['hsnCode'] ?? '6205') ?></td>
                <?php endif; ?>
                <td class="text-center font-bold"><?= htmlspecialchars($item['size'] ?? '-') ?></td>
                <td class="text-center"><?= htmlspecialchars($item['color'] ?? '-') ?></td>
                <td class="text-center font-bold"><?= (int)($item['quantity'] ?? 1) ?></td>
                <td class="text-right"><?= number_format((float)($item['unitPrice'] ?? 0), 2) ?></td>
                <td class="text-right"><?= number_format((float)($item['discountAmount'] ?? 0), 2) ?></td>
                <?php if ($isGst): ?>
                    <td class="text-right"><?= number_format((float)($item['taxableValue'] ?? 0), 2) ?></td>
                    <td class="text-center"><?= (float)($item['gstRate'] ?? 0) ?>%</td>
                    <td class="text-right"><?= number_format((float)(($item['cgstAmount'] ?? 0) + ($item['sgstAmount'] ?? 0) + ($item['igstAmount'] ?? 0)), 2) ?></td>
                <?php endif; ?>
                <td class="text-right font-bold"><?= number_format((float)($item['totalAmount'] ?? 0), 2) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- Summary & Calculations -->
<table class="summary-table">
    <tr>
        <td class="summary-left">
            <div class="words-box">
                Amount in Words: <?= htmlspecialchars($grandTotalWords) ?>
            </div>

            <?php if ($isGst && !empty($invoice['cgstTotal'])): ?>
                <table style="width:100%; border-collapse:collapse; margin-bottom:8px; font-size:9px; border:1px solid #e2e8f0;">
                    <tr style="background:#f1f5f9;">
                        <th style="padding:4px; text-align:left; border:1px solid #e2e8f0;">Tax Component</th>
                        <th style="padding:4px; text-align:right; border:1px solid #e2e8f0;">Taxable Amount</th>
                        <th style="padding:4px; text-align:right; border:1px solid #e2e8f0;">Rate</th>
                        <th style="padding:4px; text-align:right; border:1px solid #e2e8f0;">Tax Amount</th>
                    </tr>
                    <?php if (!$isInterstate): ?>
                        <tr>
                            <td style="padding:3px 4px; border:1px solid #e2e8f0;">Central GST (CGST)</td>
                            <td style="padding:3px 4px; text-align:right; border:1px solid #e2e8f0;">₹ <?= number_format((float)($invoice['taxableTotal'] ?? 0), 2) ?></td>
                            <td style="padding:3px 4px; text-align:right; border:1px solid #e2e8f0;">As applicable</td>
                            <td style="padding:3px 4px; text-align:right; border:1px solid #e2e8f0;">₹ <?= number_format((float)($invoice['cgstTotal'] ?? 0), 2) ?></td>
                        </tr>
                        <tr>
                            <td style="padding:3px 4px; border:1px solid #e2e8f0;">State GST (SGST)</td>
                            <td style="padding:3px 4px; text-align:right; border:1px solid #e2e8f0;">₹ <?= number_format((float)($invoice['taxableTotal'] ?? 0), 2) ?></td>
                            <td style="padding:3px 4px; text-align:right; border:1px solid #e2e8f0;">As applicable</td>
                            <td style="padding:3px 4px; text-align:right; border:1px solid #e2e8f0;">₹ <?= number_format((float)($invoice['sgstTotal'] ?? 0), 2) ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td style="padding:3px 4px; border:1px solid #e2e8f0;">Integrated GST (IGST)</td>
                            <td style="padding:3px 4px; text-align:right; border:1px solid #e2e8f0;">₹ <?= number_format((float)($invoice['taxableTotal'] ?? 0), 2) ?></td>
                            <td style="padding:3px 4px; text-align:right; border:1px solid #e2e8f0;">As applicable</td>
                            <td style="padding:3px 4px; text-align:right; border:1px solid #e2e8f0;">₹ <?= number_format((float)($invoice['igstTotal'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr style="font-weight:bold; background:#fafbfb;">
                        <td colspan="3" style="padding:4px; text-align:right; border:1px solid #e2e8f0;">Total Tax Collected:</td>
                        <td style="padding:4px; text-align:right; border:1px solid #e2e8f0;">₹ <?= number_format((float)($invoice['taxTotal'] ?? 0), 2) ?></td>
                    </tr>
                </table>
            <?php endif; ?>

            <div class="policy-box">
                <strong>Exchange & Return Policy:</strong><br>
                <?= nl2br(htmlspecialchars($returnPolicy)) ?><br>
                <em>* Note: No cash refund. Exchange permitted only on unaltered garments with tag and barcode intact.</em>
            </div>
        </td>

        <td class="summary-right">
            <table class="calc-table">
                <tr>
                    <td class="calc-label">Total Items / Qty:</td>
                    <td class="calc-val"><?= count($items) ?> items / <?= array_sum(array_column($items, 'quantity')) ?> pcs</td>
                </tr>
                <tr>
                    <td class="calc-label">Subtotal (Gross Value):</td>
                    <td class="calc-val">Rs. <?= number_format((float)($invoice['subtotal'] ?? 0), 2) ?></td>
                </tr>
                <?php if ((float)($invoice['discountTotal'] ?? 0) > 0): ?>
                <tr>
                    <td class="calc-label" style="color:#059669;">Total Discount:</td>
                    <td class="calc-val" style="color:#059669;">- Rs. <?= number_format((float)($invoice['discountTotal'] ?? 0), 2) ?></td>
                </tr>
                <?php endif; ?>

                <?php if ($isGst): ?>
                    <tr>
                        <td class="calc-label">Taxable Value:</td>
                        <td class="calc-val">Rs. <?= number_format((float)($invoice['taxableTotal'] ?? 0), 2) ?></td>
                    </tr>
                    <?php if (!$isInterstate): ?>
                        <tr>
                            <td class="calc-label">CGST Total:</td>
                            <td class="calc-val">Rs. <?= number_format((float)($invoice['cgstTotal'] ?? 0), 2) ?></td>
                        </tr>
                        <tr>
                            <td class="calc-label">SGST Total:</td>
                            <td class="calc-val">Rs. <?= number_format((float)($invoice['sgstTotal'] ?? 0), 2) ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td class="calc-label">IGST Total:</td>
                            <td class="calc-val">Rs. <?= number_format((float)($invoice['igstTotal'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($invoice['roundOff'])): ?>
                <tr>
                    <td class="calc-label">Round Off:</td>
                    <td class="calc-val"><?= ((float)$invoice['roundOff'] >= 0 ? '+ ' : '') ?>Rs. <?= number_format((float)$invoice['roundOff'], 2) ?></td>
                </tr>
                <?php endif; ?>

                <tr class="grand-total-row">
                    <td>GRAND TOTAL:</td>
                    <td class="calc-val">Rs. <?= number_format((float)($invoice['grandTotal'] ?? 0), 2) ?></td>
                </tr>
            </table>

            <!-- Signatory -->
            <table class="sign-table">
                <tr>
                    <td class="sign-box">
                        <br><br>
                        <div style="font-weight:bold; color:#0f392b;">For SAMRAJYA MENS WEAR</div>
                        <div style="font-size:8.5px; color:#64748b; margin-top:2px;">(Authorized Signatory)</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="footer-bar">
    <?= htmlspecialchars($footerNote) ?> | Computer Generated Invoice
</div>

</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Renders PDF and writes to file or outputs directly to browser
     */
    public static function renderPdf(array $invoice, array $settings, string $outputMode = 'stream', ?string $savePath = null) {
        $html = self::generateHtml($invoice, $settings);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        if ($savePath !== null) {
            file_put_contents($savePath, $dompdf->output());
        }

        $filename = ($invoice['invoiceNumber'] ?? 'Invoice') . '.pdf';

        if ($outputMode === 'download') {
            $dompdf->stream($filename, ['Attachment' => 1]);
            exit;
        } elseif ($outputMode === 'stream') {
            $dompdf->stream($filename, ['Attachment' => 0]);
            exit;
        }

        return $dompdf->output();
    }
}
