<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requireLinkedCustomer();

$pageTitle = 'New Inquiry';
$currentPage = 'inquiries';
$customer = getLinkedCustomer();
$user = currentUser();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $category = $_POST['category'] ?? 'general';

    if (!$subject) $errors[] = 'Subject is required.';
    if (!$message) $errors[] = 'Message is required.';

    if (empty($errors)) {
        $stmt = getDB()->prepare(
            'INSERT INTO inquiries (inquiry_number, customer_id, subject, message, category, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            generateInquiryNumber(), $customer['id'], $subject, $message, $category, $user['id'],
        ]);
        logActivity('inquiry_created', 'Customer submitted inquiry');
        flash('success', 'Inquiry submitted successfully. We will respond soon.');
        redirect('/portal/inquiries/index.php');
    }
}

require __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1>Submit Inquiry</h1>
    <a href="<?= APP_URL ?>/portal/inquiries/index.php" class="btn btn-outline">← Back</a>
</div>

<div class="card card-form">
    <?php if ($errors): ?>
    <div class="alert alert-danger"><ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="POST">
        <div class="form-grid">
            <div class="form-group full-width">
                <label for="subject">Subject *</label>
                <input type="text" id="subject" name="subject" required value="<?= e($_POST['subject'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="category">Category *</label>
                <select id="category" name="category" required>
                    <option value="account">Account</option>
                    <option value="billing">Billing</option>
                    <option value="service">Service</option>
                    <option value="general">General</option>
                </select>
            </div>
            <div class="form-group full-width">
                <label for="message">Your Message *</label>
                <textarea id="message" name="message" rows="6" required
                          placeholder="Write your question or concern about your account..."><?= e($_POST['message'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Submit Inquiry</button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
