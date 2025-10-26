<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/url.php';

auth_logout();
header('Location: ' . app_path('admin/login.php'));
exit;
