<?php
/**
 * Samrajya Mens Wear - Sidebar Navigation
 */

if (!defined('APP_ROOT')) {
    exit('Direct access not permitted');
}

$activePage = $activePage ?? 'dashboard';
$isFirestoreActive = db()->isFirestore();
$sidebarSettings = db()->get('settings', 'store_config') ?? [];
$sidebarShopName = !empty($sidebarSettings['shopName']) ? $sidebarSettings['shopName'] : 'साम्राज्य.07';
$sidebarTagline = $sidebarSettings['tagline'] ?? "The perfect men's shop";
?>
<aside class="sidebar">
    <div class="sidebar-header" style="height: 72px;">
        <img src="assets/images/logo.png" alt="<?= htmlspecialchars($sidebarShopName) ?>" style="width: 44px; height: 44px; border-radius: 50%; object-fit: contain; box-shadow: 0 2px 8px rgba(0,0,0,0.4); background: #000; border: 1.5px solid var(--gold-400);">
        <div class="brand-text">
            <h1 style="font-size: 16px; font-weight: 800; letter-spacing: 0.5px;"><?= htmlspecialchars($sidebarShopName) ?></h1>
            <span style="font-size: 9.5px; color: var(--gold-400); text-transform: none; letter-spacing: 0.5px;"><?= htmlspecialchars($sidebarTagline) ?></span>
        </div>
        <button type="button" class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close Navigation">
            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-title">Core Operations</div>

        <a href="dashboard.php" class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Dashboard
        </a>

        <a href="billing.php" class="nav-link <?= $activePage === 'billing' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            POS Billing
        </a>

        <a href="invoices.php" class="nav-link <?= $activePage === 'invoices' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Invoices & Receipts
        </a>

        <div class="nav-section-title">Catalog & Store</div>

        <a href="products.php" class="nav-link <?= $activePage === 'products' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            Products & Inventory
        </a>

        <a href="customers.php" class="nav-link <?= $activePage === 'customers' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            Customers
        </a>

        <a href="returns.php" class="nav-link <?= $activePage === 'returns' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H4m0 0l3-3m-3 3l3 3m5 4v1a3 3 0 003 3h6m0 0l-3-3m3 3l-3 3"/></svg>
            Returns & Exchanges
        </a>

        <div class="nav-section-title">Analytics & Config</div>

        <a href="reports.php" class="nav-link <?= $activePage === 'reports' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            Sales & Reports
        </a>

        <a href="settings.php" class="nav-link <?= $activePage === 'settings' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            User Settings
        </a>
    </nav>
</aside>
