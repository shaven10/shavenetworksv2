<?php

const USER_AVATAR_MAX_BYTES = 2097152;

const USER_AVATAR_MIMES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];

function userAvatarStorageDir(): string
{
    return dirname(__DIR__) . '/storage/avatars';
}

function ensureUserAvatarDir(): void
{
    $dir = userAvatarStorageDir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "Deny from all\n");
    }
}

function userAvatarInitials(string $fullName): string
{
    $fullName = trim($fullName);
    if ($fullName === '') {
        return '?';
    }

    $parts = preg_split('/\s+/', $fullName) ?: [];
    if (count($parts) >= 2) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
    }

    return strtoupper(mb_substr($fullName, 0, 2));
}

function userAvatarUrl(?array $user): ?string
{
    if (empty($user['avatar'])) {
        return null;
    }

    return APP_URL . '/avatar.php?f=' . rawurlencode($user['avatar']);
}

function renderUserAvatar(array $user, string $size = 'md', array $attrs = []): string
{
    $sizeClass = 'user-avatar-' . $size;
    $classes = trim('user-avatar ' . $sizeClass . ' ' . ($attrs['class'] ?? ''));
    unset($attrs['class']);

    $attrString = '';
    foreach ($attrs as $key => $value) {
        $attrString .= ' ' . e((string) $key) . '="' . e((string) $value) . '"';
    }

    $url = userAvatarUrl($user);
    $name = e($user['full_name'] ?? 'User');

    if ($url) {
        return '<img src="' . e($url) . '" alt="' . $name . '" class="' . e($classes) . '"' . $attrString . '>';
    }

    $initials = e(userAvatarInitials($user['full_name'] ?? ''));
    return '<span class="' . e($classes) . ' user-avatar-initials" aria-hidden="true"' . $attrString . '>' . $initials . '</span>';
}

function validateUserAvatarUpload(array $upload): ?string
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return 'Avatar upload failed. Please try again.';
    }

    if (($upload['size'] ?? 0) > USER_AVATAR_MAX_BYTES) {
        return 'Avatar must be 2 MB or smaller.';
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $upload['tmp_name']);
    finfo_close($finfo);

    if (!isset(USER_AVATAR_MIMES[$mime])) {
        return 'Avatar must be a JPG, PNG, WebP, or GIF image.';
    }

    return null;
}

function saveUserAvatar(int $userId, array $upload, ?string $currentFilename = null): ?string
{
    $error = validateUserAvatarUpload($upload);
    if ($error !== null) {
        throw new RuntimeException($error);
    }

    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $currentFilename;
    }

    ensureUserAvatarDir();

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $upload['tmp_name']);
    finfo_close($finfo);

    $extension = USER_AVATAR_MIMES[$mime];
    $filename = $userId . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $destination = userAvatarStorageDir() . '/' . $filename;

    if (!move_uploaded_file($upload['tmp_name'], $destination)) {
        throw new RuntimeException('Could not save avatar image.');
    }

    removeUserAvatarFile($currentFilename);

    return $filename;
}

function removeUserAvatarFile(?string $filename): void
{
    if (!$filename) {
        return;
    }

    $filename = basename($filename);
    $path = userAvatarStorageDir() . '/' . $filename;

    if (is_file($path)) {
        unlink($path);
    }
}

function handleUserAvatarForm(int $userId, ?string $currentFilename): ?string
{
    if (!empty($_POST['remove_avatar'])) {
        removeUserAvatarFile($currentFilename);
        return null;
    }

    if (!isset($_FILES['avatar'])) {
        return $currentFilename;
    }

    return saveUserAvatar($userId, $_FILES['avatar'], $currentFilename);
}
