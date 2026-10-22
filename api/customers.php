<?php
/**
 * Samrajya Mens Wear - Customer Management API
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_auth(true);

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$db = db();

switch ($action) {
    case 'list':
    case 'search':
        $q = strtolower(trim($_GET['q'] ?? ''));

        $customers = $db->query('customers', function($c) use ($q) {
            if (!$q) return true;
            $name = strtolower($c['name'] ?? '');
            $phone = strtolower($c['phone'] ?? '');
            $email = strtolower($c['email'] ?? '');
            return strpos($name, $q) !== false || strpos($phone, $q) !== false || strpos($email, $q) !== false;
        }, 'updatedAt', 'DESC');

        json_response([
            'success' => true,
            'count' => count($customers),
            'customers' => $customers
        ]);
        break;

    case 'save':
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?? $_POST;

        $customerId = trim($data['customerId'] ?? '');
        $name = trim($data['name'] ?? '');
        $phone = preg_replace('/[^0-9]/', '', (string)($data['phone'] ?? ''));
        $email = trim($data['email'] ?? '');
        $address = trim($data['address'] ?? '');
        $city = trim($data['city'] ?? '');
        $gstin = strtoupper(trim($data['gstin'] ?? ''));

        if (empty($name)) {
            json_error('Customer name is required.');
        }

        $now = date('c');
        $isNew = empty($customerId);

        if ($isNew) {
            // Check if customer with same phone already exists
            if (!empty($phone)) {
                $existing = $db->query('customers', function($c) use ($phone) {
                    return ($c['phone'] ?? '') === $phone;
                });
                if (!empty($existing)) {
                    json_error("Customer with phone number {$phone} already exists ({$existing[0]['name']}).");
                }
            }

            $customerId = 'CUST_' . (!empty($phone) ? $phone : uniqid());
            $customerDoc = [
                'customerId' => $customerId,
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'address' => $address,
                'city' => $city,
                'gstin' => $gstin,
                'totalPurchases' => 0.00,
                'totalInvoices' => 0,
                'lastPurchaseDate' => null,
                'createdAt' => $now,
                'updatedAt' => $now
            ];
            $db->set('customers', $customerId, $customerDoc);
        } else {
            $customerDoc = [
                'customerId' => $customerId,
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'address' => $address,
                'city' => $city,
                'gstin' => $gstin,
                'updatedAt' => $now
            ];
            $db->set('customers', $customerId, $customerDoc, true);
        }

        json_response([
            'success' => true,
            'message' => $isNew ? 'Customer registered successfully.' : 'Customer updated successfully.',
            'customer' => $customerDoc
        ]);
        break;

    case 'details':
        $customerId = trim($_GET['id'] ?? '');
        if (!$customerId) {
            json_error('Customer ID is required.');
        }

        $customer = $db->get('customers', $customerId);
        if (!$customer) {
            json_error('Customer not found.', 404);
        }

        // Fetch invoices for this customer
        $invoices = $db->query('invoices', function($inv) use ($customerId, $customer) {
            $c = $inv['customer'] ?? [];
            if (($c['customerId'] ?? '') === $customerId) return true;
            if (!empty($customer['phone']) && ($c['phone'] ?? '') === $customer['phone']) return true;
            return false;
        }, 'invoiceDate', 'DESC');

        json_response([
            'success' => true,
            'customer' => $customer,
            'invoices' => $invoices
        ]);
        break;

    default:
        json_error('Invalid customer action.');
}
