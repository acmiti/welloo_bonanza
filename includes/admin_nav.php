<?php
// includes/admin_nav.php — shared admin navigation bar.
// Include after check_access() has run, so $_SESSION['username']/['role'] are set.

$__admin_nav_items = [
    'admin' => [
        ['label' => 'Dashboard',       'href' => '/admin/dashboard.php', 'match' => 'dashboard.php'],
        ['label' => 'Draw Batches',    'href' => '/admin/batches.php',   'match' => 'batches.php'],
        ['label' => 'Draw Manager',    'href' => '/admin/draw.php',      'match' => 'draw.php'],
        ['label' => 'All Entries',     'href' => '/admin/entries.php',   'match' => 'entries.php'],
        ['label' => 'User Management', 'href' => '/admin/users.php',     'match' => 'users.php'],
    ],
    'draw_manager' => [
        ['label' => 'Draw Manager', 'href' => '/admin/draw.php',    'match' => 'draw.php'],
        ['label' => 'All Entries',  'href' => '/admin/entries.php', 'match' => 'entries.php'],
    ],
    'data_entry' => [
        ['label' => 'All Entries', 'href' => '/admin/entries.php', 'match' => 'entries.php'],
    ],
];

$__admin_nav_role  = $_SESSION['role'] ?? '';
$__admin_nav_items = $__admin_nav_items[$__admin_nav_role] ?? [];
$__admin_nav_current = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
$__admin_nav_home = $__admin_nav_items[0]['href'] ?? '/login.php';

$__admin_nav_role_labels = [
    'admin'        => 'Admin',
    'draw_manager' => 'Draw Manager',
    'data_entry'   => 'Data Entry',
];
?>
<style>
    .admin-nav { background: #141414; border-bottom: 1px solid #2A2A2A; margin: -20px -20px 24px; padding: 0 20px; }
    .admin-nav-inner { max-width: 1150px; margin: 0 auto; display: flex; align-items: center; flex-wrap: wrap; gap: 16px; padding: 14px 0; }
    .admin-nav-brand { color: #FF6600; font-weight: 900; font-size: 15px; text-decoration: none; letter-spacing: 0.2px; margin-right: 8px; }
    .admin-nav-links { display: flex; flex-wrap: wrap; gap: 4px; flex: 1; }
    .admin-nav-links a { color: #AAA; text-decoration: none; font-size: 13px; font-weight: 600; padding: 7px 12px; border-radius: 6px; transition: 0.15s ease; }
    .admin-nav-links a:hover { color: #FFF; background: #1F1F1F; }
    .admin-nav-links a.active { color: #FF9900; background: rgba(255, 102, 0, 0.12); }
    .admin-nav-user { display: flex; align-items: center; gap: 10px; }
    .admin-nav-username { color: #DDD; font-size: 13px; font-weight: 600; }
    .admin-nav-role-badge { display: inline-block; padding: 3px 9px; border-radius: 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.3px; }
    .admin-nav-role-badge.role-admin { background: rgba(255, 102, 0, 0.15); color: #FF9900; }
    .admin-nav-role-badge.role-draw_manager { background: rgba(37, 211, 102, 0.15); color: #25D366; }
    .admin-nav-role-badge.role-data_entry { background: rgba(77, 166, 255, 0.15); color: #4DA6FF; }
    .admin-nav-logout { color: #FF6B6B !important; }
</style>
<div class="admin-nav">
    <div class="admin-nav-inner">
        <a class="admin-nav-brand" href="<?= htmlspecialchars($__admin_nav_home) ?>">Welloo Bonanza Admin</a>
        <nav class="admin-nav-links">
            <?php foreach ($__admin_nav_items as $__item): ?>
                <a href="<?= htmlspecialchars($__item['href']) ?>" class="<?= $__admin_nav_current === $__item['match'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($__item['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="admin-nav-user">
            <span class="admin-nav-username"><?= htmlspecialchars($_SESSION['username'] ?? '') ?></span>
            <span class="admin-nav-role-badge role-<?= htmlspecialchars($__admin_nav_role) ?>">
                <?= htmlspecialchars($__admin_nav_role_labels[$__admin_nav_role] ?? $__admin_nav_role) ?>
            </span>
            <a class="admin-nav-logout" href="/api/auth/logout.php">Log Out</a>
        </div>
    </div>
</div>
