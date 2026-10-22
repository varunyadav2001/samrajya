<?php
/**
 * Samrajya Mens Wear - Invoices API Endpoint
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/pdf-generator.php';
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// 'pdf' action is public so customers with digital bill link can download their PDF bill
if ($action !== 'pdf') {
    require_auth(true);
}

$db = db();

switch ($action) {
    case 'list':
        $search = strtolower(trim($_GET['q'] ?? ''));
        $type = trim($_GET['type'] ?? ''); // 'gst', 'nongst', ''
        $payment = trim($_GET['payment'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $startDate = trim($_GET['start_date'] ?? '');
        $endDate = trim($_GET['end_date'] ?? '');

        $invoices = $db->query('invoices', function($inv) use ($search, $type, $payment, $status, $startDate, $endDate) {
            if ($type && ($inv['invoiceType'] ?? '') !== $type) return false;
            if ($payment && ($inv['paymentMethod'] ?? '') !== $payment) return false;
            if ($status && ($inv['status'] ?? '') !== $status) return false;

            if ($startDate) {
                $invDate = substr($inv['invoiceDate'] ?? '', 0, 10);
                if ($invDate < $startDate) return false;
            }
            if ($endDate) {
                $invDate = substr($inv['invoiceDate'] ?? '', 0, 10);
                if ($invDate > $endDate) return false;
            }

            if ($search) {
                $invNo = strtolower($inv['invoiceNumber'] ?? '');
                $custName = strtolower($inv['customer']['name'] ?? '');
                $custPhone = strtolower($inv['customer']['phone'] ?? '');
                if (strpos($invNo, $search) === false && 
                    strpos($custName, $search) === false && 
                    strpos($custPhone, $search) === false) {
                    return false;
                }
            }

            return true;
        }, 'invoiceDate', 'DESC');

        json_response([
            'success' => true,
            'count' => count($invoices),
            'invoices' => $invoices
        ]);
        break;

    case 'get':
        $id = trim($_GET['id'] ?? '');
        if (!$id) {
            json_error('Invoice ID is required.');
        }

        $inv = $db->get('invoices', $id);
        if (!$inv) {
            json_error('Invoice not found.', 404);
        }

        json_response([
            'success' => true,
            'invoice' => $inv
        ]);
        break;

    case 'pdf':
        $id = trim($_GET['id'] ?? '');
        $download = !empty($_GET['download']);
        if (!$id) {
            die('Invoice ID is required.');
        }

        $inv = $db->get('invoices', $id);
        if (!$inv) {
            die('Invoice not found.');
        }

        $settings = $db->get('settings', 'store_config') ?? [];
        $mode = $download ? 'download' : 'stream';
        
        InvoicePdfGenerator::renderPdf($inv, $settings, $mode);
        break;

    default:
        json_error('Invalid action requested.');
}
