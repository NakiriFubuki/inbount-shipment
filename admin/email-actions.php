<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
requireAdmin();

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . baseUrl('admin/email-settings.php'));
    exit;
}

switch ($action) {
    case 'save':
        $existing = getEmailConfig();
        $method = ($_POST['mail_method'] ?? 'php') === 'smtp' ? 'smtp' : 'php';

        $pass = trim($_POST['smtp_pass'] ?? '');
        if ($pass === '') {
            $pass = $existing['smtp_pass'];
        } else {
            $pass = normalizeSmtpPassword($pass);
        }

        $draftForPass = [
            'smtp_host' => trim($_POST['smtp_host'] ?? 'smtp.gmail.com'),
            'smtp_user' => strtolower(trim($_POST['smtp_user'] ?? '')),
        ];
        if ($pass !== '') {
            $pass = normalizeSmtpPasswordForConfig($draftForPass, $pass);
        }

        $fromEmail = strtolower(trim($_POST['from_email'] ?? ''));
        if ($fromEmail === '') {
            $fromEmail = defaultFromEmail();
        }

        if ($method === 'smtp' || ($pass !== '' && strtolower(trim($_POST['smtp_user'] ?? '')) !== '')) {
            $method = 'smtp';
        }

        $config = normalizeEmailConfig([
            'enabled' => isset($_POST['enabled']),
            'mail_method' => $method,
            'smtp_host' => trim($_POST['smtp_host'] ?? 'mail.localhost'),
            'smtp_port' => (int) ($_POST['smtp_port'] ?? 465),
            'smtp_encryption' => $_POST['smtp_encryption'] ?? 'ssl',
            'smtp_user' => strtolower(trim($_POST['smtp_user'] ?? '')),
            'smtp_pass' => $pass,
            'from_email' => $fromEmail,
            'from_name' => trim($_POST['from_name'] ?? 'Product Inbound Shipment System'),
            'smtp_insecure_ssl' => isset($_POST['smtp_insecure_ssl']),
        ]);

        if ($config['enabled']) {
            $validationError = validateEmailConfig($config);
            if ($validationError) {
                flash('error', $validationError);
                header('Location: ' . baseUrl('admin/email-settings.php'));
                exit;
            }
        }

        if (!saveEmailConfig($config)) {
            flash('error', __('email.config_save_failed'));
            header('Location: ' . baseUrl('admin/email-settings.php'));
            exit;
        }

        if ($config['enabled']) {
            $testTo = filter_var($fromEmail, FILTER_VALIDATE_EMAIL) ? $fromEmail : '';
            if ($testTo) {
                $test = sendTestEmail($testTo);
                if ($test['sent']) {
                    flash('success', __('email.config_saved_and_test_ok'));
                } else {
                    flash('error', __('email.config_saved_but_fail') . ($test['error'] ?? ''));
                    header('Location: ' . baseUrl('admin/email-settings.php?test=fail'));
                    exit;
                }
            } else {
                flash('success', __('email.config_saved'));
            }
        } else {
            flash('success', __('email.config_saved'));
        }
        header('Location: ' . baseUrl('admin/email-settings.php'));
        exit;

    case 'test':
        $to = trim($_POST['test_email'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('error', __('err.email_invalid'));
            header('Location: ' . baseUrl('admin/email-settings.php'));
            exit;
        }

        if (!isEmailEnabled()) {
            flash('error', __('email.not_configured'));
            header('Location: ' . baseUrl('admin/email-settings.php'));
            exit;
        }

        $result = sendTestEmail($to);
        if ($result['sent']) {
            header('Location: ' . baseUrl('admin/email-settings.php?test=ok'));
        } else {
            flash('error', __('email.test_failed') . ' ' . ($result['error'] ?? ''));
            header('Location: ' . baseUrl('admin/email-settings.php?test=fail'));
        }
        exit;

    default:
        flash('error', __('msg.invalid_action'));
        header('Location: ' . baseUrl('admin/email-settings.php'));
        exit;
}
