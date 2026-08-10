<?php
/**
 * cPanel deployment check — upload this file with the project, then open it in the browser.
 * Delete this file after your site works.
 */
header('Content-Type: text/html; charset=UTF-8');
$root = __DIR__;
$checks = [
    'auth/login.php' => is_file($root . '/auth/login.php'),
    'index.php' => is_file($root . '/index.php'),
    'includes/auth.php' => is_file($root . '/includes/auth.php'),
    'config/database.php' => is_file($root . '/config/database.php'),
    'config/database.local.php' => is_file($root . '/config/database.local.php'),
];

$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
$script = $_SERVER['SCRIPT_NAME'] ?? '';
$folderUrl = rtrim(str_replace('\\', '/', dirname($script)), '/');
$loginUrl = ($folderUrl === '' || $folderUrl === '.') ? '/auth/login.php' : $folderUrl . '/auth/login.php';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'your-domain.com';
$fullLogin = $scheme . '://' . $host . str_replace(' ', '%20', $loginUrl);
$allOk = !in_array(false, $checks, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deployment Check</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 720px; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        h1 { font-size: 1.35rem; }
        .ok { color: #15803d; }
        .bad { color: #b91c1c; }
        code, pre { background: #f1f5f9; padding: 0.15rem 0.4rem; border-radius: 4px; }
        pre { padding: 0.75rem; overflow-x: auto; }
        a { color: #2563eb; }
        ul { padding-left: 1.25rem; }
    </style>
</head>
<body>
    <h1>Inbound Shipment System — deployment check</h1>

    <?php if ($allOk): ?>
    <p class="ok"><strong>Core files found on this server.</strong> Use the login URL below (not an old bookmark with a wrong folder name).</p>
    <?php else: ?>
    <p class="bad"><strong>Some files are missing in this folder.</strong> Re-upload the full project so every file sits in the same directory as this check.php.</p>
    <?php endif; ?>

    <h2>File check</h2>
    <ul>
        <?php foreach ($checks as $path => $ok): ?>
        <li class="<?= $ok ? 'ok' : 'bad' ?>"><?= htmlspecialchars($path) ?> — <?= $ok ? 'OK' : 'MISSING' ?></li>
        <?php endforeach; ?>
    </ul>

    <h2>Paths detected on this server</h2>
    <pre>Document root: <?= htmlspecialchars($docRoot) ?>

This folder on disk: <?= htmlspecialchars($root) ?>

URL folder (web path): <?= htmlspecialchars($folderUrl ?: '/') ?>

Correct login URL:
<?= htmlspecialchars($fullLogin) ?></pre>

    <p><a href="<?= htmlspecialchars($loginUrl) ?>">Open login page</a></p>

    <h2>If you still get 404</h2>
    <ol>
        <li>In cPanel <strong>File Manager</strong>, open <code>public_html</code> and confirm the folder name matches the URL (spaces become <code>%20</code>).</li>
        <li>Upload the <strong>contents</strong> of the project (auth, admin, config, includes, index.php, …) into <strong>one</strong> folder — not nested twice.</li>
        <li>Recommended: rename the folder to <code>inbound-shipment-system</code> (no spaces), then open
            <code>https://<?= htmlspecialchars($host) ?>/inbound-shipment-system/check.php</code></li>
        <li>Set <code>config/database.local.php</code> with your cPanel MySQL user, password, and database name.</li>
        <li>Delete <code>check.php</code> after the site works.</li>
    </ol>
</body>
</html>
