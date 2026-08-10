<?php
/**
 * 邮件配置示例 — 复制为 email.php
 *
 * 推荐（cPanel）：mail_method = php，只需填写发件邮箱并勾选启用
 * 可选：mail_method = smtp，填写主机、账号、密码（如 cPanel 邮箱或 Gmail）
 */
return [
    'enabled' => true,
    /** php = 使用服务器 mail()（cPanel 推荐）；smtp = 外部 SMTP */
    'mail_method' => 'php',
    'from_email' => 'noreply@your-domain.com',
    'from_name' => 'Product Inbound Shipment System',
    'smtp_host' => 'mail.your-domain.com',
    'smtp_port' => 465,
    'smtp_encryption' => 'ssl',
    'smtp_user' => 'noreply@your-domain.com',
    'smtp_pass' => '',
    'smtp_insecure_ssl' => false,
];
