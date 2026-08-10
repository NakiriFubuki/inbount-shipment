<?php
/**
 * cPanel / production database settings.
 *
 * 1. Copy this file to database.local.php (same folder).
 * 2. Fill in the values from cPanel → MySQL Databases.
 * 3. Import database/schema.sql via phpMyAdmin.
 *
 * database.local.php is loaded automatically and overrides the defaults below.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_cpanel_db_name');
define('DB_USER', 'your_cpanel_db_user');
define('DB_PASS', 'your_cpanel_db_password');
define('DB_CHARSET', 'utf8mb4');
