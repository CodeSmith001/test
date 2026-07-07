<?php
/**
 * إعداد قاعدة البيانات
 * قم بتشغيل هذا الملف مرة واحدة فقط لإعداد قاعدة البيانات
 * ثم قم بحذفه أو نقله خارج المجلد
 */

// منع التشغيل إذا كانت قاعدة البيانات موجودة
$setup_lock = __DIR__ . '/.setup_done';
if (file_exists($setup_lock)) {
    die('تم إعداد قاعدة البيانات مسبقاً. قم بحذف ملف .setup_done إذا أردت إعادة التشغيل.');
}

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'secure_app';

echo "<!DOCTYPE html><html lang='ar' dir='rtl'><head><meta charset='UTF-8'>
<title>إعداد قاعدة البيانات</title>
<style>
body { font-family: Tahoma, Arial; background: #f1f5f9; padding: 40px; text-align: center; }
.container { background: white; max-width: 600px; margin: auto; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
h1 { color: #1e293b; }
.msg { padding: 12px; border-radius: 8px; margin: 10px 0; }
.success { background: #d1fae5; color: #065f46; }
.error { background: #fee2e2; color: #991b1b; }
.info { background: #dbeafe; color: #1e40af; }
code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 14px; }
</style></head><body><div class='container'>";
echo "<h1>🔧 إعداد قاعدة البيانات</h1>";

try {
    // الاتصال بدون قاعدة بيانات (لإنشائها)
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // إنشاء قاعدة البيانات
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<div class='msg success'>✅ تم إنشاء قاعدة البيانات `$dbname`</div>";
    
    // الاتصال بقاعدة البيانات
    $pdo->exec("USE `$dbname`");
    
    // إنشاء جدول المستخدمين
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `email` varchar(255) NOT NULL,
            `password` varchar(255) NOT NULL,
            `role` enum('user','admin') NOT NULL DEFAULT 'user',
            `login_attempts` int(11) NOT NULL DEFAULT 0,
            `locked_until` datetime DEFAULT NULL,
            `last_ip` varchar(45) DEFAULT NULL,
            `last_login` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<div class='msg success'>✅ تم إنشاء جدول `users`</div>";
    
    // إنشاء جدول الملفات
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `files` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `original_name` varchar(255) NOT NULL,
            `stored_name` varchar(255) NOT NULL,
            `file_type` varchar(100) NOT NULL,
            `file_size` bigint(20) NOT NULL,
            `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            CONSTRAINT `files_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<div class='msg success'>✅ تم إنشاء جدول `files`</div>";
    
    // إنشاء جدول logs
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) DEFAULT NULL,
            `action` varchar(50) NOT NULL,
            `details` text DEFAULT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `user_agent` text DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `action` (`action`),
            KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<div class='msg success'>✅ تم إنشاء جدول `logs`</div>";
    
    // إضافة المستخدمين التجريبيين
    // Admin: admin@secure.app / Admin@123
    $admin_hash = password_hash('Admin@123', PASSWORD_BCRYPT, ['cost' => 12]);
    $check = $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'admin@secure.app'")->fetchColumn();
    if ($check == 0) {
        $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'admin')");
        $stmt->execute(['admin@secure.app', $admin_hash]);
        echo "<div class='msg success'>✅ تم إنشاء حساب الأدمن: <code>admin@secure.app</code> / <code>Admin@123</code></div>";
    } else {
        echo "<div class='msg info'>ℹ️ حساب الأدمن موجود مسبقاً</div>";
    }
    
    // User: user@secure.app / User@123
    $user_hash = password_hash('User@123', PASSWORD_BCRYPT, ['cost' => 12]);
    $check = $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'user@secure.app'")->fetchColumn();
    if ($check == 0) {
        $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'user')");
        $stmt->execute(['user@secure.app', $user_hash]);
        echo "<div class='msg success'>✅ تم إنشاء حساب المستخدم: <code>user@secure.app</code> / <code>User@123</code></div>";
    } else {
        echo "<div class='msg info'>ℹ️ حساب المستخدم موجود مسبقاً</div>";
    }
    
    // إنشاء ملف القفل
    file_put_contents($setup_lock, date('Y-m-d H:i:s'));
    
    echo "<div class='msg success' style='margin-top:20px;font-weight:bold;'>✅ تم إعداد قاعدة البيانات بنجاح!</div>";
    echo "<p style='margin-top:20px;'><a href='index.php' style='display:inline-block;padding:12px 24px;background:#2563eb;color:white;text-decoration:none;border-radius:8px;'>الذهاب إلى صفحة تسجيل الدخول</a></p>";
    
} catch (PDOException $e) {
    echo "<div class='msg error'>❌ خطأ: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='msg info'>💡 تأكد من:<br>
          • أن خدمة MySQL شغالة<br>
          • أن اسم المستخدم وكلمة المرور صحيحان في ملف <code>config.php</code><br>
          • يمكنك تعديل الإعدادات في ملف <code>config.php</code></div>";
}

echo "</div></body></html>";
