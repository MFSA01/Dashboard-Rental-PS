<?php
/**
 * index.php — Entry point aplikasi.
 * Arahkan admin yang sudah login ke dashboard billing, selain itu ke halaman login.
 */

require_once __DIR__ . '/config/helpers.php';

if (is_admin_logged_in()) {
    redirect('pages/billing.php');
}

redirect('login.php');
