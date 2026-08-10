<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
$user = currentUser();
$flash = getFlash();
$pageTitle = $pageTitle ?? __('app.name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($pageTitle) ?> - <?= e(__('app.title_suffix')) ?></title>
    <?php require __DIR__ . '/head-assets.php'; ?>
</head>
<body>
    <?php if ($user): ?>
    <nav class="navbar" id="main-navbar">
        <div class="nav-brand">
            <span class="nav-icon">📦</span>
            <?= e(__('app.name')) ?>
        </div>
        <button type="button" class="nav-toggle" id="nav-toggle" aria-expanded="false"
                aria-controls="nav-menu"
                aria-label="<?= e(__('nav.menu_toggle')) ?>">
            <span class="nav-toggle-bar"></span>
            <span class="nav-toggle-bar"></span>
            <span class="nav-toggle-bar"></span>
        </button>
        <div class="nav-links" id="nav-menu">
            <?php if ($user['role'] === 'admin'): ?>
                <a href="<?= baseUrl('admin/dashboard.php') ?>" class="<?= ($activeNav ?? '') === 'admin' ? 'active' : '' ?>"><?= e(__('nav.admin_dashboard')) ?></a>
                <a href="<?= baseUrl('admin/users.php') ?>" class="<?= ($activeNav ?? '') === 'users' ? 'active' : '' ?>"><?= e(__('nav.user_management')) ?></a>
                <a href="<?= baseUrl('admin/email-settings.php') ?>" class="<?= ($activeNav ?? '') === 'email' ? 'active' : '' ?>"><?= e(__('nav.email_settings')) ?></a>
            <?php else: ?>
                <a href="<?= baseUrl('user/dashboard.php') ?>" class="<?= ($activeNav ?? '') === 'user' ? 'active' : '' ?>"><?= e(__('nav.user_dashboard')) ?></a>
            <?php endif; ?>
            <span class="nav-user">👤 <?= e($user['username']) ?> (<?= $user['role'] === 'admin' ? e(__('role.admin')) : e(__('role.user')) ?>)</span>
            <a href="<?= baseUrl('auth/logout.php') ?>" class="btn btn-outline btn-sm"><?= e(__('nav.logout')) ?></a>
        </div>
    </nav>
    <?php endif; ?>

    <main class="container">
        <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>">
            <?= e($flash['message']) ?>
        </div>
        <?php endif; ?>
