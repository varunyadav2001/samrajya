<?php
/**
 * Samrajya Mens Wear - Helper Functions
 */

if (!defined('APP_ROOT')) {
    exit('Direct access not permitted');
}

/**
 * Sanitize user input recursively
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/**
 * Send JSON response and exit
 */
function json_response($data, int $status = 200): void {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send standard JSON error response and exit
 */
function json_error(string $message, int $status = 400, array $details = []): void {
    json_response([
        'success' => false,
        'error' => $message,
        'details' => $details,
        'timestamp' => date('c')
    ], $status);
}

/**
 * Format currency in Indian Numbering System (e.g. ₹ 1,23,456.78)
 */
function format_currency($amount, bool $show_symbol = true, string $symbol = '₹'): string {
    $amount = (float)$amount;
    $negative = $amount < 0 ? '-' : '';
    $amount = abs($amount);
    
    $parts = explode('.', number_format($amount, 2, '.', ''));
    $integer = $parts[0];
    $fraction = $parts[1];
    
    // Apply Indian comma notation: last 3 digits, then pairs of 2 digits
    if (strlen($integer) > 3) {
        $last_three = substr($integer, -3);
        $remaining = substr($integer, 0, -3);
        $remaining_chunks = str_split(strrev($remaining), 2);
        $formatted_remaining = strrev(implode(',', $remaining_chunks));
        $formatted_integer = $formatted_remaining . ',' . $last_three;
    } else {
        $formatted_integer = $integer;
    }
    
    $result = $negative . $formatted_integer . '.' . $fraction;
    return $show_symbol ? ($symbol . ' ' . $result) : $result;
}

/**
 * Format ISO or Unix timestamp for display
 */
function format_datetime($datetime, string $format = 'd M Y, h:i A'): string {
    if (empty($datetime)) {
        return '—';
    }
    try {
        if (is_numeric($datetime)) {
            $dt = new DateTime("@{$datetime}");
        } else {
            $dt = new DateTime((string)$datetime);
        }
        $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
        return $dt->format($format);
    } catch (Exception $e) {
        return (string)$datetime;
    }
}

/**
 * Convert numbers to Indian Currency Words (Rupees and Paise)
 */
function number_to_indian_words(float $number): string {
    $no = floor($number);
    $point = round($number - $no, 2) * 100;
    $hundred = null;
    $digits_1 = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $digits_2 = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    
    $digits = ['', 'Hundred', 'Thousand', 'Lakh', 'Crore'];
    $str = [];
    
    $words = [];
    $words[0] = '';
    $words[1] = 'One';
    $words[2] = 'Two';
    $words[3] = 'Three';
    $words[4] = 'Four';
    $words[5] = 'Five';
    $words[6] = 'Six';
    $words[7] = 'Seven';
    $words[8] = 'Eight';
    $words[9] = 'Nine';
    $words[10] = 'Ten';
    $words[11] = 'Eleven';
    $words[12] = 'Twelve';
    $words[13] = 'Thirteen';
    $words[14] = 'Fourteen';
    $words[15] = 'Fifteen';
    $words[16] = 'Sixteen';
    $words[17] = 'Seventeen';
    $words[18] = 'Eighteen';
    $words[19] = 'Nineteen';
    $words[20] = 'Twenty';
    $words[30] = 'Thirty';
    $words[40] = 'Forty';
    $words[50] = 'Fifty';
    $words[60] = 'Sixty';
    $words[70] = 'Seventy';
    $words[80] = 'Eighty';
    $words[90] = 'Ninety';

    if ($no == 0) {
        $rupees_words = 'Zero';
    } else {
        $num_str = (string)$no;
        $num_length = strlen($num_str);
        $i = 0;
        $levels = [];
        
        // Split Indian number into units: Last 3, then pairs of 2
        $last3 = (int)substr($num_str, -3);
        $levels[] = $last3;
        $num_str = substr($num_str, 0, -3);
        
        while (strlen($num_str) > 0) {
            $two = (int)substr($num_str, -2);
            $levels[] = $two;
            $num_str = substr($num_str, 0, -2);
        }
        
        $level_names = ['', 'Thousand', 'Lakh', 'Crore', 'Arab'];
        $parts = [];
        
        for ($idx = 0; $idx < count($levels); $idx++) {
            $val = $levels[$idx];
            if ($val == 0) continue;
            
            $part = '';
            if ($idx == 0) { // hundreds, tens, units
                $h = floor($val / 100);
                $tu = $val % 100;
                if ($h > 0) {
                    $part .= $digits_1[$h] . ' Hundred ';
                }
                if ($tu > 0) {
                    if ($part != '') $part .= 'and ';
                    if ($tu < 20) {
                        $part .= $digits_1[$tu];
                    } else {
                        $part .= $digits_2[floor($tu / 10)] . ($tu % 10 > 0 ? ' ' . $digits_1[$tu % 10] : '');
                    }
                }
            } else { // pairs of 2
                if ($val < 20) {
                    $part = $digits_1[$val];
                } else {
                    $part = $digits_2[floor($val / 10)] . ($val % 10 > 0 ? ' ' . $digits_1[$val % 10] : '');
                }
                $part .= ' ' . $level_names[$idx];
            }
            array_unshift($parts, trim($part));
        }
        $rupees_words = implode(' ', $parts);
    }

    $paise_words = '';
    if ($point > 0) {
        $p = (int)$point;
        if ($p < 20) {
            $paise_words = $digits_1[$p];
        } else {
            $paise_words = $digits_2[floor($p / 10)] . ($p % 10 > 0 ? ' ' . $digits_1[$p % 10] : '');
        }
        $paise_words = ' and ' . $paise_words . ' Paise';
    }

    return trim($rupees_words) . ' Rupees' . $paise_words . ' Only';
}

/**
 * Concurrency-safe integer paise rounding
 */
function to_paise(float $rupees): int {
    return (int)round($rupees * 100);
}

function from_paise(int $paise): float {
    return round($paise / 100, 2);
}

/**
 * Generate a clean SKU code
 */
function generate_clean_sku(string $prefix, string $category, string $size, string $color): string {
    $clean_cat = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $category), 0, 3));
    $clean_size = strtoupper(str_replace(' ', '', $size));
    $clean_color = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $color), 0, 3));
    $rand = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
    return "{$prefix}-{$clean_cat}-{$clean_color}-{$clean_size}-{$rand}";
}

/**
 * Resolves the publicly/LAN-accessible base URL for shareable digital bill links.
 * When accessed from localhost / 127.0.0.1, it auto-detects the machine's LAN Wi-Fi IP (e.g. 192.168.1.97:8000)
 * so that links shared via WhatsApp can be opened directly on customers' mobile phones!
 */
function get_public_base_url(): string {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $proto = $isHttps ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000';
    $hostParts = explode(':', $host);
    $hostOnly = $hostParts[0];
    $port = $hostParts[1] ?? ($_SERVER['SERVER_PORT'] ?? '8000');
    
    // If accessed via loopback / localhost, resolve to LAN IP
    if ($hostOnly === 'localhost' || $hostOnly === '127.0.0.1' || $hostOnly === '::1') {
        $lanIp = gethostbyname(gethostname());
        if ($lanIp && $lanIp !== '127.0.0.1') {
            return "{$proto}://{$lanIp}:{$port}";
        }
    }
    
    return "{$proto}://{$host}";
}
