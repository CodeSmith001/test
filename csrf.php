<?php
/**
 * دوال CSRF
 * Generates tokens with IP binding, expiry, origin validation, and auto-rotation on use
 */

function generate_csrf_token() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_time']) || empty($_SESSION['csrf_ip']) || time() - $_SESSION['csrf_time'] > 7200) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_time'] = time();
        $_SESSION['csrf_ip'] = $ip;
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_time']) || empty($_SESSION['csrf_ip']) || empty($token)) {
        error_log("CSRF FAIL: missing session data from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return false;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if ($_SESSION['csrf_ip'] !== $ip) {
        error_log("CSRF FAIL: IP mismatch - session: {$_SESSION['csrf_ip']} vs client: $ip");
        return false;
    }

    if (time() - $_SESSION['csrf_time'] > 7200) {
        error_log("CSRF FAIL: token expired for IP: $ip");
        unset($_SESSION['csrf_token'], $_SESSION['csrf_time'], $_SESSION['csrf_ip']);
        return false;
    }

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        error_log("CSRF FAIL: token mismatch for IP: $ip");
        return false;
    }

    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $origin = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
        $host = $_SERVER['HTTP_HOST'];
        $host_name = explode(':', $host)[0];
        if ($origin !== $host_name && $origin !== 'localhost' && $origin !== '127.0.0.1') {
            error_log("CSRF FAIL: origin $origin rejected for host $host_name");
            return false;
        }
    } elseif (isset($_SERVER['HTTP_REFERER'])) {
        $referer = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        $host = $_SERVER['HTTP_HOST'];
        $host_name = explode(':', $host)[0];
        if ($referer !== $host_name && $referer !== 'localhost' && $referer !== '127.0.0.1') {
            error_log("CSRF FAIL: referer $referer rejected for host $host_name");
            return false;
        }
    } else {
        error_log("CSRF WARNING: no Origin or Referer header from IP: $ip");
    }

    return true;
}

function rotate_csrf_token() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_time'] = time();
    $_SESSION['csrf_ip'] = $ip;
}
