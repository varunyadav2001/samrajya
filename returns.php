<?php
/**
 * Samrajya Mens Wear - Returns & Exchanges Processing
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();

$pageTitle = 'Returns & Exchanges';
$activePage = 'returns';

$db = db();
$returnsList = $db->query('returns', null, 'createdAt', 'DESC');

require __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:20px; font-weight:800; color:var(--primary-900); margin:0;">
            Returns & Exchange Management
        </h2>
        <p style="font-size:12.5px; color:var(--slate-500); margin-top:2px;">
            Safe return processing with physical restock control and non-destructive invoice auditing.
        </p>
    </div>
    <div style="display:flex; gap:10px;">
        <button class="btn btn-primary" onclick="openNewReturnModal()">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H4m0 0l3-3m-3 3l3 3m5 4v1a3 3 0 003 3h6m0 0l-3-3m3 3l-3 3"/></svg>
            Process New Return
        </button>
    </div>
</div>

<!-- Returns History Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Processed Return Credit Notes (<?= count($returnsList) ?> records)
        </div>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Return Voucher #</th>
                    <th>Date & Time</th>
                    <th>Original Invoice</th>
                    <th>Customer</th>
                    <th>Returned Items</th>
                    <th style="text-align:right;">Refund Amount (₹)</th>
                    <th>Refund Method</th>
                    <th>Return Reason</th>
                    <th>Processed By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($returnsList)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center; padding:40px; color:var(--slate-400);">
                            No returns processed yet. All sales remain 100% fulfilled.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($returnsList as $ret): ?>
                        <tr>
                            <td>
                                <strong style="color:var(--primary-900);"><?= htmlspecialchars($ret['returnNumber']) ?></strong>
                            </td>
                            <td><?= format_datetime($ret['createdAt'], 'd M Y, h:i A') ?></td>
                            <td>
                                <strong style="color:var(--primary-800);"><?= htmlspecialchars($ret['originalInvoiceNumber']) ?></strong>
                            </td>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($ret['customer']['name'] ?? 'Walk-in') ?></div>
                                <div style="font-size:11px; color:var(--slate-400);"><?= htmlspecialchars($ret['customer']['phone'] ?? '') ?></div>
                            </td>
                            <td>
                                <?php foreach ($ret['items'] ?? [] as $rItem): ?>
                                    <div style="font-size:11.5px;">
                                        <strong><?= (int)$rItem['quantity'] ?>x</strong> <?= htmlspecialchars($rItem['productName']) ?> (<?= htmlspecialchars($rItem['size']) ?>)
                                        <?php if (!empty($rItem['restock'])): ?>
                                            <span class="badge badge-success" style="font-size:9px; padding:1px 4px;">Restocked</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                            <td style="text-align:right; font-weight:800; color:var(--danger-600);">
                                <?= format_currency($ret['totalRefundAmount']) ?>
                            </td>
                            <td>
                                <span class="badge badge-neutral"><?= strtoupper($ret['refundMethod'] ?? 'CASH') ?></span>
                            </td>
                            <td style="max-width:200px; font-size:12px; color:var(--slate-600);">
                                <?= htmlspecialchars($ret['returnReason']) ?>
                            </td>
                            <td style="font-size:11.5px; color:var(--slate-500);">
                                <?= htmlspecialchars($ret['processedBy'] ?? 'Admin') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Process Return Modal -->
<div id="newReturnModal" class="modal-backdrop">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <div class="modal-title">Process Customer Return / Exchange</div>
            <button class="modal-close" onclick="App.closeModal('newReturnModal')">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Step 1: Lookup Invoice -->
            <div style="margin-bottom:20px;">
                <label class="form-label">Step 1: Enter Original Invoice Number</label>
                <div style="display:flex; gap:10px;">
                    <input type="text" id="retLookupInput" class="form-control" placeholder="e.g. SMW-GST-202610-0001" style="font-weight:700;">
                    <button type="button" class="btn btn-primary" onclick="lookupInvoiceForReturn()">
                        Lookup Invoice
                    </button>
                </div>
            </div>

            <!-- Step 2: Invoice Items Selection (Hidden until found) -->
            <div id="retInvoiceDetails" style="display:none;">
                <div style="background:var(--slate-50); padding:12px 16px; border-radius:var(--radius-sm); border:1px solid var(--slate-200); margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-size:11px; text-transform:uppercase; color:var(--slate-400); font-weight:700;">Original Invoice</div>
                            <div id="retInvNumber" style="font-size:15px; font-weight:800; color:var(--primary-900);"></div>
                            <div id="retInvDate" style="font-size:11.5px; color:var(--slate-500);"></div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:11px; text-transform:uppercase; color:var(--slate-400); font-weight:700;">Customer</div>
                            <div id="retCustomerName" style="font-size:14px; font-weight:700; color:var(--slate-800);"></div>
                            <div id="retCustomerPhone" style="font-size:11.5px; color:var(--slate-500);"></div>
                        </div>
                    </div>
                </div>

                <div class="form-label" style="margin-bottom:8px;">Step 2: Select Items and Quantities to Return</div>
                <div class="table-responsive" style="margin-bottom:16px;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Item Details</th>
                                <th style="text-align:center;">Sold Qty</th>
                                <th style="text-align:center;">Already Returned</th>
                                <th style="text-align:center;">Eligible Qty</th>
                                <th style="width:110px;">Qty to Return</th>
                                <th style="text-align:center;">Restock?</th>
                            </tr>
                        </thead>
                        <tbody id="retItemsTableBody">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                    <div class="form-group">
                        <label class="form-label">Return / Exchange Reason *</label>
                        <select id="retReasonSelect" class="form-control" required>
                            <option value="Size mismatch - Customer exchanged for another size">Size mismatch (Exchange)</option>
                            <option value="Fabric defect or stitching issue">Fabric defect or stitching issue</option>
                            <option value="Customer changed mind / Gift exchange">Customer changed mind / Gift exchange</option>
                            <option value="Billing correction">Billing correction</option>
                            <option value="Other store policy return">Other store policy return</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Refund / Settlement Method *</label>
                        <select id="retRefundMethod" class="form-control" required>
                            <option value="cash">Cash Refund</option>
                            <option value="upi">UPI / Online Refund</option>
                            <option value="store_credit">Store Credit Voucher</option>
                            <option value="exchange">Immediate Garment Exchange</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Additional Auditor Notes</label>
                    <input type="text" id="retNotes" class="form-control" placeholder="Optional notes for audit logs...">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="App.closeModal('newReturnModal')">Cancel</button>
            <button type="button" id="submitReturnBtn" class="btn btn-danger" onclick="submitReturn()" style="display:none;">
                Confirm & Issue Return Voucher
            </button>
        </div>
    </div>
</div>

<script>
let currentLoadedInvoice = null;

function openNewReturnModal() {
    document.getElementById('retLookupInput').value = '';
    document.getElementById('retInvoiceDetails').style.display = 'none';
    document.getElementById('submitReturnBtn').style.display = 'none';
    currentLoadedInvoice = null;
    App.openModal('newReturnModal');
}

async function lookupInvoiceForReturn() {
    const q = document.getElementById('retLookupInput').value.trim();
    if (!q) {
        App.toast('Please enter an invoice number to lookup', 'warning');
        return;
    }

    try {
        const res = await App.api(`api/returns.php?action=lookup&q=${encodeURIComponent(q)}`);
        const inv = res.invoice;
        currentLoadedInvoice = inv;

        document.getElementById('retInvNumber').innerText = inv.invoiceNumber;
        document.getElementById('retInvDate').innerText = 'Date: ' + inv.invoiceDate.substr(0,10);
        document.getElementById('retCustomerName').innerText = inv.customer?.name || 'Walk-in Customer';
        document.getElementById('retCustomerPhone').innerText = inv.customer?.phone || 'N/A';

        const tbody = document.getElementById('retItemsTableBody');
        tbody.innerHTML = inv.items.map(item => `
            <tr>
                <td>
                    <strong>${item.productName}</strong>
                    <div style="font-size:10.5px; color:var(--slate-400);">SKU: ${item.sku} &bull; Size: ${item.size}</div>
                </td>
                <td style="text-align:center;">${item.soldQuantity}</td>
                <td style="text-align:center; color:var(--slate-400);">${item.alreadyReturned}</td>
                <td style="text-align:center; font-weight:700; color:var(--primary-800);">${item.eligibleQuantity}</td>
                <td>
                    ${item.eligibleQuantity > 0 ? `
                        <input type="number" min="0" max="${item.eligibleQuantity}" value="0" 
                            class="form-control ret-qty-input" 
                            data-prod-id="${item.productId}"
                            data-max="${item.eligibleQuantity}"
                            style="padding:4px 6px; font-size:12px; font-weight:bold; text-align:center;">
                    ` : '<span style="color:var(--slate-400); font-size:11px;">Fully returned</span>'}
                </td>
                <td style="text-align:center;">
                    ${item.eligibleQuantity > 0 ? `
                        <input type="checkbox" class="ret-restock-check" data-prod-id="${item.productId}" checked title="Restore stock into inventory">
                    ` : '-'}
                </td>
            </tr>
        `).join('');

        document.getElementById('retInvoiceDetails').style.display = 'block';
        document.getElementById('submitReturnBtn').style.display = 'inline-flex';
    } catch (err) {}
}

async function submitReturn() {
    if (!currentLoadedInvoice) return;

    const returnItems = [];
    document.querySelectorAll('.ret-qty-input').forEach(input => {
        const qty = parseInt(input.value || 0);
        const pId = input.dataset.prodId;
        const max = parseInt(input.dataset.max || 0);
        const restockCheck = document.querySelector(`.ret-restock-check[data-prod-id="${pId}"]`);
        const restock = restockCheck ? restockCheck.checked : true;

        if (qty > 0) {
            if (qty > max) {
                App.toast(`Return quantity cannot exceed eligible quantity (${max})`, 'warning');
                return;
            }
            returnItems.push({
                productId: pId,
                quantity: qty,
                restock: restock
            });
        }
    });

    if (returnItems.length === 0) {
        App.toast('Please enter a return quantity (> 0) for at least one item', 'warning');
        return;
    }

    const payload = {
        invoiceId: currentLoadedInvoice.invoiceId,
        items: returnItems,
        reason: document.getElementById('retReasonSelect').value,
        refundMethod: document.getElementById('retRefundMethod').value,
        notes: document.getElementById('retNotes').value.trim()
    };

    try {
        const res = await App.api('api/returns.php?action=process', {
            method: 'POST',
            body: payload
        });

        App.toast(res.message, 'success');
        App.closeModal('newReturnModal');
        setTimeout(() => location.reload(), 600);
    } catch (err) {}
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
