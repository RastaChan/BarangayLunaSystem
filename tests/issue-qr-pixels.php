<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../includes/document-verification.php';
$image=imagecreatefromstring(barangay_document_qr_png('LUNA|RES-TEST|CAMERA-PHOTO'));
$width=imagesx($image); $height=imagesy($image); $pixels=[];
for ($y=0;$y<$height;$y++) for ($x=0;$x<$width;$x++) {
  $color=imagecolorat($image,$x,$y);
  array_push($pixels,($color>>16)&255,($color>>8)&255,$color&255,255);
}
echo json_encode(['width'=>$width,'height'=>$height,'pixels'=>$pixels]);
