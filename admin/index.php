<?php

declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';

auth_start_session();

if (auth_check()) {
    header('Location: ' . app_path('admin/dashboard.php'));
    exit;
}

header('Location: ' . app_path('admin/login.php'));
exit;
