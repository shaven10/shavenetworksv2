<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('owner');

$pageTitle = 'Post Announcement';
$currentPage = 'announcements';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $publish = isset($_POST['is_published']);
    $pin = isset($_POST['is_pinned']);

    try {
        $id = createAnnouncement($title, $body, $publish, $pin, (int) currentUser()['id']);
        logActivity('announcement_created', "Posted announcement: {$title}");
        flash('success', $publish ? 'Announcement published.' : 'Announcement saved as draft.');
        redirect('/announcements/view.php?id=' . $id);
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$data = [
    'errors'       => $errors,
    'title'        => $_POST['title'] ?? '',
    'body'         => $_POST['body'] ?? '',
    'is_published' => isset($_POST['is_published']),
    'is_pinned'    => isset($_POST['is_pinned']),
];

require __DIR__ . '/../includes/header.php';
renderAnnouncementForm($data, 'create');
require __DIR__ . '/../includes/footer.php';
