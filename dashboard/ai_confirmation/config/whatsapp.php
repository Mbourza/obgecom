<?php
// config/whatsapp.php

// ============================================
// WhatsApp Configuration - 360dialog
// ============================================

// Choose your provider: '360dialog', 'meta', or 'ultramsg'
define('WHATSAPP_PROVIDER', '360dialog');

// ============================================
// Option 1: 360Dialog Configuration (PRIMARY)
// ============================================
define('WHATSAPP_API_URL', 'https://waba-v2.360dialog.io/messages');
define('WHATSAPP_API_KEY', 'lTA6wuzHgfaxFG069NNdjES5AK'); // Your 360dialog API Key

// Your WhatsApp Business Account Phone Number ID
define('WHATSAPP_PHONE_NUMBER_ID', '1207455752442734'); // Your phone number ID from 360dialog
define('WHATSAPP_FROM_NUMBER', '212669335322'); // Your WhatsApp Business number (e.g., '212600000000')

// ============================================
// Common Settings
// ============================================
define('WHATSAPP_WEBHOOK_SECRET', 'https://waba-v2.360dialog.io');
define('WHATSAPP_TEMPLATE_NAME', 'auth_template');

// Webhook URL (configure this in your provider dashboard)
// https://yourdomain.com/webhook/whatsapp_webhook.php

// ============================================
// WhatsApp Templates for AI Confirmation
// ============================================
define('WHATSAPP_TEMPLATE_AI_CONFIRMATION', 'order_confirmation_ai');
define('WHATSAPP_TEMPLATE_AI_RETRY', 'order_confirmation_ai_retry');
define('WHATSAPP_TEMPLATE_AI_ESCALATED', 'order_confirmation_ai_escalated');

// ============================================
// AI Confirmation Settings
// ============================================
define('AI_CONFIRMATION_ENABLED', true);
define('AI_MAX_ATTEMPTS', 3);
define('AI_MAX_WAIT_HOURS', 24);
define('AI_RETRY_INTERVAL_HOURS', 2);
?>