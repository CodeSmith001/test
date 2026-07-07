<?php
/**
 * دوال تسجيل الأحداث
 * log_event — تسجيل الإجراءات في ملفات يومية
 */

function log_event($action, $details = '') {
    $log_file = __DIR__ . '/logs/' . date('Y-m-d') . '.log';
    $log_dir = __DIR__ . '/logs';
    
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    
    $user_id = $_SESSION['user_id'] ?? ($_SESSION['pending_user_id'] ?? 'guest');
    $ip = get_client_ip();
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $entry = sprintf(
        "[%s] [%s] [User: %s] [IP: %s] %s | %s\n",
        date('H:i:s'),
        strtoupper($action),
        $user_id,
        $ip,
        $details,
        $ua
    );
    
    @file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);
}
