<?php
// Configuration pour la connexion IMAP/SMTP
define('IMAP_HOST', 'imap.hostinger.com');
define('IMAP_PORT', 993);
define('IMAP_SSL', true);
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 587);
define('EMAIL_USERNAME', 'support@obgecom.com');
define('EMAIL_PASSWORD', 'Obg@123456');

// Dossiers IMAP
define('INBOX_FOLDER', 'INBOX');
define('SENT_FOLDER', 'INBOX.Sent');
define('DRAFTS_FOLDER', 'INBOX.Drafts');

// Configuration PHPMailer
?>