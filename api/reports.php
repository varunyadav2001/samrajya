<?php
/**
 * Samrajya Mens Wear - Sales & Business Analytics API
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_auth(true);

$action = $_GET['action'] ?? $_POST['action'] ?? 'summary';
$db = db();

switch ($action) {
    case 'summary':
    case 'dashboard_stats':
        $todayStr = date('Y-m-d');
        $thisMonthStr = date('Y-m');

        $invoices = $db->query('invoices', function($inv) {
            return ($inv['status'] ?? '') !== 'cancelled';
        });

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

        // Prepopulate last 7 days for trend chart
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

            // Product aggregation
            foreach ($inv['items'] ?? [] as $item) {
                $pId = $item['productId'] ?? '';
                $qty = (int)($item['quantity'] ?? 0);
                $lineTotal = (float)($item['totalAmount'] ?? 0);
                $pCost = (float)($item['purchasePriceSnapshot'] ?? 0) * $qty;
                $pProfit = $lineTotal - $pCost;

                if (!isset($productSalesMap[$pId])) {
                    $productSalesMap[$pId] = [
                        'productId' => $pId,
                        'name' => $item['productName'] ?? 'Product',
                        'sku' => $item['sku'] ?? '',
                        'category' => $item['category'] ?? 'Garments',
                        'unitsSold' => 0,
                        'totalRevenue' => 0.00,
                        'totalProfit' => 0.00
                    ];
                }
                $productSalesMap[$pId]['unitsSold'] += $qty;
                $productSalesMap[$pId]['totalRevenue'] += $lineTotal;
                $productSalesMap[$pId]['totalProfit'] += $pProfit;
            }
        }

        // Sort top selling products
        usort($productSalesMap, function($a, $b) {
            return ($a['unitsSold'] < $b['unitsSold']) ? 1 : -1;
        });
        $topProducts = array_slice($productSalesMap, 0, 8);

        // Inventory stats
        $products = $db->query('products');
        $totalActiveProducts = 0;
        $totalStockUnits = 0;
        $totalCostValuation = 0.00;
        $totalRetailValuation = 0.00;
        $lowStockCount = 0;
        $lowStockItems = [];

        foreach ($products as $p) {
            $isActive = ($p['status'] ?? '') === 'active';
            $stock = (int)($p['stockQuantity'] ?? 0);
            $threshold = (int)($p['lowStockThreshold'] ?? 5);
            $cost = (float)($p['purchasePrice'] ?? 0);
            $price = (float)($p['sellingPrice'] ?? 0);

            if ($isActive) {
                $totalActiveProducts++;
            }

            $totalStockUnits += $stock;
            $totalCostValuation += ($cost * $stock);
            $totalRetailValuation += ($price * $stock);

            if ($isActive && $stock <= $threshold) {
                $lowStockCount++;
                $lowStockItems[] = [
                    'productId' => $p['productId'] ?? '',
                    'name' => $p['name'] ?? '',
                    'sku' => $p['sku'] ?? '',
                    'size' => $p['size'] ?? '',
                    'color' => $p['color'] ?? '',
                    'stockQuantity' => $stock,
                    'lowStockThreshold' => $threshold,
                    'sellingPrice' => $price
                ];
            }
        }

        // Sort low stock by urgency
        usort($lowStockItems, function($a, $b) {
            return ($a['stockQuantity'] > $b['stockQuantity']) ? 1 : -1;
        });

        // Recent Invoices
        $recentInvoices = array_slice($invoices, 0, 7);

        json_response([
            'success' => true,
            'todaySales' => $todaySales,
            'todayInvoices' => $todayInvoicesCount,
            'monthSales' => $monthSales,
            'monthInvoices' => $monthInvoicesCount,
            'totalInvoices' => count($invoices),
            'totalRevenue' => $totalRevenue,
            'totalProfit' => $totalProfit,
            'totalDiscounts' => $totalDiscounts,
            'totalTaxCollected' => $totalTaxCollected,
            'totalActiveProducts' => $totalActiveProducts,
            'totalStockUnits' => $totalStockUnits,
            'totalCostValuation' => $totalCostValuation,
            'totalRetailValuation' => $totalRetailValuation,
            'potentialGrossProfit' => max(0, $totalRetailValuation - $totalCostValuation),
            'lowStockCount' => $lowStockCount,
            'lowStockItems' => $lowStockItems,
            'topProducts' => $topProducts,
            'recentInvoices' => $recentInvoices,
            'dailyTrend' => $dailyTrendMap
        ]);
        break;

    case 'export_sales_csv':
        $startDate = trim($_GET['start_date'] ?? date('Y-m-01'));
        $endDate = trim($_GET['end_date'] ?? date('Y-m-d'));

        $invoices = $db->query('invoices', function($inv) use ($startDate, $endDate) {
            $d = substr($inv['invoiceDate'] ?? '', 0, 10);
            return $d >= $startDate && $d <= $endDate;
        }, 'invoiceDate', 'ASC');

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="samrajya_sales_' . $startDate . '_to_' . $endDate . '.csv"');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM
        fputs($out, "\xEF\xBB\xBF");
        
        fputcsv($out, ['Invoice Number', 'Date', 'Customer Name', 'Customer Phone', 'Type', 'Subtotal (INR)', 'Discount (INR)', 'Taxable (INR)', 'CGST (INR)', 'SGST (INR)', 'Tax Total (INR)', 'Round Off', 'Grand Total (INR)', 'Gross Profit (INR)', 'Payment Method', 'Status']);

        foreach ($invoices as $inv) {
            fputcsv($out, [
                $inv['invoiceNumber'] ?? '',
                format_datetime($inv['invoiceDate'] ?? '', 'Y-m-d H:i'),
                $inv['customer']['name'] ?? 'Walk-in',
                $inv['customer']['phone'] ?? '',
                strtoupper($inv['invoiceType'] ?? 'GST'),
                number_format((float)($inv['subtotal'] ?? 0), 2, '.', ''),
                number_format((float)($inv['discountTotal'] ?? 0), 2, '.', ''),
                number_format((float)($inv['taxableTotal'] ?? 0), 2, '.', ''),
                number_format((float)($inv['cgstTotal'] ?? 0), 2, '.', ''),
                number_format((float)($inv['sgstTotal'] ?? 0), 2, '.', ''),
                number_format((float)($inv['taxTotal'] ?? 0), 2, '.', ''),
                number_format((float)($inv['roundOff'] ?? 0), 2, '.', ''),
                number_format((float)($inv['grandTotal'] ?? 0), 2, '.', ''),
                number_format((float)($inv['totalProfitEstimated'] ?? 0), 2, '.', ''),
                strtoupper($inv['paymentMethod'] ?? 'CASH'),
                strtoupper($inv['status'] ?? 'COMPLETED')
            ]);
        }
        fclose($out);
        exit;

    case 'export_inventory_csv':
        $products = $db->query('products', null, 'name', 'ASC');

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="samrajya_inventory_valuation_' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");

        fputcsv($out, ['Product Name', 'SKU', 'Category', 'Brand', 'Size', 'Color', 'Cost Price (INR)', 'Selling Price (INR)', 'MRP (INR)', 'GST Rate %', 'HSN Code', 'Stock Units', 'Low Stock Threshold', 'Stock Status', 'Total Cost Value (INR)', 'Total Retail Value (INR)', 'Status']);

        foreach ($products as $p) {
            $stock = (int)($p['stockQuantity'] ?? 0);
            $cost = (float)($p['purchasePrice'] ?? 0);
            $selling = (float)($p['sellingPrice'] ?? 0);
            $threshold = (int)($p['lowStockThreshold'] ?? 5);

            $statusText = $stock <= 0 ? 'Out of Stock' : ($stock <= $threshold ? 'Low Stock' : 'In Stock');

            fputcsv($out, [
                $p['name'] ?? '',
                $p['sku'] ?? '',
                $p['category'] ?? '',
                $p['brand'] ?? '',
                $p['size'] ?? '',
                $p['color'] ?? '',
                number_format($cost, 2, '.', ''),
                number_format($selling, 2, '.', ''),
                number_format((float)($p['mrp'] ?? $selling), 2, '.', ''),
                (float)($p['gstRate'] ?? 0),
                $p['hsnCode'] ?? '',
                $stock,
                $threshold,
                $statusText,
                number_format($cost * $stock, 2, '.', ''),
                number_format($selling * $stock, 2, '.', ''),
                strtoupper($p['status'] ?? 'ACTIVE')
            ]);
        }
        fclose($out);
        exit;

    default:
        json_error('Invalid reports action.');
}
