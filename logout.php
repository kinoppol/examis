<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';
Auth::logout();
header('Location: ' . APP_BASE . '/login.php');
exit;
