<?php
/**
 * الدوال المساعدة
 * جميع دوال الأمان والتحقق — مجمعة من الملفات المتخصصة
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/logging.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/upload.php';
require_once __DIR__ . '/security.php';

// فرض HTTPS على كل الصفحات
enforce_https();

// منع عرض الملف كصفحة ويب
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
