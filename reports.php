<?php
/**
 * Samrajya Mens Wear - Sales & Business Analytics Reports
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_auth();

$pageTitle = 'Sales & Financial Reports';
$activePage = 'reports';

$db = db();
$filterRange = trim($_GET['range'] ?? 'this_month');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

$today = date('Y-m-d');

if ($filterRange === 'today') {
    $startDate = $today;
    $endDate = $today;
} elseif ($filterRange === 'this_week') {
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = $today;
} elseif ($filterRange === 'this_month') {
    $startDate = date('Y-m-01');
    $endDate = $today;
} elseif ($filterRange === 'last_month') {
    $startDate = date('Y-m-01', strtotime('first day of last month'));
    $endDate = date('Y-m-t', strtotime('last day of last month'));
} elseif ($filterRange === 'custom') {
    if (!$startDate) $startDate = date('Y-m-01');
    if (!$endDate) $endDate = $today;
} else {
    $startDate = date('Y-m-01');
    $endDate = $today;
}

// Fetch invoices within date range
$invoices = $db->query('invoices', function($inv) use ($startDate, $endDate) {
    if (($inv['status'] ?? '') === 'cancelled') return false;
    $d = substr($inv['invoiceDate'] ?? '', 0, 10);
    return $d >= $startDate && $d <= $endDate;
}, 'invoiceDate', 'DESC');

// Aggregate metrics
$totalSales = 0.00;
$totalTaxable = 0.00;
$totalCgst = 0.00;
$totalSgst = 0.00;
$totalIgst = 0.00;
$totalTax = 0.00;
$totalDiscounts = 0.00;
$totalPurchaseCost = 0.00;
$totalGrossProfit = 0.00;

$gstCount = 0;
$nongstCount = 0;

$productMap = [];

foreach ($invoices as $inv) {
    $grand = (float)($inv['grandTotal'] ?? 0);
    $taxable = (float)($inv['taxableTotal'] ?? 0);
    $cgst = (float)($inv['cgstTotal'] ?? 0);
    $sgst = (float)($inv['sgstTotal'] ?? 0);
    $igst = (float)($inv['igstTotal'] ?? 0);
    $tax = (float)($inv['taxTotal'] ?? 0);
    $disc = (float)($inv['discountTotal'] ?? 0);
    $cost = (float)($inv['totalPurchaseCost'] ?? 0);
    $profit = (float)($inv['totalProfitEstimated'] ?? ($taxable - $cost));

    $totalSales += $grand;
    $totalTaxable += $taxable;
    $totalCgst += $cgst;
    $totalSgst += $sgst;
    $totalIgst += $igst;
    $totalTax += $tax;
    $totalDiscounts += $disc;
    $totalPurchaseCost += $cost;
    $totalGrossProfit += $profit;

    if (($inv['invoiceType'] ?? '') === 'gst') {
        $gstCount++;
    } else {
        $nongstCount++;
    }

    foreach ($inv['items'] ?? [] as $item) {
        $pId = $item['productId'] ?? '';
        $qty = (int)($item['quantity'] ?? 0);
        $lineRev = (float)($item['totalAmount'] ?? 0);
        $lineCost = (float)($item['purchasePriceSnapshot'] ?? 0) * $qty;
        $lineProfit = $lineRev - $lineCost;

        if (!isset($productMap[$pId])) {
            $productMap[$pId] = [
                'name' => $item['productName'] ?? 'Product',
                'sku' => $item['sku'] ?? '',
                'category' => $item['category'] ?? 'Garments',
                'units' => 0,
                'revenue' => 0.00,
                'cost' => 0.00,
                'profit' => 0.00
            ];
        }
        $productMap[$pId]['units'] += $qty;
        $productMap[$pId]['revenue'] += $lineRev;
        $productMap[$pId]['cost'] += $lineCost;
        $productMap[$pId]['profit'] += $lineProfit;
    }
}

usort($productMap, function($a, $b) {
    return ($a['revenue'] < $b['revenue']) ? 1 : -1;
});

// Inventory Valuation
$products = $db->query('products');
$totalStockPcs = 0;
$totalValuationCost = 0.00;
$totalValuationRetail = 0.00;

foreach ($products as $p) {
    $stock = (int)($p['stockQuantity'] ?? 0);
    $cost = (float)($p['purchasePrice'] ?? 0);
    $retail = (float)($p['sellingPrice'] ?? 0);
    $totalStockPcs += $stock;
    $totalValuationCost += ($cost * $stock);
    $totalValuationRetail += ($retail * $stock);
}

require __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:20px; font-weight:800; color:var(--primary-900); margin:0;">
            Sales Analytics & Tax Summary
        </h2>
        <p style="font-size:12.5px; color:var(--slate-500); margin-top:2px;">
            Accurate gross profits derived from exact purchase-cost snapshots at time of sale.
        </p>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="api/reports.php?action=export_sales_csv&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" class="btn btn-secondary">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Export Sales CSV
        </a>
        <a href="api/reports.php?action=export_inventory_csv" class="btn btn-secondary">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Export Valuation CSV
        </a>
    </div>
</div>

<!-- Date Preset Selector -->
<div class="card" style="margin-bottom:20px; padding:14px 20px;">
    <form method="GET" action="reports.php" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
        <div style="display:flex; gap:8px; align-items:center;">
            <a href="reports.php?range=today" class="btn <?= $filterRange === 'today' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Today</a>
            <a href="reports.php?range=this_week" class="btn <?= $filterRange === 'this_week' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">This Week</a>
            <a href="reports.php?range=this_month" class="btn <?= $filterRange === 'this_month' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">This Month</a>
            <a href="reports.php?range=last_month" class="btn <?= $filterRange === 'last_month' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Last Month</a>
        </div>

        <div style="display:flex; gap:10px; align-items:center;">
            <input type="hidden" name="range" value="custom">
            <span style="font-size:12px; color:var(--slate-500);">Custom:</span>
            <input type="date" name="start_date" class="form-control" style="width:140px; padding:5px 8px; font-size:12px;" value="<?= htmlspecialchars($startDate) ?>">
            <span style="font-size:12px; color:var(--slate-400);">to</span>
            <input type="date" name="end_date" class="form-control" style="width:140px; padding:5px 8px; font-size:12px;" value="<?= htmlspecialchars($endDate) ?>">
            <button type="submit" class="btn btn-secondary btn-sm">Apply</button>
        </div>
    </form>
</div>

<!-- Financial Summary Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div>
            <div class="stat-title">Period Sales Revenue</div>
            <div class="stat-value"><?= format_currency($totalSales) ?></div>
            <div class="stat-subtitle"><?= count($invoices) ?> bills (<?= $gstCount ?> GST / <?= $nongstCount ?> Non-GST)</div>
        </div>
        <div class="stat-icon icon-emerald">
            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-title">Gross Profit</div>
            <div class="stat-value" style="color:#059669;"><?= format_currency($totalGrossProfit) ?></div>
            <div class="stat-subtitle">Purchase Cost: <?= format_currency($totalPurchaseCost) ?></div>
        </div>
        <div class="stat-icon icon-emerald">
            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-title">Total GST Collected</div>
            <div class="stat-value"><?= format_currency($totalTax) ?></div>
            <div class="stat-subtitle">CGST: <?= format_currency($totalCgst) ?> | SGST: <?= format_currency($totalSgst) ?></div>
        </div>
        <div class="stat-icon icon-blue">
            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-title">Total Discounts Given</div>
            <div class="stat-value" style="color:var(--gold-600);"><?= format_currency($totalDiscounts) ?></div>
            <div class="stat-subtitle">Item & bill level promotions</div>
        </div>
        <div class="stat-icon icon-gold">
            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
        </div>
    </div>
</div>

<!-- Inventory Valuation Overview Card -->
<div class="card" style="background:linear-gradient(135deg, #0f392b, #1b5e48); color:#fff; padding:20px 24px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <div>
            <div style="font-size:12px; text-transform:uppercase; letter-spacing:1px; color:var(--gold-400); font-weight:700;">
                Current Inventory Valuation Snapshot
            </div>
            <div style="font-size:24px; font-weight:800; margin-top:4px;">
                <?= format_currency($totalValuationRetail) ?> <span style="font-size:14px; font-weight:500; opacity:0.8;">(Retail Value)</span>
            </div>
            <div style="font-size:12.5px; opacity:0.85; margin-top:2px;">
                Total Cost Investment: <?= format_currency($totalValuationCost) ?> across <?= number_format($totalStockPcs) ?> units in stock.
            </div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:11.5px; opacity:0.8;">Potential Unrealized Gross Profit</div>
            <div style="font-size:20px; font-weight:800; color:#34d399;">
                <?= format_currency(max(0, $totalValuationRetail - $totalValuationCost)) ?>
            </div>
            <div style="font-size:11px; opacity:0.75;">
                Margin: <?= $totalValuationRetail > 0 ? round((($totalValuationRetail - $totalValuationCost) / $totalValuationRetail) * 100, 1) : 0 ?>%
            </div>
        </div>
    </div>
</div>

<!-- Product-Wise Sales Breakdown Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Product-Wise Sales & Profit Performance
        </div>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Product Name & SKU</th>
                    <th>Category</th>
                    <th style="text-align:center;">Units Sold</th>
                    <th style="text-align:right;">Total Revenue (₹)</th>
                    <th style="text-align:right;">Total Cost (₹)</th>
                    <th style="text-align:right;">Estimated Profit (₹)</th>
                    <th style="text-align:center;">Margin %</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($productMap)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding:30px; color:var(--slate-400);">
                            No product sales recorded in this selected period.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($productMap as $pm): 
                        $margin = $pm['revenue'] > 0 ? round(($pm['profit'] / $pm['revenue']) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($pm['name']) ?></strong>
                                <div style="font-size:11px; color:var(--slate-400);">SKU: <?= htmlspecialchars($pm['sku']) ?></div>
                            </td>
                            <td><span class="badge badge-neutral"><?= htmlspecialchars($pm['category']) ?></span></td>
                            <td style="text-align:center; font-weight:700; color:var(--primary-800);"><?= $pm['units'] ?> pcs</td>
                            <td style="text-align:right; font-weight:700;"><?= format_currency($pm['revenue']) ?></td>
                            <td style="text-align:right; color:var(--slate-500);"><?= format_currency($pm['cost']) ?></td>
                            <td style="text-align:right; font-weight:800; color:#059669;"><?= format_currency($pm['profit']) ?></td>
                            <td style="text-align:center;">
                                <span class="badge badge-success"><?= $margin ?>%</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
