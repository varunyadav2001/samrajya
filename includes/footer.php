<?php
/**
 * Samrajya Mens Wear - Footer Component
 */

if (!defined('APP_ROOT')) {
    exit('Direct access not permitted');
}
?>
        </div> <!-- End .page-container -->
    </main>
</div> <!-- End .app-layout -->

<!-- Toast Notification Container -->
<div id="toastContainer"></div>

<!-- Firebase App & Analytics SDK -->
<script src="https://www.gstatic.com/firebasejs/10.9.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.9.0/firebase-analytics-compat.js"></script>
<script>
    try {
        const fbCfg = <?= json_encode(($GLOBALS['config']['firebase_client'] ?? [])) ?>;
        if (fbCfg && fbCfg.apiKey && !fbCfg.apiKey.includes('DEMO')) {
            if (!firebase.apps.length) {
                firebase.initializeApp(fbCfg);
            }
            if (typeof firebase.analytics === 'function') {
                firebase.analytics();
            }
        }
    } catch(e) {
        console.warn('Firebase analytics init:', e);
    }
</script>

<!-- Core Scripts -->
<script src="assets/js/app.js"></script>
<?php if (isset($extraJs)): ?>
    <?php foreach ($extraJs as $js): ?>
        <script src="<?= htmlspecialchars($js) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
