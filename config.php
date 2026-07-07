<?php
/**
 * ملف الإعدادات الرئيسي
 * يحتوي على إعدادات قاعدة البيانات والأمان
 */

// إعدادات قاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_NAME', 'secure_app');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// إعدادات الموقع
define('SITE_NAME', 'SecureApp');
if (php_sapi_name() === 'cli' || php_sapi_name() === 'cli-server') {
    define('SITE_URL', 'http://localhost/Secure');
} else {
    $site_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $site_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $site_base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/Secure'), '/');
    define('SITE_URL', $site_protocol . '://' . $site_host . $site_base);
}

// تحذير عند استخدام صلاحيات قاعدة البيانات الافتراضية في بيئة إنتاج
if (DB_USER === 'root' && DB_PASS === '') {
    if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cli-server') {
        $host_name = explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0];
        if ($host_name !== 'localhost' && $host_name !== '127.0.0.1') {
            error_log("SECURITY WARNING: Using default DB credentials (root/no password) on non-localhost server: $host_name");
        }
    }
}

// إعدادات رفع الملفات
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 ميجابايت
define('UPLOAD_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);

// الملفات المسموح برفعها
$allowed_types = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'application/pdf' => 'pdf',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.ms-excel' => 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
];

// إعدادات الأمان
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 15);
define('PASSWORD_MIN_LENGTH', 8);

// تفعيل الجلسة بإعدادات أمان
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']), // فقط عبر HTTPS إذا كان متاح
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// إنشاء اتصال قاعدة البيانات
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("عذراً، حدث خطأ في الاتصال بقاعدة البيانات. الرجاء المحاولة لاحقاً.");
        }
    }
    return $pdo;
}

// فرض HTTPS في الإنتاج (يتم تعطيلها في localhost)
function enforce_https() {
    // نتخطى إذا كان الأمر من CLI (مثل php -S)
    if (php_sapi_name() === 'cli' || php_sapi_name() === 'cli-server') {
        return;
    }
    // نتخطى إذا بدأ الإخراج أصلاً (التحذيرات تمنع إرسال الـ header)
    if (headers_sent()) {
        return;
    }
    
    $https = $_SERVER['HTTPS'] ?? '';
    $host  = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    $uri   = $_SERVER['REQUEST_URI'] ?? '';
    
    if ($https !== 'on' && $host !== '') {
        $host_name = explode(':', $host)[0];
        if ($host_name !== 'localhost' && $host_name !== '127.0.0.1') {
            header('Location: https://' . $host . $uri);
            exit;
        }
    }
}
