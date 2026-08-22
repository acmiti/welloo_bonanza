<?php
require_once __DIR__ . '/includes/auth.php';

// Already logged in — bounce straight to the right landing page.
if (!empty($_SESSION['user_id'])) {
    $landing_pages = [
        'admin'        => '/admin/dashboard.php',
        'data_entry'   => '/admin/entries.php',
        'draw_manager' => '/admin/draw.php',
    ];
    header('Location: ' . ($landing_pages[$_SESSION['role']] ?? '/login.php'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Welloo Always Dinum Bonanza</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Roboto, sans-serif; }
        body { background-color: #0A0A0A; color: #FFFFFF; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px 12px; }
        .container { max-width: 380px; width: 100%; background: linear-gradient(180deg, #1A1A1A 0%, #0A0A0A 100%); border: 1px solid #333; border-radius: 16px; padding: 32px 24px; box-shadow: 0 10px 30px rgba(255, 102, 0, 0.15); }

        h1 { color: #FF9900; font-size: 20px; text-align: center; margin-bottom: 4px; }
        .subtitle { color: #888; font-size: 12px; text-align: center; margin-bottom: 26px; }

        form { display: flex; flex-direction: column; gap: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        label { font-size: 13px; color: #DDD; font-weight: 600; }
        input { width: 100%; padding: 14px; background: #141414; border: 1px solid #333; border-radius: 8px; color: #FFF; font-size: 15px; outline: none; transition: border-color 0.2s, box-shadow 0.2s; }
        input:focus { border-color: #FF6600; box-shadow: 0 0 8px rgba(255,102,0,0.5); }

        .error-banner { display: none; background: rgba(255, 51, 51, 0.1); border: 1px solid #FF3333; color: #FF6B6B; font-size: 12.5px; font-weight: 600; padding: 10px 12px; border-radius: 6px; }

        .btn-login { background: linear-gradient(180deg, #FF6600 0%, #D64F00 100%); color: #FFF; font-size: 15px; font-weight: 900; padding: 16px; border: none; border-radius: 8px; cursor: pointer; margin-top: 4px; text-transform: uppercase; box-shadow: 0 4px 15px rgba(255,102,0,0.4); transition: transform 0.1s ease, opacity 0.2s ease; }
        .btn-login:active { transform: scale(0.98); }
        .btn-login:disabled { opacity: 0.6; cursor: not-allowed; }

        .footer-note { margin-top: 22px; text-align: center; font-size: 11px; color: #666; }
    </style>
</head>
<body>

<div class="container">
    <h1>Welloo Bonanza Admin</h1>
    <div class="subtitle">Sign in to manage the campaign</div>

    <div class="error-banner" id="error-banner"></div>

    <form id="loginForm">
        <div class="form-group">
            <label for="username">Username or Email</label>
            <input type="text" id="username" name="username" autocomplete="username" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn-login" id="btn-submit">Log In</button>
    </form>

    <div class="footer-note">Welloo Sri Lanka &middot; RIT Distributors</div>
</div>

<script>
    document.getElementById('loginForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const btn = document.getElementById('btn-submit');
        const errorBanner = document.getElementById('error-banner');
        errorBanner.style.display = 'none';

        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;

        btn.disabled = true;
        btn.innerText = 'Signing in...';

        try {
            const res = await fetch('api/auth/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ username, password })
            });
            const data = await res.json();

            if (data.status === 'success') {
                window.location.href = data.redirect;
                return;
            }

            errorBanner.innerText = data.message || 'Login failed. Please try again.';
            errorBanner.style.display = 'block';
        } catch (err) {
            errorBanner.innerText = 'Something went wrong. Please try again.';
            errorBanner.style.display = 'block';
        } finally {
            btn.disabled = false;
            btn.innerText = 'Log In';
        }
    });
</script>
</body>
</html>
