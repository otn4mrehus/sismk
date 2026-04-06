<?php
session_start();

header('Content-Type: image/png');

$code = $_SESSION['captcha_code'] ?? str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

$width = 120;
$height = 40;

$image = imagecreate($width, $height);

$bgColor = imagecolorallocate($image, 255, 255, 255);
$textColor = imagecolorallocate($image, 0, 0, 0);
$lineColor = imagecolorallocate($image, 200, 200, 200);

for ($i = 0; $i < 5; $i++) {
    imageline($image, rand(0, $width), rand(0, $height), rand(0, $width), rand(0, $height), $lineColor);
}

for ($i = 0; $i < 50; $i++) {
    imagesetpixel($image, rand(0, $width), rand(0, $height), $lineColor);
}

$fontSize = 5;
$x = 15;
$y = 10;

imagestring($image, $fontSize, $x, $y, $code[0], $textColor);
imagestring($image, $fontSize, $x + 25, $y, $code[1], $textColor);
imagestring($image, $fontSize, $x + 50, $y, $code[2], $textColor);
imagestring($image, $fontSize, $x + 75, $y, $code[3], $textColor);

imagepng($image);
imagedestroy($image);
