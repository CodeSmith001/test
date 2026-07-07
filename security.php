<?php
/**
 * دوال الأمان
 * التحقق من الكابتشا، HTTPS، الهيدرات الأمنية
 */

function verify_captcha($user_input) {
    if (empty($_SESSION['captcha_hash']) || empty($_SESSION['captcha_time']) || empty($_SESSION['captcha_ip']) || empty($_SESSION['captcha_ua'])) {
        error_log("CAPTCHA FAIL: missing session data - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        unset($_SESSION['captcha_text'], $_SESSION['captcha_hash'], $_SESSION['captcha_time'], $_SESSION['captcha_ip'], $_SESSION['captcha_ua']);
        return 'invalid';
    }
    
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if ($_SESSION['captcha_ip'] !== $client_ip) {
        error_log("CAPTCHA FAIL: IP mismatch - session: {$_SESSION['captcha_ip']} vs client: $client_ip");
        unset($_SESSION['captcha_text'], $_SESSION['captcha_hash'], $_SESSION['captcha_time'], $_SESSION['captcha_ip'], $_SESSION['captcha_ua']);
        return 'invalid';
    }
    
    $client_ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    if ($_SESSION['captcha_ua'] !== $client_ua) {
        error_log("CAPTCHA FAIL: User-Agent mismatch");
        unset($_SESSION['captcha_text'], $_SESSION['captcha_hash'], $_SESSION['captcha_time'], $_SESSION['captcha_ip'], $_SESSION['captcha_ua']);
        return 'invalid';
    }
    
    if (time() - $_SESSION['captcha_time'] > 300) {
        error_log("CAPTCHA FAIL: expired (age: " . (time() - $_SESSION['captcha_time']) . "s)");
        unset($_SESSION['captcha_text'], $_SESSION['captcha_hash'], $_SESSION['captcha_time'], $_SESSION['captcha_ip'], $_SESSION['captcha_ua']);
        return 'expired';
    }
    
    $input_hash = hash('sha256', strtoupper($user_input) . 'S3cur3C4ptch4!');
    $valid = hash_equals($_SESSION['captcha_hash'], $input_hash);
    
    unset($_SESSION['captcha_text'], $_SESSION['captcha_hash'], $_SESSION['captcha_time'], $_SESSION['captcha_ip'], $_SESSION['captcha_ua']);
    
    if (!$valid) {
        error_log("CAPTCHA FAIL: wrong code from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return 'invalid';
    }
    
    return true;
}
