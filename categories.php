<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';
use App\Auth;
use App\Helpers;
Auth::requireAdmin();
// SSRN schema uses brands, not vehicle_categories. Redirect to brands.
Helpers::redirect(Helpers::baseUrl() . '/admin/brands.php');
