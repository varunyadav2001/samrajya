<?php
/**
 * Samrajya Mens Wear - Products & Inventory API
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_auth(true);

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$db = db();

switch ($action) {
    case 'list':
        $search = strtolower(trim($_GET['q'] ?? ''));
        $category = trim($_GET['category'] ?? '');
        $size = trim($_GET['size'] ?? '');
        $stockStatus = trim($_GET['stock_status'] ?? '');
        $status = trim($_GET['status'] ?? 'all'); // 'active', 'inactive', 'all'

        $products = $db->query('products', function($p) use ($search, $category, $size, $stockStatus, $status) {
            if ($status === 'active' && ($p['status'] ?? '') !== 'active') return false;
            if ($status === 'inactive' && ($p['status'] ?? '') !== 'inactive') return false;

            if ($category && ($p['category'] ?? '') !== $category) return false;
            if ($size && ($p['size'] ?? '') !== $size) return false;

            $stock = (int)($p['stockQuantity'] ?? 0);
            $lowThreshold = (int)($p['lowStockThreshold'] ?? 5);

            if ($stockStatus === 'in_stock' && $stock <= $lowThreshold) return false;
            if ($stockStatus === 'low_stock' && ($stock <= 0 || $stock > $lowThreshold)) return false;
            if ($stockStatus === 'out_of_stock' && $stock > 0) return false;

            if ($search) {
                $name = strtolower($p['name'] ?? '');
                $sku = strtolower($p['sku'] ?? '');
                $color = strtolower($p['color'] ?? '');
                $brand = strtolower($p['brand'] ?? '');
                $barcode = strtolower($p['barcode'] ?? '');
                if (strpos($name, $search) === false && 
                    strpos($sku, $search) === false && 
                    strpos($color, $search) === false && 
                    strpos($brand, $search) === false &&
                    strpos($barcode, $search) === false) {
                    return false;
                }
            }

            return true;
        }, 'name', 'ASC');

        json_response([
            'success' => true,
            'count' => count($products),
            'products' => $products
        ]);
        break;

    case 'save':
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?? $_POST;

        $productId = trim($data['productId'] ?? '');
        $name = trim($data['name'] ?? '');
        $sku = strtoupper(trim($data['sku'] ?? ''));
        $category = trim($data['category'] ?? '');
        $brand = trim($data['brand'] ?? '');
        $size = trim($data['size'] ?? '');
        $color = trim($data['color'] ?? '');
        $purchasePrice = max(0, (float)($data['purchasePrice'] ?? 0));
        $sellingPrice = max(0, (float)($data['sellingPrice'] ?? 0));
        $mrp = max($sellingPrice, (float)($data['mrp'] ?? $sellingPrice));
        $gstRate = max(0, (float)($data['gstRate'] ?? 0));
        $hsnCode = trim($data['hsnCode'] ?? '');
        $stockQuantity = max(0, (int)($data['stockQuantity'] ?? 0));
        $lowStockThreshold = max(1, (int)($data['lowStockThreshold'] ?? 5));
        $status = in_array($data['status'] ?? '', ['active', 'inactive']) ? $data['status'] : 'active';
        $description = trim($data['description'] ?? '');

        if (empty($name)) {
            json_error('Product name is required.');
        }

        // Auto-generate clean SKU if empty
        if (empty($sku)) {
            $sku = generate_clean_sku('SMW', $category, $size, $color ?: 'STD');
        }

        // Validate duplicate SKU
        $existingBySku = $db->query('products', function($p) use ($sku, $productId) {
            return strtoupper($p['sku'] ?? '') === $sku && ($p['productId'] ?? '') !== $productId;
        });

        if (!empty($existingBySku)) {
            json_error("A product with SKU '{$sku}' already exists. Every variant must have a unique SKU.");
        }

        $now = date('c');
        $currentUser = get_authenticated_user();
        $isNew = empty($productId);

        if ($isNew) {
            $productId = 'PROD_' . strtoupper(substr(uniqid(), -6));
            $productDoc = [
                'productId' => $productId,
                'name' => $name,
                'sku' => $sku,
                'category' => $category,
                'brand' => $brand,
                'size' => $size,
                'color' => $color,
                'purchasePrice' => $purchasePrice,
                'sellingPrice' => $sellingPrice,
                'mrp' => $mrp,
                'gstRate' => $gstRate,
                'hsnCode' => $hsnCode,
                'stockQuantity' => $stockQuantity,
                'lowStockThreshold' => $lowStockThreshold,
                'status' => $status,
                'description' => $description,
                'barcode' => $sku,
                'createdAt' => $now,
                'updatedAt' => $now
            ];
            $db->set('products', $productId, $productDoc);

            // Record initial inventory movement
            if ($stockQuantity > 0) {
                $movId = 'MOV_' . uniqid();
                $db->set('inventory_movements', $movId, [
                    'movementId' => $movId,
                    'productId' => $productId,
                    'sku' => $sku,
                    'productName' => $name,
                    'type' => 'initial_stock',
                    'quantity' => $stockQuantity,
                    'previousStock' => 0,
                    'newStock' => $stockQuantity,
                    'referenceId' => 'NEW_PROD',
                    'reason' => 'Initial stock on creation',
                    'createdBy' => $currentUser['name'] ?? 'Admin',
                    'timestamp' => $now
                ]);
            }
        } else {
            $currentProd = $db->get('products', $productId);
            if (!$currentProd) {
                json_error('Product not found for update.', 404);
            }

            $oldStock = (int)($currentProd['stockQuantity'] ?? 0);

            $productDoc = [
                'productId' => $productId,
                'name' => $name,
                'sku' => $sku,
                'category' => $category,
                'brand' => $brand,
                'size' => $size,
                'color' => $color,
                'purchasePrice' => $purchasePrice,
                'sellingPrice' => $sellingPrice,
                'mrp' => $mrp,
                'gstRate' => $gstRate,
                'hsnCode' => $hsnCode,
                'stockQuantity' => $stockQuantity,
                'lowStockThreshold' => $lowStockThreshold,
                'status' => $status,
                'description' => $description,
                'barcode' => $sku,
                'updatedAt' => $now
            ];
            $db->set('products', $productId, $productDoc, true);

            // If stock was edited directly in the form, record movement log
            if ($oldStock !== $stockQuantity) {
                $diff = $stockQuantity - $oldStock;
                $movId = 'MOV_' . uniqid();
                $db->set('inventory_movements', $movId, [
                    'movementId' => $movId,
                    'productId' => $productId,
                    'sku' => $sku,
                    'productName' => $name,
                    'type' => $diff > 0 ? 'adjustment_add' : 'adjustment_remove',
                    'quantity' => abs($diff),
                    'previousStock' => $oldStock,
                    'newStock' => $stockQuantity,
                    'referenceId' => 'EDIT_FORM',
                    'reason' => 'Direct inventory quantity edit in product modal',
                    'createdBy' => $currentUser['name'] ?? 'Admin',
                    'timestamp' => $now
                ]);
            }
        }

        json_response([
            'success' => true,
            'message' => $isNew ? 'Product created successfully.' : 'Product updated successfully.',
            'product' => $productDoc
        ]);
        break;

    case 'adjust_stock':
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?? $_POST;

        $productId = trim($data['productId'] ?? '');
        $type = trim($data['type'] ?? 'add'); // 'add' or 'remove'
        $quantity = abs((int)($data['quantity'] ?? 0));
        $reason = trim($data['reason'] ?? 'Manual stock adjustment');

        if ($quantity <= 0) {
            json_error('Adjustment quantity must be greater than 0.');
        }

        $prod = $db->get('products', $productId);
        if (!$prod) {
            json_error('Product not found.', 404);
        }

        $currentStock = (int)($prod['stockQuantity'] ?? 0);
        $newStock = ($type === 'add') ? ($currentStock + $quantity) : ($currentStock - $quantity);

        if ($newStock < 0) {
            json_error("Cannot deduct {$quantity} units. Available stock is only {$currentStock}.");
        }

        $now = date('c');
        $currentUser = get_authenticated_user();

        // Update product stock
        $db->set('products', $productId, [
            'stockQuantity' => $newStock,
            'updatedAt' => $now
        ], true);

        // Record movement
        $movId = 'MOV_' . uniqid();
        $db->set('inventory_movements', $movId, [
            'movementId' => $movId,
            'productId' => $productId,
            'sku' => $prod['sku'] ?? '',
            'productName' => $prod['name'] ?? '',
            'type' => $type === 'add' ? 'adjustment_add' : 'adjustment_remove',
            'quantity' => $quantity,
            'previousStock' => $currentStock,
            'newStock' => $newStock,
            'referenceId' => 'STOCK_ADJUST',
            'reason' => $reason,
            'createdBy' => $currentUser['name'] ?? 'Admin',
            'timestamp' => $now
        ]);

        json_response([
            'success' => true,
            'message' => 'Stock adjusted successfully.',
            'productId' => $productId,
            'previousStock' => $currentStock,
            'newStock' => $newStock
        ]);
        break;

    case 'delete':
        $productId = trim($_GET['id'] ?? $_POST['id'] ?? '');
        if (empty($productId)) {
            json_error('Product ID is required.');
        }

        $prod = $db->get('products', $productId);
        if (!$prod) {
            json_error('Product not found.', 404);
        }

        // Soft deactivation to preserve historical billing relations
        $db->set('products', $productId, [
            'status' => 'inactive',
            'updatedAt' => date('c')
        ], true);

        json_response([
            'success' => true,
            'message' => 'Product deactivated successfully.'
        ]);
        break;

    default:
        json_error('Invalid action.');
}
