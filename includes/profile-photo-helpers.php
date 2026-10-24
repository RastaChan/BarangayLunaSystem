<?php
require_once __DIR__ . '/database.php';

function barangay_ensure_profile_photo_column(): void {
  $stmt = barangay_db()->prepare(
    "SELECT COLUMN_NAME
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'users'
       AND COLUMN_NAME = 'profile_photo_path'"
  );
  $stmt->execute();
  if (!$stmt->fetchColumn()) {
    barangay_db()->exec('ALTER TABLE users ADD COLUMN profile_photo_path VARCHAR(255) DEFAULT NULL AFTER qr_generated_at');
  }
}

function barangay_profile_photo_upload_dir(): string {
  $dir = dirname(__DIR__) . '/storage/profile-photos';
  if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
  }
  return $dir;
}

function barangay_save_profile_photo_data_url(int $userId, string $dataUrl): ?string {
  $dataUrl = trim($dataUrl);
  if ($dataUrl === '' || !preg_match('/^data:image\/(png|jpe?g|webp);base64,(.+)$/i', $dataUrl, $matches)) {
    return null;
  }

  $binary = base64_decode($matches[2], true);
  if ($binary === false || strlen($binary) > 5 * 1024 * 1024) {
    return null;
  }

  $image = imagecreatefromstring($binary);
  if (!$image) {
    return null;
  }

  $filename = 'user-' . $userId . '-' . date('YmdHis') . '.jpg';
  $target = barangay_profile_photo_upload_dir() . '/' . $filename;
  imagejpeg($image, $target, 88);
  imagedestroy($image);

  return 'storage/profile-photos/' . $filename;
}

function barangay_save_profile_photo_upload(int $userId, string $field = 'profile_photo'): ?string {
  if (empty($_FILES[$field]['tmp_name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    return null;
  }

  $tmp = $_FILES[$field]['tmp_name'];
  $info = @getimagesize($tmp);
  if (!$info || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
    return null;
  }

  $source = match ($info['mime']) {
    'image/jpeg' => imagecreatefromjpeg($tmp),
    'image/png' => imagecreatefrompng($tmp),
    'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($tmp) : null,
    default => null,
  };
  if (!$source) {
    return null;
  }

  $width = imagesx($source);
  $height = imagesy($source);
  $side = min($width, $height);
  $srcX = (int) (($width - $side) / 2);
  $srcY = (int) (($height - $side) / 2);
  $targetImage = imagecreatetruecolor(512, 512);
  imagecopyresampled($targetImage, $source, 0, 0, $srcX, $srcY, 512, 512, $side, $side);

  $filename = 'user-' . $userId . '-' . date('YmdHis') . '.jpg';
  $target = barangay_profile_photo_upload_dir() . '/' . $filename;
  imagejpeg($targetImage, $target, 88);
  imagedestroy($source);
  imagedestroy($targetImage);

  return 'storage/profile-photos/' . $filename;
}

function barangay_handle_profile_photo_upload(array &$user): ?string {
  barangay_ensure_profile_photo_column();
  $userId = (int) ($user['id'] ?? 0);
  if ($userId < 1) {
    return null;
  }

  $path = barangay_save_profile_photo_data_url($userId, (string) ($_POST['profile_photo_cropped'] ?? ''));
  if (!$path) {
    $path = barangay_save_profile_photo_upload($userId);
  }
  if (!$path) {
    return null;
  }

  $stmt = barangay_db()->prepare('UPDATE users SET profile_photo_path = :path WHERE id = :id');
  $stmt->execute([':id' => $userId, ':path' => $path]);
  $user['profile_photo_path'] = $path;
  return $path;
}
?>
