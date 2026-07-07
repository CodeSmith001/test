<?php
/**
 * لوحة التحكم - رفع وعرض الملفات
 */

require_once __DIR__ . '/functions.php';
require_login();

$pdo = getDB();

// معالجة رفع الملف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'خطأ في التحقق من صحة الطلب.';
    } else {
        rotate_csrf_token();
        if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
            $_SESSION['error'] = 'الرجاء اختيار ملف للرفع.';
        } else {
            
            $file = $_FILES['file'];
            $validation = validate_upload_file($file);
            
            if ($validation !== true) {
                $_SESSION['error'] = $validation;
            } else {
                if (save_uploaded_file($file, $_SESSION['user_id'])) {
                    log_event('FILE_UPLOAD', "File: {$file['name']} | Size: {$file['size']}");
                    $_SESSION['success'] = 'تم رفع الملف بنجاح!';
                } else {
                    $_SESSION['error'] = 'حدث خطأ أثناء رفع الملف. الرجاء المحاولة مرة أخرى.';
                }
            }
        }
    }
    
    header('Location: dashboard.php');
    exit;
}

// معالجة حذف ملف
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {

    $file_id = (int)($_POST['delete'] ?? 0);

    if (!verify_csrf_token($_POST['token'] ?? '')) {
        $_SESSION['error'] = 'رمز التحقق غير صحيح.';
        header('Location: dashboard.php');
        exit;
    }

    rotate_csrf_token();

    if (is_admin()) {
        $stmt = $pdo->prepare("SELECT stored_name FROM files WHERE id = ?");
        $stmt->execute([$file_id]);
    } else {
        $stmt = $pdo->prepare("SELECT stored_name FROM files WHERE id = ? AND user_id = ?");
        $stmt->execute([$file_id, $_SESSION['user_id']]);
    }

    $file = $stmt->fetch();

    if ($file) {
        $file_path = UPLOAD_DIR . $file['stored_name'];

        if (file_exists($file_path)) {
            unlink($file_path);
        }

        $stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");
        $stmt->execute([$file_id]);

        $_SESSION['success'] = 'تم حذف الملف بنجاح.';
    } else {
        $_SESSION['error'] = 'الملف غير موجود أو ليس لديك صلاحية حذفه.';
    }

    header('Location: dashboard.php');
    exit;
}

// جلب ملفات المستخدم
if (is_admin()) {
    $stmt = $pdo->query("
        SELECT f.*, u.email 
        FROM files f 
        JOIN users u ON f.user_id = u.id 
        ORDER BY f.uploaded_at DESC
    ");
    $files = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT * FROM files WHERE user_id = ? ORDER BY uploaded_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $files = $stmt->fetchAll();
}

// إحصائيات سريعة
if (is_admin()) {
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total_files,
            COUNT(DISTINCT user_id) as total_users,
            COALESCE(SUM(file_size), 0) as total_size
        FROM files
    ")->fetch();
} else {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_files,
            COALESCE(SUM(file_size), 0) as total_size
        FROM files WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch();
}

$csrf_token = generate_csrf_token();

// تحديد نوع الملف لعرض الأيقونة المناسبة
function get_file_icon(string $type) {
    if (strpos($type, 'image/') === 0) return '🖼️';
    if (strpos($type, 'pdf') !== false) return '📄';
    if (strpos($type, 'word') !== false || strpos($type, 'document') !== false) return '📝';
    if (strpos($type, 'sheet') !== false || strpos($type, 'excel') !== false) return '📊';
    return '📁';
}

function format_size($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    
    <!-- شريط التنقل -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <h2><?php echo SITE_NAME; ?></h2>
            </div>
            <div class="nav-menu">
                <span class="nav-user">
                    👤 <?php echo htmlspecialchars($_SESSION['user_email']); ?>
                    <span class="badge badge-<?php echo is_admin() ? 'admin' : 'user'; ?>">
                        <?php echo is_admin() ? 'أدمن' : 'مستخدم'; ?>
                    </span>
                </span>
                <?php if (is_admin()): ?>
                    <a href="admin.php" class="btn btn-sm">لوحة الإدارة</a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-sm btn-danger">تسجيل الخروج</a>
            </div>
        </div>
    </nav>

    <div class="main-container">

        <!-- بطاقات الإحصائيات -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_files']; ?></div>
                <div class="stat-label">إجمالي الملفات</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo format_size($stats['total_size']); ?></div>
                <div class="stat-label">الحجم الإجمالي</div>
            </div>
            <?php if (is_admin()): ?>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">المستخدمين النشطين</div>
            </div>
            <?php endif; ?>
        </div>

        <?php display_message(); ?>

        <!-- قسم رفع الملفات -->
        <div class="card">
            <div class="card-header">
                <h3>رفع ملف جديد</h3>
                <span class="card-subtitle">الأنواع المسموحة: JPG, PNG, GIF, PDF, DOC, DOCX, XLS, XLSX (حتى 10MB)</span>
            </div>
            <div class="card-body">
                <form method="POST" action="dashboard.php" enctype="multipart/form-data" class="upload-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="upload" value="1">
                    <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo MAX_FILE_SIZE; ?>">
                    
                    <div class="file-drop-zone" id="dropZone">
                        <div class="drop-zone-content">
                            <span class="drop-icon">📤</span>
                            <p>اسحب وأفلت الملف هنا أو <span class="browse-link">اختر ملف</span></p>
                            <p class="drop-hint">أقصى حجم: 10 ميجابايت</p>
                        </div>
                        <input type="file" name="file" id="fileInput" class="file-input" required>
                    </div>
                    
                    <div id="fileInfo" class="file-info" style="display:none;">
                        <span id="fileName"></span>
                        <span id="fileSize"></span>
                        <button type="button" onclick="resetFileInput()" class="btn btn-sm">إلغاء</button>
                    </div>

                    <button type="submit" class="btn btn-primary" id="uploadBtn" disabled>رفع الملف</button>
                </form>
            </div>
        </div>

        <!-- قائمة الملفات -->
        <div class="card">
            <div class="card-header">
                <h3><?php echo is_admin() ? 'جميع الملفات المرفوعة' : 'ملفاتي'; ?></h3>
                <span class="card-subtitle">إجمالي <?php echo count($files); ?> ملف</span>
            </div>
            <div class="card-body">
                <?php if (empty($files)): ?>
                    <div class="empty-state">
                        <p>لا توجد ملفات مرفوعة بعد.</p>
                        <p>قم برفع ملفك الأول من النموذج أعلاه.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="files-table">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>اسم الملف</th>
                                    <?php if (is_admin()): ?>
                                        <th>المستخدم</th>
                                    <?php endif; ?>
                                    <th>النوع</th>
                                    <th>الحجم</th>
                                    <th>تاريخ الرفع</th>
                                    <th>إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($files as $file): ?>
                                <tr>
                                    <td><?php echo get_file_icon($file['file_type']); ?></td>
                                    <td title="<?php echo htmlspecialchars($file['original_name']); ?>">
                                        <?php echo htmlspecialchars(function_exists('mb_substr') ? mb_substr($file['original_name'], 0, 40) : substr($file['original_name'], 0, 40)) . (strlen($file['original_name']) > 40 ? '...' : ''); ?>
                                    </td>
                                    <?php if (is_admin()): ?>
                                        <td><?php echo htmlspecialchars($file['email']); ?></td>
                                    <?php endif; ?>
                                    <td><span class="badge badge-type"><?php echo strtoupper(pathinfo($file['original_name'], PATHINFO_EXTENSION)); ?></span></td>
                                    <td><?php echo format_size($file['file_size']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($file['uploaded_at'])); ?></td>
                                    <td class="actions">
                                        <a href="download.php?file=<?php echo $file['id']; ?>&token=<?php echo $csrf_token; ?>" class="btn btn-sm btn-success" title="تحميل">⬇</a>
                                        <form method="POST" action="dashboard.php" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذا الملف؟')">
                                            <input type="hidden" name="delete" value="<?php echo $file['id']; ?>">
                                            <input type="hidden" name="token" value="<?php echo generate_csrf_token(); ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="حذف">🗑</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <script>
        // ========== رفع الملفات بالسحب والإفلات ==========
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const uploadBtn = document.getElementById('uploadBtn');

        // النقر على منطقة السحب لفتح اختيار الملف
        dropZone.addEventListener('click', function(e) {
            if (e.target !== fileInput) {
                fileInput.click();
            }
        });

        // عند اختيار ملف
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                showFileInfo(this.files[0]);
            }
        });

        // أحداث السحب والإفلات
        dropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                fileInput.files = e.dataTransfer.files;
                showFileInfo(e.dataTransfer.files[0]);
            }
        });

        function showFileInfo(file) {
            dropZone.style.display = 'none';
            fileInfo.style.display = 'flex';
            fileName.textContent = file.name;
            
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(file.size) / Math.log(1024));
            fileSize.textContent = (file.size / Math.pow(1024, i)).toFixed(2) + ' ' + sizes[i];
            
            uploadBtn.disabled = false;
        }

        function resetFileInput() {
            fileInput.value = '';
            dropZone.style.display = 'flex';
            fileInfo.style.display = 'none';
            uploadBtn.disabled = true;
        }

        // منع الإرسال إذا كان الملف أكبر من المسموح
        document.querySelector('.upload-form').addEventListener('submit', function(e) {
            if (fileInput.files && fileInput.files[0]) {
                if (fileInput.files[0].size > <?php echo MAX_FILE_SIZE; ?>) {
                    e.preventDefault();
                    alert('حجم الملف يتجاوز الحد المسموح به وهو 10 ميجابايت');
                }
            }
        });

        // إخفاء رسائل التنبيه
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(el) {
                el.style.opacity = '0';
                setTimeout(function() { el.remove(); }, 500);
            });
        }, 5000);
    </script>

</body>
</html>
