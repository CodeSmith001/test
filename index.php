<?php
/**
 * صفحة تسجيل الدخول وإنشاء الحساب
 */

require_once __DIR__ . '/functions.php';

// إذا كان المستخدم مسجل دخوله بالفعل، نحوله للوحة التحكم
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

// التحقق من محاولات الدخول من نفس الـ IP
$ip_locked = is_ip_locked();

// معالجة تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'خطأ في التحقق من صحة الطلب. الرجاء المحاولة مرة أخرى.';
    } elseif ($ip_locked) {
        $_SESSION['error'] = 'تم حظر عنوان IP الخاص بك مؤقتاً لكثرة المحاولات الفاشلة.';
    } else {
        
        rotate_csrf_token();
        $email = clean_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $captcha = $_POST['captcha'] ?? '';
        
        // التحقق من الكابتشا
        $captcha_result = verify_captcha($captcha);
        if ($captcha_result === 'expired') {
            $_SESSION['error'] = 'انتهت صلاحية رمز التحقق، الرجاء تحديث الصفحة والمحاولة مرة أخرى.';
        } elseif ($captcha_result !== true) {
            $_SESSION['error'] = 'رمز التحقق غير صحيح، الرجاء المحاولة مرة أخرى.';
        } elseif (!validate_email($email)) {
            $_SESSION['error'] = 'البريد الإلكتروني غير صالح.';
        } elseif (empty($password)) {
            $_SESSION['error'] = 'الرجاء إدخال كلمة المرور.';
        } else {
            
            // التحقق من القفل
            if (!check_login_attempts($email)) {
                // تم تعيين رسالة الخطأ داخل الدالة
            } else {
                $pdo = getDB();
                $stmt = $pdo->prepare("SELECT id, email, password, role FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    
                    // تسجيل دخول ناجح
                    reset_login_attempts($email);
                    log_event('LOGIN_SUCCESS', "Email: $email | Role: {$user['role']}");
                    
                    // تجديد الجلسة لمنع session fixation
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['logged_in'] = true;
                    
                    // تحديث آخر تسجيل دخول
                    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?");
                    $stmt->execute([get_client_ip(), $user['id']]);
                    
                    $_SESSION['success'] = 'مرحباً بعودتك!';
                    header('Location: dashboard.php');
                    exit;
                    
                } else {
                    increment_login_attempts($email);
                    log_event('LOGIN_FAIL', "Email: $email | IP: " . get_client_ip());
                }
            }
        }
    }
    
    // الكابتشا تنحاز مباشرة بعد الخطأ
}

// معالجة إنشاء حساب جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'خطأ في التحقق من صحة الطلب. الرجاء المحاولة مرة أخرى.';
    } else {
        
        rotate_csrf_token();
        $email = clean_input($_POST['reg_email'] ?? '');
        $password = $_POST['reg_password'] ?? '';
        $confirm_password = $_POST['reg_confirm_password'] ?? '';
        $captcha = $_POST['reg_captcha'] ?? '';
        
        // التحقق من الكابتشا
        $captcha_result = verify_captcha($captcha);
        if ($captcha_result === 'expired') {
            $_SESSION['error'] = 'انتهت صلاحية رمز التحقق، الرجاء تحديث الصفحة والمحاولة مرة أخرى.';
        } elseif ($captcha_result !== true) {
            $_SESSION['error'] = 'رمز التحقق غير صحيح، الرجاء المحاولة مرة أخرى.';
        } elseif (!validate_email($email)) {
            $_SESSION['error'] = 'البريد الإلكتروني غير صالح.';
        } elseif ($password !== $confirm_password) {
            $_SESSION['error'] = 'كلمة المرور وتأكيدها غير متطابقين.';
        } else {
            $password_validation = validate_password($password);
            if ($password_validation !== true) {
                $_SESSION['error'] = $password_validation;
            } else {
                $pdo = getDB();
                
                // التحقق من عدم وجود البريد الإلكتروني مسبقاً
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->fetch()) {
                    $_SESSION['error'] = 'البريد الإلكتروني مسجل مسبقاً. الرجاء استخدام بريد آخر أو تسجيل الدخول.';
                } else {
                    // تشفير كلمة المرور وتخزين المستخدم
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    
                    $stmt = $pdo->prepare("INSERT INTO users (email, password, role, created_at) VALUES (?, ?, 'user', NOW())");
                    
                    if ($stmt->execute([$email, $hashed_password])) {
                        log_event('REGISTER', "New user: $email");
                        $_SESSION['success'] = 'تم إنشاء الحساب بنجاح! يمكنك تسجيل الدخول الآن.';
                        // إعادة تحميل الصفحة
                        header('Location: index.php#login');
                        exit;
                    } else {
                        $_SESSION['error'] = 'حدث خطأ أثناء إنشاء الحساب. الرجاء المحاولة لاحقاً.';
                    }
                }
            }
        }
    }
}

// إنشاء رمز CSRF جديد
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
    
    <div class="auth-container">
        
        <div class="auth-header">
                <h1><?php echo SITE_NAME; ?></h1>
            <p>نظام آمن لإدارة الملفات</p>
        </div>

        <?php display_message(); ?>

        <div class="auth-tabs">
            <button class="tab-btn active" onclick="switchTab('login')">تسجيل الدخول</button>
            <button class="tab-btn" onclick="switchTab('register')">إنشاء حساب</button>
        </div>

        <!-- ========== نموذج تسجيل الدخول ========== -->
        <div id="login-form" class="auth-form active">
            <form method="POST" action="index.php#login" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="login" value="1">

                <div class="form-group">
                    <label for="email">البريد الإلكتروني</label>
                    <input type="email" id="email" name="email" placeholder="example@domain.com" required maxlength="255"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password">كلمة المرور</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required minlength="8">
                </div>

                <div class="form-group captcha-group">
                    <label>رمز التحقق</label>
                    <div class="captcha-wrapper">
                        <img src="captcha.php" alt="Captcha" id="captcha-img" onclick="this.src='captcha.php?'+Math.random()">
                        <button type="button" class="refresh-captcha" onclick="document.getElementById('captcha-img').src='captcha.php?'+Math.random()">🔄</button>
                    </div>
                    <input type="text" name="captcha" placeholder="أدخل الرقم الذي تراه" required maxlength="6" autocomplete="off">
                </div>

                <button type="submit" class="btn btn-primary btn-full">تسجيل الدخول</button>
            </form>
        </div>

        <!-- ========== نموذج إنشاء حساب ========== -->
        <div id="register-form" class="auth-form">
            <form method="POST" action="index.php#register" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="register" value="1">

                <div class="form-group">
                    <label for="reg_email">البريد الإلكتروني</label>
                    <input type="email" id="reg_email" name="reg_email" placeholder="example@domain.com" required maxlength="255">
                </div>

                <div class="form-group">
                    <label for="reg_password">كلمة المرور</label>
                    <input type="password" id="reg_password" name="reg_password" placeholder="••••••••" required minlength="8">
                    <small>يجب أن تحتوي على 8 أحرف على الأقل، حرف كبير، حرف صغير، رقم، ورمز خاص</small>
                </div>

                <div class="form-group">
                    <label for="reg_confirm_password">تأكيد كلمة المرور</label>
                    <input type="password" id="reg_confirm_password" name="reg_confirm_password" placeholder="••••••••" required minlength="8">
                </div>

                <div class="form-group captcha-group">
                    <label>رمز التحقق</label>
                    <div class="captcha-wrapper">
                        <img src="captcha.php" alt="Captcha" id="reg-captcha-img" onclick="this.src='captcha.php?'+Math.random()">
                        <button type="button" class="refresh-captcha" onclick="document.getElementById('reg-captcha-img').src='captcha.php?'+Math.random()">🔄</button>
                    </div>
                    <input type="text" name="reg_captcha" placeholder="أدخل الرقم الذي تراه" required maxlength="6" autocomplete="off">
                </div>

                <button type="submit" class="btn btn-primary btn-full">إنشاء حساب</button>
            </form>
        </div>

    </div>

    <script>
        function switchTab(tab) {
            document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            
            if (tab === 'login') {
                document.getElementById('login-form').classList.add('active');
                document.querySelectorAll('.tab-btn')[0].classList.add('active');
            } else {
                document.getElementById('register-form').classList.add('active');
                document.querySelectorAll('.tab-btn')[1].classList.add('active');
            }
        }

        // التبديل للتبويب الصحيح بناءً على الهاش
        if (window.location.hash === '#register') {
            switchTab('register');
        }

        // إخفاء رسائل التنبيه بعد فترة
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(el) {
                el.style.opacity = '0';
                setTimeout(function() { el.remove(); }, 500);
            });
        }, 5000);
    </script>

</body>
</html>
