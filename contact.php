<?php
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Csrf;
use App\Database;
use App\Helpers;

$sent = false;
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateOrAbort();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '') ?: null;
    $message = trim($_POST['message'] ?? '');
    if ($name === '') $errors[] = 'Name is required.';
    if ($email === '') $errors[] = 'Email is required.';
    if ($message === '') $errors[] = 'Message is required.';
    if (empty($errors)) {
        Database::run('INSERT INTO contact_queries (name, email, subject, message) VALUES (?,?,?,?)', [$name, $email, $subject, $message]);
        $sent = true;
    }
}

$base = Helpers::baseUrl();
$pageTitle = 'Contact Us';
require __DIR__ . '/inc/portal_header.php';
?>
<main class="container-fluid px-4 px-lg-5 py-5">
    <h1 class="mb-4">Contact us</h1>
    <?php if ($sent): ?>
        <div class="alert alert-success">Thank you. We have received your message and will get back to you soon.</div>
        <a href="<?= $base ?>/index.php" class="btn btn-danger">Back to home</a>
    <?php else: ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><?= implode('<br>', array_map([Helpers::class, 'e'], $errors)) ?></div>
        <?php endif; ?>
        <div class="card shadow-sm" style="max-width: 600px;">
            <div class="card-body">
                <form method="post">
                    <?= Csrf::field() ?>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" value="<?= Helpers::e($_POST['name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" value="<?= Helpers::e($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" class="form-control" name="subject" value="<?= Helpers::e($_POST['subject'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" name="message" rows="4" required><?= Helpers::e($_POST['message'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger">Send</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/inc/portal_footer.php'; ?>
