<?php
require_once __DIR__ . '/../includes/auth.php';

check_access(['admin', 'data_entry']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entries | Welloo Bonanza Admin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Roboto, sans-serif; }
        body { background: #0F0F0F; color: #FFF; padding: 20px; }
        .wrapper { max-width: 1100px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #222; }
        h1 { color: #FF6600; font-size: 24px; }
        .placeholder { background: #1A1A1A; border: 1px solid #333; border-radius: 10px; padding: 40px; text-align: center; color: #888; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/admin_nav.php'; ?>
<div class="wrapper">
    <div class="header">
        <h1>Entries</h1>
    </div>
    <div class="placeholder">Entry management tools are coming soon. See the <a href="index.php" style="color:#FF9900;">Leads Dashboard</a> for now.</div>
</div>
</body>
</html>
