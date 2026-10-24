<?php
require_once __DIR__ . '/includes/auth.php';

barangay_sign_out();
header('Location: login.php?status=logged_out');
exit;
?>
