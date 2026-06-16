<footer class="bg-dark text-light py-4 mt-5">
    <div class="container-fluid px-4 px-lg-5 text-center">
        <p class="mb-0">&copy; <?= date('Y') ?> DriveSmart Rentals Portal — Ghana</p>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php
if (!empty($portalPasswordToggle)) {
    $ptBase = \App\Helpers::baseUrl();
    echo '<script src="' . \App\Helpers::e($ptBase) . '/assets/password-toggle.js"></script>' . "\n";
}
?>
</body>
</html>
