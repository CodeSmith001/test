<?php
/**
 * تحميل الملفات بطريقة آمنة
 */

require_once __DIR__ . '/functions.php';
require_login();

if (!isset($_GET['file']) || !isset($_GET['token'])) {
    $_SESSION['error'] = 'طلب غير صحيح.';
    header('Location: dashboard.php');
    exit;
}

if (!verify_csrf_token($_GET['token'])) {
    $_SESSION['error'] = 'رمز التحقق غير صحيح.';
    header('Location: dashboard.php');
    exit;
}

$file_id = (int)$_GET['file'];
$pdo = getDB();

// التحقق من صلاحية الوصول للملف
if (is_admin()) {
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
    $stmt->execute([$file_id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
    $stmt->execute([$file_id, $_SESSION['user_id']]);
}

$file = $stmt->fetch();

if (!$file) {
    $_SESSION['error'] = 'الملف غير موجود أو ليس لديك صلاحية الوصول إليه.';
    header('Location: dashboard.php');
    exit;
}

$file_path = UPLOAD_DIR . $file['stored_name'];

if (!file_exists($file_path)) {
    $_SESSION['error'] = 'الملف غير موجود على الخادم.';
    header('Location: dashboard.php');
    exit;
}

// منع تنفيذ الملفات الضارة (PDF, images, etc should be downloaded not executed)
$public_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
$extension = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));

// تنظيف اسم الملف للتحميل
$safe_filename = preg_replace('/[^\x{0600}-\x{06FF}\x{0020}-\x{007E}.\-_]/u', '_', $file['original_name']);
if (empty($safe_filename)) {
    $safe_filename = 'download_' . $file['stored_name'];
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $safe_filename . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// قراءة الملف وإرساله
readfile($file_path);
exit;
