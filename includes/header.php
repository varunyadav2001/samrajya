<?php
/**
 * Samrajya Mens Wear - Header Component
 */

if (!defined('APP_ROOT')) {
    exit('Direct access not permitted');
}

$currentUser = get_authenticated_user();
$pageTitle = $pageTitle ?? 'Dashboard';
$activePage = $activePage ?? 'dashboard';

// Fetch store settings for global badge
$storeSettings = db()->get('settings', 'store_config') ?? [];
$isGstEnabled = !empty($storeSettings['isGstRegistered']);
$dbDriver = db()->getDriver();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — साम्राज्य.07 (The perfect men's shop)</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <?php if (isset($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
            <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
<div class="app-layout">
    <?php require __DIR__ . '/sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="btn btn-secondary btn-icon mobile-toggle" style="display:none;" aria-label="Toggle Navigation">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="page-title"><?= htmlspecialchars($pageTitle) ?></div>
            </div>

            <div class="topbar-right">
                <!-- GST Status Pill -->
                <?php if ($isGstEnabled): ?>
                    <span class="badge badge-success" title="GSTIN: <?= htmlspecialchars($storeSettings['gstin'] ?? '') ?>">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        GST Active (<?= htmlspecialchars($storeSettings['stateCode'] ?? '27') ?>)
                    </span>
                <?php else: ?>
                    <span class="badge badge-neutral">Non-GST Mode</span>
                <?php endif; ?>

                <?php if ($activePage !== 'billing'): ?>
                    <a href="billing.php" class="btn-quick-pos">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        New Sale (POS)
                    </a>
                <?php endif; ?>

                <!-- User profile badge -->
                <div class="user-pill">
                    <div class="user-avatar">
                        <?= strtoupper(substr($currentUser['name'] ?? 'A', 0, 1)) ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?= htmlspecialchars($currentUser['name'] ?? 'Admin') ?></div>
                        <div class="user-role"><?= htmlspecialchars($currentUser['role'] ?? 'Superadmin') ?></div>
                    </div>
                    <a href="api/auth.php?action=logout" class="btn btn-secondary btn-sm" title="Sign Out" style="margin-left: 6px; padding: 4px 6px;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </a>
                </div>
            </div>
        </header>

        <div class="page-container">
