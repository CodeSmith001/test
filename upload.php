<?php
/**
 * دوال رفع الملفات
 * التحقق من الملفات وحفظها بأمان
 */

function validate_upload_file($file) {
    global $allowed_types;
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'حجم الملف تجاوز الحد المسموح به في إعدادات السيرفر',
            UPLOAD_ERR_FORM_SIZE => 'حجم الملف تجاوز الحد المسموح به في النموذج',
            UPLOAD_ERR_PARTIAL => 'تم رفع جزء فقط من الملف',
            UPLOAD_ERR_NO_FILE => 'لم يتم رفع أي ملف',
            UPLOAD_ERR_NO_TMP_DIR => 'مجلد مؤقت غير موجود',
            UPLOAD_ERR_CANT_WRITE => 'فشل في كتابة الملف على القرص',
        ];
        return isset($errors[$file['error']]) ? $errors[$file['error']] : 'خطأ غير معروف في رفع الملف';
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return 'حجم الملف يتجاوز الحد المسموح به وهو 10 ميجابايت';
    }
    
    if ($file['size'] === 0) {
        return 'الملف فارغ';
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!isset($allowed_types[$mime_type])) {
        return 'نوع الملف غير مسموح به. الأنواع المسموحة: JPG, PNG, GIF, PDF, DOC, DOCX, XLS, XLSX';
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
    
    if (!in_array($extension, $allowed_extensions)) {
        return 'امتداد الملف غير مسموح به';
    }
    
    $expected_ext = $allowed_types[$mime_type];
    if ($extension !== $expected_ext && !($extension === 'jpg' && $expected_ext === 'jpeg')) {
        return 'نوع الملف لا يتطابق مع امتداده';
    }
    
    return true;
}

function save_uploaded_file($file, $user_id) {
    $pdo = getDB();

    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) {
        log_event('UPLOAD_FAIL', "User $user_id not found in database");
        return false;
    }

    $upload_dir = UPLOAD_DIR;
    $quarantine_dir = $upload_dir . 'quarantine/';

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    if (!is_dir($quarantine_dir)) {
        mkdir($quarantine_dir, 0755, true);
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    $blocked_ext = ['php', 'phtml', 'exe', 'js', 'sh', 'bat', 'cmd'];
    if (in_array($extension, $blocked_ext)) {
        return false;
    }

    $stored_name = bin2hex(random_bytes(16)) . '.' . $extension;

    $temp_path = $file['tmp_name'];
    $destination = $upload_dir . $stored_name;

    $clamav_available = false;
    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        @exec("where clamscan 2>NUL", $output, $status);
        $clamav_available = ($status === 0);
    } else {
        @exec("which clamscan 2>/dev/null", $output, $status);
        $clamav_available = ($status === 0);
    }
    if ($clamav_available) {
        $output2 = [];
        @exec("clamscan " . escapeshellarg($temp_path), $output2, $status2);

        if ($status2 !== 0) {
            move_uploaded_file($temp_path, $quarantine_dir . $stored_name);
            log_event('FILE_QUARANTINE', "Malware detected: " . $file['name']);
            return false;
        }
    }

    if (!move_uploaded_file($temp_path, $destination)) {
        return false;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $destination);
    finfo_close($finfo);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO files (user_id, original_name, stored_name, file_type, file_size)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $file['name'],
            $stored_name,
            $mime_type,
            $file['size']
        ]);
    } catch (PDOException $e) {
        if (file_exists($destination)) {
            unlink($destination);
        }
        log_event('UPLOAD_FAIL', "DB error for user $user_id: " . $e->getMessage());
        return false;
    }

    log_event('FILE_UPLOAD', "User $user_id uploaded " . $file['name']);

    return true;
}
