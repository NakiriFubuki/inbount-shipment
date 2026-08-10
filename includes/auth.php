<?php
/**
 * Authentication helpers
 */
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT id, username, email, role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . baseUrl('auth/login.php'));
        exit;
    }
}

function requireAdmin(): void
{
    requireLogin();
    $user = currentUser();
    if (!$user || $user['role'] !== 'admin') {
        header('Location: ' . baseUrl('user/dashboard.php'));
        exit;
    }
}

function requireUser(): void
{
    requireLogin();
    $user = currentUser();
    if (!$user || $user['role'] !== 'user') {
        header('Location: ' . baseUrl('admin/dashboard.php'));
        exit;
    }
}

function loginUser(int $userId, string $role): void
{
    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = $role;
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function appConfig(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }
    $file = __DIR__ . '/../config/app.php';
    $config = is_file($file) ? (require $file) : [];
    if (!is_array($config)) {
        $config = [];
    }
    return $config;
}

function appBasePathFromScript(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if ($script === '' || $script === '/') {
        return '';
    }
    $dir = dirname($script);
    if (preg_match('#/(admin|user|auth)(/|$)#', $dir)) {
        $base = preg_replace('#/(admin|user|auth)/?$#', '', rtrim($dir, '/'));
        return $base === '/' ? '' : (string) $base;
    }
    $base = rtrim($dir, '/');
    return $base === '/' ? '' : $base;
}

function appBasePath(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $cfgPath = appConfig()['base_path'] ?? null;
    if ($cfgPath !== null && $cfgPath !== '') {
        $base = rtrim(str_replace('\\', '/', (string) $cfgPath), '/');
        return $base;
    }

    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $projectRoot = rtrim(str_replace('\\', '/', realpath(__DIR__ . '/..') ?: ''), '/');

    if ($docRoot !== '' && $projectRoot !== '' && strncmp($projectRoot, $docRoot, strlen($docRoot)) === 0) {
        $base = substr($projectRoot, strlen($docRoot));
    } else {
        $base = appBasePathFromScript();
    }

    if ($base === '' || $base === false) {
        $base = '';
    }
    return $base;
}

function baseUrl(string $path = ''): string
{
    $base = appBasePath();
    $url = $base . ($path !== '' ? '/' . ltrim(str_replace('\\', '/', $path), '/') : '');
    if ($url === '') {
        return '/';
    }
    return str_replace(' ', '%20', $url);
}

/** CSS/JS URL with cache-busting version (fixes stale assets on cPanel). */
function assetUrl(string $path): string
{
    $url = baseUrl($path);
    $fullPath = realpath(__DIR__ . '/../' . ltrim(str_replace('\\', '/', $path), '/'));
    if ($fullPath && is_file($fullPath)) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . 'v=' . filemtime($fullPath);
    }
    return $url;
}
