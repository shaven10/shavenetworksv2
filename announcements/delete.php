<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('owner');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/announcements/index.php');
}

$id = (int) ($_POST['id'] ?? 0);

try {
    $announcement = getAnnouncementById($id, true);
    if (!$announcement) {
        throw new RuntimeException('Announcement not found.');
    }

    deleteAnnouncement($id);
    logActivity('announcement_deleted', 'Deleted announcement: ' . $announcement['title']);
    flash('success', 'Announcement deleted.');
} catch (Throwable $e) {
    flash('danger', $e->getMessage());
}

redirect('/announcements/index.php');
