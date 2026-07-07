<?php
/**
 * تسجيل الخروج
 */

require_once __DIR__ . '/functions.php';

// تنظيف الجلسة بالكامل
$_SESSION = [];

// حذف كوكي الجلسة
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly']
    ]);
}

// تدمير الجلسة
session_destroy();

// بدء جلسة جديدة لحفظ رسالة التأكيد
session_start();
$_SESSION['success'] = 'تم تسجيل الخروج بنجاح.';

header('Location: index.php');
exit;
