<?php
$src = dirname(__DIR__).'/tools/guide-video-build/slides/slide-01.png';
$dst = dirname(__DIR__).'/public/videos/ndamstore-guide-poster.jpg';
$im = imagecreatefrompng($src);
if (!$im) {
    fwrite(STDERR, "fail png\n");
    exit(1);
}
imagejpeg($im, $dst, 85);
imagedestroy($im);
echo $dst.' '.filesize($dst)."\n";
