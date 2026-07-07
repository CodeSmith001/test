<?php
/**
 * دوال مساعدة عامة
 * تنظيف المدخلات، التحقق، IP، الرسائل
 */

function clean_input($data) {
    $data = trim($data);
    if (is_string($data)) {
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    return $data;
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 255;
}

function validate_password($password) {
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return 'كلمة المرور يجب أن تكون على الأقل ' . PASSWORD_MIN_LENGTH . ' أحرف';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'كلمة المرور يجب أن تحتوي على حرف كبير واحد على الأقل';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'كلمة المرور يجب أن تحتوي على حرف صغير واحد على الأقل';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'كلمة المرور يجب أن تحتوي على رقم واحد على الأقل';
    }
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        return 'كلمة المرور يجب أن تحتوي على رمز خاص واحد على الأقل';
    }
    return true;
}

function get_client_ip() {
    $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($ip_keys as $key) {
        if (isset($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function redirect_with_message(string $url, string $message, string $type = 'success') {
    $_SESSION[$type] = $message;
    header('Location: ' . $url);
    exit;
}

function display_message() {
    $types = ['success', 'error', 'info', 'warning'];
    foreach ($types as $type) {
        if (isset($_SESSION[$type])) {
            $class = $type === 'success' ? 'alert-success' : ($type === 'error' ? 'alert-error' : ($type === 'info' ? 'alert-info' : 'alert-warning'));
            echo '<div class="alert ' . $class . '">' . htmlspecialchars($_SESSION[$type]) . '</div>';
            unset($_SESSION[$type]);
        }
    }
}
