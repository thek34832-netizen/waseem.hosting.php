<?php
session_start();
require_once __DIR__ . '/storage_config.php';
require_once __DIR__ . '/auth_helpers.php';

// Already logged in? Skip straight to the dashboard.
if (authCurrentUser()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $result = authLogin($username, $password);
    if ($result['success']) {
        $_SESSION['username'] = $result['username'];
        header('Location: index.php');
        exit;
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Login — MR WASEEM HACKER HOSTING</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    html, body {
        width:100%; height:100%; overflow-x:hidden;
        font-family:'Plus Jakarta Sans', sans-serif;
        background: radial-gradient(ellipse at top, #0d1420 0%, #05070c 70%);
        color:#e8f6f5;
    }
    #network-bg-canvas {
        position: fixed; inset:0; width:100%; height:100%; z-index:0; display:block;
    }
    .wrap {
        position: relative; z-index: 2;
        min-height: 100vh;
        display:flex; align-items:center; justify-content:center;
        padding: 40px 18px;
    }
    .card {
        width:100%; max-width: 440px;
        background: rgba(15, 22, 34, 0.72);
        border: 1px solid rgba(56, 224, 209, 0.25);
        border-radius: 20px;
        padding: 38px 30px;
        backdrop-filter: blur(14px);
        box-shadow: 0 0 45px rgba(56, 224, 209, 0.12), 0 20px 50px rgba(0,0,0,0.5);
        text-align:center;
    }
    .avatar-ring {
        width: 96px; height: 96px; margin: 0 auto 20px;
        position: relative;
    }
    .avatar-ring::before {
        content:''; position:absolute; inset:-8px; border-radius:50%;
        border: 3px solid transparent;
        border-top-color:#38e0d1; border-right-color:#c04cff;
        animation: spin 2.4s linear infinite;
    }
    .avatar-ring::after {
        content:''; position:absolute; inset:-16px; border-radius:50%;
        border: 2px solid transparent;
        border-bottom-color:#ff3d9a; border-left-color: rgba(56,224,209,0.3);
        animation: spinRev 3.6s linear infinite;
    }
    .avatar-ring img {
        width:100%; height:100%; border-radius:50%; object-fit:cover;
        border: 3px solid rgba(255,255,255,0.15);
        box-shadow: 0 0 25px rgba(56,224,209,0.4);
        position:relative; z-index:2;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    @keyframes spinRev { to { transform: rotate(-360deg); } }

    h1.brand-title {
        font-family:'Poppins', sans-serif;
        font-size: 1.7rem; font-weight:800; line-height:1.3;
        background: linear-gradient(90deg, #38e0d1, #8b5cf6, #ff3d9a);
        -webkit-background-clip:text; background-clip:text; color:transparent;
        margin-bottom: 6px;
    }
    p.subtitle {
        color:#a9b8c4; font-size:0.92rem; margin-bottom: 26px; line-height:1.5;
    }
    .form-group { text-align:left; margin-bottom:18px; }
    .form-group label {
        display:block; font-size:0.82rem; font-weight:700; color:#cfe9e6;
        margin-bottom:8px; letter-spacing:0.3px;
    }
    .form-group input {
        width:100%; padding:14px 16px; border-radius:12px;
        border:1px solid rgba(56,224,209,0.25);
        background: rgba(255,255,255,0.04);
        color:#eafffb; font-size:0.95rem; font-family:inherit;
        outline:none; transition: border-color 0.25s, box-shadow 0.25s;
    }
    .form-group input:focus {
        border-color:#38e0d1; box-shadow: 0 0 0 3px rgba(56,224,209,0.15);
    }
    .form-group input::placeholder { color:#6b7c86; }

    .btn-unlock {
        width:100%; padding:15px; border:none; border-radius:12px;
        font-size:1rem; font-weight:800; letter-spacing:0.3px; cursor:pointer;
        color:#08131a;
        background: linear-gradient(90deg, #38e0d1, #ff3d9a);
        box-shadow: 0 8px 25px rgba(56,224,209,0.25);
        transition: transform 0.2s, box-shadow 0.2s;
        margin-top: 6px;
    }
    .btn-unlock:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(255,61,154,0.3); }

    .switch-link {
        margin-top: 22px; font-size:0.88rem; color:#a9b8c4;
    }
    .switch-link a { color:#38e0d1; text-decoration:none; font-weight:700; }

    .alert-error {
        background: rgba(244,67,54,0.12); border:1px solid rgba(244,67,54,0.4);
        color:#ff8a80; padding:10px 14px; border-radius:10px;
        font-size:0.85rem; margin-bottom:18px; text-align:left;
    }
</style>
</head>
<body>
    <canvas id="network-bg-canvas"></canvas>

    <div class="wrap">
        <div class="card">
            <div class="avatar-ring">
                <img src="<?php echo htmlspecialchars(OWNER_PHOTO_URL); ?>" alt="MR WASEEM HACKER">
            </div>

            <h1 class="brand-title">WASEEM - HACKER<br>Login - Page</h1>
            <p class="subtitle">Please enter username &amp; password to unlock website</p>

            <?php if ($error): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="Username" autocomplete="username" required autofocus>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Password" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn-unlock">Unlock</button>
            </form>

            <div class="switch-link">
                Don't have an account? <a href="register.php">Sign up</a>
            </div>
        </div>
    </div>

    <script src="network-bg.js"></script>
</body>
</html>
