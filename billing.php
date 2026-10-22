<?php
/**
 * Samrajya Mens Wear - POS Billing Screen
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();

$pageTitle = 'Point of Sale (POS) Billing';
$activePage = 'billing';
$extraCss = ['assets/css/pos.css'];
$extraJs = ['assets/js/pos.js'];

// Dynamically load only categories that actually exist in the store's inventory
$db = db();
$activeProducts = $db->query('products', function($p) {
    return ($p['status'] ?? 'active') === 'active';
});
$existingCategories = array_values(array_unique(array_filter(array_map(function($p) {
    return trim($p['category'] ?? '');
}, $activeProducts))));
sort($existingCategories);

require __DIR__ . '/includes/header.php';
?>

<div class="pos-layout">
    <!-- Mobile Tabs Navigation (Visible only on screens <= 900px) -->
    <div class="pos-mobile-nav" id="posMobileNav">
        <button type="button" class="pos-mobile-tab-btn active" id="tabBtnProducts" onclick="POS.switchTab('products')">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            <span>Products / माल</span>
        </button>
        <button type="button" class="pos-mobile-tab-btn" id="tabBtnCart" onclick="POS.switchTab('cart')">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            <span>Cart / बिल (<span id="mobileTabCount">0</span>)</span>
        </button>
    </div>

    <!-- Products Panel (Left) -->
    <div class="pos-products-panel">
        <div class="pos-search-bar">
            <div class="search-input-wrapper">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" id="posSearchInput" class="pos-search-input" placeholder="Search by Product Name, SKU, Barcode, or Color... (Press F2 to focus)">
            </div>
            <button class="btn btn-secondary" onclick="POS.loadProducts()" title="Refresh Inventory Stock">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </button>
        </div>

        <!-- Dynamic Category Pills -->
        <div class="category-pills" id="posCategoryPills">
            <div class="category-pill active" data-category="all">
                All Items (<?= count($activeProducts) ?>)
            </div>
            <?php foreach ($existingCategories as $cat): ?>
                <div class="category-pill" data-category="<?= htmlspecialchars($cat) ?>">
                    <?= htmlspecialchars($cat) ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Product Cards Grid -->
        <div class="pos-products-grid" id="posProductsGrid">
            <!-- Dynamically populated by pos.js -->
            <div style="grid-column: 1 / -1; text-align:center; padding:50px; color:var(--slate-400);">
                Loading inventory catalog...
            </div>
        </div>
    </div>

    <!-- Cart & Billing Panel (Right) -->
    <div class="pos-cart-panel">
        <div class="pos-cart-header">
            <div class="cart-title">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                Current Bill
            </div>
            <div class="cart-controls">
                <span id="cartItemsCount" style="font-size:12px; font-weight:700; color:var(--slate-500);">0 items</span>
                <button class="btn btn-secondary btn-sm" onclick="POS.clearCart()" title="Clear Cart">Clear</button>
            </div>
        </div>

        <!-- Invoice Type Switcher -->
        <div class="invoice-type-toggle">
            <div class="type-toggle-btn active" id="typeGstBtn">
                GST Tax Invoice
            </div>
            <div class="type-toggle-btn" id="typeNongstBtn">
                Non-GST Sales Bill
            </div>
        </div>

        <!-- Customer Details Bar -->
        <div class="pos-customer-bar">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                <label style="font-size:11.5px; font-weight:700; color:var(--slate-700); display:flex; align-items:center; gap:5px; margin:0;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Customer Details (ग्राहक नाव व मोबाईल)
                </label>
                <div style="display:flex; align-items:center; gap:6px;">
                    <span id="customerBadge" class="customer-status-badge" style="display:none;"></span>
                    <button type="button" id="btnWalkinReset" class="btn btn-secondary btn-sm" style="font-size:10px; padding:2px 7px; text-transform:none;" title="Clear & set as Walk-in Customer">
                        Walk-in (किरकोळ)
                    </button>
                </div>
            </div>

            <div class="customer-inputs-container">
                <div class="customer-inputs">
                    <div style="flex:1; position:relative;">
                        <input type="tel" id="customerPhone" class="form-control" placeholder="Mobile (10 digits)" maxlength="10" autocomplete="off" style="font-size:12.5px; padding:6px 10px; width:100%;">
                    </div>
                    <div style="flex:1.3; position:relative;">
                        <input type="text" id="customerName" class="form-control" placeholder="Customer Name (ग्राहकाचे नाव)" autocomplete="off" style="font-size:12.5px; padding:6px 10px; width:100%;">
                    </div>
                </div>

                <!-- Live Customer Autocomplete Dropdown -->
                <div id="customerSuggestions" class="customer-autocomplete-dropdown" style="display:none;"></div>
            </div>
        </div>

        <!-- Cart Items List -->
        <div class="pos-cart-items" id="posCartItems">
            <!-- Dynamically populated by pos.js -->
        </div>

        <!-- Summary & Calculations -->
        <div class="pos-cart-summary">
            <div class="summary-line">
                <span>Subtotal (Gross):</span>
                <span id="cartSubtotal" style="font-weight:700;">₹ 0.00</span>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                <span style="font-size:12px; color:var(--slate-600);">Bill Discount:</span>
                <div style="display:flex; gap:6px;">
                    <select id="billDiscountType" style="font-size:11px; padding:2px 4px; border:1px solid var(--slate-300); border-radius:3px;">
                        <option value="fixed">₹ (Fixed)</option>
                        <option value="percentage">% (Percent)</option>
                    </select>
                    <input type="number" id="billDiscountInput" placeholder="0" min="0" style="width:65px; font-size:11px; padding:2px 6px; border:1px solid var(--slate-300); border-radius:3px; text-align:right;">
                </div>
            </div>

            <div class="summary-line" style="color:#059669;">
                <span>Total Discount:</span>
                <span id="cartDiscountTotal">- ₹ 0.00</span>
            </div>

            <!-- GST Breakup (Toggled in GST mode) -->
            <div id="gstBreakupBox">
                <div class="summary-line" style="font-size:11px; color:var(--slate-500);">
                    <span>Taxable Value:</span>
                    <span id="cartTaxableValue">₹ 0.00</span>
                </div>
                <div class="summary-line" style="font-size:11px; color:var(--slate-500);">
                    <span>CGST:</span>
                    <span id="cartCgst">₹ 0.00</span>
                </div>
                <div class="summary-line" style="font-size:11px; color:var(--slate-500);">
                    <span>SGST:</span>
                    <span id="cartSgst">₹ 0.00</span>
                </div>
            </div>

            <div class="summary-line" style="font-size:11px; color:var(--slate-400);">
                <span>Round Off:</span>
                <span id="cartRoundOff">₹ 0.00</span>
            </div>

            <div class="summary-line grand-total">
                <span>PAYABLE TOTAL:</span>
                <span id="cartGrandTotal" style="color:var(--primary-800);">₹ 0.00</span>
            </div>

            <!-- Payment Method -->
            <div class="payment-method-selector">
                <div class="pay-btn active" data-method="cash">Cash</div>
                <div class="pay-btn" data-method="upi">UPI / QR</div>
                <div class="pay-btn" data-method="card">Card</div>
                <div class="pay-btn" data-method="mixed">Other</div>
            </div>

            <button id="checkoutBtn" class="btn-checkout" onclick="POS.checkout()" disabled>
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Complete Sale & Print Bill (F9)
            </button>
        </div>
    </div>

    <!-- Mobile Floating Checkout Bar -->
    <div id="posMobileFloatingBar" class="pos-mobile-floating-bar" style="display:none;" onclick="POS.switchTab('cart')">
        <div class="floating-bar-info">
            <span class="floating-bar-badge" id="mobileFloatCount">0 items</span>
            <span class="floating-bar-total" id="mobileFloatTotal">₹ 0.00</span>
        </div>
        <div class="floating-bar-action">
            <span>View Bill & Pay</span>
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
        </div>
    </div>
</div>

<!-- Sale Success Modal -->
<div id="saleSuccessModal" class="modal-backdrop">
    <div class="modal-dialog" style="max-width: 480px; text-align:center;">
        <div class="modal-body" style="padding: 36px 28px;">
            <div style="width:64px; height:64px; border-radius:50%; background:#d1fae5; color:#059669; margin: 0 auto 16px; display:flex; align-items:center; justify-content:center;">
                <svg width="34" height="34" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </div>

            <h3 style="font-size: 20px; font-weight: 800; color: var(--primary-900); margin-bottom: 6px;">
                Sale Completed Successfully!
            </h3>
            <p style="font-size: 13px; color: var(--slate-500); margin-bottom: 16px;">
                Stock deducted and legal invoice record saved to database.
            </p>

            <div style="background: var(--slate-50); border: 1px dashed var(--slate-300); border-radius: var(--radius-sm); padding: 14px; margin-bottom: 20px;">
                <div style="font-size: 11px; text-transform: uppercase; color: var(--slate-400); font-weight: 700;">Invoice Number</div>
                <div id="successInvoiceNo" style="font-size: 18px; font-weight: 800; color: var(--primary-800); margin: 2px 0 6px;">SMW-GST-202610-0001</div>
                <div style="font-size: 11px; text-transform: uppercase; color: var(--slate-400); font-weight: 700;">Total Paid</div>
                <div id="successGrandTotal" style="font-size: 22px; font-weight: 800; color: #059669;">₹ 0.00</div>
                <div id="successCustomerText" style="font-size: 12px; color: var(--slate-600); margin-top: 4px;"></div>
            </div>

            <!-- WhatsApp & Digital Bill Share Section -->
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: var(--radius-sm); padding: 14px; margin-bottom: 20px; text-align: left;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                    <span style="font-size: 12px; font-weight: 800; color: #166534; display:flex; align-items:center; gap:6px;">
                        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24" style="color:#25D366;"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                        Customer Share Options
                    </span>
                    <button type="button" id="successShareBtn" class="btn btn-secondary btn-sm" style="padding:4px 8px; font-size:11px;" title="Share via Mobile Share Sheet">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        Share
                    </button>
                </div>

                <div style="display:flex; flex-direction:column; gap:8px;">
                    <a id="successWhatsAppBtn" href="#" target="_blank" class="btn" style="background:#25D366; color:#ffffff; font-weight:700; display:flex; align-items:center; justify-content:center; gap:8px; padding:10px 14px; text-decoration:none; border-radius:var(--radius-sm); border:none; box-shadow:0 2px 6px rgba(37,211,102,0.3);">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                        <span>Send Bill on WhatsApp</span>
                    </a>

                    <div style="display:flex; gap:8px;">
                        <button type="button" id="successCopyBtn" class="btn btn-secondary btn-sm" style="flex:1; padding:8px; font-size:12px; font-weight:600; display:flex; align-items:center; justify-content:center; gap:6px;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                            Copy Bill Link
                        </button>
                        <a id="successPublicViewBtn" href="#" target="_blank" class="btn btn-secondary btn-sm" style="flex:1; padding:8px; font-size:12px; font-weight:600; display:flex; align-items:center; justify-content:center; gap:6px;">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            View Digital Bill
                        </a>
                    </div>
                </div>
            </div>

            <div style="display:flex; flex-direction:column; gap:10px;">
                <div style="display:flex; gap:10px;">
                    <a id="successPrintBtn" href="#" target="_blank" class="btn btn-primary" style="flex:1; padding:11px;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Print Invoice
                    </a>
                    <a id="successPdfBtn" href="#" target="_blank" class="btn btn-secondary" style="flex:1; padding:11px;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Download PDF
                    </a>
                </div>
                <button class="btn btn-secondary" onclick="App.closeModal('saleSuccessModal')">
                    Start Next Bill
                </button>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
