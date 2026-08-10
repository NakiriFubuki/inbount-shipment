<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . baseUrl('index.php'));
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'user';

    if (!in_array($role, ['admin', 'user'], true)) {
        $role = 'user';
    }

    if (strlen($username) < 3) {
        $errors[] = __('err.username_min');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = __('err.email_invalid');
    }
    if (strlen($password) < 6) {
        $errors[] = __('err.password_min');
    }
    if ($password !== $confirm) {
        $errors[] = __('err.password_mismatch');
    }

    if (empty($errors)) {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $errors[] = __('err.username_email_taken');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    'INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$username, $email, $hash, $role]);
                flash('success', __('msg.register_success'));
                header('Location: ' . baseUrl('auth/login.php?role=' . $role));
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = $e->getCode() == 23000
                ? __('err.register_duplicate')
                : __('err.register_failed');
        }
    }
}

$pageTitle = __('auth.register_title');
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
        <h1><?= e(__('auth.create_account')) ?></h1>
        <p class="subtitle"><?= e(__('auth.register_subtitle')) ?></p>

        <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" action="">
            <div class="form-group">
                <label for="username"><?= e(__('common.username')) ?></label>
                <input type="text" id="username" name="username" class="form-control"
                       value="<?= e($_POST['username'] ?? '') ?>" required minlength="3">
            </div>

            <div class="form-group">
                <label for="email"><?= e(__('common.email')) ?></label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?= e($_POST['email'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="role"><?= e(__('auth.register_type')) ?></label>
                <select id="role" name="role" class="form-control">
                    <option value="user" <?= ($_POST['role'] ?? 'user') === 'user' ? 'selected' : '' ?>><?= e(__('auth.warehouse_user')) ?></option>
                    <option value="admin" <?= ($_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>><?= e(__('role.admin')) ?></option>
                </select>
                <p class="form-hint"><?= e(__('auth.register_hint')) ?></p>
            </div>

            <div class="form-group">
                <label for="password"><?= e(__('common.password')) ?></label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" class="form-control" required minlength="6">
                    <button type="button" class="toggle-password" aria-label="<?= e(__('common.show_password')) ?>">👁</button>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password"><?= e(__('common.confirm_password')) ?></label>
                <div class="password-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password"
                           class="form-control" required minlength="6">
                    <button type="button" class="toggle-password" aria-label="<?= e(__('common.show_password')) ?>">👁</button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block"><?= e(__('auth.register_btn')) ?></button>
        </form>

        <div class="auth-links">
            <p><?= e(__('auth.has_account')) ?> <a href="<?= baseUrl('auth/login.php') ?>"><?= e(__('auth.back_login')) ?></a></p>
        </div>
    </div>
</div>
<script src="<?= e(assetUrl('assets/js/app.js')) ?>"></script>
</body>
</html>
