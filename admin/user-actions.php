<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pdo = getDB();
$current = currentUser();
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . baseUrl('admin/users.php'));
    exit;
}

function validateUserInput(array $data, bool $requirePassword): array
{
    $errors = [];
    $username = trim($data['username'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $confirm = $data['confirm_password'] ?? '';
    $role = $data['role'] ?? 'user';

    if (!in_array($role, ['admin', 'user'], true)) {
        $role = 'user';
    }

    if (strlen($username) < 3) {
        $errors[] = __('err.username_min');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = __('err.email_invalid');
    }
    if ($requirePassword) {
        if (strlen($password) < 6) {
            $errors[] = __('err.password_min');
        }
        if ($password !== $confirm) {
            $errors[] = __('err.password_mismatch');
        }
    } elseif ($password !== '' || $confirm !== '') {
        if (strlen($password) < 6) {
            $errors[] = __('err.password_min');
        }
        if ($password !== $confirm) {
            $errors[] = __('err.password_mismatch');
        }
    }

    return [
        'errors' => $errors,
        'username' => $username,
        'email' => $email,
        'password' => $password,
        'role' => $role,
    ];
}

switch ($action) {
    case 'create':
        $v = validateUserInput($_POST, true);
        if (!empty($v['errors'])) {
            flash('error', implode(' ', $v['errors']));
            break;
        }
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$v['username'], $v['email']]);
        if ($stmt->fetch()) {
            flash('error', __('err.username_email_taken'));
            break;
        }
        $hash = password_hash($v['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$v['username'], $v['email'], $hash, $v['role']]);
        flash('success', __('msg.user_created'));
        break;

    case 'update':
        $id = (int) ($_POST['id'] ?? 0);
        $v = validateUserInput($_POST, false);
        if ($id <= 0) {
            flash('error', __('msg.invalid_action'));
            break;
        }
        if (!empty($v['errors'])) {
            flash('error', implode(' ', $v['errors']));
            header('Location: ' . baseUrl('admin/users.php?edit=' . $id));
            exit;
        }
        $stmt = $pdo->prepare('SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?');
        $stmt->execute([$v['username'], $v['email'], $id]);
        if ($stmt->fetch()) {
            flash('error', __('err.username_email_taken'));
            header('Location: ' . baseUrl('admin/users.php?edit=' . $id));
            exit;
        }
        if ($v['password'] !== '') {
            $hash = password_hash($v['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'UPDATE users SET username=?, email=?, role=?, password_hash=? WHERE id=?'
            );
            $stmt->execute([$v['username'], $v['email'], $v['role'], $hash, $id]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET username=?, email=?, role=? WHERE id=?');
            $stmt->execute([$v['username'], $v['email'], $v['role'], $id]);
        }
        flash('success', __('msg.user_updated'));
        break;

    case 'delete':
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash('error', __('msg.invalid_action'));
            break;
        }
        if ($id === (int) $current['id']) {
            flash('error', __('msg.cannot_delete_self'));
            break;
        }
        $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if (!$target) {
            flash('error', __('msg.invalid_action'));
            break;
        }
        if ($target['role'] === 'admin') {
            $count = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            if ($count <= 1) {
                flash('error', __('msg.cannot_delete_last_admin'));
                break;
            }
        }
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        flash('success', __('msg.user_deleted'));
        break;

    default:
        flash('error', __('msg.invalid_action'));
}

header('Location: ' . baseUrl('admin/users.php'));
exit;
