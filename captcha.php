<?php
/**
 * رمز التحقق البصري — Generates captcha with 3-tier fallback:
 *   Tier 1: GD image (PNG)
 *   Tier 2: SVG vector
 *   Tier 3: Math text as SVG
 */

require_once __DIR__ . '/config.php';

$captcha_type = 'alphanumeric';

// If GD is missing, log a warning for the admin but keep running
if (!extension_loaded('gd')) {
    error_log("CAPTCHA WARNING: PHP GD extension is not installed. Using SVG/text fallback. Install php-gd for image-based captcha.");
}

$chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
$code = '';
for ($i = 0; $i < 5; $i++) {
    $code .= $chars[random_int(0, strlen($chars) - 1)];
}

$_SESSION['captcha_text'] = strtoupper($code);
$_SESSION['captcha_hash'] = hash('sha256', strtoupper($code) . 'S3cur3C4ptch4!');
$_SESSION['captcha_time'] = time();
$_SESSION['captcha_ip'] = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$_SESSION['captcha_ua'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

$use_gd = extension_loaded('gd') && function_exists('imagecreatetruecolor');

if ($use_gd) {

    $width = 200;
    $height = 70;

    $img = imagecreatetruecolor($width, $height);

    $bg = imagecolorallocate($img, 248, 250, 252);
    $colors[] = imagecolorallocate($img, 30, 41, 59);
    $colors[] = imagecolorallocate($img, 37, 99, 235);
    $colors[] = imagecolorallocate($img, 147, 51, 234);
    $colors[] = imagecolorallocate($img, 220, 38, 38);
    $colors[] = imagecolorallocate($img, 5, 150, 105);

    imagefill($img, 0, 0, $bg);

    for ($i = 0; $i < 4; $i++) {
        $line_c = imagecolorallocate($img, random_int(180, 220), random_int(180, 220), random_int(180, 220));
        imageline($img, random_int(0, $width), random_int(0, $height),
                        random_int(0, $width), random_int(0, $height), $line_c);
    }

    for ($i = 0; $i < 150; $i++) {
        $noise = imagecolorallocate($img, random_int(150, 200), random_int(150, 200), random_int(150, 200));
        imagesetpixel($img, random_int(0, $width), random_int(0, $height), $noise);
    }

    $font = 5;
    $char_w = imagefontwidth($font);
    $char_h = imagefontheight($font);

    $total_w = strlen($code) * $char_w;
    $start_x = ($width - $total_w) / 2;
    $y = ($height - $char_h) / 2;

    for ($i = 0; $i < strlen($code); $i++) {
        $c = $colors[$i % count($colors)];
        $x = $start_x + ($i * $char_w) + random_int(-3, 3);
        $yy = $y + random_int(-5, 5);
        imagestring($img, $font, $x, $yy, $code[$i], $c);
    }

    imagefilter($img, IMG_FILTER_GAUSSIAN_BLUR);

    header('Content-Type: image/png');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    imagepng($img);
    imagedestroy($img);

} else {

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="70" viewBox="0 0 200 70">';
    $svg .= '<rect width="200" height="70" fill="#f8fafc" rx="8"/>';

    if ($captcha_type === 'math') {
        $ops = ['+', '-'];
        $op = $ops[random_int(0, 1)];
        $a = random_int(10, 50);
        $b = random_int(1, 20);
        if ($op === '-') {
            if ($a < $b) { $a += $b; $b = $a - $b; $a = $a - $b; }
            $answer = $a - $b;
        } else {
            $answer = $a + $b;
        }
        $display = "$a $op $b = ?";
        $_SESSION['captcha_text'] = (string)$answer;
        $_SESSION['captcha_hash'] = hash('sha256', strtoupper((string)$answer) . 'S3cur3C4ptch4!');

        $svg .= '<text x="20" y="45" fill="#1e293b" font-size="30" font-family="monospace" font-weight="bold">' . htmlspecialchars($display) . '</text>';
    } else {
        $colors_svg = ['#1e293b', '#2563eb', '#9333ea', '#dc2626', '#059669'];
        for ($i = 0; $i < 4; $i++) {
            $x1 = random_int(0, 200); $y1 = random_int(0, 70);
            $x2 = random_int(0, 200); $y2 = random_int(0, 70);
            $svg .= '<line x1="'.$x1.'" y1="'.$y1.'" x2="'.$x2.'" y2="'.$y2.'" stroke="#cbd5e1" stroke-width="1.5"/>';
        }
        for ($i = 0; $i < strlen($code); $i++) {
            $x = 25 + ($i * 32) + random_int(-5, 5);
            $y = 40 + random_int(-8, 8);
            $angle = random_int(-15, 15);
            $c = $colors_svg[$i % count($colors_svg)];
            $svg .= '<text x="'.$x.'" y="'.$y.'" transform="rotate('.$angle.', '.$x.', '.$y.')" fill="'.$c.'" font-size="28" font-family="monospace" font-weight="bold">'.htmlspecialchars($code[$i]).'</text>';
        }
    }

    $svg .= '</svg>';

    header('Content-Type: image/svg+xml');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo $svg;
}
