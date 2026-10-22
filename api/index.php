<?php
/**
 * Samrajya Mens Wear - Vercel Serverless Front Controller
 * Routes all incoming HTTP requests to their corresponding pages and endpoints.
 */

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$cleanPath = trim($path, '/');

// Base directory (repository root)
$baseDir = dirname(__DIR__);

// 1. Root / Default route -> index.php (handles authentication redirect)
if ($cleanPath === '' || $cleanPath === 'index.php') {
    require $baseDir . '/index.php';
    exit;
}

// 2. Direct API endpoints (e.g. /api/billing.php, /api/auth.php)
if (str_starts_with($cleanPath, 'api/')) {
    $script = substr($cleanPath, 4); // strip 'api/'
    if ($script !== 'index.php') {
        $target = $baseDir . '/api/' . $script;
        if (file_exists($target) && is_file($target)) {
            require $target;
            exit;
        }
    }
}

// 3. Direct Root PHP files (e.g. /login.php, /dashboard.php, /billing.php)
$target = $baseDir . '/' . $cleanPath;
if (file_exists($target) && is_file($target)) {
    require $target;
    exit;
}

// 4. Clean URLs without .php extension (e.g. /login -> /login.php, /dashboard -> /dashboard.php)
$withPhp = $baseDir . '/' . $cleanPath . '.php';
if (file_exists($withPhp) && is_file($withPhp)) {
    require $withPhp;
    exit;
}

// 5. 404 Handler
http_response_code(404);
echo "<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 Not Found</h1><p>The requested URL /" . htmlspecialchars($cleanPath) . " was not found.</p></body></html>";
