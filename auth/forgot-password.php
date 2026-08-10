<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

if (isLoggedIn()) {
    header('Location: ' . baseUrl('index.php'));
    exit;
}

$errors = [];
$email = '';
$emailSent = false;
$emailError = null;
$showLocalDevCode = false;
$localDevCode = null;
$resetPageUrl = buildAbsoluteUrl('auth/reset-password.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = __('err.email_invalid');
    } elseif (!isEmailEnabled()) {
        if (isLocalEnvironment()) {
            $errors[] = __('auth.email_not_configured_local');
        } else {
            $errors[] = __('auth.email_not_configured_public');
        }
    } else {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare('SELECT id, username FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $code = generatePasswordResetCode();
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                $stmt = $pdo->prepare(
                    'UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?'
                );
                $stmt->execute([$code, $expires, $user['id']]);

                $prefillUrl = buildAbsoluteUrl(
                    'auth/reset-password.php?email=' . rawurlencode($email) . '&code=' . rawurlencode($code)
                );
                $result = sendPasswordResetEmail($email, $user['username'], $code, $prefillUrl);
                $emailSent = $result['sent'];
                $emailError = $result['error'];

                if (!$emailSent && isLocalEnvironment()) {
                    $showLocalDevCode = true;
                    $localDevCode = $code;
                }
                if (!$emailSent && !isLocalEnvironment()) {
                    $errors[] = ($emailError
                        ? __('auth.reset_email_failed') . $emailError
                        : __('auth.reset_email_failed_short'));
                }
            }
        } catch (PDOException $e) {
            $errors[] = __('err.operation_failed');
        }
    }
}

$pageTitle = __('auth.forgot_title');
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
        <h1><?= e(__('auth.forgot_title')) ?></h1>
        <p class="subtitle"><?= e(__('auth.forgot_subtitle')) ?></p>

        <div class="alert alert-info" style="margin-bottom:1rem;">
            <?= e(__('auth.reset_phone_note')) ?>
        </div>

        <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= e($err) ?></div>
        <?php endforeach; ?>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)): ?>

            <?php if ($emailSent): ?>
            <div class="alert alert-success">
                <p><strong><?= e(__('auth.reset_email_success')) ?></strong></p>
                <p><?= e(__('auth.reset_check_code')) ?></p>
            </div>
            <p style="text-align:center; margin-top:1rem;">
                <a href="<?= e($resetPageUrl) ?>" class="btn btn-primary"><?= e(__('auth.go_reset_page')) ?></a>
            </p>

            <?php elseif ($showLocalDevCode && $localDevCode): ?>
            <div class="alert alert-warning">
                <p><strong><?= e(__('auth.dev_mode_title')) ?></strong></p>
                <p><?= e(__('auth.dev_mode_code_only')) ?></p>
                <p style="text-align:center;font-size:2rem;font-weight:800;letter-spacing:6px;margin:1rem 0;color:#4f46e5;">
                    <?= e($localDevCode) ?>
                </p>
                <a href="<?= e(buildAbsoluteUrl('auth/reset-password.php?email=' . rawurlencode($email) . '&code=' . rawurlencode($localDevCode))) ?>"
                   class="btn btn-primary btn-block"><?= e(__('auth.go_reset_page')) ?></a>
            </div>

            <?php elseif ($emailError): ?>
            <div class="alert alert-error">
                <p><strong><?= e(__('auth.reset_email_failed')) ?></strong></p>
                <p><?= e($emailError) ?></p>
                <p class="form-hint" style="margin-top:0.75rem;"><?= e(__('auth.reset_smtp_hint')) ?></p>
            </div>

            <?php else: ?>
            <div class="alert alert-info">
                <p><?= e(__('auth.reset_generic_done')) ?></p>
            </div>
            <?php endif; ?>

        <?php else: ?>

        <?php if (!isEmailEnabled() && !isLocalEnvironment()): ?>
        <div class="alert alert-warning"><?= e(__('auth.email_not_configured_public')) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-group">
                <label for="email"><?= e(__('auth.registered_email')) ?></label>
                <input type="email" id="email" name="email" class="form-control"
                       placeholder="example@gmail.com"
                       value="<?= e($email) ?>" required>
                <p class="form-hint"><?= e(__('auth.forgot_email_hint')) ?></p>
            </div>
            <button type="submit" class="btn btn-primary btn-block">
                <?= e(__('auth.send_reset')) ?>
            </button>
        </form>

        <?php endif; ?>

        <div class="auth-links">
            <p><a href="<?= baseUrl('auth/login.php') ?>"><?= e(__('auth.back_login')) ?></a></p>
        </div>
    </div>
</div>
</body>
</html>
