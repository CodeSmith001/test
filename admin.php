<?php
/**
 * لوحة الإدارة - إدارة المستخدمين والملفات
 */

require_once __DIR__ . '/functions.php';
require_admin();

$pdo = getDB();

// معالجة تغيير صلاحية مستخدم
if (isset($_POST['update_role']) && isset($_POST['user_id']) && isset($_POST['new_role'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'خطأ في التحقق من صحة الطلب.';
    } else {
        rotate_csrf_token();
        $user_id = (int)$_POST['user_id'];
        $new_role = $_POST['new_role'] === 'admin' ? 'admin' : 'user';
        
        // منع الأدمن من تغيير صلاحية نفسه
        if ($user_id === $_SESSION['user_id']) {
            $_SESSION['error'] = 'لا يمكنك تغيير صلاحية حسابك الخاص.';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            if ($stmt->execute([$new_role, $user_id])) {
                $_SESSION['success'] = 'تم تحديث صلاحية المستخدم بنجاح.';
            }
        }
    }
    header('Location: admin.php');
    exit;
}

// جلب جميع المستخدمين
$stmt = $pdo->query("
    SELECT u.*, 
           COUNT(f.id) as file_count,
           COALESCE(SUM(f.file_size), 0) as total_size
    FROM users u
    LEFT JOIN files f ON u.id = f.user_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();

// إحصائيات عامة
$stats = $pdo->query("
    SELECT 
        COUNT(DISTINCT u.id) as total_users,
        COUNT(f.id) as total_files,
        COALESCE(SUM(f.file_size), 0) as total_size,
        (SELECT COUNT(*) FROM users WHERE role = 'admin') as admin_count
    FROM users u
    LEFT JOIN files f ON u.id = f.user_id
")->fetch();

$csrf_token = generate_csrf_token();

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
    <title>لوحة الإدارة - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <h2><?php echo SITE_NAME; ?> - الإدارة</h2>
            </div>
            <div class="nav-menu">
                <span class="nav-user">👤 <?php echo htmlspecialchars($_SESSION['user_email']); ?></span>
                <a href="dashboard.php" class="btn btn-sm">الملفات</a>
                <a href="logout.php" class="btn btn-sm btn-danger">تسجيل الخروج</a>
            </div>
        </div>
    </nav>

    <div class="main-container">

        <!-- إحصائيات -->
        <div class="stats-grid">
            <div class="stat-card admin-stat">
                <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">إجمالي المستخدمين</div>
            </div>
            <div class="stat-card admin-stat">
                <div class="stat-value"><?php echo $stats['admin_count']; ?></div>
                <div class="stat-label">المشرفين</div>
            </div>
            <div class="stat-card admin-stat">
                <div class="stat-value"><?php echo $stats['total_files']; ?></div>
                <div class="stat-label">إجمالي الملفات</div>
            </div>
            <div class="stat-card admin-stat">
                <div class="stat-value"><?php echo format_size($stats['total_size']); ?></div>
                <div class="stat-label">الحجم الإجمالي</div>
            </div>
        </div>

        <?php display_message(); ?>

        <!-- قائمة المستخدمين -->
        <div class="card">
            <div class="card-header">
                <h3>إدارة المستخدمين</h3>
                <span class="card-subtitle">إجمالي <?php echo count($users); ?> مستخدم</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="files-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>البريد الإلكتروني</th>
                                <th>الصلاحية</th>
                                <th>عدد الملفات</th>
                                <th>الحجم</th>
                                <th>المحاولات الفاشلة</th>
                                <th>حالة القفل</th>
                                <th>تاريخ التسجيل</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $user['role'] === 'admin' ? 'admin' : 'user'; ?>">
                                        <?php echo $user['role'] === 'admin' ? 'أدمن' : 'مستخدم'; ?>
                                    </span>
                                </td>
                                <td><?php echo $user['file_count']; ?></td>
                                <td><?php echo format_size($user['total_size']); ?></td>
                                <td><?php echo $user['login_attempts']; ?> / <?php echo MAX_LOGIN_ATTEMPTS; ?></td>
                                <td>
                                    <?php if ($user['locked_until'] && strtotime($user['locked_until']) > time()): ?>
                                        <span class="badge badge-danger">مقفول</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">نشط</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                <td class="actions">
                                    <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                    <form method="POST" action="admin.php" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="new_role" value="<?php echo $user['role'] === 'admin' ? 'user' : 'admin'; ?>">
                                        <button type="submit" name="update_role" class="btn btn-sm">
                                            <?php echo $user['role'] === 'admin' ? 'إزالة الصلاحية' : 'ترقية لأدمن'; ?>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script>
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(el) {
                el.style.opacity = '0';
                setTimeout(function() { el.remove(); }, 500);
            });
        }, 5000);
    </script>

</body>
</html>
