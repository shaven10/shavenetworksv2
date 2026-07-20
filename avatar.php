<?php

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/user_avatar.php';

$filename = basename($_GET['f'] ?? '');

if ($filename === '' || !preg_match('/^\d+_[a-f0-9]+\.(jpe?g|png|webp|gif)$/i', $filename)) {
    http_response_code(404);
    exit;
}

$path = userAvatarStorageDir() . '/' . $filename;

if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $path);
finfo_close($finfo);

if (!isset(USER_AVATAR_MIMES[$mime])) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . filesize($path));
readfile($path);
