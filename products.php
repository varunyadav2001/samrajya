<?php
/**
 * Samrajya Mens Wear - Product & Inventory Management
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();

$pageTitle = 'Products & Inventory Management';
$activePage = 'products';

$db = db();
$categoryFilter = trim($_GET['category'] ?? '');
$sizeFilter = trim($_GET['size'] ?? '');
$stockFilter = trim($_GET['stock_status'] ?? '');
$search = strtolower(trim($_GET['q'] ?? ''));

// Available standard garment sizes for quick suggestions
$availableSizes = ['S', 'M', 'L', 'XL', 'XXL', 'XXXL', 'Free Size', '28', '30', '32', '34', '36', '38', '40', '42', '44'];

// Dynamically fetch only categories and sizes that actually exist in the inventory
$allInventory = $db->query('products');
$existingCategories = array_values(array_unique(array_filter(array_map(function($p) {
    return trim($p['category'] ?? '');
}, $allInventory))));
sort($existingCategories);

// Fetch products from database
$products = $db->query('products', function($p) use ($categoryFilter, $sizeFilter, $stockFilter, $search) {
    if ($categoryFilter && ($p['category'] ?? '') !== $categoryFilter) return false;
    if ($sizeFilter && ($p['size'] ?? '') !== $sizeFilter) return false;

    $stock = (int)($p['stockQuantity'] ?? 0);
    $thresh = (int)($p['lowStockThreshold'] ?? 5);

    if ($stockFilter === 'low_stock' && ($stock <= 0 || $stock > $thresh)) return false;
    if ($stockFilter === 'out_of_stock' && $stock > 0) return false;
    if ($stockFilter === 'in_stock' && $stock <= $thresh) return false;

    if ($search) {
        $name = strtolower($p['name'] ?? '');
        $sku = strtolower($p['sku'] ?? '');
        $brand = strtolower($p['brand'] ?? '');
        $color = strtolower($p['color'] ?? '');
        if (strpos($name, $search) === false && 
            strpos($sku, $search) === false && 
            strpos($brand, $search) === false && 
            strpos($color, $search) === false) {
            return false;
        }
    }
    return true;
}, 'updatedAt', 'DESC');

require __DIR__ . '/includes/header.php';
?>

<!-- Header Toolbar -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:20px; font-weight:800; color:var(--primary-900); margin:0;">
            Garment Inventory Catalog
        </h2>
        <p style="font-size:12.5px; color:var(--slate-500); margin-top:2px;">
            Manage size & color variants, real-time stock levels, pricing, and HSN tax codes.
        </p>
    </div>
    <div style="display:flex; gap:10px;">
        <button class="btn btn-primary" onclick="openProductModal()">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add New Product
        </button>
    </div>
</div>

<!-- Filters Bar -->
<div class="card" style="margin-bottom:20px; padding:16px 20px;">
    <form method="GET" action="products.php" style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;">
        <div style="flex:1.5; min-width:200px;">
            <label class="form-label" style="font-size:11.5px;">Search Name / SKU / Barcode</label>
            <input type="text" name="q" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
        </div>

        <div style="flex:1; min-width:140px;">
            <label class="form-label" style="font-size:11.5px;">Category</label>
            <select name="category" class="form-control">
                <option value="">All Categories</option>
                <?php foreach ($existingCategories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $categoryFilter === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex:0.8; min-width:110px;">
            <label class="form-label" style="font-size:11.5px;">Size</label>
            <select name="size" class="form-control">
                <option value="">All Sizes</option>
                <?php foreach ($availableSizes as $sz): ?>
                    <option value="<?= htmlspecialchars($sz) ?>" <?= $sizeFilter === $sz ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sz) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex:1; min-width:140px;">
            <label class="form-label" style="font-size:11.5px;">Stock Status</label>
            <select name="stock_status" class="form-control">
                <option value="">All Stock Levels</option>
                <option value="in_stock" <?= $stockFilter === 'in_stock' ? 'selected' : '' ?>>Healthy Stock</option>
                <option value="low_stock" <?= $stockFilter === 'low_stock' ? 'selected' : '' ?>>Low Stock Alert</option>
                <option value="out_of_stock" <?= $stockFilter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
            </select>
        </div>

        <div style="display:flex; gap:8px;">
            <button type="submit" class="btn btn-secondary">Filter</button>
            <a href="products.php" class="btn btn-secondary" title="Reset Filters">Reset</a>
        </div>
    </form>
</div>

<!-- Products Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
            Product Catalog (<?= count($products) ?> items)
        </div>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Product & SKU</th>
                    <th>Category</th>
                    <th>Size & Color</th>
                    <th style="text-align:right;">Cost (₹)</th>
                    <th style="text-align:right;">Selling (₹)</th>
                    <th style="text-align:center;">GST %</th>
                    <th style="text-align:center;">Stock Level</th>
                    <th style="text-align:center;">Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center; padding:40px; color:var(--slate-400);">
                            No products found matching your filter criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): 
                        $stock = (int)($p['stockQuantity'] ?? 0);
                        $threshold = (int)($p['lowStockThreshold'] ?? 5);
                        $isActive = ($p['status'] ?? '') === 'active';
                    ?>
                        <tr style="<?= !$isActive ? 'opacity:0.6;' : '' ?>">
                            <td>
                                <div style="font-weight:700; color:var(--slate-800); font-size:13.5px;">
                                    <?= htmlspecialchars($p['name']) ?>
                                </div>
                                <div style="font-size:11px; color:var(--slate-400);">
                                    SKU: <strong style="color:var(--primary-800);"><?= htmlspecialchars($p['sku']) ?></strong> 
                                    <?php if (!empty($p['brand'])): ?> &bull; <?= htmlspecialchars($p['brand']) ?><?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-neutral"><?= htmlspecialchars($p['category'] ?? '-') ?></span>
                                <div style="font-size:10px; color:var(--slate-400); margin-top:2px;">HSN: <?= htmlspecialchars($p['hsnCode'] ?? '6205') ?></div>
                            </td>
                            <td>
                                <div style="display:flex; gap:4px; align-items:center;">
                                    <span style="font-weight:700; background:#f1f5f9; padding:2px 6px; border-radius:4px; font-size:11px;"><?= htmlspecialchars($p['size'] ?? '-') ?></span>
                                    <span style="color:var(--slate-600); font-size:11.5px;"><?= htmlspecialchars($p['color'] ?? '-') ?></span>
                                </div>
                            </td>
                            <td style="text-align:right; color:var(--slate-500);">
                                <?= format_currency($p['purchasePrice'] ?? 0) ?>
                            </td>
                            <td style="text-align:right; font-weight:700; color:var(--primary-800);">
                                <?= format_currency($p['sellingPrice'] ?? 0) ?>
                                <?php if (!empty($p['mrp']) && $p['mrp'] > $p['sellingPrice']): ?>
                                    <div style="font-size:10px; text-decoration:line-through; color:var(--slate-400);">MRP <?= format_currency($p['mrp']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center; font-weight:600;">
                                <?= (float)($p['gstRate'] ?? 0) ?>%
                            </td>
                            <td style="text-align:center;">
                                <?php if ($stock <= 0): ?>
                                    <span class="badge badge-danger">Out of Stock</span>
                                <?php elseif ($stock <= $threshold): ?>
                                    <span class="badge badge-warning"><?= $stock ?> pcs (Low)</span>
                                <?php else: ?>
                                    <span class="badge badge-success"><?= $stock ?> pcs</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <span class="badge <?= $isActive ? 'badge-success' : 'badge-neutral' ?>">
                                    <?= strtoupper($p['status'] ?? 'ACTIVE') ?>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <button class="btn btn-secondary btn-sm" onclick='openStockModal(<?= json_encode($p) ?>)' title="Adjust Stock">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
                                        Stock
                                    </button>
                                    <button class="btn btn-secondary btn-sm" onclick='editProduct(<?= json_encode($p) ?>)' title="Edit Product">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </button>
                                    <?php if ($isActive): ?>
                                        <button class="btn btn-secondary btn-sm" onclick="deactivateProduct('<?= $p['productId'] ?>', '<?= htmlspecialchars(addslashes($p['name'])) ?>')" title="Deactivate">
                                            <svg width="14" height="14" fill="none" stroke="#ef4444" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add / Edit Product Modal -->
<div id="productModal" class="modal-backdrop">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <div class="modal-title" id="productModalTitle">Add New Product Variant</div>
            <button class="modal-close" onclick="App.closeModal('productModal')">&times;</button>
        </div>
        <form id="productForm" onsubmit="saveProduct(event)">
            <div class="modal-body">
                <input type="hidden" id="p_id" name="productId">

                <div style="display:grid; grid-template-columns: 2fr 1fr; gap:16px;">
                    <div class="form-group">
                        <label class="form-label">Product Name *</label>
                        <input type="text" id="p_name" class="form-control" required placeholder="e.g. Royal Oxford Formal Shirt">
                    </div>
                    <div class="form-group">
                        <label class="form-label">SKU / Unique Code (Auto if blank)</label>
                        <input type="text" id="p_sku" class="form-control" placeholder="e.g. SMW-SH-WHT-40">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <input type="text" id="p_category" class="form-control" list="categoryDatalist" placeholder="e.g. Shirts, Jeans..." required autocomplete="off">
                        <datalist id="categoryDatalist">
                            <?php foreach ($existingCategories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Brand</label>
                        <input type="text" id="p_brand" class="form-control" placeholder="Brand Name (Optional)">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Size *</label>
                        <input type="text" id="p_size" class="form-control" list="sizeDatalist" placeholder="e.g. M, L, XL, 32..." required autocomplete="off">
                        <datalist id="sizeDatalist">
                            <?php foreach ($availableSizes as $sz): ?>
                                <option value="<?= htmlspecialchars($sz) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Color *</label>
                        <input type="text" id="p_color" class="form-control" placeholder="e.g. Navy Blue, White">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Purchase Price (₹) *</label>
                        <input type="number" step="0.5" min="0" id="p_cost" class="form-control" required placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Selling Price (₹) *</label>
                        <input type="number" step="0.5" min="0" id="p_price" class="form-control" required placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">MRP (₹)</label>
                        <input type="number" step="0.5" min="0" id="p_mrp" class="form-control" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">GST Rate (%) *</label>
                        <select id="p_gst" class="form-control" required>
                            <option value="0">0% (Nil)</option>
                            <option value="5" selected>5% (Apparel &lt; ₹1,000)</option>
                            <option value="12">12% (Apparel &gt; ₹1,000 / Jeans)</option>
                            <option value="18">18% (Accessories / Belts)</option>
                            <option value="28">28% (Luxury items)</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Opening Stock *</label>
                        <input type="number" min="0" id="p_stock" class="form-control" required value="10">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Low Stock Alert *</label>
                        <input type="number" min="1" id="p_threshold" class="form-control" required value="5">
                    </div>
                    <div class="form-group">
                        <label class="form-label">HSN Code</label>
                        <input type="text" id="p_hsn" class="form-control" value="6205">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select id="p_status" class="form-control">
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Fabric & Garment Description (Optional)</label>
                    <textarea id="p_description" class="form-control" rows="2" placeholder="e.g. 100% fine Egyptian cotton shirt with soft wash finish..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('productModal')">Cancel</button>
                <button type="submit" id="saveProductBtn" class="btn btn-primary">Save Product</button>
            </div>
        </form>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div id="stockModal" class="modal-backdrop">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-title">Inventory Stock Adjustment</div>
            <button class="modal-close" onclick="App.closeModal('stockModal')">&times;</button>
        </div>
        <form id="stockForm" onsubmit="adjustStock(event)">
            <div class="modal-body">
                <input type="hidden" id="adj_prodId">
                <div style="background:var(--slate-50); padding:12px; border-radius:var(--radius-sm); margin-bottom:16px;">
                    <div id="adj_prodName" style="font-weight:700; color:var(--primary-900);"></div>
                    <div id="adj_prodSku" style="font-size:11px; color:var(--slate-500);"></div>
                    <div id="adj_currentStock" style="font-size:13px; font-weight:700; color:#059669; margin-top:4px;"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Adjustment Type</label>
                    <select id="adj_type" class="form-control">
                        <option value="add">Add Stock (+) — Stock Arrival / Restock</option>
                        <option value="remove">Remove Stock (-) — Damaged / Correction</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Quantity *</label>
                    <input type="number" min="1" id="adj_qty" class="form-control" required placeholder="Number of units">
                </div>

                <div class="form-group">
                    <label class="form-label">Reason for Adjustment *</label>
                    <input type="text" id="adj_reason" class="form-control" required placeholder="e.g. New consignment arrived from distributor">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('stockModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm Adjustment</button>
            </div>
        </form>
    </div>
</div>

<script>
function openProductModal() {
    document.getElementById('productForm').reset();
    document.getElementById('p_id').value = '';
    document.getElementById('p_category').value = '';
    document.getElementById('p_brand').value = '';
    document.getElementById('p_size').value = '';
    document.getElementById('p_color').value = '';
    document.getElementById('p_hsn').value = '';
    document.getElementById('productModalTitle').innerText = 'Add New Product Variant';
    App.openModal('productModal');
}

function editProduct(p) {
    document.getElementById('p_id').value = p.productId || '';
    document.getElementById('p_name').value = p.name || '';
    document.getElementById('p_sku').value = p.sku || '';
    document.getElementById('p_category').value = p.category || '';
    document.getElementById('p_brand').value = p.brand || '';
    document.getElementById('p_size').value = p.size || '';
    document.getElementById('p_color').value = p.color || '';
    document.getElementById('p_cost').value = p.purchasePrice !== undefined ? p.purchasePrice : '';
    document.getElementById('p_price').value = p.sellingPrice !== undefined ? p.sellingPrice : '';
    document.getElementById('p_mrp').value = p.mrp !== undefined ? p.mrp : '';
    document.getElementById('p_gst').value = p.gstRate !== undefined ? p.gstRate : 0;
    document.getElementById('p_stock').value = p.stockQuantity !== undefined ? p.stockQuantity : 0;
    document.getElementById('p_threshold').value = p.lowStockThreshold || 5;
    document.getElementById('p_hsn').value = p.hsnCode || '';
    document.getElementById('p_status').value = p.status || 'active';
    document.getElementById('p_description').value = p.description || '';

    document.getElementById('productModalTitle').innerText = 'Edit Product — ' + p.name;
    App.openModal('productModal');
}

async function saveProduct(e) {
    e.preventDefault();
    const btn = document.getElementById('saveProductBtn');
    btn.disabled = true;

    const payload = {
        productId: document.getElementById('p_id').value,
        name: document.getElementById('p_name').value.trim(),
        sku: document.getElementById('p_sku').value.trim(),
        category: document.getElementById('p_category').value,
        brand: document.getElementById('p_brand').value.trim(),
        size: document.getElementById('p_size').value,
        color: document.getElementById('p_color').value.trim(),
        purchasePrice: document.getElementById('p_cost').value,
        sellingPrice: document.getElementById('p_price').value,
        mrp: document.getElementById('p_mrp').value,
        gstRate: document.getElementById('p_gst').value,
        stockQuantity: document.getElementById('p_stock').value,
        lowStockThreshold: document.getElementById('p_threshold').value,
        hsnCode: document.getElementById('p_hsn').value.trim(),
        status: document.getElementById('p_status').value,
        description: document.getElementById('p_description').value.trim()
    };

    try {
        const res = await App.api('api/products.php?action=save', {
            method: 'POST',
            body: payload
        });
        App.toast(res.message, 'success');
        App.closeModal('productModal');
        setTimeout(() => location.reload(), 500);
    } catch (err) {
        btn.disabled = false;
    }
}

function openStockModal(p) {
    document.getElementById('stockForm').reset();
    document.getElementById('adj_prodId').value = p.productId;
    document.getElementById('adj_prodName').innerText = p.name;
    document.getElementById('adj_prodSku').innerText = 'SKU: ' + p.sku + ' | Size: ' + p.size;
    document.getElementById('adj_currentStock').innerText = 'Current Available Stock: ' + p.stockQuantity + ' pcs';
    App.openModal('stockModal');
}

async function adjustStock(e) {
    e.preventDefault();
    const payload = {
        productId: document.getElementById('adj_prodId').value,
        type: document.getElementById('adj_type').value,
        quantity: document.getElementById('adj_qty').value,
        reason: document.getElementById('adj_reason').value.trim()
    };

    try {
        const res = await App.api('api/products.php?action=adjust_stock', {
            method: 'POST',
            body: payload
        });
        App.toast(res.message, 'success');
        App.closeModal('stockModal');
        setTimeout(() => location.reload(), 500);
    } catch (err) {}
}

function deactivateProduct(id, name) {
    App.confirm('Deactivate Product', `Are you sure you want to deactivate '${name}'? Historical invoices will retain this product's snapshot.`, async () => {
        try {
            const res = await App.api(`api/products.php?action=delete&id=${encodeURIComponent(id)}`);
            App.toast(res.message, 'success');
            setTimeout(() => location.reload(), 500);
        } catch (err) {}
    });
}

// Auto open modal if URL has ?action=new
if (window.location.search.includes('action=new')) {
    openProductModal();
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
