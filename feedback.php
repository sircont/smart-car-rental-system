<?php
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Database;
use App\Helpers;

$sent = false;
$errors = [];
$bookingId = isset($_GET['booking']) ? (int)$_GET['booking'] : 0;
$booking = null;
if ($bookingId > 0 && Auth::check()) {
    $booking = Database::run(
        'SELECT b.*, v.model AS vehicle_model, br.name AS brand_name
         FROM bookings b
         JOIN vehicles v ON b.vehicle_id = v.id
         JOIN brands br ON v.brand_id = br.id
         WHERE b.id = ? AND b.user_id = ?',
        [$bookingId, Auth::id()]
    )->fetch();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateOrAbort();
    Auth::requireLogin();
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    if ($bookingId > 0) {
        $booking = Database::run(
            'SELECT b.*, v.model AS vehicle_model, br.name AS brand_name
             FROM bookings b
             JOIN vehicles v ON b.vehicle_id = v.id
             JOIN brands br ON v.brand_id = br.id
             WHERE b.id = ? AND b.user_id = ?',
            [$bookingId, Auth::id()]
        )->fetch();
        if (!$booking || $booking['booking_status'] !== 'completed') {
            $errors[] = 'You can only review completed bookings.';
        }
    }
    $rating = (int)($_POST['rating'] ?? 5);
    $title = trim($_POST['title'] ?? '') ?: null;
    $content = trim($_POST['content'] ?? '');
    if ($content === '') $errors[] = 'Review content is required.';
    if ($rating < 1 || $rating > 5) $rating = 5;
    if (empty($errors)) {
        Database::run(
            'INSERT INTO testimonials (user_id, booking_id, rating, title, content, is_approved) VALUES (?, ?, ?, ?, ?, 0)',
            [Auth::id(), $bookingId ?: null, $rating, $title, $content]
        );
        $sent = true;
    }
}

$approved = Database::run(
    'SELECT t.*, u.full_name AS author_name FROM testimonials t LEFT JOIN users u ON t.user_id = u.id WHERE t.is_approved = 1 ORDER BY t.created_at DESC LIMIT 10'
)->fetchAll();

$base = Helpers::baseUrl();
$pageTitle = 'Feedback & testimonials';
require __DIR__ . '/inc/portal_header.php';
?>
<main class="container-fluid px-4 px-lg-5 py-5">
    <h1 class="mb-4">Feedback &amp; testimonials</h1>
    <?php if ($sent): ?>
        <div class="alert alert-success">Thank you for your feedback! It will be reviewed before publishing.</div>
        <?php else: ?>
        <?php if (!Auth::check()): ?>
            <div class="alert alert-info">Please <a href="<?= $base ?>/login.php">log in</a> to submit a review.</div>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger"><?= implode('<br>', array_map([Helpers::class, 'e'], $errors)) ?></div>
            <?php endif; ?>
            <div class="card shadow-sm mb-4" style="max-width: 600px;">
                <div class="card-body">
                    <h5>Submit your review</h5>
                    <?php if ($booking): ?>
                        <p class="small text-muted mb-2">
                            For booking #<?= (int)$booking['id'] ?> —
                            <?= Helpers::e($booking['brand_name']) ?> <?= Helpers::e($booking['vehicle_model']) ?>
                        </p>
                    <?php endif; ?>
                    <form method="post">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="booking_id" value="<?= $booking ? (int)$booking['id'] : 0 ?>">
                        <div class="mb-3">
                            <label class="form-label">Rating</label>
                            <select name="rating" class="form-select" style="width:auto;">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <option value="<?= $i ?>" <?= (int)($_POST['rating'] ?? 5) === $i ? 'selected' : '' ?>><?= $i ?> ★</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Title (optional)</label>
                            <input type="text" class="form-control" name="title" value="<?= Helpers::e($_POST['title'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Your review</label>
                            <textarea class="form-control" name="content" rows="4" required><?= Helpers::e($_POST['content'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger">Submit</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <h5 class="mt-4">What our customers say</h5>
    <div class="row g-3">
        <?php foreach ($approved as $t): ?>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="mb-2"><?= str_repeat('★', (int)$t['rating']) ?></div>
                        <?php if (!empty($t['title'])): ?><h6 class="card-title"><?= Helpers::e($t['title']) ?></h6><?php endif; ?>
                        <p class="card-text"><?= nl2br(Helpers::e($t['content'])) ?></p>
                        <small class="text-muted">— <?= Helpers::e($t['author_name'] ?? 'Customer') ?></small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($approved)): ?>
            <p class="text-muted">No testimonials published yet.</p>
        <?php endif; ?>
    </div>
</main>
<?php require __DIR__ . '/inc/portal_footer.php'; ?>
