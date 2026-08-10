<?php
require_once __DIR__ . '/includes/auth.php';

header('Location: ' . baseUrl('auth/login.php'));
exit;
