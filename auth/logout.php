<?php
require_once __DIR__ . '/../includes/auth.php';
logoutUser();
header('Location: ' . baseUrl('auth/login.php'));
exit;
