<?php
/**
 * Samrajya Mens Wear - Customer Management Directory
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();

$pageTitle = 'Customer Relationship Directory';
$activePage = 'customers';

$db = db();
$search = strtolower(trim($_GET['q'] ?? ''));

$customers = $db->query('customers', function($c) use ($search) {
    if (!$search) return true;
    $name = strtolower($c['name'] ?? '');
    $phone = strtolower($c['phone'] ?? '');
    $email = strtolower($c['email'] ?? '');
    return strpos($name, $search) !== false || strpos($phone, $search) !== false || strpos($email, $search) !== false;
}, 'updatedAt', 'DESC');

require __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:20px; font-weight:800; color:var(--primary-900); margin:0;">
            Customer Records & Loyalty
        </h2>
        <p style="font-size:12.5px; color:var(--slate-500); margin-top:2px;">
            Lookup purchasing history, total lifetime spend, and past GST invoices per customer.
        </p>
    </div>
    <div style="display:flex; gap:10px;">
        <button class="btn btn-primary" onclick="openCustomerModal()">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
            Add New Customer
        </button>
    </div>
</div>

<!-- Search Bar -->
<div class="card" style="margin-bottom:20px; padding:16px 20px;">
    <form method="GET" action="customers.php" style="display:flex; gap:12px;">
        <input type="text" name="q" class="form-control" placeholder="Search by Customer Name, 10-digit Mobile, or Email..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" style="flex:1;">
        <button type="submit" class="btn btn-secondary">Search</button>
        <?php if ($search): ?>
            <a href="customers.php" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Customers Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            Registered Customers (<?= count($customers) ?> records)
        </div>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Mobile Number</th>
                    <th>City / Address</th>
                    <th>GSTIN (B2B)</th>
                    <th style="text-align:center;">Invoices Count</th>
                    <th style="text-align:right;">Lifetime Spend (₹)</th>
                    <th>Last Purchase</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center; padding:40px; color:var(--slate-400);">
                            No customers found. Customers are automatically saved when you enter their mobile number during billing!
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td>
                                <div style="font-weight:700; color:var(--slate-800); font-size:13.5px;">
                                    <?= htmlspecialchars($c['name']) ?>
                                </div>
                                <?php if (!empty($c['email'])): ?>
                                    <div style="font-size:11px; color:var(--slate-400);"><?= htmlspecialchars($c['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color:var(--primary-800);"><?= htmlspecialchars($c['phone'] ?: 'N/A') ?></strong>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($c['city'] ?? 'Tasgaon') ?></div>
                                <?php if (!empty($c['address'])): ?>
                                    <div style="font-size:10.5px; color:var(--slate-400);"><?= htmlspecialchars($c['address']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($c['gstin'])): ?>
                                    <span class="badge badge-info"><?= htmlspecialchars($c['gstin']) ?></span>
                                <?php else: ?>
                                    <span style="color:var(--slate-400); font-size:11px;">B2C Retail</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="badge badge-neutral"><?= (int)($c['totalInvoices'] ?? 0) ?> bills</span>
                            </td>
                            <td style="text-align:right; font-weight:800; color:var(--primary-900);">
                                <?= format_currency($c['totalPurchases'] ?? 0) ?>
                            </td>
                            <td>
                                <?= !empty($c['lastPurchaseDate']) ? format_datetime($c['lastPurchaseDate'], 'd M Y') : '—' ?>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <button class="btn btn-secondary btn-sm" onclick="viewCustomerHistory('<?= $c['customerId'] ?>')" title="Purchase History">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        History
                                    </button>
                                    <button class="btn btn-secondary btn-sm" onclick='editCustomer(<?= json_encode($c) ?>)' title="Edit Profile">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Customer Modal -->
<div id="customerModal" class="modal-backdrop">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-title" id="custModalTitle">Add Customer Record</div>
            <button class="modal-close" onclick="App.closeModal('customerModal')">&times;</button>
        </div>
        <form id="customerForm" onsubmit="saveCustomer(event)">
            <div class="modal-body">
                <input type="hidden" id="c_id">

                <div class="form-group">
                    <label class="form-label">Customer Full Name *</label>
                    <input type="text" id="c_name" class="form-control" required placeholder="e.g. Ramesh Patil">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Mobile Number *</label>
                        <input type="tel" id="c_phone" class="form-control" required placeholder="10-digit number">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address (Optional)</label>
                        <input type="email" id="c_email" class="form-control" placeholder="ramesh@example.com">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label class="form-label">City</label>
                        <input type="text" id="c_city" class="form-control" placeholder="City (e.g. Tasgaon)">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Customer GSTIN (For B2B Billing)</label>
                        <input type="text" id="c_gstin" class="form-control" placeholder="27AAAAA0000A1Z5">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Street / Postal Address</label>
                    <textarea id="c_address" class="form-control" rows="2" placeholder="House no, area, landmark..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('customerModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Customer</button>
            </div>
        </form>
    </div>
</div>

<!-- Customer History Modal -->
<div id="customerHistoryModal" class="modal-backdrop">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <div class="modal-title" id="custHistoryTitle">Customer Purchase History</div>
            <button class="modal-close" onclick="App.closeModal('customerHistoryModal')">&times;</button>
        </div>
        <div class="modal-body" id="custHistoryBody">
            <!-- Dynamic Invoices -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="App.closeModal('customerHistoryModal')">Close</button>
        </div>
    </div>
</div>

<script>
function openCustomerModal() {
    document.getElementById('customerForm').reset();
    document.getElementById('c_id').value = '';
    document.getElementById('custModalTitle').innerText = 'Add Customer Record';
    App.openModal('customerModal');
}

function editCustomer(c) {
    document.getElementById('c_id').value = c.customerId || '';
    document.getElementById('c_name').value = c.name || '';
    document.getElementById('c_phone').value = c.phone || '';
    document.getElementById('c_email').value = c.email || '';
    document.getElementById('c_city').value = c.city || 'Tasgaon';
    document.getElementById('c_gstin').value = c.gstin || '';
    document.getElementById('c_address').value = c.address || '';
    document.getElementById('custModalTitle').innerText = 'Edit Customer — ' + c.name;
    App.openModal('customerModal');
}

async function saveCustomer(e) {
    e.preventDefault();
    const payload = {
        customerId: document.getElementById('c_id').value,
        name: document.getElementById('c_name').value.trim(),
        phone: document.getElementById('c_phone').value.trim(),
        email: document.getElementById('c_email').value.trim(),
        city: document.getElementById('c_city').value.trim(),
        gstin: document.getElementById('c_gstin').value.trim(),
        address: document.getElementById('c_address').value.trim()
    };

    try {
        const res = await App.api('api/customers.php?action=save', {
            method: 'POST',
            body: payload
        });
        App.toast(res.message, 'success');
        App.closeModal('customerModal');
        setTimeout(() => location.reload(), 500);
    } catch (err) {}
}

async function viewCustomerHistory(customerId) {
    try {
        const res = await App.api('api/customers.php?action=details&id=' + encodeURIComponent(customerId));
        const cust = res.customer;
        const invoices = res.invoices || [];

        document.getElementById('custHistoryTitle').innerText = cust.name + ' — Purchase History';

        let invHtml = '';
        if (invoices.length === 0) {
            invHtml = '<div style="text-align:center; padding:30px; color:var(--slate-400);">No prior invoices found for this customer.</div>';
        } else {
            invHtml = `
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th style="text-align:right;">Total (₹)</th>
                            <th>Payment</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${invoices.map(inv => `
                            <tr>
                                <td><strong>${inv.invoiceNumber}</strong></td>
                                <td>${inv.invoiceDate.substr(0,10)}</td>
                                <td>${inv.items?.length || 0} items</td>
                                <td style="text-align:right; font-weight:700; color:var(--primary-800);">${App.formatINR(inv.grandTotal)}</td>
                                <td><span class="badge badge-neutral">${(inv.paymentMethod || 'cash').toUpperCase()}</span></td>
                                <td style="text-align:right;">
                                    <a href="api/invoices.php?action=pdf&id=${encodeURIComponent(inv.invoiceId)}" target="_blank" class="btn btn-secondary btn-sm">PDF</a>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        }

        document.getElementById('custHistoryBody').innerHTML = `
            <div style="background:var(--slate-50); padding:14px; border-radius:var(--radius-sm); margin-bottom:16px; display:flex; justify-content:space-between;">
                <div>
                    <div style="font-size:16px; font-weight:800; color:var(--primary-900);">${cust.name}</div>
                    <div style="font-size:12px; color:var(--slate-500);">Phone: ${cust.phone} ${cust.email ? ' &bull; ' + cust.email : ''}</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:11px; text-transform:uppercase; color:var(--slate-400); font-weight:700;">Total Lifetime Purchases</div>
                    <div style="font-size:18px; font-weight:800; color:#059669;">${App.formatINR(cust.totalPurchases || 0)}</div>
                </div>
            </div>
            ${invHtml}
        `;

        App.openModal('customerHistoryModal');
    } catch (err) {}
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
