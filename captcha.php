<?php
/**
 * captcha.php — 图形验证码生成器（v2，修复黑块）
 * 纯 GD 实现，无需 TTF 字体文件
 * 验证码存入 $_SESSION['captcha_code']（大写）
 *
 * v1 黑块原因：imagecopymerge() 不支持透明通道，
 *   旋转字符临时图的背景色被当作不透明黑色混入主画布。
 * v2 修复：直接用 imagechar() 把字符画到主画布，
 *   随机 Y 偏移 + 正弦波形变替代旋转干扰，完全规避透明合并问题。
 */
session_start();

// ── 1. 生成验证码字符串 ────────────────────────────────────
$charset = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$length  = 5;
$code    = '';
for ($i = 0; $i < $length; $i++) {
    $code .= $charset[random_int(0, strlen($charset) - 1)];
}
$_SESSION['captcha_code'] = $code;

// ── 2. 画布尺寸 ────────────────────────────────────────────
$W = 140;
$H = 46;

$img = imagecreatetruecolor($W, $H);

// ── 3. 背景（浅紫灰渐变，左→右）─────────────────────────
for ($x = 0; $x < $W; $x++) {
    $ratio = $x / $W;
    $c = imagecolorallocate(
        $img,
        (int)(242 + $ratio * 6),
        (int)(240 + $ratio * 4),
        255
    );
    imageline($img, $x, 0, $x, $H - 1, $c);
}

// ── 4. 干扰线（低对比度）─────────────────────────────────
for ($i = 0; $i < 5; $i++) {
    $lc = imagecolorallocate(
        $img,
        random_int(185, 215),
        random_int(180, 210),
        random_int(200, 235)
    );
    imageline(
        $img,
        random_int(0, $W), random_int(0, $H),
        random_int(0, $W), random_int(0, $H),
        $lc
    );
}

// ── 5. 噪点 ───────────────────────────────────────────────
for ($i = 0; $i < 200; $i++) {
    $dc = imagecolorallocate(
        $img,
        random_int(155, 210),
        random_int(155, 210),
        random_int(155, 210)
    );
    imagesetpixel($img, random_int(0, $W - 1), random_int(0, $H - 1), $dc);
}

// ── 6. 绘制字符（直接画到主画布）─────────────────────────
// GD 内置 font 5：9px wide × 15px tall
$font  = 5;
$fontW = imagefontwidth($font);   // 9
$fontH = imagefontheight($font);  // 15

// 调色板（深色，确保在浅背景上可读）
$palette = [
    [ 80,  50, 200],  // 深紫
    [190,  30, 120],  // 玫红
    [ 15, 110, 180],  // 深蓝
    [  5, 140,  70],  // 深绿
    [180,  60,  10],  // 深橙
];

$spacing = 6;                                        // 字符间额外间距
$totalW  = $length * ($fontW + $spacing) - $spacing; // 总宽去掉末尾间距
$startX  = (int)(($W - $totalW) / 2);
$baseY   = (int)(($H - $fontH) / 2);

for ($i = 0; $i < $length; $i++) {
    $p  = $palette[$i % count($palette)];
    $fc = imagecolorallocate($img, $p[0], $p[1], $p[2]);
    $x  = $startX + $i * ($fontW + $spacing);
    $y  = $baseY  + random_int(-4, 4);   // 随机纵向偏移
    imagechar($img, $font, $x, $y, $code[$i], $fc);
}

// ── 7. 正弦波形变（增加机器识别难度，不影响肉眼阅读）───
$distorted = imagecreatetruecolor($W, $H);
// 用画布左上角的背景色填充新画布
$bgColor = imagecolorat($img, 0, 0);
imagefill($distorted, 0, 0, $bgColor);

$amp  = 1.8;    // 波幅（px）
$freq = 0.045;  // 波频
for ($x = 0; $x < $W; $x++) {
    for ($y = 0; $y < $H; $y++) {
        $sx = (int)($x + $amp * sin($y * $freq * 2 * M_PI));
        $sy = (int)($y + $amp * sin($x * $freq * 2 * M_PI));
        if ($sx >= 0 && $sx < $W && $sy >= 0 && $sy < $H) {
            imagesetpixel($distorted, $x, $y, imagecolorat($img, $sx, $sy));
        }
    }
}
imagedestroy($img);

// ── 8. 输出 PNG ───────────────────────────────────────────
header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
imagepng($distorted);
imagedestroy($distorted);