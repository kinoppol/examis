<?php
declare(strict_types=1);

if (!file_exists(__DIR__ . '/config/.installed')) {
    header('Location: setup.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>EXAMIS — ระบบจัดการสอบปลายภาคออนไลน์</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,1,0&display=block" rel="stylesheet">
<style>
  :root{
    --bg-page:#F4F6F8;--bg-card:#fff;--bg-card2:#F9FAFB;--bg-input:#fff;
    --bg-hover:#FAFAFA;--bg-brand-soft:#FEF2F2;--bg-form-add:#FFFAFA;
    --txt-1:#1A1A2A;--txt-2:#374151;--txt-3:#6B7280;--txt-4:#9CA3AF;--txt-brand:#7B1C1C;
    --bdr:#E5E7EB;--bdr-s:#F3F4F6;
    --topbar-bg:#fff;--topbar-bdr:#E5E7EB;
    --shadow-s:0 1px 3px rgba(0,0,0,0.07);
    --shadow-m:0 2px 12px rgba(0,0,0,0.08);
    --shadow-l:0 24px 80px rgba(0,0,0,0.4);
    --sb-from:#3D0A0A;--sb-to:#6B1414;
    --st-green-bg:#DCFCE7;--st-green-c:#15803D;--st-green-bdr:#BBF7D0;
    --st-blue-bg:#DBEAFE;--st-blue-c:#1D4ED8;
    --st-amber-bg:#FEF3C7;--st-amber-c:#D97706;
    --st-red-bg:#FEE2E2;--st-red-c:#DC2626;--st-red-bdr:#FECACA;
    --st-gray-bg:#F3F4F6;--st-gray-c:#6B7280;
    --st-purple-bg:#EDE9FE;--st-purple-c:#7C3AED;
  }
  [data-theme="dark"]{
    --bg-page:#0F172A;--bg-card:#1E293B;--bg-card2:#0F172A;--bg-input:#1E293B;
    --bg-hover:#162032;--bg-brand-soft:rgba(123,28,28,0.25);--bg-form-add:#180808;
    --txt-1:#F1F5F9;--txt-2:#CBD5E1;--txt-3:#94A3B8;--txt-4:#64748B;--txt-brand:#FCA5A5;
    --bdr:#334155;--bdr-s:#1E293B;
    --topbar-bg:#1E293B;--topbar-bdr:#334155;
    --shadow-s:0 1px 4px rgba(0,0,0,0.4);
    --shadow-m:0 2px 14px rgba(0,0,0,0.5);
    --shadow-l:0 24px 80px rgba(0,0,0,0.75);
    --sb-from:#090303;--sb-to:#2D0A0A;
    --st-green-bg:#14532D;--st-green-c:#4ADE80;--st-green-bdr:#166534;
    --st-blue-bg:#1E3A5F;--st-blue-c:#60A5FA;
    --st-amber-bg:#451A03;--st-amber-c:#FCD34D;
    --st-red-bg:#450A0A;--st-red-c:#FCA5A5;--st-red-bdr:#7F1D1D;
    --st-gray-bg:#1E293B;--st-gray-c:#94A3B8;
    --st-purple-bg:#2E1065;--st-purple-c:#A78BFA;
  }
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  html,body,#app{height:100%}
  body{font-family:'Noto Sans Thai',system-ui,sans-serif;background:var(--bg-page);color:var(--txt-1);transition:background .2s,color .2s}
  input,select,textarea,button{font-family:inherit}
  ::-webkit-scrollbar{width:5px;height:5px}
  ::-webkit-scrollbar-thumb{background:rgba(128,128,128,0.28);border-radius:3px}
  @keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
  .msi{font-family:'Material Symbols Rounded';font-feature-settings:'liga' 1;-webkit-font-feature-settings:'liga' 1;font-variation-settings:'FILL' 1,'wght' 400,'GRAD' 0,'opsz' 24;text-rendering:optimizeLegibility;display:inline-flex;align-items:center;justify-content:center;user-select:none;line-height:1;word-wrap:normal;white-space:nowrap;letter-spacing:normal;direction:ltr;-webkit-font-smoothing:antialiased;flex-shrink:0;}
  .toast{position:fixed;top:20px;right:20px;padding:12px 20px;border-radius:10px;font-size:14px;font-weight:600;z-index:9999;animation:slideIn 0.3s ease;box-shadow:0 4px 20px rgba(0,0,0,0.2);}
  .toast-ok{background:var(--st-green-bg);color:var(--st-green-c);border:1px solid var(--st-green-bdr);}
  .toast-err{background:var(--st-red-bg);color:var(--st-red-c);border:1px solid var(--st-red-bdr);}
  @keyframes slideIn{from{transform:translateX(120%);opacity:0}to{transform:none;opacity:1}}
  @keyframes fadeIn{from{opacity:0}to{opacity:1}}
  @keyframes scaleIn{from{opacity:0;transform:scale(0.92)}to{opacity:1;transform:scale(1)}}
</style>
</head>
<body>
<div id="app" style="height:100vh;overflow:hidden;font-family:'Noto Sans Thai',system-ui,sans-serif;"></div>
<div id="toasts"></div>
<script src="assets/app.js?v=<?= filemtime(__DIR__ . '/assets/app.js') ?>"></script>
</body>
</html>
