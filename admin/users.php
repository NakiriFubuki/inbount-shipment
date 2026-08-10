<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pdo = getDB();
$current = currentUser();
$activeNav = 'users';
$pageTitle = __('users.page_title');

$search = trim($_GET['search'] ?? '');
$sql = 'SELECT u.*, 
        (SELECT COUNT(*) FROM counting_records cr WHERE cr.user_id = u.id) AS record_count
        FROM users u WHERE 1=1';
$params = [];

if ($search !== '') {
    $sql .= ' AND (u.username LIKE ? OR u.email LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$sql .= ' ORDER BY u.role DESC, u.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$editId = (int) ($_GET['edit'] ?? 0);
$editUser = null;
if ($editId) {
    foreach ($users as $u) {
        if ((int) $u['id'] === $editId) {
            $editUser = $u;
            break;
        }
    }
    if (!$editUser) {
        $stmt = $pdo->prepare(
            'SELECT u.*, (SELECT COUNT(*) FROM counting_records cr WHERE cr.user_id = u.id) AS record_count
             FROM users u WHERE u.id = ?'
        );
        $stmt->execute([$editId]);
        $editUser = $stmt->fetch();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><?= e(__('users.heading')) ?></h1>
    <p><?= e(__('users.subheading')) ?></p>
</div>

<div class="card">
    <div class="card-header">
        <h2><?= $editUser ? e(__('users.edit_user')) : e(__('users.add_user')) ?></h2>
        <?php if ($editUser): ?>
        <a href="<?= baseUrl('admin/users.php') ?>" class="btn btn-outline btn-sm"><?= e(__('common.cancel_edit')) ?></a>
        <?php endif; ?>
    </div>
    <form method="post" action="<?= baseUrl('admin/user-actions.php') ?>">
        <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'create' ?>">
        <?php if ($editUser): ?>
        <input type="hidden" name="id" value="<?= (int) $editUser['id'] ?>">
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label for="username"><?= e(__('common.username')) ?> <?= e(__('common.required')) ?></label>
                <input type="text" id="username" name="username" class="form-control" required minlength="3"
                       value="<?= e($editUser['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="email"><?= e(__('common.email')) ?> <?= e(__('common.required')) ?></label>
                <input type="email" id="email" name="email" class="form-control" required
                       value="<?= e($editUser['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="role"><?= e(__('auth.register_type')) ?> <?= e(__('common.required')) ?></label>
                <select id="role" name="role" class="form-control">
                    <option value="user" <?= ($editUser['role'] ?? 'user') === 'user' ? 'selected' : '' ?>>
                        <?= e(__('auth.warehouse_user')) ?>
                    </option>
                    <option value="admin" <?= ($editUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>
                        <?= e(__('role.admin')) ?>
                    </option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="password">
                    <?= $editUser ? e(__('users.new_password')) : e(__('common.password')) ?>
                    <?= $editUser ? '' : e(__('common.required')) ?>
                </label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" class="form-control"
                           <?= $editUser ? '' : 'required minlength="6"' ?>>
                    <button type="button" class="toggle-password" aria-label="<?= e(__('common.show_password')) ?>">👁</button>
                </div>
                <?php if ($editUser): ?>
                <p class="form-hint"><?= e(__('users.leave_password_blank')) ?></p>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="confirm_password"><?= e(__('common.confirm_password')) ?> <?= $editUser ? '' : e(__('common.required')) ?></label>
                <div class="password-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                           <?= $editUser ? '' : 'required minlength="6"' ?>>
                    <button type="button" class="toggle-password" aria-label="<?= e(__('common.show_password')) ?>">👁</button>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            <?= $editUser ? e(__('common.save_changes')) : e(__('users.add_user')) ?>
        </button>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2><?= e(__('users.user_list')) ?></h2>
        <span><?= e(__('common.records_count', ['count' => count($users)])) ?></span>
    </div>

    <form method="get" class="toolbar" style="margin-bottom: 1rem;">
        <div class="form-group" style="flex:1; min-width: 200px;">
            <input type="text" name="search" class="form-control"
                   placeholder="<?= e(__('users.search_placeholder')) ?>"
                   value="<?= e($search) ?>">
        </div>
        <button type="submit" class="btn btn-primary"><?= e(__('common.search')) ?></button>
        <?php if ($search !== ''): ?>
        <a href="<?= baseUrl('admin/users.php') ?>" class="btn btn-outline"><?= e(__('common.clear')) ?></a>
        <?php endif; ?>
    </form>

    <?php if (empty($users)): ?>
    <div class="empty-state"><?= e(__('users.empty')) ?></div>
    <?php else: ?>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th><?= e(__('common.username')) ?></th>
                    <th><?= e(__('common.email')) ?></th>
                    <th><?= e(__('auth.register_type')) ?></th>
                    <th><?= e(__('users.counting_records')) ?></th>
                    <th><?= e(__('users.registered_at')) ?></th>
                    <th><?= e(__('admin.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u):
                $isSelf = (int) $u['id'] === (int) $current['id'];
            ?>
                <tr>
                    <td>
                        <strong><?= e($u['username']) ?></strong>
                        <?php if ($isSelf): ?>
                        <span class="badge badge-admin"><?= e(__('users.you')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($u['email']) ?></td>
                    <td>
                        <span class="badge <?= $u['role'] === 'admin' ? 'badge-admin' : 'badge-user' ?>">
                            <?= $u['role'] === 'admin' ? e(__('role.admin')) : e(__('role.user')) ?>
                        </span>
                    </td>
                    <td><?= (int) $u['record_count'] ?></td>
                    <td><?= e(date('Y-m-d H:i', strtotime($u['created_at']))) ?></td>
                    <td class="td-actions">
                        <div class="btn-group">
                            <a href="?edit=<?= (int) $u['id'] ?><?= $search ? '&search=' . urlencode($search) : '' ?>"
                               class="btn btn-warning btn-sm"><?= e(__('common.edit')) ?></a>
                            <?php if (!$isSelf): ?>
                            <form method="post" action="<?= baseUrl('admin/user-actions.php') ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm"
                                        data-confirm="<?= e(__('users.delete_confirm', ['name' => $u['username']])) ?>">
                                    <?= e(__('common.delete')) ?>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
