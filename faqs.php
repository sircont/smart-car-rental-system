<?php
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Helpers;

$base = Helpers::baseUrl();
$pageTitle = 'FAQs';
require __DIR__ . '/inc/portal_header.php';
?>
<main class="container-fluid px-4 px-lg-5 py-5">
    <h1 class="mb-4">FAQs</h1>
    <div class="accordion" id="faqAccordion">
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">How do I book a car?</button>
            </h2>
            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                <div class="accordion-body">Register or log in, go to Car Listing, choose a vehicle and dates, then complete payment. You can pay via Mobile Money or card.</div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">What documents do I need?</button>
            </h2>
            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">A valid driver's licence and ID are required at pickup. Details may be requested during booking.</div>
            </div>
        </div>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">Can I cancel or modify my booking?</button>
            </h2>
            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">Contact us via the support email or helpline to request changes or cancellation. Terms may apply depending on how close to the pickup date you cancel.</div>
            </div>
        </div>
    </div>
</main>
<?php require __DIR__ . '/inc/portal_footer.php'; ?>
