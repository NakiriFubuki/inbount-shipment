<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
requireAdmin();

$activeNav = 'email';
$pageTitle = __('email.diagnose_title');
$config = getEmailConfig();
$result = testSmtpAuthentication();
$isPhpMode = !usesSmtpMail($config);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><?= e(__('email.diagnose_title')) ?></h1>
    <p><?= e(__('email.diagnose_subtitle')) ?></p>
</div>

<?php if ($isPhpMode): ?>
<div class="alert alert-info"><?= e(__('email.diagnose_php_mode')) ?></div>
<?php elseif ($result['ok']): ?>
<div class="alert alert-success"><?= e(__('email.diagnose_ok')) ?></div>
<?php else: ?>
<div class="alert alert-error">
    <p><strong><?= e(__('email.diagnose_fail')) ?></strong></p>
    <?php if ($result['error']): ?>
    <p><?= e($result['error']) ?></p>
    <?php endif; ?>
</div>
<?php if (str_contains($result['error'] ?? '', __('email.err_535')) || str_contains($result['log'] ?? '', '535')): ?>
<p><a href="<?= baseUrl('admin/email-settings.php?test=fail&code=535') ?>" class="btn btn-primary btn-sm">
    <?= e(__('email.diagnose_fix_link')) ?>
</a></p>
<?php endif; ?>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h2><?= e(__('email.diagnose_log')) ?></h2></div>
    <pre style="white-space:pre-wrap; font-size:0.85rem; line-height:1.6; margin:0; color:var(--text-muted);"><?= e($result['log'] ?: __('email.diagnose_no_log')) ?></pre>
</div>

<p style="margin-top:1.5rem;">
    <a href="<?= baseUrl('admin/email-settings.php') ?>" class="btn btn-outline"><?= e(__('email.back_to_settings')) ?></a>
</p>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
