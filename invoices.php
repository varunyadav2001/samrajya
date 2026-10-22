<?php
/**
 * Samrajya Mens Wear - Invoices History & Archive
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();

$pageTitle = 'Invoices & Sales Receipts';
$activePage = 'invoices';

$db = db();
$search = strtolower(trim($_GET['q'] ?? ''));
$typeFilter = trim($_GET['type'] ?? '');
$paymentFilter = trim($_GET['payment'] ?? '');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

$invoices = $db->query('invoices', function($inv) use ($search, $typeFilter, $paymentFilter, $startDate, $endDate) {
    if ($typeFilter && ($inv['invoiceType'] ?? '') !== $typeFilter) return false;
    if ($paymentFilter && ($inv['paymentMethod'] ?? '') !== $paymentFilter) return false;

    if ($startDate) {
        $invDate = substr($inv['invoiceDate'] ?? '', 0, 10);
        if ($invDate < $startDate) return false;
    }
    if ($endDate) {
        $invDate = substr($inv['invoiceDate'] ?? '', 0, 10);
        if ($invDate > $endDate) return false;
    }

    if ($search) {
        $invNo = strtolower($inv['invoiceNumber'] ?? '');
        $custName = strtolower($inv['customer']['name'] ?? '');
        $custPhone = strtolower($inv['customer']['phone'] ?? '');
        if (strpos($invNo, $search) === false && 
            strpos($custName, $search) === false && 
            strpos($custPhone, $search) === false) {
            return false;
        }
    }

    return true;
}, 'invoiceDate', 'DESC');

require __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:20px; font-weight:800; color:var(--primary-900); margin:0;">
            Invoice & Receipt Archive
        </h2>
        <p style="font-size:12.5px; color:var(--slate-500); margin-top:2px;">
            All historical invoices are permanently snapshotted in Firestore with immutable lines and costs.
        </p>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="billing.php" class="btn btn-primary">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Create New Bill
        </a>
    </div>
</div>

<!-- Filters Bar -->
<div class="card" style="margin-bottom:20px; padding:16px 20px;">
    <form method="GET" action="invoices.php" style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;">
        <div style="flex:1.5; min-width:200px;">
            <label class="form-label" style="font-size:11.5px;">Search Invoice # / Customer Phone / Name</label>
            <input type="text" name="q" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
        </div>

        <div style="flex:1; min-width:130px;">
            <label class="form-label" style="font-size:11.5px;">Invoice Type</label>
            <select name="type" class="form-control">
                <option value="">All Types</option>
                <option value="gst" <?= $typeFilter === 'gst' ? 'selected' : '' ?>>GST Tax Invoice</option>
                <option value="nongst" <?= $typeFilter === 'nongst' ? 'selected' : '' ?>>Non-GST Sales Bill</option>
            </select>
        </div>

        <div style="flex:1; min-width:130px;">
            <label class="form-label" style="font-size:11.5px;">Payment Method</label>
            <select name="payment" class="form-control">
                <option value="">All Payment Modes</option>
                <option value="cash" <?= $paymentFilter === 'cash' ? 'selected' : '' ?>>Cash</option>
                <option value="upi" <?= $paymentFilter === 'upi' ? 'selected' : '' ?>>UPI / QR</option>
                <option value="card" <?= $paymentFilter === 'card' ? 'selected' : '' ?>>Card</option>
                <option value="mixed" <?= $paymentFilter === 'mixed' ? 'selected' : '' ?>>Other / Mixed</option>
            </select>
        </div>

        <div style="flex:1; min-width:130px;">
            <label class="form-label" style="font-size:11.5px;">From Date</label>
            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
        </div>

        <div style="flex:1; min-width:130px;">
            <label class="form-label" style="font-size:11.5px;">To Date</label>
            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
        </div>

        <div style="display:flex; gap:8px;">
            <button type="submit" class="btn btn-secondary">Filter</button>
            <a href="invoices.php" class="btn btn-secondary" title="Reset Filters">Reset</a>
        </div>
    </form>
</div>

<!-- Invoices Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Invoices List (<?= count($invoices) ?> records)
        </div>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Invoice Number</th>
                    <th>Date & Time</th>
                    <th>Customer</th>
                    <th>Type</th>
                    <th>Items</th>
                    <th style="text-align:right;">Subtotal (₹)</th>
                    <th style="text-align:right;">Grand Total (₹)</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="10" style="text-align:center; padding:40px; color:var(--slate-400);">
                            No invoices found matching your filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): 
                        $items = $inv['items'] ?? [];
                        $totalPieces = array_sum(array_column($items, 'quantity'));
                        $isGst = ($inv['invoiceType'] ?? '') === 'gst';
                        $status = $inv['status'] ?? 'completed';
                    ?>
                        <tr>
                            <td>
                                <strong style="color:var(--primary-900); font-size:13.5px;"><?= htmlspecialchars($inv['invoiceNumber']) ?></strong>
                                <div style="font-size:10px; color:var(--slate-400);">Billed by: <?= htmlspecialchars($inv['createdBy'] ?? 'Admin') ?></div>
                            </td>
                            <td><?= format_datetime($inv['invoiceDate'], 'd M Y, h:i A') ?></td>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($inv['customer']['name'] ?? 'Walk-in Customer') ?></div>
                                <div style="font-size:11px; color:var(--slate-400);"><?= htmlspecialchars($inv['customer']['phone'] ?? '-') ?></div>
                            </td>
                            <td>
                                <span class="badge <?= $isGst ? 'badge-success' : 'badge-neutral' ?>">
                                    <?= $isGst ? 'GST Tax Invoice' : 'Non-GST Bill' ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-weight:600;"><?= count($items) ?> items</span>
                                <span style="font-size:11px; color:var(--slate-500);">(<?= $totalPieces ?> pcs)</span>
                            </td>
                            <td style="text-align:right; color:var(--slate-500);">
                                <?= format_currency($inv['subtotal'] ?? 0) ?>
                            </td>
                            <td style="text-align:right; font-weight:800; font-size:14px; color:var(--primary-800);">
                                <?= format_currency($inv['grandTotal'] ?? 0) ?>
                            </td>
                            <td>
                                <span class="badge badge-neutral"><?= strtoupper($inv['paymentMethod'] ?? 'CASH') ?></span>
                            </td>
                            <td>
                                <?php if ($status === 'completed'): ?>
                                    <span class="badge badge-success">Completed</span>
                                <?php elseif ($status === 'partially_returned'): ?>
                                    <span class="badge badge-warning">Partial Return</span>
                                <?php elseif ($status === 'returned'): ?>
                                    <span class="badge badge-danger">Returned</span>
                                <?php else: ?>
                                    <span class="badge badge-neutral"><?= ucfirst($status) ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <?php
                                        $custPhoneRaw = preg_replace('/[^0-9]/', '', $inv['customer']['phone'] ?? '');
                                        if (strlen($custPhoneRaw) === 10) $custPhoneRaw = '91' . $custPhoneRaw;
                                        $baseUrl = get_public_base_url();
                                        $baseScript = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
                                        $publicBill = "{$baseUrl}{$baseScript}/bill.php?id=" . urlencode($inv['invoiceId']);
                                        $cName = $inv['customer']['name'] ?? 'Walk-in Customer';

                                        $itemStr = "";
                                        $k = 1;
                                        foreach (($inv['items'] ?? []) as $it) {
                                            $pName = $it['productName'] ?? 'Item';
                                            $sz = !empty($it['size']) ? " [{$it['size']}]" : "";
                                            $q = (int)($it['quantity'] ?? 1);
                                            $r = number_format((float)($it['unitPrice'] ?? 0), 2);
                                            $tot = number_format((float)($it['totalAmount'] ?? 0), 2);
                                            $itemStr .= "{$k}. *{$pName}*{$sz}\n   {$q} pcs × ₹{$r} = *₹{$tot}*\n";
                                            $k++;
                                        }

                                        $storeCfg = $db->get('settings', 'store_config') ?? [];
                                        $shopTitle = $storeCfg['shopName'] ?? "साम्राज्य.07";
                                        $shopTagline = !empty($storeCfg['tagline']) ? " — {$storeCfg['tagline']}" : "";
                                        $shopAddr = "";
                                        if (!empty($storeCfg['addressLine1'])) {
                                            $shopAddr .= "📍 " . $storeCfg['addressLine1'];
                                            if (!empty($storeCfg['city'])) $shopAddr .= ", " . $storeCfg['city'];
                                            $shopAddr .= "\n";
                                        } elseif (!empty($storeCfg['city'])) {
                                            $shopAddr .= "📍 " . $storeCfg['city'] . "\n";
                                        }
                                        $shopPhoneStr = !empty($storeCfg['phone']) ? "📞 मो.: " . $storeCfg['phone'] . "\n" : "";

                                        $waText = "👑 *{$shopTitle}{$shopTagline}*\n";
                                        if ($shopAddr) $waText .= $shopAddr;
                                        if ($shopPhoneStr) $waText .= $shopPhoneStr;
                                        $waText .= "━━━━━━━━━━━━━━━━━━━━━\n";
                                        $waText .= "🧾 *टॅक्स इनव्हॉइस / SALES BILL*\n";
                                        $waText .= "बिल क्र.: *{$inv['invoiceNumber']}*\n";
                                        $waText .= "दिनांक: " . date('d-m-Y, h:i A', strtotime($inv['invoiceDate'])) . "\n";
                                        $waText .= "ग्राहक: *{$cName}*\n";
                                        $waText .= "पेमेंट: *" . strtoupper($inv['paymentMethod'] ?? 'CASH') . " (PAID)*\n";
                                        $waText .= "━━━━━━━━━━━━━━━━━━━━━\n";
                                        $waText .= "🛍️ *खरेदी तपशील:*\n" . $itemStr;
                                        $waText .= "━━━━━━━━━━━━━━━━━━━━━\n";
                                        $waText .= "💰 *एकूण देय रक्कम: ₹" . number_format($inv['grandTotal'] ?? 0, 2) . "*\n";
                                        $waText .= "━━━━━━━━━━━━━━━━━━━━━\n";
                                        $waText .= "🌐 *आपले मूळ डिजिटल बिल पाहण्यासाठी खालील लिंक उघडा:*\n";
                                        $waText .= "👉 {$publicBill}\n\n";
                                        $waText .= "🙏 खरेदी केल्याबद्दल मनापासून धन्यवाद! पुन्हा नक्की भेट द्या! ✨";

                                        $waUrl = $custPhoneRaw ? "https://wa.me/{$custPhoneRaw}?text=" . rawurlencode($waText) : "https://api.whatsapp.com/send?text=" . rawurlencode($waText);
                                    ?>
                                    <a href="<?= $waUrl ?>" target="_blank" class="btn btn-sm" style="background:#25D366; color:#ffffff; border:none; padding:4px 8px;" title="Share on WhatsApp">
                                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                    </a>
                                    <button class="btn btn-secondary btn-sm" onclick='viewInvoiceDetails(<?= json_encode($inv) ?>)' title="View Line Items">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        Details
                                    </button>
                                    <a href="invoice-print.php?id=<?= urlencode($inv['invoiceId']) ?>" target="_blank" class="btn btn-secondary btn-sm" title="Print Invoice">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                        Print
                                    </a>
                                    <a href="api/invoices.php?action=pdf&id=<?= urlencode($inv['invoiceId']) ?>&download=1" class="btn btn-secondary btn-sm" title="Download Official PDF">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        PDF
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Invoice Details Modal -->
<div id="invoiceDetailsModal" class="modal-backdrop">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <div class="modal-title" id="invDetailTitle">Invoice Details</div>
            <button class="modal-close" onclick="App.closeModal('invoiceDetailsModal')">&times;</button>
        </div>
        <div class="modal-body" id="invDetailBody">
            <!-- Dynamic Content -->
        </div>
        <div class="modal-footer" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
            <button type="button" class="btn btn-secondary" onclick="App.closeModal('invoiceDetailsModal')">Close</button>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <a id="invDetailFooterWaBtn" href="#" target="_blank" class="btn" style="background:#25D366; color:#ffffff; font-weight:700; border:none; display:inline-flex; align-items:center; gap:6px;">
                    <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                    WhatsApp शेअर
                </a>
                <a id="invDetailPrintBtn" href="#" target="_blank" class="btn btn-secondary">Print View</a>
                <a id="invDetailPdfBtn" href="#" target="_blank" class="btn btn-primary">Download PDF</a>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/html2canvas.min.js"></script>
<script>
<?php
$scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$baseDir = rtrim($scriptName, '/');
$publicBase = get_public_base_url() . $baseDir;
$storeCfg = $db->get('settings', 'store_config') ?? [];
$storeName = !empty($storeCfg['shopName']) ? $storeCfg['shopName'] : "साम्राज्य.07";
$storeTagline = !empty($storeCfg['tagline']) ? $storeCfg['tagline'] : "The perfect men's shop";
$storeAddr = "तासगाव (महाराष्ट्र - ४१६३१२)";
if (!empty($storeCfg['addressLine1'])) {
    $storeAddr = $storeCfg['addressLine1'];
}
$storePhone = $storeCfg['phone'] ?? "";
?>
const PUBLIC_BASE_URL = <?= json_encode($publicBase) ?>;
const STORE_NAME = <?= json_encode($storeName) ?>;
const STORE_TAGLINE = <?= json_encode($storeTagline) ?>;
const STORE_ADDRESS = <?= json_encode($storeAddr) ?>;
const STORE_PHONE = <?= json_encode($storePhone) ?>;

function viewInvoiceDetails(inv) {
    document.getElementById('invDetailTitle').innerText = 'Invoice — ' + inv.invoiceNumber;
    document.getElementById('invDetailPrintBtn').href = 'invoice-print.php?id=' + encodeURIComponent(inv.invoiceId);
    document.getElementById('invDetailPdfBtn').href = 'api/invoices.php?action=pdf&id=' + encodeURIComponent(inv.invoiceId) + '&download=1';

    const items = inv.items || [];
    const isGst = (inv.invoiceType === 'gst');

    // Build Public Digital Bill URL with LAN Wi-Fi Base so mobile phones can open directly
    const publicUrl = PUBLIC_BASE_URL + '/bill.php?id=' + encodeURIComponent(inv.invoiceId);
    const phone = ((inv.customer && inv.customer.phone) || '').replace(/[^0-9]/g, '');
    const cleanPhone = (phone.length === 10) ? ('91' + phone) : phone;
    const custName = (inv.customer && inv.customer.name && inv.customer.name !== 'Walk-in Customer') ? inv.customer.name : 'ग्राहक';

    // Build Itemized Text for WhatsApp Digital Bill
    let waItemsStr = '';
    items.forEach((it, idx) => {
        const pName = it.productName || 'Item';
        let metaParts = [];
        if (it.size) metaParts.push(it.size);
        if (it.color) metaParts.push(it.color);
        const metaStr = metaParts.length > 0 ? ` [${metaParts.join(' • ')}]` : '';
        const qty = parseInt(it.quantity || 1);
        const rate = parseFloat(it.unitPrice || 0).toFixed(2);
        const tot = parseFloat(it.totalAmount || 0).toFixed(2);
        waItemsStr += `${idx + 1}. *${pName}*${metaStr}\n   ${qty} pcs × ₹${rate} = *₹${tot}*\n`;
    });

    const invDateStr = inv.invoiceDate ? new Date(inv.invoiceDate).toLocaleString('en-IN', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: true
    }) : '';

    let waText = `👑 *${STORE_NAME} — ${STORE_TAGLINE}*\n`;
    waText += `📍 ${STORE_ADDRESS}\n`;
    if (STORE_PHONE) waText += `📞 मो.: ${STORE_PHONE}\n`;
    waText += `━━━━━━━━━━━━━━━━━━━━━\n`;
    waText += `🧾 *${isGst ? 'टॅक्स इनव्हॉइस (TAX INVOICE)' : 'सेल्स बिल (SALES RECEIPT)'}*\n`;
    waText += `बिल क्र.: *${inv.invoiceNumber}*\n`;
    if (invDateStr) waText += `दिनांक: ${invDateStr}\n`;
    waText += `ग्राहक: *${custName}*${phone ? ' (' + phone + ')' : ''}\n`;
    waText += `पेमेंट: *${(inv.paymentMethod || 'CASH').toUpperCase()} (PAID)*\n`;
    waText += `━━━━━━━━━━━━━━━━━━━━━\n`;
    waText += `🛍️ *खरेदी तपशील (Items):*\n${waItemsStr}`;
    waText += `━━━━━━━━━━━━━━━━━━━━━\n`;
    waText += `सबटोटल: ₹${parseFloat(inv.subtotal || 0).toFixed(2)}\n`;
    if (parseFloat(inv.discountTotal || 0) > 0) {
        waText += `सूट (Discount): - ₹${parseFloat(inv.discountTotal || 0).toFixed(2)}\n`;
    }
    if (isGst && parseFloat(inv.taxTotal || 0) > 0) {
        waText += `जीएसटी (GST): ₹${parseFloat(inv.taxTotal || 0).toFixed(2)}\n`;
    }
    waText += `💰 *एकूण देय रक्कम: ₹${parseFloat(inv.grandTotal || 0).toFixed(2)}*\n`;
    waText += `━━━━━━━━━━━━━━━━━━━━━\n`;
    waText += `🌐 *आपले मूळ डिजिटल बिल पाहण्यासाठी खालील लिंक उघडा:*\n`;
    waText += `👉 ${publicUrl}\n\n`;
    waText += `🙏 साम्राज्य.07 मध्ये खरेदी केल्याबद्दल धन्यवाद! पुन्हा नक्की भेट द्या! ✨`;

    const waLink = cleanPhone 
        ? `https://wa.me/${cleanPhone}?text=${encodeURIComponent(waText)}` 
        : `https://api.whatsapp.com/send?text=${encodeURIComponent(waText)}`;

    const footerWa = document.getElementById('invDetailFooterWaBtn');
    if (footerWa) footerWa.href = waLink;

    let itemsHtml = items.map((item, idx) => `
        <tr>
            <td>${idx + 1}</td>
            <td>
                <strong>${App.escapeHtml(item.productName)}</strong>
                <div style="font-size:10.5px; color:var(--slate-400);">SKU: ${App.escapeHtml(item.sku || '-')} &bull; Size: ${App.escapeHtml(item.size || '-')} &bull; Color: ${App.escapeHtml(item.color || '-')}</div>
            </td>
            ${isGst ? `<td>${item.hsnCode || '6205'}</td>` : ''}
            <td style="text-align:center;"><strong>${item.quantity}</strong></td>
            <td style="text-align:right;">${App.formatINR(item.unitPrice)}</td>
            <td style="text-align:right;">${App.formatINR(item.discountAmount || 0)}</td>
            ${isGst ? `
                <td style="text-align:right;">${App.formatINR(item.taxableValue)}</td>
                <td style="text-align:center;">${item.gstRate}%</td>
            ` : ''}
            <td style="text-align:right; font-weight:700;">${App.formatINR(item.totalAmount)}</td>
        </tr>
    `).join('');

    const bodyHtml = `
        <div id="invBillCaptureCard" style="background:#ffffff; border-radius:8px; padding:6px;">
            <!-- Brand Header -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; padding-bottom:12px; border-bottom:2px solid #f1f5f9; flex-wrap:wrap; gap:12px;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <img src="assets/images/logo.png" alt="साम्राज्य.07" style="width:48px; height:48px; border-radius:50%; border:2px solid #f59e0b; background:#0f392b; object-fit:contain;">
                    <div>
                        <div style="font-size:16px; font-weight:800; color:#0f392b;">${STORE_NAME}</div>
                        <div style="font-size:11.5px; color:#d97706; font-weight:600;">${STORE_TAGLINE}</div>
                        <div style="font-size:11px; color:#64748b;">📍 ${STORE_ADDRESS}</div>
                    </div>
                </div>
                <div style="text-align:right;">
                    <span class="badge ${isGst ? 'badge-success' : 'badge-neutral'}" style="font-size:11.5px;">
                        ${isGst ? 'GST Tax Invoice' : 'Retail Sales Receipt'}
                    </span>
                    <div style="font-size:11px; color:#64748b; margin-top:4px;">Status: <strong style="color:#10b981;">PAID</strong></div>
                </div>
            </div>

            <!-- Customer & Invoice Info Box -->
            <div style="display:flex; justify-content:space-between; margin-bottom:16px; padding:12px; background:#f8fafc; border-radius:6px; border:1px solid #e2e8f0; flex-wrap:wrap; gap:12px;">
                <div>
                    <div style="font-size:11px; color:var(--slate-400); text-transform:uppercase; font-weight:700;">ग्राहक तपशील (Customer)</div>
                    <div style="font-size:15px; font-weight:700; color:var(--slate-800);">${App.escapeHtml(inv.customer?.name || 'Walk-in Customer')}</div>
                    <div style="font-size:12px; color:var(--slate-600); margin-top:2px;">📱 ${App.escapeHtml(inv.customer?.phone || 'N/A')}</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:11px; color:var(--slate-400); text-transform:uppercase; font-weight:700;">बिल तपशील (Invoice)</div>
                    <div style="font-size:14px; font-weight:800; color:var(--primary-800);">${App.escapeHtml(inv.invoiceNumber)}</div>
                    <div style="font-size:11.5px; color:var(--slate-500);">${App.escapeHtml(inv.invoiceDate || '')}</div>
                </div>
            </div>

            <!-- Items Table -->
            <table class="data-table" style="margin-bottom:16px;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item Description</th>
                        ${isGst ? '<th>HSN</th>' : ''}
                        <th style="text-align:center;">Qty</th>
                        <th style="text-align:right;">Rate</th>
                        <th style="text-align:right;">Disc</th>
                        ${isGst ? '<th style="text-align:right;">Taxable</th><th style="text-align:center;">GST</th>' : ''}
                        <th style="text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>${itemsHtml}</tbody>
            </table>

            <!-- Totals & Actions -->
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px; margin-top:14px;">
                <div style="flex:1; min-width:260px;">
                    <div style="font-size:12px; font-weight:700; color:#0f392b; margin-bottom:8px;">💡 शेअरिंग पर्याय (Instant Digital Sharing):</div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <a href="${waLink}" target="_blank" class="btn" style="background:#25D366; color:#ffffff; font-weight:700; border:none; display:inline-flex; align-items:center; gap:6px; box-shadow:0 2px 6px rgba(37,211,102,0.35);">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                            WhatsApp वर डिजिटल बिल पाठवा
                        </a>
                        <button type="button" class="btn btn-secondary" onclick="downloadBillSnapshot('${inv.invoiceNumber}')" style="display:inline-flex; align-items:center; gap:6px;">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            📸 बिल फोटो (PNG)
                        </button>
                        <a href="${publicUrl}" target="_blank" class="btn btn-secondary" style="display:inline-flex; align-items:center; gap:6px;">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            डिजिटल बिल लिंक
                        </a>
                    </div>
                </div>

                <div style="width:300px; background:#f8fafc; padding:14px; border-radius:6px; border:1px solid #e2e8f0;">
                    <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px;">
                        <span>Subtotal:</span>
                        <span style="font-weight:600;">${App.formatINR(inv.subtotal)}</span>
                    </div>
                    ${inv.discountTotal > 0 ? `
                    <div style="display:flex; justify-content:space-between; font-size:12px; color:#059669; margin-bottom:4px;">
                        <span>Discount:</span>
                        <span>- ${App.formatINR(inv.discountTotal)}</span>
                    </div>` : ''}
                    ${isGst ? `
                    <div style="display:flex; justify-content:space-between; font-size:12px; color:var(--slate-600); margin-bottom:4px;">
                        <span>Taxable Base:</span>
                        <span>${App.formatINR(inv.taxableTotal)}</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:12px; color:var(--slate-600); margin-bottom:4px;">
                        <span>CGST + SGST:</span>
                        <span>${App.formatINR(inv.taxTotal)}</span>
                    </div>` : ''}
                    <div style="display:flex; justify-content:space-between; font-size:16px; font-weight:800; color:var(--primary-900); padding-top:6px; margin-top:6px; border-top:1px dashed var(--slate-300);">
                        <span>Grand Total:</span>
                        <span>${App.formatINR(inv.grandTotal)}</span>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.getElementById('invDetailBody').innerHTML = bodyHtml;
    App.openModal('invoiceDetailsModal');
}

function downloadBillSnapshot(invNum) {
    const el = document.getElementById('invBillCaptureCard');
    if (!el) return;
    if (!window.html2canvas) {
        App.toast('Image generator is initializing...', 'info');
        return;
    }
    App.toast('बिल फोटो तयार होत आहे... 📸', 'info');
    html2canvas(el, { scale: 2, useCORS: true, backgroundColor: '#ffffff' }).then(canvas => {
        const link = document.createElement('a');
        link.download = `Bill_${invNum || 'invoice'}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
        App.toast('बिल फोटो सेव्ह झाला! 📸', 'success');
    }).catch(err => {
        console.error(err);
        App.toast('फोटो डाऊनलोड करताना त्रुटी आली.', 'error');
    });
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
