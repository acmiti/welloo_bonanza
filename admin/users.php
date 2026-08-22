<?php
require_once __DIR__ . '/../includes/auth.php';

check_access(['admin']);

$stmt = $pdo->query("SELECT id, username, email, role, status, created_at FROM users ORDER BY id DESC");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | Welloo Bonanza Admin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Roboto, sans-serif; }
        body { background: #0F0F0F; color: #FFF; padding: 20px; }
        .wrapper { max-width: 1100px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #222; flex-wrap: wrap; gap: 12px; }
        h1 { color: #FF6600; font-size: 24px; }
        .nav-links { display: flex; gap: 14px; align-items: center; }
        .nav-links a { color: #AAA; text-decoration: none; font-size: 13px; font-weight: 600; }
        .nav-links a:hover { color: #FF9900; }
        .btn { background: #FF6600; color: #000; border: none; padding: 10px 18px; font-weight: bold; border-radius: 6px; font-size: 14px; cursor: pointer; }
        .btn-secondary { background: #333; color: #FFF; }

        table { width: 100%; border-collapse: collapse; background: #1A1A1A; border-radius: 10px; overflow: hidden; border: 1px solid #333; }
        th, td { padding: 12px 14px; text-align: left; font-size: 13px; border-bottom: 1px solid #2A2A2A; }
        th { background: #222; color: #AAA; text-transform: uppercase; font-size: 11px; }
        tr:hover { background: #202020; }

        select.role-select, select.status-select { background: #141414; border: 1px solid #333; color: #FFF; padding: 6px 8px; border-radius: 6px; font-size: 12px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .badge-active { background: rgba(37, 211, 102, 0.15); color: #25D366; }
        .badge-inactive { background: rgba(255, 51, 51, 0.15); color: #FF6B6B; }

        .row-actions { display: flex; gap: 8px; align-items: center; }
        .row-actions button { background: #262626; color: #DDD; border: 1px solid #3A3A3A; padding: 6px 10px; border-radius: 6px; font-size: 11px; cursor: pointer; }
        .row-actions button:hover { border-color: #FF6600; color: #FF9900; }

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: none; justify-content: center; align-items: center; z-index: 200; padding: 20px; }
        .modal-overlay.open { display: flex; }
        .modal-box { background: #1A1A1A; border: 1px solid #444; border-radius: 12px; max-width: 380px; width: 100%; padding: 24px; }
        .modal-box h2 { color: #FF9900; font-size: 17px; margin-bottom: 18px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
        .form-group label { font-size: 12.5px; color: #DDD; font-weight: 600; }
        .form-group input, .form-group select { padding: 10px 12px; background: #141414; border: 1px solid #333; border-radius: 6px; color: #FFF; font-size: 14px; outline: none; }
        .form-group input:focus, .form-group select:focus { border-color: #FF6600; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; }
        .modal-error { display: none; background: rgba(255, 51, 51, 0.1); border: 1px solid #FF3333; color: #FF6B6B; font-size: 12px; font-weight: 600; padding: 8px 10px; border-radius: 6px; margin-bottom: 12px; }

        #toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%) translateY(80px); background: #1A1A1A; border: 1px solid #FF6600; border-radius: 8px; padding: 10px 18px; font-size: 13px; opacity: 0; transition: 0.3s ease; z-index: 300; }
        #toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/admin_nav.php'; ?>
<div class="wrapper">
    <div class="header">
        <h1>User Management</h1>
        <div class="nav-links">
            <button class="btn" onclick="openCreateModal()">+ New User</button>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="users-tbody">
            <?php if (empty($users)): ?>
                <tr><td colspan="7" style="text-align:center; color:#777;">No users found.</td></tr>
            <?php else: ?>
                <?php foreach ($users as $u): ?>
                    <tr data-id="<?= (int) $u['id'] ?>">
                        <td>#<?= (int) $u['id'] ?></td>
                        <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <select class="role-select" onchange="updateRole(<?= (int) $u['id'] ?>, this.value)">
                                <?php foreach (['admin', 'data_entry', 'draw_manager'] as $r): ?>
                                    <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= $r ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <span class="badge <?= $u['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>">
                                <?= htmlspecialchars($u['status']) ?>
                            </span>
                        </td>
                        <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                        <td class="row-actions">
                            <button onclick="toggleStatus(<?= (int) $u['id'] ?>, '<?= $u['status'] === 'active' ? 'inactive' : 'active' ?>')">
                                <?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                            </button>
                            <button onclick="openResetModal(<?= (int) $u['id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">Reset Password</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Create User Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal-box">
        <h2>Create New User</h2>
        <div class="modal-error" id="create-error"></div>
        <form id="createForm">
            <div class="form-group">
                <label>Username</label>
                <input type="text" id="new-username" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" id="new-email" required>
            </div>
            <div class="form-group">
                <label>Temporary Password</label>
                <input type="text" id="new-password" minlength="8" required>
            </div>
            <div class="form-group">
                <label>Role</label>
                <select id="new-role">
                    <option value="data_entry">data_entry</option>
                    <option value="draw_manager">draw_manager</option>
                    <option value="admin">admin</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-overlay" id="resetModal">
    <div class="modal-box">
        <h2>Reset Password</h2>
        <div class="modal-error" id="reset-error"></div>
        <form id="resetForm">
            <p style="font-size:13px; color:#AAA; margin-bottom:14px;">Setting a new password for <strong id="reset-username"></strong></p>
            <div class="form-group">
                <label>New Password</label>
                <input type="text" id="reset-password" minlength="8" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('resetModal')">Cancel</button>
                <button type="submit" class="btn">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<div id="toast"></div>

<script>
    let resetUserId = null;

    function openCreateModal() {
        document.getElementById('create-error').style.display = 'none';
        document.getElementById('createForm').reset();
        document.getElementById('createModal').classList.add('open');
    }

    function openResetModal(id, username) {
        resetUserId = id;
        document.getElementById('reset-error').style.display = 'none';
        document.getElementById('resetForm').reset();
        document.getElementById('reset-username').innerText = username;
        document.getElementById('resetModal').classList.add('open');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    function showToast(message) {
        const toast = document.getElementById('toast');
        toast.innerText = message;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 2500);
    }

    async function callApi(payload) {
        const res = await fetch('/api/users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        });
        return { ok: res.ok, data: await res.json() };
    }

    document.getElementById('createForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const errorBox = document.getElementById('create-error');
        errorBox.style.display = 'none';

        const { ok, data } = await callApi({
            action: 'create',
            username: document.getElementById('new-username').value.trim(),
            email: document.getElementById('new-email').value.trim(),
            password: document.getElementById('new-password').value,
            role: document.getElementById('new-role').value
        });

        if (!ok) {
            errorBox.innerText = data.message || 'Failed to create user';
            errorBox.style.display = 'block';
            return;
        }

        closeModal('createModal');
        showToast('User created');
        setTimeout(() => window.location.reload(), 600);
    });

    document.getElementById('resetForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const errorBox = document.getElementById('reset-error');
        errorBox.style.display = 'none';

        const { ok, data } = await callApi({
            action: 'reset_password',
            id: resetUserId,
            password: document.getElementById('reset-password').value
        });

        if (!ok) {
            errorBox.innerText = data.message || 'Failed to reset password';
            errorBox.style.display = 'block';
            return;
        }

        closeModal('resetModal');
        showToast('Password reset');
    });

    async function updateRole(id, role) {
        const { ok, data } = await callApi({ action: 'update_role', id, role });
        showToast(ok ? 'Role updated' : (data.message || 'Failed to update role'));
    }

    async function toggleStatus(id, status) {
        const { ok, data } = await callApi({ action: 'toggle_status', id, status });
        if (!ok) {
            showToast(data.message || 'Failed to update status');
            return;
        }
        showToast('Status updated');
        setTimeout(() => window.location.reload(), 500);
    }
</script>
</body>
</html>
