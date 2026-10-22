<?php
/**
 * Samrajya Mens Wear - POS Billing & Invoice Processing API
 * Recalculates all financial figures server-side with integer paise precision.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_auth(true);

$action = $_GET['action'] ?? $_POST['action'] ?? 'checkout';
$db = db();

switch ($action) {
    case 'checkout':
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?? $_POST;

        $items = $data['items'] ?? [];
        if (empty($items) || !is_array($items)) {
            json_error('Cart is empty. Please add items before checking out.');
        }

        $invoiceType = ($data['invoiceType'] ?? 'gst') === 'nongst' ? 'nongst' : 'gst';
        $paymentMethod = strtolower(trim($data['paymentMethod'] ?? 'cash'));
        if (!in_array($paymentMethod, ['cash', 'upi', 'card', 'mixed', 'other'])) {
            $paymentMethod = 'cash';
        }

        $isInterstate = !empty($data['isInterstate']);
        $billDiscountType = ($data['billDiscountType'] ?? 'fixed') === 'percentage' ? 'percentage' : 'fixed';
        $billDiscountValue = max(0, (float)($data['billDiscountValue'] ?? 0));
        $notes = trim($data['notes'] ?? '');

        // Customer details
        $customerData = $data['customer'] ?? [];
        $rawCustName = trim($customerData['name'] ?? '');
        $customerPhone = preg_replace('/[^0-9]/', '', (string)($customerData['phone'] ?? ''));
        $customerEmail = trim($customerData['email'] ?? '');
        $customerAddress = trim($customerData['address'] ?? '');
        $customerGstin = strtoupper(trim($customerData['gstin'] ?? ''));

        if (!empty($rawCustName) && strtolower($rawCustName) !== 'walk-in customer') {
            $customerName = $rawCustName;
        } elseif (!empty($customerPhone)) {
            $customerName = 'Customer';
        } else {
            $customerName = 'Walk-in Customer';
        }

        // 1. Validate Stock for all cart items and fetch canonical product records
        $validatedItems = [];
        $totalPurchaseCostPaise = 0;
        $subtotalPaise = 0;
        $itemDiscountTotalPaise = 0;

        foreach ($items as $idx => $cartItem) {
            $prodId = trim($cartItem['productId'] ?? '');
            $qty = (int)($cartItem['quantity'] ?? 1);
            if ($qty <= 0) {
                json_error("Invalid quantity for line item #" . ($idx + 1));
            }

            $product = $db->get('products', $prodId);
            if (!$product) {
                json_error("Product not found in inventory (ID: {$prodId}).");
            }

            $currentStock = (int)($product['stockQuantity'] ?? 0);
            if ($currentStock < $qty) {
                json_error("Insufficient stock for '{$product['name']}' (SKU: {$product['sku']}). Requested: {$qty}, Available: {$currentStock}.");
            }

            $unitPrice = (float)($product['sellingPrice'] ?? 0);
            $purchasePrice = (float)($product['purchasePrice'] ?? 0);
            $gstRate = ($invoiceType === 'gst') ? (float)($product['gstRate'] ?? 5) : 0;
            $hsnCode = $product['hsnCode'] ?? '6205';

            // Item discount
            $discountPerUnit = max(0, (float)($cartItem['discountAmount'] ?? 0));
            if ($discountPerUnit > $unitPrice) {
                $discountPerUnit = $unitPrice;
            }

            $itemGrossPaise = to_paise($unitPrice * $qty);
            $itemDiscPaise = to_paise($discountPerUnit * $qty);
            $itemNetPaise = max(0, $itemGrossPaise - $itemDiscPaise);

            $subtotalPaise += $itemGrossPaise;
            $itemDiscountTotalPaise += $itemDiscPaise;
            $totalPurchaseCostPaise += to_paise($purchasePrice * $qty);

            $validatedItems[] = [
                'productId' => $prodId,
                'productName' => $product['name'],
                'sku' => $product['sku'],
                'category' => $product['category'] ?? '',
                'brand' => $product['brand'] ?? '',
                'size' => $product['size'] ?? '-',
                'color' => $product['color'] ?? '-',
                'hsnCode' => $hsnCode,
                'quantity' => $qty,
                'unitPrice' => $unitPrice,
                'purchasePriceSnapshot' => $purchasePrice, // Crucial for exact historical profit!
                'discountAmount' => $discountPerUnit,
                'itemGrossPaise' => $itemGrossPaise,
                'itemDiscPaise' => $itemDiscPaise,
                'itemNetPaise' => $itemNetPaise,
                'gstRate' => $gstRate,
                'currentStock' => $currentStock
            ];
        }

        // 2. Bill-level discount calculation
        $billDiscountPaise = 0;
        if ($billDiscountType === 'percentage') {
            $pct = min(100, max(0, $billDiscountValue));
            $billDiscountPaise = (int)round(($subtotalPaise - $itemDiscountTotalPaise) * ($pct / 100));
        } else {
            $billDiscountPaise = to_paise($billDiscountValue);
        }

        $totalDiscountPaise = $itemDiscountTotalPaise + $billDiscountPaise;
        $netAfterDiscountPaise = max(0, $subtotalPaise - $totalDiscountPaise);

        // 3. Tax calculations per line item with integer precision
        $taxableTotalPaise = 0;
        $cgstTotalPaise = 0;
        $sgstTotalPaise = 0;
        $igstTotalPaise = 0;
        $finalLineItems = [];

        // Distribute bill discount proportionally across items
        $sumNetItems = array_sum(array_column($validatedItems, 'itemNetPaise'));

        foreach ($validatedItems as $item) {
            $ratio = ($sumNetItems > 0) ? ($item['itemNetPaise'] / $sumNetItems) : (1 / count($validatedItems));
            $proportionalBillDisc = (int)round($billDiscountPaise * $ratio);
            $effectiveItemNetPaise = max(0, $item['itemNetPaise'] - $proportionalBillDisc);

            $rate = $item['gstRate'];
            $lineCgstPaise = 0;
            $lineSgstPaise = 0;
            $lineIgstPaise = 0;
            $lineTaxPaise = 0;

            if ($invoiceType === 'gst' && $rate > 0) {
                // Retail tax calculation: Taxable value + GST
                $lineTaxPaise = (int)round($effectiveItemNetPaise * ($rate / 100));
                if ($isInterstate) {
                    $lineIgstPaise = $lineTaxPaise;
                } else {
                    $lineCgstPaise = (int)round($lineTaxPaise / 2);
                    $lineSgstPaise = $lineTaxPaise - $lineCgstPaise;
                }
            }

            $lineTotalPaise = $effectiveItemNetPaise + $lineTaxPaise;

            $taxableTotalPaise += $effectiveItemNetPaise;
            $cgstTotalPaise += $lineCgstPaise;
            $sgstTotalPaise += $lineSgstPaise;
            $igstTotalPaise += $lineIgstPaise;

            $finalLineItems[] = [
                'productId' => $item['productId'],
                'productName' => $item['productName'],
                'sku' => $item['sku'],
                'category' => $item['category'],
                'brand' => $item['brand'],
                'size' => $item['size'],
                'color' => $item['color'],
                'hsnCode' => $item['hsnCode'],
                'quantity' => $item['quantity'],
                'unitPrice' => $item['unitPrice'],
                'purchasePriceSnapshot' => $item['purchasePriceSnapshot'],
                'discountAmount' => $item['discountAmount'],
                'taxableValue' => from_paise($effectiveItemNetPaise),
                'gstRate' => $rate,
                'cgstRate' => $isInterstate ? 0 : ($rate / 2),
                'cgstAmount' => from_paise($lineCgstPaise),
                'sgstRate' => $isInterstate ? 0 : ($rate / 2),
                'sgstAmount' => from_paise($lineSgstPaise),
                'igstRate' => $isInterstate ? $rate : 0,
                'igstAmount' => from_paise($lineIgstPaise),
                'totalAmount' => from_paise($lineTotalPaise)
            ];
        }

        $taxTotalPaise = $cgstTotalPaise + $sgstTotalPaise + $igstTotalPaise;
        $exactGrandTotalPaise = $taxableTotalPaise + $taxTotalPaise;

        // Round to nearest Rupee
        $exactRupees = from_paise($exactGrandTotalPaise);
        $roundedRupees = round($exactRupees);
        $roundOff = round($roundedRupees - $exactRupees, 2);

        // 4. Generate Concurrency-Safe Sequential Invoice Number
        $invoiceNumber = $db->nextInvoiceNumber($invoiceType);
        $invoiceId = 'INV_' . preg_replace('/[^a-zA-Z0-9]/', '', $invoiceNumber);

        $now = date('c');
        $currentUser = get_authenticated_user();

        // 5. Customer Record Handling
        $customerId = trim($customerData['customerId'] ?? '');
        if (empty($customerId)) {
            $customerId = 'CUST_WALKIN';
        }

        if (!empty($customerPhone)) {
            $existingCust = $db->query('customers', function($c) use ($customerPhone) {
                return ($c['phone'] ?? '') === $customerPhone;
            });

            if (!empty($existingCust)) {
                $customerId = $existingCust[0]['customerId'];
                $updateData = [
                    'totalPurchases' => ((float)($existingCust[0]['totalPurchases'] ?? 0)) + $roundedRupees,
                    'totalInvoices' => ((int)($existingCust[0]['totalInvoices'] ?? 0)) + 1,
                    'lastPurchaseDate' => $now,
                    'updatedAt' => $now
                ];
                // Update name if user explicitly specified a non-generic name
                if ($customerName !== 'Walk-in Customer' && $customerName !== 'Customer') {
                    $updateData['name'] = $customerName;
                } else {
                    $customerName = $existingCust[0]['name'] ?? $customerName;
                }
                $db->set('customers', $customerId, $updateData, true);
            } else {
                $customerId = 'CUST_' . $customerPhone;
                $savedName = ($customerName !== 'Walk-in Customer') ? $customerName : 'Customer';
                $db->set('customers', $customerId, [
                    'customerId' => $customerId,
                    'name' => $savedName,
                    'phone' => $customerPhone,
                    'email' => $customerEmail,
                    'address' => $customerAddress,
                    'gstin' => $customerGstin,
                    'totalPurchases' => $roundedRupees,
                    'totalInvoices' => 1,
                    'lastPurchaseDate' => $now,
                    'createdAt' => $now,
                    'updatedAt' => $now
                ]);
                $customerName = $savedName;
            }
        } elseif (!empty($customerName) && strtolower($customerName) !== 'walk-in customer') {
            // Customer provided name without phone
            $existingCust = $db->query('customers', function($c) use ($customerName) {
                return strtolower($c['name'] ?? '') === strtolower($customerName);
            });
            if (!empty($existingCust)) {
                $customerId = $existingCust[0]['customerId'];
                $db->set('customers', $customerId, [
                    'totalPurchases' => ((float)($existingCust[0]['totalPurchases'] ?? 0)) + $roundedRupees,
                    'totalInvoices' => ((int)($existingCust[0]['totalInvoices'] ?? 0)) + 1,
                    'lastPurchaseDate' => $now,
                    'updatedAt' => $now
                ], true);
            } else {
                $customerId = 'CUST_' . substr(md5($customerName . time()), 0, 10);
                $db->set('customers', $customerId, [
                    'customerId' => $customerId,
                    'name' => $customerName,
                    'phone' => '',
                    'email' => $customerEmail,
                    'address' => $customerAddress,
                    'gstin' => $customerGstin,
                    'totalPurchases' => $roundedRupees,
                    'totalInvoices' => 1,
                    'lastPurchaseDate' => $now,
                    'createdAt' => $now,
                    'updatedAt' => $now
                ]);
            }
        }

        // Calculate Gross Profit Snapshot
        $totalPurchaseCost = from_paise($totalPurchaseCostPaise);
        $taxableTotal = from_paise($taxableTotalPaise);
        $estimatedProfit = round($taxableTotal - $totalPurchaseCost, 2);

        // 6. Build Master Invoice Snapshot Document
        $invoiceDoc = [
            'invoiceId' => $invoiceId,
            'invoiceNumber' => $invoiceNumber,
            'invoiceType' => $invoiceType,
            'invoiceDate' => $now,
            'customer' => [
                'customerId' => $customerId,
                'name' => $customerName ?: 'Walk-in Customer',
                'phone' => $customerPhone,
                'email' => $customerEmail,
                'address' => $customerAddress,
                'gstin' => $customerGstin
            ],
            'items' => $finalLineItems,
            'subtotal' => from_paise($subtotalPaise),
            'discountTotal' => from_paise($totalDiscountPaise),
            'billDiscountType' => $billDiscountType,
            'billDiscountValue' => $billDiscountValue,
            'billDiscountAmount' => from_paise($billDiscountPaise),
            'taxableTotal' => $taxableTotal,
            'cgstTotal' => from_paise($cgstTotalPaise),
            'sgstTotal' => from_paise($sgstTotalPaise),
            'igstTotal' => from_paise($igstTotalPaise),
            'taxTotal' => from_paise($taxTotalPaise),
            'roundOff' => $roundOff,
            'grandTotal' => $roundedRupees,
            'totalPurchaseCost' => $totalPurchaseCost,
            'totalProfitEstimated' => $estimatedProfit,
            'paymentMethod' => $paymentMethod,
            'paymentStatus' => 'paid',
            'status' => 'completed',
            'isInterstate' => $isInterstate,
            'notes' => $notes,
            'createdBy' => $currentUser['name'] ?? 'Admin',
            'createdByUid' => $currentUser['uid'] ?? 'admin_uid',
            'createdAt' => $now,
            'updatedAt' => $now
        ];

        // 7. Save Invoice to Database
        $db->set('invoices', $invoiceId, $invoiceDoc);

        // 8. Deduct Stock and Record Inventory Movement Ledgers
        foreach ($validatedItems as $vItem) {
            $pId = $vItem['productId'];
            $qtySold = $vItem['quantity'];
            $newStock = $vItem['currentStock'] - $qtySold;

            $db->set('products', $pId, [
                'stockQuantity' => $newStock,
                'updatedAt' => $now
            ], true);

            $movId = 'MOV_' . uniqid();
            $db->set('inventory_movements', $movId, [
                'movementId' => $movId,
                'productId' => $pId,
                'sku' => $vItem['sku'],
                'productName' => $vItem['productName'],
                'type' => 'sale',
                'quantity' => $qtySold,
                'previousStock' => $vItem['currentStock'],
                'newStock' => $newStock,
                'referenceId' => $invoiceNumber,
                'reason' => "POS Sale ({$invoiceNumber})",
                'createdBy' => $currentUser['name'] ?? 'Cashier',
                'timestamp' => $now
            ]);
        }

        // 9. Record Audit Log
        $logId = 'LOG_' . uniqid();
        $db->set('audit_logs', $logId, [
            'logId' => $logId,
            'action' => 'BILL_CREATE',
            'actor' => $currentUser['email'] ?? 'admin',
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'details' => [
                'invoiceNumber' => $invoiceNumber,
                'invoiceType' => $invoiceType,
                'grandTotal' => $roundedRupees,
                'itemCount' => count($finalLineItems)
            ],
            'timestamp' => $now
        ]);

        // 10. Generate WhatsApp share link and public digital bill link
        $baseUrl = get_public_base_url();
        $scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $baseDir = rtrim(str_replace('/api', '', $scriptName), '/');
        $publicBillUrl = "{$baseUrl}{$baseDir}/bill.php?id=" . urlencode($invoiceId);

        $settings = $db->get('settings', 'store_config') ?? [];
        $shopTitle = !empty($settings['shopName']) ? $settings['shopName'] : "साम्राज्य.07";
        $shopTagline = !empty($settings['tagline']) ? " — " . $settings['tagline'] : "";
        $shopAddr = "";
        if (!empty($settings['addressLine1'])) {
            $shopAddr .= "📍 " . $settings['addressLine1'];
            if (!empty($settings['city'])) $shopAddr .= ", " . $settings['city'];
            $shopAddr .= "\n";
        } elseif (!empty($settings['city'])) {
            $shopAddr .= "📍 " . $settings['city'] . "\n";
        }
        $shopPhoneStr = !empty($settings['phone']) ? "📞 मो.: " . $settings['phone'] . "\n" : "";
        $cName = ($customerName && strtolower($customerName) !== 'walk-in customer') ? $customerName : 'ग्राहक';

        $waText = "👑 *{$shopTitle}{$shopTagline}*\n";
        if ($shopAddr) $waText .= $shopAddr;
        if ($shopPhoneStr) $waText .= $shopPhoneStr;
        $waText .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $waText .= "🧾 *" . ($invoiceType === 'gst' ? "टॅक्स इनव्हॉइस (TAX INVOICE)" : "सेल्स बिल (SALES RECEIPT)") . "*\n";
        $waText .= "बिल क्र.: *{$invoiceNumber}*\n";
        $waText .= "दिनांक: " . date('d/m/Y h:i A') . "\n";
        $waText .= "ग्राहक: *{$cName}*\n";
        $waText .= "पेमेंट: *" . strtoupper($paymentMethod) . " (PAID)*\n";
        $waText .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $waText .= "🛍️ *खरेदी तपशील (Items):*\n";

        $i = 1;
        foreach ($finalLineItems as $fItem) {
            $pName = $fItem['productName'] ?? 'Item';
            $size = !empty($fItem['size']) ? " [{$fItem['size']}]" : "";
            $qty = (int)($fItem['quantity'] ?? 1);
            $rate = number_format((float)($fItem['unitPrice'] ?? 0), 2);
            $itemTotal = number_format((float)($fItem['totalAmount'] ?? 0), 2);
            $waText .= "{$i}. *{$pName}*{$size}\n";
            $waText .= "   {$qty} pcs × ₹{$rate} = *₹{$itemTotal}*\n";
            $i++;
        }

        $waText .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $waText .= "सबटोटल: ₹" . number_format(from_paise($subtotalPaise), 2) . "\n";
        if ($totalDiscountPaise > 0) {
            $waText .= "सूट (Discount): - ₹" . number_format(from_paise($totalDiscountPaise), 2) . "\n";
        }
        if ($invoiceType === 'gst' && $taxTotalPaise > 0) {
            $waText .= "जीएसटी (GST): ₹" . number_format(from_paise($taxTotalPaise), 2) . "\n";
        }
        $waText .= "💰 *एकूण देय रक्कम: ₹" . number_format($roundedRupees, 2) . "*\n";
        $waText .= "━━━━━━━━━━━━━━━━━━━━━\n";
        $waText .= "🌐 *आपले मूळ डिजिटल बिल पाहण्यासाठी खालील लिंक उघडा:*\n";
        $waText .= "👉 {$publicBillUrl}\n\n";
        $waText .= "🙏 साम्राज्य.07 मध्ये खरेदी केल्याबद्दल धन्यवाद! पुन्हा नक्की भेट द्या! ✨";

        $cleanPhone = preg_replace('/[^0-9]/', '', $customerPhone);
        if (strlen($cleanPhone) === 10) {
            $cleanPhone = '91' . $cleanPhone;
        }
        $whatsappUrl = $cleanPhone 
            ? "https://wa.me/{$cleanPhone}?text=" . rawurlencode($waText)
            : "https://api.whatsapp.com/send?text=" . rawurlencode($waText);

        json_response([
            'success' => true,
            'message' => 'Sale completed successfully.',
            'invoiceId' => $invoiceId,
            'invoiceNumber' => $invoiceNumber,
            'grandTotal' => $roundedRupees,
            'customerName' => $customerName ?: 'Walk-in Customer',
            'customerPhone' => $customerPhone,
            'printUrl' => "invoice-print.php?id={$invoiceId}",
            'pdfUrl' => "api/invoices.php?action=pdf&id={$invoiceId}",
            'publicBillUrl' => $publicBillUrl,
            'whatsappUrl' => $whatsappUrl,
            'shareText' => $waText
        ]);
        break;

    default:
        json_error('Invalid billing action.');
}
