<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
requireAdmin();

$activeNav = 'email';
$pageTitle = __('email.settings_title');
$config = normalizeEmailConfig(getEmailConfig());
$testResult = $_GET['test'] ?? null;
$flash = getFlash();
$isSmtp = usesSmtpMail($config);
$activeTransport = $isSmtp ? __('email.transport_smtp_active') : __('email.transport_php_active');
$emailLogLines = readEmailLogTail(12);
$suggestedFrom = defaultFromEmail();
if (hasSmtpCredentials($config)) {
    $suggestedFrom = getEffectiveFromEmail($config);
}
$showGmailGuide = isGmailSmtpConfig($config);
$gmailPassRequired = $showGmailGuide && empty($config['smtp_pass']);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><?= e(__('email.settings_title')) ?></h1>
    <p><?= e(__('email.settings_subtitle')) ?></p>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<?php if ($testResult === 'ok'): ?>
<div class="alert alert-success"><?= e(__('email.test_success')) ?></div>
<?php elseif ($testResult === 'fail'): ?>
<div class="alert alert-error"><?= e(__('email.test_failed_hint')) ?></div>
<?php endif; ?>

<div class="card" id="gmail-app-password-guide">
    <div class="card-header"><h2><?= e(__('email.gmail_app_title')) ?></h2></div>
    <ol style="margin-left:1.25rem; color:var(--text-muted); font-size:0.9rem; line-height:1.85;">
        <li><?= e(__('email.gmail_app_step1')) ?></li>
        <li><?= e(__('email.gmail_app_step2')) ?></li>
        <li><?= e(__('email.gmail_app_step3')) ?></li>
        <li><?= e(__('email.gmail_app_step4')) ?></li>
    </ol>
    <p style="margin-top:1rem;">
        <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener" class="btn btn-primary btn-sm">
            <?= e(__('email.open_app_passwords')) ?>
        </a>
    </p>
</div>

<div class="card">
    <div class="card-header">
        <h2><?= e(__('email.mail_config')) ?></h2>
        <?php if (isEmailEnabled()): ?>
        <span class="badge badge-user"><?= e(__('email.status_on')) ?></span>
        <?php else: ?>
        <span class="badge" style="background:#fee2e2;color:#b91c1c;"><?= e(__('email.status_off')) ?></span>
        <?php endif; ?>
    </div>
    <?php if (isEmailEnabled()): ?>
    <p class="form-hint" style="margin-bottom:1rem;"><strong><?= e(__('email.active_transport')) ?>:</strong> <?= e($activeTransport) ?></p>
    <p class="form-hint"><?= e(__('email.cpanel_guide')) ?></p>
    <?php endif; ?>
    <?php if (hasSmtpCredentials($config) && ($config['mail_method'] ?? '') === 'php'): ?>
    <div class="alert alert-warning"><?= e(__('email.warn_php_with_smtp_creds')) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= baseUrl('admin/email-actions.php') ?>" id="email-settings-form">
        <input type="hidden" name="action" value="save">

        <div class="form-group">
            <label>
                <input type="checkbox" name="enabled" value="1" <?= !empty($config['enabled']) ? 'checked' : '' ?>>
                <?= e(__('email.enable_sending')) ?>
            </label>
            <p class="form-hint"><?= e(__('email.enable_hint')) ?></p>
        </div>

        <div class="form-group">
            <label><?= e(__('email.mail_method')) ?></label>
            <div style="display:flex;flex-wrap:wrap;gap:1rem;margin-top:0.35rem;">
                <label style="font-weight:normal;">
                    <input type="radio" name="mail_method" value="php" <?= !$isSmtp ? 'checked' : '' ?>>
                    <?= e(__('email.method_php')) ?>
                </label>
                <label style="font-weight:normal;">
                    <input type="radio" name="mail_method" value="smtp" <?= $isSmtp ? 'checked' : '' ?>>
                    <?= e(__('email.method_smtp')) ?>
                </label>
            </div>
            <p class="form-hint"><?= e(__('email.method_php_hint')) ?></p>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="from_email"><?= e(__('email.from_email')) ?></label>
                <input type="email" id="from_email" name="from_email" class="form-control"
                       placeholder="<?= e($suggestedFrom) ?>"
                       value="<?= e($config['from_email']) ?>" required>
                <p class="form-hint"><?= e(__('email.from_email_hint', ['suggest' => $suggestedFrom])) ?></p>
            </div>
            <div class="form-group">
                <label for="from_name"><?= e(__('email.from_name')) ?></label>
                <input type="text" id="from_name" name="from_name" class="form-control"
                       value="<?= e($config['from_name']) ?>">
            </div>
        </div>

        <div id="smtp-fields" style="<?= $isSmtp ? '' : 'display:none;' ?>">
            <hr style="margin:1.5rem 0;border:none;border-top:1px solid var(--border);">
            <p class="form-hint" style="margin-bottom:0.75rem;"><?= e(__('email.smtp_optional_hint')) ?></p>
            <div class="email-preset-row" style="margin-bottom:1rem;">
                <button type="button" class="btn btn-outline btn-sm" id="preset-cpanel">cPanel 邮箱 (推荐线上)</button>
                <button type="button" class="btn btn-outline btn-sm" id="preset-gmail-ssl">Gmail SSL (465)</button>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="smtp_host"><?= e(__('email.smtp_host')) ?></label>
                    <input type="text" id="smtp_host" name="smtp_host" class="form-control"
                           value="<?= e($config['smtp_host']) ?>">
                </div>
                <div class="form-group">
                    <label for="smtp_port"><?= e(__('email.smtp_port')) ?></label>
                    <input type="number" id="smtp_port" name="smtp_port" class="form-control"
                           value="<?= (int) $config['smtp_port'] ?>">
                </div>
                <div class="form-group">
                    <label for="smtp_encryption"><?= e(__('email.smtp_encryption')) ?></label>
                    <select id="smtp_encryption" name="smtp_encryption" class="form-control">
                        <option value="tls" <?= ($config['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS (587)</option>
                        <option value="ssl" <?= ($config['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (465)</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="smtp_user"><?= e(__('email.smtp_user')) ?></label>
                    <input type="email" id="smtp_user" name="smtp_user" class="form-control"
                           value="<?= e($config['smtp_user']) ?>">
                </div>
                <div class="form-group">
                    <label for="smtp_pass" id="smtp_pass_label"><?= e($showGmailGuide ? __('email.gmail_app_pass_label') : __('email.smtp_pass')) ?></label>
                    <div class="password-wrapper">
                        <input type="password" id="smtp_pass" name="smtp_pass" class="form-control"
                               autocomplete="new-password"
                               maxlength="20"
                               inputmode="text"
                               placeholder="<?= e($showGmailGuide ? __('email.gmail_pass_placeholder') : __('email.pass_placeholder')) ?>"
                               <?= $gmailPassRequired ? 'required' : '' ?>>
                        <button type="button" class="toggle-password" aria-label="Show">👁</button>
                    </div>
                    <p class="form-hint" id="smtp_pass_hint"><?= e($showGmailGuide ? __('email.gmail_pass_hint') : __('email.pass_hint')) ?></p>
                    <p class="form-hint" id="pass-length-hint" style="display:none;"></p>
                </div>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="smtp_insecure_ssl" value="1"
                        <?= !empty($config['smtp_insecure_ssl']) ? 'checked' : '' ?>>
                    <?= e(__('email.smtp_insecure_ssl')) ?>
                </label>
            </div>
        </div>

        <button type="submit" class="btn btn-primary"><?= e(__('common.save')) ?></button>
    </form>
</div>

<div class="card">
    <div class="card-header"><h2><?= e(__('email.test_send')) ?></h2></div>
    <form method="post" action="<?= baseUrl('admin/email-actions.php') ?>" class="toolbar">
        <input type="hidden" name="action" value="test">
        <div class="form-group" style="flex:1; min-width:220px;">
            <label for="test_email"><?= e(__('email.test_to')) ?></label>
            <input type="email" id="test_email" name="test_email" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-success"><?= e(__('email.send_test')) ?></button>
    </form>
    <p class="form-hint" style="margin-top:0.75rem;"><?= e(__('email.test_hint')) ?></p>
</div>

<?php if ($isSmtp): ?>
<div class="card">
    <div class="card-header"><h2><?= e(__('email.diagnose_title')) ?></h2></div>
    <a href="<?= baseUrl('admin/email-diagnose.php') ?>" class="btn btn-outline"><?= e(__('email.run_diagnose')) ?></a>
</div>
<?php endif; ?>

<?php if (!empty($emailLogLines)): ?>
<div class="card">
    <div class="card-header"><h2><?= e(__('email.log_title')) ?></h2></div>
    <pre style="white-space:pre-wrap;font-size:0.8rem;line-height:1.5;margin:0;color:var(--text-muted);max-height:220px;overflow:auto;"><?= e(implode("\n", $emailLogLines)) ?></pre>
    <p class="form-hint" style="margin-top:0.75rem;"><?= e(__('email.log_hint')) ?></p>
</div>
<?php endif; ?>

<script>
(function () {
    var siteHost = <?= json_encode(preg_replace('/^www\./', '', preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'your-domain.com'))) ?>;
    document.getElementById('preset-cpanel')?.addEventListener('click', function () {
        var smtpRadio = document.querySelector('input[name="mail_method"][value="smtp"]');
        if (smtpRadio) smtpRadio.checked = true;
        var host = document.getElementById('smtp_host');
        var port = document.getElementById('smtp_port');
        var enc = document.getElementById('smtp_encryption');
        var user = document.getElementById('smtp_user');
        var from = document.getElementById('from_email');
        var mailbox = 'noreply@' + siteHost;
        if (host) host.value = 'mail.' + siteHost;
        if (port) port.value = '465';
        if (enc) enc.value = 'ssl';
        if (user) user.value = mailbox;
        if (from) from.value = mailbox;
        document.querySelector('input[name="mail_method"][value="smtp"]')?.dispatchEvent(new Event('change'));
    });
    function applyGmailPreset(enc) {
        var smtpRadio = document.querySelector('input[name="mail_method"][value="smtp"]');
        if (smtpRadio) smtpRadio.checked = true;
        var host = document.getElementById('smtp_host');
        var port = document.getElementById('smtp_port');
        var encryption = document.getElementById('smtp_encryption');
        if (host) host.value = 'smtp.gmail.com';
        if (encryption) encryption.value = enc;
        if (port) port.value = enc === 'ssl' ? '465' : '587';
        document.querySelector('input[name="mail_method"][value="smtp"]')?.dispatchEvent(new Event('change'));
    }
    document.getElementById('preset-gmail-ssl')?.addEventListener('click', function () {
        applyGmailPreset('ssl');
        updateGmailPassUi(true);
    });
    function updateGmailPassUi(isGmail) {
        var guide = document.getElementById('gmail-app-password-guide');
        var label = document.getElementById('smtp_pass_label');
        var hint = document.getElementById('smtp_pass_hint');
        var passInput = document.getElementById('smtp_pass');
        var msgs = <?= json_encode([
            'label' => __('email.gmail_app_pass_label'),
            'hint' => __('email.gmail_pass_hint'),
            'placeholder' => __('email.gmail_pass_placeholder'),
            'labelDefault' => __('email.smtp_pass'),
            'hintDefault' => __('email.pass_hint'),
            'placeholderDefault' => __('email.pass_placeholder'),
        ], JSON_UNESCAPED_UNICODE) ?>;
        if (guide) guide.style.display = isGmail ? '' : 'none';
        if (isGmail) {
            if (label) label.textContent = msgs.label;
            if (hint) hint.textContent = msgs.hint;
            if (passInput) passInput.placeholder = msgs.placeholder;
        } else {
            if (label) label.textContent = msgs.labelDefault;
            if (hint) hint.textContent = msgs.hintDefault;
            if (passInput) passInput.placeholder = msgs.placeholderDefault;
        }
    }
    function hostLooksGmail() {
        var host = (document.getElementById('smtp_host')?.value || '').toLowerCase();
        var user = (document.getElementById('smtp_user')?.value || '').toLowerCase();
        return host.indexOf('gmail') >= 0 || user.endsWith('@gmail.com');
    }
    document.getElementById('smtp_host')?.addEventListener('input', function () { updateGmailPassUi(hostLooksGmail()); });
    document.getElementById('smtp_user')?.addEventListener('input', function () {
        updateGmailPassUi(hostLooksGmail());
        var from = document.getElementById('from_email');
        if (from && hostLooksGmail()) from.value = this.value;
    });
    updateGmailPassUi(<?= $showGmailGuide ? 'true' : 'false' ?>);
})();
(function () {
    var passInput = document.getElementById('smtp_pass');
    var hint = document.getElementById('pass-length-hint');
    if (!passInput || !hint) return;
    var okMsg = <?= json_encode(__('email.pass_len_ok')) ?>;
    var warnMsg = <?= json_encode(__('email.pass_len_warn')) ?>;
    function checkLen() {
        var len = passInput.value.replace(/[\s\-]/g, '').length;
        if (len === 0) { hint.style.display = 'none'; return; }
        hint.style.display = 'block';
        hint.textContent = len === 16 ? '✓ ' + okMsg : '⚠ ' + warnMsg + ' (' + len + '/16)';
        hint.style.color = len === 16 ? '#047857' : '#c2410c';
    }
    passInput.addEventListener('input', checkLen);
})();
(function () {
    var smtpBlock = document.getElementById('smtp-fields');
    var radios = document.querySelectorAll('input[name="mail_method"]');
    function toggle() {
        var smtp = document.querySelector('input[name="mail_method"]:checked')?.value === 'smtp';
        if (smtpBlock) smtpBlock.style.display = smtp ? '' : 'none';
        ['smtp_host', 'smtp_user'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.required = smtp;
        });
    }
    radios.forEach(function (r) { r.addEventListener('change', toggle); });
    toggle();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
