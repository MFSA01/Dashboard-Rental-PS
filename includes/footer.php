<?php
/**
 * includes/footer.php
 * Script global: memuat app.js dan meneruskan flash message ke SweetAlert2.
 */

if (!function_exists('base_url')) {
    require_once dirname(__DIR__) . '/config/helpers.php';
}

$flash = get_flash();
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<script>
    window.DGS_FLASH = <?= $flash !== null ? json_encode($flash, $jsonFlags) : 'null' ?>;
</script>
<script src="<?= e(base_url('assets/js/timer.js')) ?>"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>"></script>
</body>
</html>
