<?php
/**
 * Samrajya Mens Wear - Admin Dashboard
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();

$pageTitle = 'Dashboard Overview';
$activePage = 'dashboard';

// Fetch live metrics from database
$db = db();
$todayStr = date('Y-m-d');
$thisMonthStr = date('Y-m');

$invoices = $db->query('invoices', function($inv) {
    return ($inv['status'] ?? '') !== 'cancelled';
}, 'invoiceDate', 'DESC');

$todaySales = 0.00;
$monthSales = 0.00;
$todayInvoicesCount = 0;
$monthInvoicesCount = 0;
$totalRevenue = 0.00;
$totalProfit = 0.00;
$totalDiscounts = 0.00;
$totalTaxCollected = 0.00;

$productSalesMap = [];
$dailyTrendMap = [];

for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $dailyTrendMap[$d] = 0.00;
}

foreach ($invoices as $inv) {
    $grandTotal = (float)($inv['grandTotal'] ?? 0);
    $invDate = substr($inv['invoiceDate'] ?? '', 0, 10);
    $invMonth = substr($inv['invoiceDate'] ?? '', 0, 7);
    $profit = (float)($inv['totalProfitEstimated'] ?? 0);
    $discount = (float)($inv['discountTotal'] ?? 0);
    $tax = (float)($inv['taxTotal'] ?? 0);

    $totalRevenue += $grandTotal;
    $totalProfit += $profit;
    $totalDiscounts += $discount;
    $totalTaxCollected += $tax;

    if ($invDate === $todayStr) {
        $todaySales += $grandTotal;
        $todayInvoicesCount++;
    }

    if ($invMonth === $thisMonthStr) {
        $monthSales += $grandTotal;
        $monthInvoicesCount++;
    }

    if (isset($dailyTrendMap[$invDate])) {
        $dailyTrendMap[$invDate] += $grandTotal;
    }

    // Accumulate product sales
    foreach ($inv['items'] ?? [] as $item) {
        $pId = $item['productId'] ?? '';
        $qty = (int)($item['quantity'] ?? 0);
        $lineTotal = (float)($item['totalAmount'] ?? 0);
        $cost = (float)($item['purchasePriceSnapshot'] ?? 0) * $qty;

        if (!isset($productSalesMap[$pId])) {
            $productSalesMap[$pId] = [
                'name' => $item['productName'] ?? 'Product',
                'sku' => $item['sku'] ?? '',
                'category' => $item['category'] ?? 'Garments',
                'units' => 0,
                'revenue' => 0.00,
                'profit' => 0.00
            ];
        }
        $productSalesMap[$pId]['units'] += $qty;
        $productSalesMap[$pId]['revenue'] += $lineTotal;
        $productSalesMap[$pId]['profit'] += ($lineTotal - $cost);
    }
}

// Sort top products
usort($productSalesMap, function($a, $b) {
    return ($a['units'] < $b['units']) ? 1 : -1;
});
$topProducts = array_slice($productSalesMap, 0, 5);

// Inventory stats
$products = $db->query('products');
$totalActiveProducts = 0;
$totalStockUnits = 0;
$totalCostValuation = 0.00;
$totalRetailValuation = 0.00;
$lowStockItems = [];

foreach ($products as $p) {
    $isActive = ($p['status'] ?? '') === 'active';
    $stock = (int)($p['stockQuantity'] ?? 0);
    $threshold = (int)($p['lowStockThreshold'] ?? 5);
    $cost = (float)($p['purchasePrice'] ?? 0);
    $selling = (float)($p['sellingPrice'] ?? 0);

    if ($isActive) {
        $totalActiveProducts++;
    }

    $totalStockUnits += $stock;
    $totalCostValuation += ($cost * $stock);
    $totalRetailValuation += ($selling * $stock);

    if ($isActive && $stock <= $threshold) {
        $lowStockItems[] = $p;
    }
}

usort($lowStockItems, function($a, $b) {
    return ((int)$a['stockQuantity'] > (int)$b['stockQuantity']) ? 1 : -1;
});
$lowStockCount = count($lowStockItems);

$recentInvoices = array_slice($invoices, 0, 6);

$dashSettings = $db->get('settings', 'store_config') ?? [];
$dashShopName = !empty($dashSettings['shopName']) ? $dashSettings['shopName'] : 'साम्राज्य.07';

require __DIR__ . '/includes/header.php';
?>

<!-- Quick Action Banner -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:22px; font-weight:800; color:var(--primary-900); margin:0;">
            Welcome to <?= htmlspecialchars($dashShopName) ?>
        </h2>
        <p style="font-size:13px; color:var(--slate-500); margin-top:2px;">
            Here is your live retail sales, inventory status, and store performance overview.
        </p>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="billing.php" class="btn btn-primary" style="padding:10px 18px; font-size:14px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            Create Bill (POS)
        </a>
        <a href="products.php?action=new" class="btn btn-secondary">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Product
        </a>
    </div>
</div>

<!-- Primary Stats Grid -->
<div class="stats-grid">
    <!-- Today's Sales -->
    <div class="stat-card">
        <div>
            <div class="stat-title">आजची विक्री (Today's Sales)</div>
            <div class="stat-value"><?= format_currency($todaySales) ?></div>
            <div class="stat-subtitle"><?= $todayInvoicesCount ?> bills generated today</div>
        </div>
        <div class="stat-icon icon-emerald">
            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
    </div>

    <!-- Monthly Sales -->
    <div class="stat-card">
        <div>
            <div class="stat-title">या महिन्याची विक्री (Month Sales)</div>
            <div class="stat-value"><?= format_currency($monthSales) ?></div>
            <div class="stat-subtitle"><?= $monthInvoicesCount ?> invoices in <?= date('F') ?></div>
        </div>
        <div class="stat-icon icon-gold">
            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </div>
    </div>

    <!-- Active Products -->
    <div class="stat-card">
        <div>
            <div class="stat-title">उपलब्ध व्हरायटी (Active Products)</div>
            <div class="stat-value"><?= $totalActiveProducts ?></div>
            <div class="stat-subtitle"><?= count($products) ?> catalog items in store</div>
        </div>
        <div class="stat-icon icon-blue">
            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        </div>
    </div>

    <!-- Units in Stock -->
    <div class="stat-card">
        <div>
            <div class="stat-title">शिल्लक कपडे (Stock Quantity)</div>
            <div class="stat-value"><?= number_format($totalStockUnits) ?> <span style="font-size:14px; font-weight:600; color:var(--slate-500);">pcs</span></div>
            <div class="stat-subtitle">Valuation: <?= format_currency($totalRetailValuation) ?></div>
        </div>
        <div class="stat-icon icon-amber">
            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
        </div>
    </div>

    <!-- Low Stock Alert -->
    <div class="stat-card" style="<?= $lowStockCount > 0 ? 'border-color: #fca5a5;' : '' ?>">
        <div>
            <div class="stat-title" style="<?= $lowStockCount > 0 ? 'color:var(--danger-600);' : '' ?>">कमी स्टॉक अलर्ट (Low Stock)</div>
            <div class="stat-value" style="<?= $lowStockCount > 0 ? 'color:var(--danger-600);' : '' ?>"><?= $lowStockCount ?></div>
            <div class="stat-subtitle"><?= $lowStockCount > 0 ? 'Requires immediate restock' : 'All items optimal' ?></div>
        </div>
        <div class="stat-icon icon-rose">
            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </div>
    </div>
</div>

<!-- Secondary Metrics Bar -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:16px; margin-bottom:24px;">
    <?php
    $isProfitable = $totalProfit >= 0;
    $profitColor = $isProfitable ? '#059669' : '#dc2626';
    $profitBadge = $isProfitable 
        ? '<span class="badge badge-success" style="font-size:12px;">Profitable (नफा)</span>' 
        : '<span class="badge" style="background:#fee2e2; color:#b91c1c; font-size:12px;">Loss (तोटा)</span>';
    ?>
    <div style="background:#fff; border:1px solid var(--slate-200); border-radius:var(--radius-md); padding:16px 20px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:11px; text-transform:uppercase; color:var(--slate-500); font-weight:700;">अंदाजे एकूण नफा (Gross Profit)</div>
            <div style="font-size:20px; font-weight:800; color:<?= $profitColor ?>; margin-top:2px;"><?= format_currency($totalProfit) ?></div>
            <div style="font-size:11px; color:var(--slate-400);">विक्री किंमत वजा खरेदी किंमत</div>
        </div>
        <?= $profitBadge ?>
    </div>

    <div style="background:#fff; border:1px solid var(--slate-200); border-radius:var(--radius-md); padding:16px 20px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:11px; text-transform:uppercase; color:var(--slate-500); font-weight:700;">एकूण GST कर (GST Output)</div>
            <div style="font-size:20px; font-weight:800; color:var(--primary-800); margin-top:2px;"><?= format_currency($totalTaxCollected) ?></div>
            <div style="font-size:11px; color:var(--slate-400);"><?= $totalTaxCollected > 0 ? 'CGST + SGST tax output' : 'Non-GST Retail Mode (करमुक्त)' ?></div>
        </div>
        <span class="badge <?= $totalTaxCollected > 0 ? 'badge-info' : 'badge-neutral' ?>" style="font-size:12px;">
            <?= $totalTaxCollected > 0 ? 'Tax Collected' : 'Retail Mode' ?>
        </span>
    </div>

    <div style="background:#fff; border:1px solid var(--slate-200); border-radius:var(--radius-md); padding:16px 20px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:11px; text-transform:uppercase; color:var(--slate-500); font-weight:700;">एकूण डिस्काउंट (Total Discounts)</div>
            <div style="font-size:20px; font-weight:800; color:var(--gold-600); margin-top:2px;"><?= format_currency($totalDiscounts) ?></div>
            <div style="font-size:11px; color:var(--slate-400);">ग्राहकांना दिलेली एकूण बचत</div>
        </div>
        <span class="badge badge-warning" style="font-size:12px;">Savings</span>
    </div>
</div>

<!-- Charts & Highlights Row -->
<div style="display:grid; grid-template-columns: 2fr 1fr; gap:20px; margin-bottom:24px;">
    <!-- Sales Revenue Trend -->
    <div class="card" style="margin-bottom:0;">
        <div class="card-header">
            <div class="card-title">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                Last 7 Days Sales Trend
            </div>
            <span style="font-size:12px; color:var(--slate-500);">Live figures</span>
        </div>
        <div class="card-body">
            <!-- SVG Trend Bar Chart -->
            <div style="display:flex; align-items:flex-end; justify-content:space-between; height:180px; padding:10px 10px 0; border-bottom:1px solid var(--slate-200); gap:12px;">
                <?php
                $maxDaily = max(100, max(array_values($dailyTrendMap)));
                foreach ($dailyTrendMap as $dayDate => $dayAmt):
                    $heightPercent = max(6, round(($dayAmt / $maxDaily) * 100));
                    $dayLabel = date('D (d)', strtotime($dayDate));
                ?>
                    <div style="flex:1; display:flex; flex-direction:column; align-items:center; gap:6px;">
                        <div style="font-size:10px; font-weight:700; color:var(--primary-800);">
                            <?= $dayAmt > 0 ? ('₹' . number_format($dayAmt, 0)) : '' ?>
                        </div>
                        <div style="width:100%; max-width:42px; height:<?= $heightPercent ?>%; background:linear-gradient(180deg, var(--primary-600), var(--primary-800)); border-radius:4px 4px 0 0; transition:height 0.3s;" title="<?= $dayDate ?>: ₹<?= number_format($dayAmt, 2) ?>"></div>
                        <div style="font-size:10.5px; color:var(--slate-500); font-weight:600; white-space:nowrap;"><?= $dayLabel ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Top Selling Products -->
    <div class="card" style="margin-bottom:0;">
        <div class="card-header">
            <div class="card-title">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                Best Selling Items
            </div>
            <a href="reports.php" style="font-size:11.5px; color:var(--primary-700); text-decoration:none; font-weight:600;">View All</a>
        </div>
        <div class="card-body" style="padding:12px 16px;">
            <?php if (empty($topProducts)): ?>
                <div style="text-align:center; padding:30px 10px; color:var(--slate-400); font-size:13px;">
                    No sales recorded yet.<br>
                    <a href="billing.php" style="color:var(--primary-700); font-weight:600;">Create your first bill</a>
                </div>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <?php foreach ($topProducts as $idx => $tp): ?>
                        <div style="display:flex; align-items:center; justify-content:space-between; padding-bottom:8px; border-bottom:1px solid var(--slate-100);">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <span style="width:22px; height:22px; border-radius:50%; background:var(--primary-50); color:var(--primary-800); font-size:11px; font-weight:bold; display:flex; align-items:center; justify-content:center;">
                                    <?= $idx + 1 ?>
                                </span>
                                <div>
                                    <div style="font-size:12.5px; font-weight:700; color:var(--slate-800);"><?= htmlspecialchars($tp['name']) ?></div>
                                    <div style="font-size:10px; color:var(--slate-400);">SKU: <?= htmlspecialchars($tp['sku']) ?></div>
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:12px; font-weight:700; color:var(--primary-800);"><?= $tp['units'] ?> pcs</div>
                                <div style="font-size:10px; color:var(--slate-500);"><?= format_currency($tp['revenue']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bottom Row: Recent Invoices & Low Stock -->
<div style="display:grid; grid-template-columns: 3fr 2fr; gap:20px;">
    <!-- Recent Invoices -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Recent Invoices
            </div>
            <a href="invoices.php" style="font-size:12px; color:var(--primary-700); font-weight:600; text-decoration:none;">View All Invoices &rarr;</a>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date & Time</th>
                        <th>Customer</th>
                        <th>Mode</th>
                        <th style="text-align:right;">Amount (₹)</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentInvoices)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:30px; color:var(--slate-400);">No invoices recorded yet. Start POS billing to generate bills!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentInvoices as $inv): ?>
                            <tr>
                                <td>
                                    <span style="font-weight:700; color:var(--primary-900);"><?= htmlspecialchars($inv['invoiceNumber']) ?></span>
                                    <div>
                                        <span class="badge <?= ($inv['invoiceType'] ?? '') === 'gst' ? 'badge-success' : 'badge-neutral' ?>" style="font-size:9.5px; padding:1px 5px;">
                                            <?= strtoupper($inv['invoiceType'] ?? 'GST') ?>
                                        </span>
                                    </div>
                                </td>
                                <td><?= format_datetime($inv['invoiceDate'], 'd M, h:i A') ?></td>
                                <td>
                                    <div style="font-weight:600;"><?= htmlspecialchars($inv['customer']['name'] ?? 'Walk-in') ?></div>
                                    <div style="font-size:10.5px; color:var(--slate-400);"><?= htmlspecialchars($inv['customer']['phone'] ?? '') ?></div>
                                </td>
                                <td>
                                    <span class="badge badge-neutral"><?= strtoupper($inv['paymentMethod'] ?? 'Cash') ?></span>
                                </td>
                                <td style="text-align:right; font-weight:800; color:var(--primary-900);">
                                    <?= format_currency($inv['grandTotal']) ?>
                                </td>
                                <td style="text-align:center;">
                                    <a href="api/invoices.php?action=pdf&id=<?= urlencode($inv['invoiceId']) ?>" target="_blank" class="btn btn-secondary btn-sm" title="View PDF">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Low Stock Products List -->
    <div class="card">
        <div class="card-header">
            <div class="card-title" style="color:var(--danger-600);">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Low Stock Warning (<?= $lowStockCount ?>)
            </div>
            <a href="products.php?stock_status=low_stock" style="font-size:12px; color:var(--danger-600); font-weight:600; text-decoration:none;">Manage Stock &rarr;</a>
        </div>
        <div class="card-body" style="padding:12px 16px;">
            <?php if (empty($lowStockItems)): ?>
                <div style="text-align:center; padding:30px 10px; color:var(--slate-400); font-size:13px;">
                    <svg width="36" height="36" fill="none" stroke="#10b981" viewBox="0 0 24 24" style="margin:0 auto 8px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div style="font-weight:600; color:var(--slate-700);">Stock is Healthy!</div>
                    <div>No products below threshold level.</div>
                </div>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <?php foreach (array_slice($lowStockItems, 0, 5) as $ls): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 10px; background:#fff1f2; border:1px solid #fecdd3; border-radius:var(--radius-sm);">
                            <div>
                                <div style="font-size:12.5px; font-weight:700; color:#9f1239;"><?= htmlspecialchars($ls['name']) ?></div>
                                <div style="font-size:10px; color:#be123c;">SKU: <?= htmlspecialchars($ls['sku']) ?> &bull; Size: <?= htmlspecialchars($ls['size']) ?></div>
                            </div>
                            <div style="text-align:right;">
                                <span class="badge badge-danger" style="font-size:11px;">
                                    <?= (int)$ls['stockQuantity'] ?> left
                                </span>
                                <div style="font-size:9.5px; color:#be123c; margin-top:2px;">Threshold: <?= (int)$ls['lowStockThreshold'] ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
