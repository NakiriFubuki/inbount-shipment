<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . baseUrl('index.php'));
    exit;
}

$email = trim($_GET['email'] ?? $_POST['email'] ?? '');
$code = trim($_GET['code'] ?? $_POST['verification_code'] ?? '');
$errors = [];
$user = null;
$pdo = getDB();

function findUserByResetCode(PDO $pdo, string $email, string $code): ?array
{
    $email = trim($email);
    $code = trim($code);
    if ($email === '' || $code === '') {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT id, username FROM users
         WHERE email = ? AND reset_token = ? AND reset_token_expires > NOW()'
    );
    $stmt->execute([$email, $code]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = __('err.email_invalid');
    }
    if (!preg_match('/^\d{6}$/', $code)) {
        $errors[] = __('auth.reset_code_invalid');
    }

    $user = findUserByResetCode($pdo, $email, $code);
    if (!$user && empty($errors)) {
        $errors[] = __('err.reset_expired');
    }

    if (strlen($password) < 6) {
        $errors[] = __('err.password_min');
    }
    if ($password !== $confirm) {
        $errors[] = __('err.password_mismatch');
    }

    if (empty($errors) && $user) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?'
        );
        $stmt->execute([$hash, $user['id']]);
        flash('success', __('msg.password_reset_success'));
        header('Location: ' . baseUrl('auth/login.php'));
        exit;
    }
}

$pageTitle = __('auth.reset_title');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($pageTitle) ?> - <?= e(__('app.title_suffix')) ?></title>
    <?php require __DIR__ . '/../includes/head-assets.php'; ?>
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card" style="max-width:480px;">
        <h1><?= e(__('auth.reset_title')) ?></h1>
        <p class="subtitle"><?= e(__('auth.reset_subtitle_code')) ?></p>

        <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" action="">
            <div class="form-group">
                <label for="email"><?= e(__('auth.registered_email')) ?></label>
                <input type="email" id="email" name="email" class="form-control" required
                       value="<?= e($email) ?>" autocomplete="email">
            </div>

            <div class="form-group">
                <label for="verification_code"><?= e(__('auth.verification_code')) ?></label>
                <input type="text" id="verification_code" name="verification_code" class="form-control"
                       inputmode="numeric" pattern="\d{6}" maxlength="6" minlength="6" required
                       placeholder="000000" value="<?= e($code) ?>" autocomplete="one-time-code">
                <p class="form-hint"><?= e(__('auth.verification_code_hint')) ?></p>
            </div>

            <div class="form-group">
                <label for="password"><?= e(__('auth.new_password')) ?></label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" class="form-control" required minlength="6">
                    <button type="button" class="toggle-password" aria-label="<?= e(__('common.show_password')) ?>">👁</button>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password"><?= e(__('auth.confirm_new_password')) ?></label>
                <div class="password-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password"
                           class="form-control" required minlength="6">
                    <button type="button" class="toggle-password" aria-label="<?= e(__('common.show_password')) ?>">👁</button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block"><?= e(__('auth.reset_btn')) ?></button>
        </form>

        <div class="auth-links">
            <p><a href="<?= baseUrl('auth/forgot-password.php') ?>"><?= e(__('auth.resend_code')) ?></a></p>
            <p><a href="<?= baseUrl('auth/login.php') ?>"><?= e(__('auth.back_login')) ?></a></p>
        </div>
    </div>
</div>
<script src="<?= e(assetUrl('assets/js/app.js')) ?>"></script>
</body>
</html>
