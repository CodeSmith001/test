<?php
/**
 * دوال المصادقة وإدارة الجلسات
 * تسجيل الدخول، محاولات الدخول، الصلاحيات، قفل الحسابات
 */

function check_login_attempts($email) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT login_attempts, locked_until FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        return true;
    }

    if ($user['locked_until'] !== null) {
        $lockout_time = strtotime($user['locked_until']);
        if (time() < $lockout_time) {
            $_SESSION['error'] = 'حسابك مقفول مؤقتاً. الرجاء المحاولة لاحقاً.';
            return false;
        } else {
            $stmt = $pdo->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE email = ?");
            $stmt->execute([$email]);
        }
    }

    return true;
}

function increment_login_attempts($email) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT login_attempts FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $attempts = $user['login_attempts'] + 1;
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $locked_until = date('Y-m-d H:i:s', time() + (LOCKOUT_MINUTES * 60));
            $stmt = $pdo->prepare("UPDATE users SET login_attempts = ?, locked_until = ? WHERE email = ?");
            $stmt->execute([$attempts, $locked_until, $email]);
            $_SESSION['error'] = 'بريد إلكتروني أو كلمة مرور غير صحيحة.';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET login_attempts = ? WHERE email = ?");
            $stmt->execute([$attempts, $email]);
            $_SESSION['error'] = 'بريد إلكتروني أو كلمة مرور غير صحيحة.';
        }
    } else {
        track_unknown_login($email);
        $_SESSION['error'] = 'بريد إلكتروني أو كلمة مرور غير صحيحة.';
    }
}

function reset_login_attempts($email) {
    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE email = ?");
    $stmt->execute([$email]);
}

function track_unknown_login($email) {
    $ip = get_client_ip();
    $attempt_key = 'login_attempts_' . md5($ip);
    
    if (!isset($_SESSION[$attempt_key])) {
        $_SESSION[$attempt_key] = ['count' => 0, 'first_attempt' => time()];
    }
    
    $_SESSION[$attempt_key]['count']++;
    
    if ($_SESSION[$attempt_key]['count'] >= MAX_LOGIN_ATTEMPTS) {
        $_SESSION['locked_ip'] = time() + (LOCKOUT_MINUTES * 60);
    }
}

function is_ip_locked() {
    $ip = get_client_ip();
    $attempt_key = 'login_attempts_' . md5($ip);
    
    if (isset($_SESSION['locked_ip']) && $_SESSION['locked_ip'] > time()) {
        return true;
    }
    
    if (isset($_SESSION[$attempt_key]) && $_SESSION[$attempt_key]['count'] >= MAX_LOGIN_ATTEMPTS) {
        $_SESSION['locked_ip'] = time() + (LOCKOUT_MINUTES * 60);
        return true;
    }
    
    return false;
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function is_admin() {
    return is_logged_in() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function require_login() {
    if (!is_logged_in()) {
        $_SESSION['error'] = 'الرجاء تسجيل الدخول أولاً.';
        header('Location: index.php');
        exit;
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        $_SESSION['error'] = 'ليس لديك صلاحية الوصول إلى هذه الصفحة.';
        header('Location: dashboard.php');
        exit;
    }
}
