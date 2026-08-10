<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    $user = currentUser();
    header('Location: ' . ($user['role'] === 'admin'
        ? baseUrl('admin/dashboard.php')
        : baseUrl('user/dashboard.php')));
    exit;
}

$errors = [];
$role = $_GET['role'] ?? $_POST['role'] ?? 'user';
if (!in_array($role, ['admin', 'user'], true)) {
    $role = 'user';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';

    if ($username === '' || $password === '') {
        $errors[] = __('err.username_password_required');
    } else {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND role = ?');
            $stmt->execute([$username, $role]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                loginUser((int) $user['id'], $user['role']);
                flash('success', __('msg.login_success'));
                header('Location: ' . ($user['role'] === 'admin'
                    ? baseUrl('admin/dashboard.php')
                    : baseUrl('user/dashboard.php')));
                exit;
            }
            $errors[] = __('err.login_failed');
        } catch (PDOException $e) {
            $errors[] = __('err.db_connect');
        }
    }
}

$keepFormValues = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($errors);
if ($keepFormValues) {
    $usernameValue = trim($_POST['username'] ?? '');
    $passwordValue = $_POST['password'] ?? '';
} elseif ($role === 'admin') {
    $usernameValue = 'admin';
    $passwordValue = 'admin123';
} else {
    $usernameValue = 'user';
    $passwordValue = 'user123';
}

$pageTitle = __('auth.login_title');
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
    <div class="auth-card">
        <h1>📦 <?= e(__('app.name')) ?></h1>
        <p class="subtitle"><?= e(__('auth.login_subtitle')) ?></p>

        <div class="auth-tabs">
            <a href="?role=user" class="auth-tab <?= $role === 'user' ? 'active' : '' ?>"><?= e(__('auth.user_login')) ?></a>
            <a href="?role=admin" class="auth-tab <?= $role === 'admin' ? 'active' : '' ?>"><?= e(__('auth.admin_login')) ?></a>
        </div>

        <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" action="" class="login-form<?= $keepFormValues ? ' login-keep-values' : '' ?>"
              autocomplete="off" data-role="<?= e($role) ?>">
            <input type="hidden" name="role" value="<?= e($role) ?>">
            <!-- 干扰浏览器自动填充 -->
            <input type="text" name="fake_username" tabindex="-1" autocomplete="username"
                   aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;">
            <input type="password" name="fake_password" tabindex="-1" autocomplete="current-password"
                   aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;">

            <div class="form-group">
                <label for="username"><?= e(__('common.username')) ?></label>
                <input type="text" id="username" name="username" class="form-control"
                       value="<?= e($usernameValue) ?>"
                       autocomplete="username" autocorrect="off" autocapitalize="off" spellcheck="false"
                       required>
            </div>

            <div class="form-group">
                <label for="password"><?= e(__('common.password')) ?></label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" class="form-control"
                           value="<?= e($passwordValue) ?>"
                           autocomplete="current-password" required>
                    <button type="button" class="toggle-password" aria-label="<?= e(__('common.show_password')) ?>">👁</button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block"><?= e(__('auth.login_btn')) ?></button>
        </form>

        <div class="auth-links">
            <p><a href="<?= baseUrl('auth/forgot-password.php') ?>"><?= e(__('auth.forgot_password')) ?></a></p>
            <p><?= e(__('auth.no_account')) ?> <a href="<?= baseUrl('auth/register.php') ?>"><?= e(__('auth.register_now')) ?></a></p>
        </div>
    </div>
    <?php require __DIR__ . '/../includes/auth-footer.php'; ?>
</div>
<script src="<?= e(assetUrl('assets/js/app.js')) ?>"></script>
</body>
</html>
