<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

// Already logged in → go to SPA
if (Auth::user()) {
    header('Location: ' . APP_BASE . '/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $user = Auth::login($username, $password);
    if ($user) {
        header('Location: ' . APP_BASE . '/');
        exit;
    }
    $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>examis — เข้าสู่ระบบ</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box}
  body{margin:0;font-family:'Sarabun',system-ui,sans-serif;background:radial-gradient(130% 90% at 50% 0%,#134e4a 0%,#0c3d39 50%,#082c29 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;color:#0f172a;-webkit-font-smoothing:antialiased}
  .card{background:#fff;border-radius:22px;padding:34px 30px 28px;width:100%;max-width:420px;box-shadow:0 30px 70px -30px rgba(0,0,0,.6)}
  .logo{display:flex;align-items:center;gap:11px;margin-bottom:28px}
  .logo-icon{width:42px;height:42px;border-radius:12px;background:linear-gradient(150deg,#0f766e,#134e4a);display:flex;align-items:center;justify-content:center}
  .logo-text{font-size:20px;font-weight:800;letter-spacing:-.3px}
  .logo-sub{font-size:12px;color:#94a3b8;margin-top:-2px}
  label{display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:7px}
  .field{margin-bottom:16px}
  input[type=text],input[type=password]{width:100%;padding:12px 14px;border:1.5px solid #e2e8f0;border-radius:12px;font-size:15px;font-family:inherit;outline:none;transition:border-color .15s,box-shadow .15s}
  input:focus{border-color:#0f766e;box-shadow:0 0 0 3px rgba(15,118,110,.12)}
  .error{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:11px 14px;border-radius:10px;font-size:13.5px;margin-bottom:18px;display:flex;align-items:center;gap:8px}
  .btn{width:100%;padding:14px;border-radius:13px;border:none;background:#0f766e;color:#fff;font-size:16px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:8px;transition:background .15s}
  .btn:hover{background:#0d6660}
  .hint{text-align:center;color:#6ee7d4;font-size:12.5px;margin-top:18px}
  .role-hint{background:#f0fdfa;border:1px solid #cdeae6;border-radius:12px;padding:12px 14px;font-size:12.5px;color:#0f766e;line-height:1.6;margin-bottom:20px}
  .role-hint b{display:block;font-weight:700;margin-bottom:4px}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-icon">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#99f6e4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M9 8h7M9 12h5"/></svg>
    </div>
    <div>
      <div class="logo-text">examis</div>
      <div class="logo-sub">ระบบจัดการการสอบปลายภาคออนไลน์</div>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="error">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <form method="post" autocomplete="on">
    <div class="field">
      <label for="username">ชื่อผู้ใช้</label>
      <input id="username" name="username" type="text" placeholder="username"
             value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
             autocomplete="username" required autofocus>
    </div>
    <div class="field">
      <label for="password">รหัสผ่าน</label>
      <input id="password" name="password" type="password" placeholder="••••••••"
             autocomplete="current-password" required>
    </div>
    <button class="btn" type="submit">
      เข้าสู่ระบบ
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
    </button>
  </form>

  <div class="role-hint">
    <b>บัญชีทดสอบ (หลังติดตั้ง)</b>
    admin / Admin@1234 &nbsp;·&nbsp; malee.j / Pass@1234<br>
    somying.r / Pass@1234 &nbsp;·&nbsp; std13045 / Student@1
  </div>

  <div class="hint">ยังไม่มีบัญชี? ติดต่อผู้ดูแลระบบ</div>
</div>
</body>
</html>
