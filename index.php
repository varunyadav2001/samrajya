<?php
/**
 * Samrajya Mens Wear - Entrypoint
 */

require_once __DIR__ . '/includes/bootstrap.php';

if (is_authenticated()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
