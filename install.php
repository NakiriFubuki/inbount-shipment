<?php
/**
 * One-click database installer
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$step = $_GET['step'] ?? 'form';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = $_POST['host'] ?? 'localhost';
    $user = $_POST['user'] ?? 'root';
    $pass = $_POST['pass'] ?? '';
    $adminUser = trim($_POST['admin_user'] ?? 'admin');
    $adminPass = $_POST['admin_pass'] ?? 'admin123';
    $adminEmail = trim($_POST['admin_email'] ?? 'admin@inbound.local');

    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $sql = file_get_contents(__DIR__ . '/database/schema.sql');
        $sql = preg_replace('/-- Default admin.*?ON DUPLICATE KEY UPDATE username = username;/s', '', $sql);

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }

        $hash = password_hash($adminPass, PASSWORD_DEFAULT);
        $pdo->exec('USE inbound_shipment_db');
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), email = VALUES(email)'
        );
        $stmt->execute([$adminUser, $adminEmail, $hash, 'admin']);

        $configPath = __DIR__ . '/config/database.php';
        $config = file_get_contents($configPath);
        $config = preg_replace("/define\('DB_HOST', '.*?'\)/", "define('DB_HOST', '$host')", $config);
        $config = preg_replace("/define\('DB_USER', '.*?'\)/", "define('DB_USER', '$user')", $config);
        $config = preg_replace("/define\('DB_PASS', '.*?'\)/", "define('DB_PASS', '" . addslashes($pass) . "')", $config);
        file_put_contents($configPath, $config);

        $message = __('install.success', ['user' => $adminUser]);
        $step = 'done';
    } catch (Exception $e) {
        $error = __('install.failed', ['error' => $e->getMessage()]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(__('install.title')) ?> - <?= e(__('app.title_suffix')) ?></title>
    <?php require __DIR__ . '/includes/head-assets.php'; ?>
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card" style="max-width:480px;">
        <h1>📦 <?= e(__('install.title')) ?></h1>
        <p class="subtitle"><?= e(__('install.subtitle')) ?></p>

        <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($step === 'done'): ?>
        <div class="alert alert-success"><?= e($message) ?></div>
        <a href="auth/login.php?role=admin" class="btn btn-primary btn-block"><?= e(__('install.go_login')) ?></a>
        <p class="auth-links" style="margin-top:1rem;">
            <strong><?= e(__('install.security_hint')) ?></strong>
        </p>
        <?php else: ?>
        <form method="post">
            <div class="form-group">
                <label><?= e(__('install.mysql_host')) ?></label>
                <input type="text" name="host" class="form-control" value="localhost" required>
            </div>
            <div class="form-group">
                <label><?= e(__('install.mysql_user')) ?></label>
                <input type="text" name="user" class="form-control" value="root" required>
            </div>
            <div class="form-group">
                <label><?= e(__('install.mysql_pass')) ?></label>
                <input type="password" name="pass" class="form-control" placeholder="<?= e(__('install.mysql_pass_hint')) ?>">
            </div>
            <hr style="margin:1rem 0; border:none; border-top:1px solid #e2e8f0;">
            <div class="form-group">
                <label><?= e(__('install.admin_user')) ?></label>
                <input type="text" name="admin_user" class="form-control" value="admin" required>
            </div>
            <div class="form-group">
                <label><?= e(__('install.admin_email')) ?></label>
                <input type="email" name="admin_email" class="form-control" value="admin@inbound.local" required>
            </div>
            <div class="form-group">
                <label><?= e(__('install.admin_pass')) ?></label>
                <div class="password-wrapper">
                    <input type="password" name="admin_pass" class="form-control" value="admin123" required minlength="6">
                    <button type="button" class="toggle-password" aria-label="<?= e(__('common.show_password')) ?>">👁</button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block"><?= e(__('install.start')) ?></button>
        </form>
        <?php endif; ?>
    </div>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
