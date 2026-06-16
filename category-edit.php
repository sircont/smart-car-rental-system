<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';
use App\Auth;
use App\Helpers;
Auth::requireAdmin();
Helpers::redirect(Helpers::baseUrl() . '/admin/brands.php');
