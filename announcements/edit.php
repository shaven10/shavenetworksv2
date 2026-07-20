<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('owner');

$id = (int) ($_GET['id'] ?? 0);
$announcement = getAnnouncementById($id, true);

if (!$announcement) {
    flash('danger', 'Announcement not found.');
    redirect('/announcements/index.php');
}

$pageTitle = 'Edit Announcement';
$currentPage = 'announcements';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $publish = isset($_POST['is_published']);
    $pin = isset($_POST['is_pinned']);

    try {
        updateAnnouncement($id, $title, $body, $publish, $pin);
        logActivity('announcement_updated', "Updated announcement: {$title}");
        flash('success', 'Announcement updated.');
        redirect('/announcements/view.php?id=' . $id);
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$data = [
    'errors' => $errors,
    'title'  => $_POST['title'] ?? $announcement['title'],
    'body'   => $_POST['body'] ?? $announcement['body'],
    'is_published' => $_SERVER['REQUEST_METHOD'] === 'POST'
        ? isset($_POST['is_published'])
        : (int) $announcement['is_published'] === 1,
    'is_pinned' => $_SERVER['REQUEST_METHOD'] === 'POST'
        ? isset($_POST['is_pinned'])
        : (int) $announcement['is_pinned'] === 1,
];

require __DIR__ . '/../includes/header.php';
renderAnnouncementForm($data, 'edit', $id);
?>

<div class="card danger-zone">
    <div class="card-header"><h2>Delete Announcement</h2></div>
    <p class="danger-intro">Permanently remove this announcement.</p>
    <form method="POST" action="<?= APP_URL ?>/announcements/delete.php" class="delete-account-form">
        <input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this announcement?')">Delete Announcement</button>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
