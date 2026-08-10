<?php
/**
 * Mail — PHPMailer SMTP (reliable) or PHP mail() fallback
 */

$mailerAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($mailerAutoload)) {
    require_once $mailerAutoload;
}

function hasSmtpCredentials(array $config): bool
{
    return !empty($config['smtp_user'])
        && !empty($config['smtp_pass'])
        && filter_var($config['smtp_user'], FILTER_VALIDATE_EMAIL);
}

/** SMTP when method=smtp OR when SMTP account/password are saved */
function resolveMailMethod(array $config): string
{
    if (hasSmtpCredentials($config)) {
        return 'smtp';
    }

    return ($config['mail_method'] ?? 'php') === 'smtp' ? 'smtp' : 'php';
}

function normalizeEmailConfig(array $config): array
{
    if (hasSmtpCredentials($config)) {
        $config['mail_method'] = 'smtp';
    }
    $host = strtolower($config['smtp_host'] ?? '');
    if (str_contains($host, 'gmail') && hasSmtpCredentials($config)) {
        $config['from_email'] = strtolower(trim($config['smtp_user']));
    }

    return $config;
}

function logEmailAttempt(string $to, string $subject, bool $ok, string $method, ?string $error = null): void
{
    $dir = __DIR__ . '/../storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $line = date('Y-m-d H:i:s')
        . ' | ' . ($ok ? 'OK' : 'FAIL')
        . ' | ' . $method
        . ' | to=' . $to
        . ' | subj=' . mb_substr($subject, 0, 60)
        . ($error ? ' | err=' . $error : '')
        . "\n";
    @file_put_contents($dir . '/email.log', $line, FILE_APPEND | LOCK_EX);
}

/** @return array<int, string> */
function readEmailLogTail(int $lines = 15): array
{
    $file = __DIR__ . '/../storage/logs/email.log';
    if (!is_file($file)) {
        return [];
    }
    $content = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($content)) {
        return [];
    }

    return array_slice($content, -$lines);
}

function getEmailConfig(): array
{
    $defaults = [
        'enabled' => false,
        'mail_method' => 'php',
        'smtp_host' => 'mail.localhost',
        'smtp_port' => 465,
        'smtp_encryption' => 'ssl',
        'smtp_user' => '',
        'smtp_pass' => '',
        'from_email' => '',
        'from_name' => 'Product Inbound Shipment System',
        'smtp_insecure_ssl' => false,
    ];
    $file = __DIR__ . '/../config/email.php';
    if (!is_file($file)) {
        return $defaults;
    }
    $config = require $file;
    $config = array_merge($defaults, is_array($config) ? $config : []);
    if (empty($config['mail_method'])) {
        $config['mail_method'] = hasSmtpCredentials($config) ? 'smtp' : 'php';
    }

    return normalizeEmailConfig($config);
}

function saveEmailConfig(array $config): bool
{
    $path = __DIR__ . '/../config/email.php';
    $method = ($config['mail_method'] ?? 'php') === 'smtp' ? 'smtp' : 'php';
    $export = var_export([
        'enabled' => (bool) ($config['enabled'] ?? false),
        'mail_method' => $method,
        'from_email' => strtolower(trim((string) ($config['from_email'] ?? ''))),
        'from_name' => (string) ($config['from_name'] ?? 'Product Inbound Shipment System'),
        'smtp_host' => (string) ($config['smtp_host'] ?? 'mail.localhost'),
        'smtp_port' => (int) ($config['smtp_port'] ?? 465),
        'smtp_encryption' => (string) ($config['smtp_encryption'] ?? 'ssl'),
        'smtp_user' => strtolower(trim((string) ($config['smtp_user'] ?? ''))),
        'smtp_pass' => normalizeSmtpPasswordForConfig($config, (string) ($config['smtp_pass'] ?? '')),
        'smtp_insecure_ssl' => !empty($config['smtp_insecure_ssl']),
    ], true);
    $content = "<?php\n/** Auto-generated email config */\nreturn {$export};\n";

    return file_put_contents($path, $content) !== false;
}

function usesSmtpMail(array $config): bool
{
    return resolveMailMethod($config) === 'smtp';
}

function defaultFromEmail(): string
{
    $host = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    if ($host === 'localhost' || $host === '127.0.0.1' || $host === '') {
        return 'noreply@localhost';
    }

    return 'noreply@' . $host;
}

function getEffectiveFromEmail(array $config): string
{
    $config = normalizeEmailConfig($config);
    $user = strtolower(trim($config['smtp_user'] ?? ''));
    $host = strtolower($config['smtp_host'] ?? '');

    if (hasSmtpCredentials($config) && str_contains($host, 'gmail')) {
        return $user;
    }

    $from = strtolower(trim($config['from_email'] ?? ''));
    if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)) {
        if (hasSmtpCredentials($config) && $from !== $user && str_contains($host, 'gmail')) {
            return $user;
        }

        return $from;
    }
    if (hasSmtpCredentials($config) && filter_var($user, FILTER_VALIDATE_EMAIL)) {
        return $user;
    }

    return defaultFromEmail();
}

function isEmailEnabled(): bool
{
    $c = normalizeEmailConfig(getEmailConfig());
    if (empty($c['enabled'])) {
        return false;
    }
    if (resolveMailMethod($c) === 'smtp') {
        return hasSmtpCredentials($c)
            && !empty($c['smtp_host'])
            && filter_var(getEffectiveFromEmail($c), FILTER_VALIDATE_EMAIL);
    }

    return filter_var(getEffectiveFromEmail($c), FILTER_VALIDATE_EMAIL) !== false;
}

function isLocalEnvironment(): bool
{
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');

    return $host === 'localhost'
        || str_starts_with($host, 'localhost:')
        || $host === '127.0.0.1'
        || str_starts_with($host, '127.0.0.1:');
}

function requestIsHttps(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
        return true;
    }

    return false;
}

function buildAbsoluteUrl(string $path): string
{
    $scheme = requestIsHttps() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host . baseUrl($path);
}

function generatePasswordResetCode(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function normalizeSmtpPassword(string $password): string
{
    return preg_replace('/[\s\-]+/', '', trim($password));
}

function isGmailSmtpConfig(array $config): bool
{
    $host = strtolower($config['smtp_host'] ?? '');
    $user = strtolower($config['smtp_user'] ?? '');

    return str_contains($host, 'gmail') || str_ends_with($user, '@gmail.com');
}

/** Gmail SMTP requires a 16-character Google App Password (not login password). */
function validateGmailAppPassword(string $password): ?string
{
    $pass = strtolower(normalizeSmtpPassword($password));
    if (strlen($pass) !== 16) {
        return __('email.err_pass_not_app');
    }
    if (!preg_match('/^[a-z]{16}$/', $pass)) {
        return __('email.err_pass_format');
    }

    return null;
}

function normalizeSmtpPasswordForConfig(array $config, string $password): string
{
    $pass = normalizeSmtpPassword($password);

    return isGmailSmtpConfig($config) ? strtolower($pass) : $pass;
}

function parseSmtpError(?string $error): string
{
    if ($error === null || $error === '') {
        return __('email.err_unknown');
    }
    $e = $error;
    if (str_contains($e, '535') || stripos($e, 'BadCredentials') !== false) {
        return __('email.err_535');
    }
    if (str_contains($e, '534') || stripos($e, 'Application-specific password') !== false) {
        return __('email.err_534');
    }
    if (str_contains($e, '530') || stripos($e, 'Must issue a STARTTLS') !== false) {
        return __('email.err_530');
    }
    if (stripos($e, 'OpenSSL') !== false) {
        return __('email.err_openssl');
    }

    return $e;
}

function validateEmailConfig(array $config): ?string
{
    if (empty($config['enabled'])) {
        return null;
    }
    $from = getEffectiveFromEmail($config);
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return __('email.err_invalid_from');
    }
    if (!usesSmtpMail($config)) {
        return null;
    }
    if (empty($config['smtp_host'])) {
        return __('email.err_smtp_host');
    }
    if (empty($config['smtp_user']) || !filter_var($config['smtp_user'], FILTER_VALIDATE_EMAIL)) {
        return __('email.err_invalid_user');
    }
    if (empty($config['smtp_pass'])) {
        return __('email.err_empty_pass');
    }
    if (isGmailSmtpConfig($config)) {
        $gmailPassError = validateGmailAppPassword($config['smtp_pass']);
        if ($gmailPassError) {
            return $gmailPassError;
        }
        if (strtolower(trim($config['from_email'] ?? '')) !== strtolower(trim($config['smtp_user'] ?? ''))) {
            return __('email.err_from_must_match');
        }
    }

    return null;
}

function encodeMailHeader(string $text): string
{
    if (preg_match('/[^\x20-\x7E]/', $text)) {
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }

    return $text;
}

/**
 * @return array{sent: bool, error: ?string}
 */
function sendPasswordResetEmail(string $to, string $username, string $verificationCode, string $resetPageUrl): array
{
    if (!isEmailEnabled()) {
        return ['sent' => false, 'error' => __('email.not_configured')];
    }

    $config = getEmailConfig();
    $subject = __('email.reset_subject');
    $codeHtml = '<p style="text-align:center;margin:28px 0;">'
        . '<span style="font-size:32px;font-weight:800;letter-spacing:8px;color:#4f46e5;'
        . 'background:#eef2ff;padding:16px 24px;border-radius:12px;display:inline-block;">'
        . htmlspecialchars($verificationCode) . '</span></p>';

    $htmlBody = '<!DOCTYPE html><html><body style="font-family:Segoe UI,sans-serif;padding:20px;max-width:520px;">'
        . '<h2 style="color:#4f46e5;">' . htmlspecialchars(__('email.reset_heading')) . '</h2>'
        . '<p>' . htmlspecialchars(__('email.reset_hello', ['user' => $username])) . '</p>'
        . '<p>' . htmlspecialchars(__('email.reset_code_body')) . '</p>'
        . $codeHtml
        . '<p style="font-size:14px;color:#475569;">' . htmlspecialchars(__('email.reset_code_steps')) . '</p>'
        . '<p style="margin:20px 0;"><a href="' . htmlspecialchars($resetPageUrl) . '" '
        . 'style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;padding:12px 24px;'
        . 'text-decoration:none;border-radius:8px;display:inline-block;">'
        . htmlspecialchars(__('email.reset_button')) . '</a></p>'
        . '<p style="font-size:13px;color:#475569;word-break:break-all;">'
        . htmlspecialchars(__('email.reset_link_label')) . '<br>'
        . '<a href="' . htmlspecialchars($resetPageUrl) . '">' . htmlspecialchars($resetPageUrl) . '</a></p>'
        . '<p style="font-size:12px;color:#64748b;">' . htmlspecialchars(__('email.reset_expire')) . '</p>'
        . '</body></html>';

    $textBody = __('email.reset_hello', ['user' => $username]) . "\n\n"
        . __('email.reset_code_body') . "\n\n"
        . __('email.reset_code_label') . ': ' . $verificationCode . "\n\n"
        . __('email.reset_code_steps') . "\n"
        . __('email.reset_link_label') . ': ' . $resetPageUrl . "\n\n"
        . __('email.reset_expire');

    try {
        sendMail($config, $to, $subject, $htmlBody, $textBody);

        return ['sent' => true, 'error' => null];
    } catch (Throwable $e) {
        return ['sent' => false, 'error' => usesSmtpMail($config) ? parseSmtpError($e->getMessage()) : $e->getMessage()];
    }
}

/**
 * @return array{sent: bool, error: ?string}
 */
function sendTestEmail(string $to): array
{
    if (!isEmailEnabled()) {
        return ['sent' => false, 'error' => __('email.not_configured')];
    }
    $config = getEmailConfig();
    $subject = __('email.test_subject');
    $html = '<p>' . htmlspecialchars(__('email.test_body')) . '</p><p>' . date('Y-m-d H:i:s') . '</p>';
    $text = __('email.test_body') . ' ' . date('Y-m-d H:i:s');
    try {
        sendMail($config, $to, $subject, $html, $text);

        return ['sent' => true, 'error' => null];
    } catch (Throwable $e) {
        return ['sent' => false, 'error' => usesSmtpMail($config) ? parseSmtpError($e->getMessage()) : $e->getMessage()];
    }
}

function sendMail(array $config, string $to, string $subject, string $htmlBody, string $textBody): void
{
    $config = normalizeEmailConfig($config);
    $errors = [];

    if (hasSmtpCredentials($config)) {
        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            try {
                phpmailerSendMail($config, $to, $subject, $htmlBody, $textBody);
                logEmailAttempt($to, $subject, true, 'phpmailer-smtp');

                return;
            } catch (Throwable $e) {
                $errors[] = 'PHPMailer: ' . $e->getMessage();
                logEmailAttempt($to, $subject, false, 'phpmailer-smtp', $e->getMessage());
            }
        }
        try {
            smtpSendMail($config, $to, $subject, $htmlBody, $textBody);
            logEmailAttempt($to, $subject, true, 'smtp');

            return;
        } catch (Throwable $e) {
            $errors[] = 'SMTP: ' . $e->getMessage();
            logEmailAttempt($to, $subject, false, 'smtp', $e->getMessage());
        }
        throw new RuntimeException(parseSmtpError(implode(' | ', $errors)));
    }

    if (resolveMailMethod($config) === 'php') {
        try {
            phpMailSend($config, $to, $subject, $htmlBody, $textBody);
            logEmailAttempt($to, $subject, true, 'php-mail');

            return;
        } catch (Throwable $e) {
            logEmailAttempt($to, $subject, false, 'php-mail', $e->getMessage());
            throw $e;
        }
    }

    throw new RuntimeException(__('email.err_no_transport'));
}

function phpmailerSendMail(
    array $config,
    string $to,
    string $subject,
    string $htmlBody,
    string $textBody
): void {
    $lastError = '';
    foreach (smtpConnectionProfiles($config) as $profile) {
        try {
            phpmailerSendWithProfile($config, $profile, $to, $subject, $htmlBody, $textBody);

            return;
        } catch (Throwable $e) {
            $lastError = $e->getMessage();
        }
    }
    throw new RuntimeException($lastError ?: __('email.err_unknown'));
}

function phpmailerSendWithProfile(
    array $config,
    array $profile,
    string $to,
    string $subject,
    string $htmlBody,
    string $textBody
): void {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = $profile['host'];
    $mail->Port = (int) $profile['port'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['smtp_user'];
    $mail->Password = $config['smtp_pass'];
    $enc = strtolower($profile['encryption']);
    if ($enc === 'ssl') {
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    }
    if (!empty($config['smtp_insecure_ssl'])) {
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
    }
    $mail->Timeout = 45;

    $from = getEffectiveFromEmail($config);
    $mail->setFrom($from, $config['from_name'] ?? 'Product Inbound Shipment System');
    $mail->addAddress($to);
    $mail->Subject = $subject;
    $mail->isHTML(true);
    $mail->Body = $htmlBody;
    $mail->AltBody = $textBody;
    $mail->send();
}

function phpMailSend(array $config, string $to, string $subject, string $htmlBody, string $textBody): void
{
    $from = getEffectiveFromEmail($config);
    $fromName = $config['from_name'] ?? 'Product Inbound Shipment System';
    $boundary = '----=_Part_' . bin2hex(random_bytes(8));

    $encodedSubject = encodeMailHeader($subject);
    $encodedFrom = encodeMailHeader($fromName);

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "From: {$encodedFrom} <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "X-Mailer: PHP/" . PHP_VERSION . "\r\n";

    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $textBody . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $htmlBody . "\r\n";
    $body .= "--{$boundary}--\r\n";

    $ok = @mail($to, $encodedSubject, $body, $headers, "-f{$from}");
    if (!$ok) {
        throw new RuntimeException(__('email.err_php_mail'));
    }
}

/** @return array{ok: bool, log: string, error: ?string} */
function testSmtpAuthentication(): array
{
    $config = getEmailConfig();
    if (!usesSmtpMail($config) || !isEmailEnabled()) {
        return ['ok' => false, 'log' => __('email.diagnose_php_mode'), 'error' => null];
    }
    $log = [];
    $pass = normalizeSmtpPassword($config['smtp_pass']);
    $user = strtolower(trim($config['smtp_user']));
    $log[] = 'User: ' . $user;

    $lastErr = '';
    foreach (smtpConnectionProfiles($config) as $profile) {
        $label = $profile['label'] . ' ' . $profile['host'] . ':' . $profile['port'] . ' ' . $profile['encryption'];
        try {
            smtpTestAuthOnProfile($config, $profile, $user, $pass, $log);
            return ['ok' => true, 'log' => implode("\n", $log), 'error' => null];
        } catch (Throwable $e) {
            $lastErr = $e->getMessage();
            $log[] = 'FAIL ' . $label . ': ' . $lastErr;
        }
    }

    return ['ok' => false, 'log' => implode("\n", $log), 'error' => parseSmtpError($lastErr)];
}

function smtpEhloHostname(array $config): string
{
    $user = $config['smtp_user'] ?? '';
    if (filter_var($user, FILTER_VALIDATE_EMAIL)) {
        return substr($user, (int) strpos($user, '@') + 1);
    }

    return preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
}

function smtpConnectionProfiles(array $config): array
{
    $host = $config['smtp_host'];
    $enc = strtolower($config['smtp_encryption'] ?? 'tls');
    $port = (int) $config['smtp_port'];
    $profiles = [
        ['host' => $host, 'port' => $port, 'encryption' => $enc, 'label' => 'saved-config'],
    ];
    if (str_contains(strtolower($host), 'gmail')) {
        $profiles[] = ['host' => $host, 'port' => 465, 'encryption' => 'ssl', 'label' => 'gmail-ssl-465'];
        $profiles[] = ['host' => $host, 'port' => 587, 'encryption' => 'tls', 'label' => 'gmail-tls-587'];
    }
    $seen = [];
    $unique = [];
    foreach ($profiles as $p) {
        $key = $p['host'] . ':' . $p['port'] . ':' . $p['encryption'];
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $unique[] = $p;
        }
    }

    return $unique;
}

function smtpStreamContext(array $config)
{
    $insecure = !empty($config['smtp_insecure_ssl']);
    $ssl = [
        'verify_peer' => !$insecure,
        'verify_peer_name' => !$insecure,
        'allow_self_signed' => $insecure,
    ];
    if (!$insecure) {
        foreach ([
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/usr/local/share/certs/ca-root-nss.crt',
            'C:/xampp/apache/bin/curl-ca-bundle.crt',
        ] as $cafile) {
            if (is_file($cafile)) {
                $ssl['cafile'] = $cafile;
                break;
            }
        }
    }

    return stream_context_create(['ssl' => $ssl]);
}

function smtpSendMail(array $config, string $to, string $subject, string $htmlBody, string $textBody): void
{
    if (!extension_loaded('openssl')) {
        throw new RuntimeException(__('email.err_openssl'));
    }

    $errors = [];
    foreach (smtpConnectionProfiles($config) as $profile) {
        try {
            smtpSendMailViaProfile($config, $profile, $to, $subject, $htmlBody, $textBody);

            return;
        } catch (Throwable $e) {
            $errors[] = '[' . $profile['label'] . '] ' . $e->getMessage();
        }
    }
    throw new RuntimeException(implode(' | ', $errors));
}

function smtpSendMailViaProfile(
    array $config,
    array $profile,
    string $to,
    string $subject,
    string $htmlBody,
    string $textBody
): void {
    $host = $profile['host'];
    $port = (int) $profile['port'];
    $encryption = strtolower($profile['encryption']);
    $user = strtolower(trim($config['smtp_user']));
    $pass = normalizeSmtpPassword($config['smtp_pass']);
    $from = getEffectiveFromEmail($config);
    $fromName = $config['from_name'];
    $ehlo = smtpEhloHostname($config);

    $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $context = smtpStreamContext($config);

    $socket = @stream_socket_client(
        $remote,
        $errno,
        $errstr,
        45,
        STREAM_CLIENT_CONNECT,
        $context
    );
    if (!$socket) {
        throw new RuntimeException("Connect failed ($host:$port): $errstr ($errno)");
    }
    stream_set_timeout($socket, 90);

    $readResponse = static function ($socket): string {
        $data = '';
        while ($line = fgets($socket, 8192)) {
            if ($line === false) {
                break;
            }
            $data .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        return $data;
    };

    $sendCmd = static function ($socket, string $command, array $okCodes) use ($readResponse): void {
        if ($command !== '') {
            fwrite($socket, $command . "\r\n");
        }
        $resp = $readResponse($socket);
        $code = substr($resp, 0, 3);
        if (!in_array($code, $okCodes, true)) {
            throw new RuntimeException(trim($resp));
        }
    };

    $readResponse($socket);
    $sendCmd($socket, 'EHLO ' . $ehlo, ['250']);

    if ($encryption === 'tls') {
        $sendCmd($socket, 'STARTTLS', ['220']);
        $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (!@stream_socket_enable_crypto($socket, true, $crypto)) {
            throw new RuntimeException('STARTTLS handshake failed.');
        }
        $sendCmd($socket, 'EHLO ' . $ehlo, ['250']);
    }

    smtpAuthenticate($socket, $user, $pass, $ehlo, $sendCmd, $readResponse);

    $sendCmd($socket, 'MAIL FROM:<' . $from . '>', ['250']);
    $sendCmd($socket, 'RCPT TO:<' . $to . '>', ['250', '251']);
    $sendCmd($socket, 'DATA', ['354']);

    $boundary = '----=_Part_' . bin2hex(random_bytes(8));
    $encodedSubject = encodeMailHeader($subject);
    $encodedFrom = encodeMailHeader($fromName);

    $headers = "Date: " . date('r') . "\r\n";
    $headers .= "From: {$encodedFrom} <{$from}>\r\n";
    $headers .= "To: <{$to}>\r\n";
    $headers .= "Subject: {$encodedSubject}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= smtpDotStuff($textBody) . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= smtpDotStuff($htmlBody) . "\r\n";
    $body .= "--{$boundary}--\r\n";

    $message = smtpDotStuff($headers) . "\r\n" . $body;
    fwrite($socket, $message . "\r\n.\r\n");
    $resp = $readResponse($socket);
    if (substr($resp, 0, 3) !== '250') {
        throw new RuntimeException(trim('DATA failed: ' . $resp));
    }

    $sendCmd($socket, 'QUIT', ['221']);
    fclose($socket);
}

function smtpAuthenticate($socket, string $user, string $pass, string $ehlo, callable $sendCmd, callable $readResponse): void
{
    $user = strtolower(trim($user));
    $lastResp = '';

    $attempts = [
        static function ($socket, $user, $pass, $sendCmd, $readResponse): void {
            $sendCmd($socket, 'AUTH LOGIN', ['334']);
            $sendCmd($socket, base64_encode($user), ['334']);
            fwrite($socket, base64_encode($pass) . "\r\n");
            $authResp = $readResponse($socket);
            if (substr($authResp, 0, 3) !== '235') {
                throw new RuntimeException(trim($authResp));
            }
        },
        static function ($socket, $user, $pass, $sendCmd, $readResponse): void {
            $plain = base64_encode("\0{$user}\0{$pass}");
            $sendCmd($socket, 'AUTH PLAIN ' . $plain, ['235']);
        },
    ];

    foreach ($attempts as $attempt) {
        try {
            $attempt($socket, $user, $pass, $sendCmd, $readResponse);
            return;
        } catch (Throwable $e) {
            $lastResp = $e->getMessage();
            try {
                $sendCmd($socket, 'RSET', ['250']);
                $sendCmd($socket, 'EHLO ' . $ehlo, ['250']);
            } catch (Throwable $resetEx) {
                break;
            }
        }
    }

    if (str_contains($lastResp, '535')) {
        throw new RuntimeException('SMTP 535 BadCredentials: ' . $lastResp);
    }
    throw new RuntimeException('SMTP AUTH failed: ' . $lastResp);
}

/** @param array<int, string> $log */
function smtpTestAuthOnProfile(array $config, array $profile, string $user, string $pass, array &$log): void
{
    $ehlo = smtpEhloHostname($config);
    $host = $profile['host'];
    $port = (int) $profile['port'];
    $encryption = strtolower($profile['encryption']);
    $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $log[] = 'Trying ' . $remote . ' ...';

    $socket = @stream_socket_client($remote, $errno, $errstr, 45, STREAM_CLIENT_CONNECT, smtpStreamContext($config));
    if (!$socket) {
        throw new RuntimeException("Connect: $errstr ($errno)");
    }
    stream_set_timeout($socket, 45);

    $readResponse = static function ($socket): string {
        $data = '';
        while ($line = fgets($socket, 8192)) {
            if ($line === false) {
                break;
            }
            $data .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        return $data;
    };
    $sendCmd = static function ($socket, string $command, array $okCodes) use ($readResponse): void {
        if ($command !== '') {
            fwrite($socket, $command . "\r\n");
        }
        $resp = $readResponse($socket);
        if (!in_array(substr($resp, 0, 3), $okCodes, true)) {
            throw new RuntimeException(trim($resp));
        }
    };

    $readResponse($socket);
    $sendCmd($socket, 'EHLO ' . $ehlo, ['250']);
    if ($encryption === 'tls') {
        $sendCmd($socket, 'STARTTLS', ['220']);
        $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (!@stream_socket_enable_crypto($socket, true, $crypto)) {
            throw new RuntimeException('STARTTLS failed');
        }
        $sendCmd($socket, 'EHLO ' . $ehlo, ['250']);
    }
    smtpAuthenticate($socket, $user, $pass, $ehlo, $sendCmd, $readResponse);
    $log[] = 'AUTH OK on ' . $remote;
    $sendCmd($socket, 'QUIT', ['221']);
    fclose($socket);
}

function smtpDotStuff(string $text): string
{
    return preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $text));
}
