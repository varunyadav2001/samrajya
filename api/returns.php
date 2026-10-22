<?php
/**
 * Samrajya Mens Wear - Returns & Exchanges API
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_auth(true);

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$db = db();

switch ($action) {
    case 'list':
        $returns = $db->query('returns', null, 'createdAt', 'DESC');
        json_response([
            'success' => true,
            'count' => count($returns),
            'returns' => $returns
        ]);
        break;

    case 'lookup':
        $query = strtoupper(trim($_GET['q'] ?? ''));
        if (empty($query)) {
            json_error('Please enter an Invoice Number.');
        }

        $invoices = $db->query('invoices', function($inv) use ($query) {
            $invNo = strtoupper($inv['invoiceNumber'] ?? '');
            $invId = strtoupper($inv['invoiceId'] ?? '');
            return $invNo === $query || $invId === $query || strpos($invNo, $query) !== false;
        });

        if (empty($invoices)) {
            json_error("No invoice found matching '{$query}'.", 404);
        }

        $invoice = $invoices[0];

        // Fetch existing returns for this invoice to calculate already returned quantities
        $prevReturns = $db->query('returns', function($ret) use ($invoice) {
            return ($ret['originalInvoiceId'] ?? '') === $invoice['invoiceId'] || 
                   ($ret['originalInvoiceNumber'] ?? '') === $invoice['invoiceNumber'];
        });

        $returnedCounts = [];
        foreach ($prevReturns as $ret) {
            foreach ($ret['items'] ?? [] as $retItem) {
                $pId = $retItem['productId'] ?? '';
                $returnedCounts[$pId] = ($returnedCounts[$pId] ?? 0) + (int)($retItem['quantity'] ?? 0);
            }
        }

        // Augment items with return eligibility
        $itemsWithEligibility = [];
        foreach ($invoice['items'] ?? [] as $item) {
            $pId = $item['productId'] ?? '';
            $soldQty = (int)($item['quantity'] ?? 0);
            $alreadyReturned = $returnedCounts[$pId] ?? 0;
            $eligibleQty = max(0, $soldQty - $alreadyReturned);

            $item['soldQuantity'] = $soldQty;
            $item['alreadyReturned'] = $alreadyReturned;
            $item['eligibleQuantity'] = $eligibleQty;
            $itemsWithEligibility[] = $item;
        }

        $invoice['items'] = $itemsWithEligibility;
        $invoice['previousReturns'] = $prevReturns;

        json_response([
            'success' => true,
            'invoice' => $invoice
        ]);
        break;

    case 'process':
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?? $_POST;

        $invoiceId = trim($data['invoiceId'] ?? '');
        $returnItems = $data['items'] ?? [];
        $returnReason = trim($data['reason'] ?? 'Customer request');
        $refundMethod = trim($data['refundMethod'] ?? 'cash');
        $notes = trim($data['notes'] ?? '');

        if (empty($invoiceId)) {
            json_error('Invoice ID is required.');
        }

        if (empty($returnItems) || !is_array($returnItems)) {
            json_error('Please select at least one item to return.');
        }

        $invoice = $db->get('invoices', $invoiceId);
        if (!$invoice) {
            json_error('Original invoice not found.', 404);
        }

        // Fetch existing returns to verify quantities
        $prevReturns = $db->query('returns', function($ret) use ($invoice) {
            return ($ret['originalInvoiceId'] ?? '') === $invoice['invoiceId'];
        });

        $returnedCounts = [];
        foreach ($prevReturns as $ret) {
            foreach ($ret['items'] ?? [] as $rItem) {
                $pId = $rItem['productId'] ?? '';
                $returnedCounts[$pId] = ($returnedCounts[$pId] ?? 0) + (int)($rItem['quantity'] ?? 0);
            }
        }

        $validatedReturnItems = [];
        $totalRefundAmount = 0.00;
        $now = date('c');
        $currentUser = get_authenticated_user();

        // Index original line items
        $originalItemsMap = [];
        foreach ($invoice['items'] ?? [] as $origItem) {
            $originalItemsMap[$origItem['productId']] = $origItem;
        }

        foreach ($returnItems as $rReq) {
            $pId = $rReq['productId'] ?? '';
            $qtyToReturn = (int)($rReq['quantity'] ?? 0);
            $restock = !empty($rReq['restock']);

            if ($qtyToReturn <= 0) continue;

            if (!isset($originalItemsMap[$pId])) {
                json_error("Item {$pId} was not in the original invoice.");
            }

            $orig = $originalItemsMap[$pId];
            $soldQty = (int)($orig['quantity'] ?? 0);
            $alreadyRet = $returnedCounts[$pId] ?? 0;
            $eligible = $soldQty - $alreadyRet;

            if ($qtyToReturn > $eligible) {
                json_error("Cannot return {$qtyToReturn} units of '{$orig['productName']}'. Eligible quantity is only {$eligible}.");
            }

            // Calculate refund amount based on original net line price
            $unitEffectivePrice = ((float)($orig['totalAmount'] ?? 0)) / $soldQty;
            $lineRefund = round($unitEffectivePrice * $qtyToReturn, 2);
            $totalRefundAmount += $lineRefund;

            $validatedReturnItems[] = [
                'productId' => $pId,
                'productName' => $orig['productName'] ?? '',
                'sku' => $orig['sku'] ?? '',
                'size' => $orig['size'] ?? '',
                'color' => $orig['color'] ?? '',
                'quantity' => $qtyToReturn,
                'unitPrice' => (float)($orig['unitPrice'] ?? 0),
                'refundAmount' => $lineRefund,
                'restock' => $restock
            ];

            // Restore physical stock if accepted into inventory
            if ($restock) {
                $product = $db->get('products', $pId);
                if ($product) {
                    $curStock = (int)($product['stockQuantity'] ?? 0);
                    $newStock = $curStock + $qtyToReturn;

                    $db->set('products', $pId, [
                        'stockQuantity' => $newStock,
                        'updatedAt' => $now
                    ], true);

                    $movId = 'MOV_' . uniqid();
                    $db->set('inventory_movements', $movId, [
                        'movementId' => $movId,
                        'productId' => $pId,
                        'sku' => $orig['sku'] ?? '',
                        'productName' => $orig['productName'] ?? '',
                        'type' => 'return',
                        'quantity' => $qtyToReturn,
                        'previousStock' => $curStock,
                        'newStock' => $newStock,
                        'referenceId' => $invoice['invoiceNumber'] ?? '',
                        'reason' => "Return processed: {$returnReason}",
                        'createdBy' => $currentUser['name'] ?? 'Admin',
                        'timestamp' => $now
                    ]);
                }
            }
        }

        if (empty($validatedReturnItems)) {
            json_error('No valid items selected for return.');
        }

        // Generate sequential Return Number
        $returnNumber = $db->nextReturnNumber();
        $returnId = 'RET_' . preg_replace('/[^a-zA-Z0-9]/', '', $returnNumber);

        $returnDoc = [
            'returnId' => $returnId,
            'returnNumber' => $returnNumber,
            'originalInvoiceId' => $invoice['invoiceId'],
            'originalInvoiceNumber' => $invoice['invoiceNumber'],
            'customer' => $invoice['customer'] ?? [],
            'items' => $validatedReturnItems,
            'totalRefundAmount' => $totalRefundAmount,
            'refundMethod' => $refundMethod,
            'returnReason' => $returnReason,
            'status' => 'completed',
            'notes' => $notes,
            'processedBy' => $currentUser['name'] ?? 'Admin',
            'createdAt' => $now,
            'updatedAt' => $now
        ];

        // Save return record
        $db->set('returns', $returnId, $returnDoc);

        // Update original invoice status
        $totalSoldAll = array_sum(array_column($invoice['items'] ?? [], 'quantity'));
        $totalReturnedAll = 0;
        foreach ($returnedCounts as $c) { $totalReturnedAll += $c; }
        foreach ($validatedReturnItems as $v) { $totalReturnedAll += $v['quantity']; }

        $newInvoiceStatus = ($totalReturnedAll >= $totalSoldAll) ? 'returned' : 'partially_returned';
        $db->set('invoices', $invoiceId, [
            'status' => $newInvoiceStatus,
            'updatedAt' => $now
        ], true);

        // Audit Log
        $logId = 'LOG_' . uniqid();
        $db->set('audit_logs', $logId, [
            'logId' => $logId,
            'action' => 'RETURN_PROCESS',
            'actor' => $currentUser['email'] ?? 'admin',
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'details' => [
                'returnNumber' => $returnNumber,
                'invoiceNumber' => $invoice['invoiceNumber'],
                'refundAmount' => $totalRefundAmount
            ],
            'timestamp' => $now
        ]);

        json_response([
            'success' => true,
            'message' => 'Return processed successfully.',
            'returnId' => $returnId,
            'returnNumber' => $returnNumber,
            'refundAmount' => $totalRefundAmount
        ]);
        break;

    default:
        json_error('Invalid return action.');
}
