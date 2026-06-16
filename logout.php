<?php
require_once dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Helpers;

Auth::logout();
Helpers::redirect(Helpers::baseUrl() . '/index.php');
