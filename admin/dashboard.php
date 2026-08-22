<?php
require_once __DIR__ . '/../includes/auth.php';

check_access(['admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Welloo Bonanza</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Roboto, sans-serif; }
        body { background: #0F0F0F; color: #FFF; padding: 20px; }
        .wrapper { max-width: 1100px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #222; flex-wrap: wrap; gap: 12px; }
        h1 { color: #FF6600; font-size: 24px; }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .card { background: #1A1A1A; border: 1px solid #333; padding: 22px; border-radius: 10px; text-decoration: none; display: block; }
        .card h3 { color: #FF9900; font-size: 15px; margin-bottom: 6px; }
        .card p { color: #888; font-size: 12.5px; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/admin_nav.php'; ?>
<div class="wrapper">
    <div class="header">
        <h1>Welcome, <?= htmlspecialchars($_SESSION['username']) ?></h1>
    </div>

    <div class="cards">
        <a class="card" href="users.php">
            <h3>User Management</h3>
            <p>Create, edit, and manage admin accounts.</p>
        </a>
        <a class="card" href="entries.php">
            <h3>Entries</h3>
            <p>Manage and verify registration entries.</p>
        </a>
        <a class="card" href="draw.php">
            <h3>Weekly Draw</h3>
            <p>Run and manage the weekly prize draw.</p>
        </a>
        <a class="card" href="batches.php">
            <h3>Draw Batches</h3>
            <p>Manage weekly entry windows and deadlines.</p>
        </a>
    </div>
</div>
</body>
</html>
