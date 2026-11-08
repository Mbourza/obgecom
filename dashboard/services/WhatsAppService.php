<?php
// services/WhatsAppService.php

class WhatsAppService {
    private $db;
    private $user_id;
    private $isEnabled = false;
    private $apiUrl;
    private $apiKey;
    private $fromNumber;
    private $phoneNumberId;
    
    public function __construct($db, $user_id = null) {
        $this->db = $db;
        $this->user_id = $user_id;
        $this->loadConfiguration();
    }
    
    /**
     * Load WhatsApp configuration
     */
    private function loadConfiguration() {
        // Load configuration file
        if (defined('WHATSAPP_API_URL') && defined('WHATSAPP_API_KEY')) {
            $this->apiUrl = WHATSAPP_API_URL;
            $this->apiKey = WHATSAPP_API_KEY;
            $this->fromNumber = defined('WHATSAPP_FROM_NUMBER') ? WHATSAPP_FROM_NUMBER : '';
            $this->phoneNumberId = defined('WHATSAPP_PHONE_NUMBER_ID') ? WHATSAPP_PHONE_NUMBER_ID : '';
            
            // Check if API key is configured
            if (!empty($this->apiKey) && !empty($this->apiUrl)) {
                $this->isEnabled = true;
            }
        }
        
        // Try to load user-specific settings if user_id is provided
        if ($this->user_id && $this->db) {
            $userSettings = $this->db->getThisQuery(
                "SELECT setting_key, setting_value 
                 FROM system_settings 
                 WHERE user_id = ? 
                 AND setting_key IN ('whatsapp_api_key', 'whatsapp_api_url', 'whatsapp_from_number')",
                [$this->user_id]
            );
            
            foreach ($userSettings as $setting) {
                switch ($setting['setting_key']) {
                    case 'whatsapp_api_key':
                        $this->apiKey = $setting['setting_value'];
                        break;
                    case 'whatsapp_api_url':
                        $this->apiUrl = $setting['setting_value'];
                        break;
                    case 'whatsapp_from_number':
                        $this->fromNumber = $setting['setting_value'];
                        break;
                }
            }
            
            // Recheck if enabled
            if (!empty($this->apiKey) && !empty($this->apiUrl)) {
                $this->isEnabled = true;
            }
        }
    }
    
    /**
     * Check if WhatsApp service is enabled
     */
    public function isEnabled() {
        return $this->isEnabled;
    }
    
    /**
     * Format phone number for WhatsApp API
     */
    public function formatPhoneNumber($phone) {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // If phone doesn't start with +, add country code
        if (!str_starts_with($phone, '+')) {
            // Remove leading zeros
            $phone = ltrim($phone, '0');
            // Add country code (default to Morocco +212)
            if (strlen($phone) == 9) {
                $phone = '+212' . $phone;
            } else {
                $phone = '+' . $phone;
            }
        }
        
        return $phone;
    }
    
    /**
     * Send a WhatsApp message
     */
    public function sendMessage($to, $message, $template = null) {
        if (!$this->isEnabled) {
            return ['success' => false, 'error' => 'WhatsApp service is not enabled'];
        }
        
        $to = $this->formatPhoneNumber($to);
        
        // Determine which API to use
        $provider = defined('WHATSAPP_PROVIDER') ? WHATSAPP_PROVIDER : '360dialog';
        
        switch ($provider) {
            case '360dialog':
                return $this->sendVia360Dialog($to, $message);
            case 'meta':
                return $this->sendViaMetaAPI($to, $message, $template);
            case 'ultramsg':
                return $this->sendViaUltraMsg($to, $message);
            default:
                return ['success' => false, 'error' => 'Unknown WhatsApp provider'];
        }
    }
    
    /**
     * Send message via 360dialog
     */
    public function sendVia360Dialog($to, $message) {
        // Prepare the payload for 360dialog
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'body' => $message
            ]
        ];
        
        return $this->makeApiRequest($payload);
    }
    
    /**
     * Send message via Meta/Facebook Cloud API
     */
    public function sendViaMetaAPI($to, $message, $template = null) {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'body' => $message
            ]
        ];
        
        // If template is provided, use template instead
        if ($template) {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $template,
                    'language' => ['code' => 'fr']
                ]
            ];
        }
        
        return $this->makeApiRequest($payload, 'meta');
    }
    
    /**
     * Send message via UltraMsg
     */
    public function sendViaUltraMsg($to, $message) {
        $payload = [
            'to' => $to,
            'body' => $message,
            'priority' => 1,
            'referenceId' => 'ai_confirmation_' . time()
        ];
        
        return $this->makeApiRequest($payload, 'ultramsg');
    }
    
    /**
     * Make API request to WhatsApp provider
     */
    private function makeApiRequest($payload, $provider = null) {
        $provider = $provider ?? (defined('WHATSAPP_PROVIDER') ? WHATSAPP_PROVIDER : '360dialog');
        
        $url = $this->apiUrl;
        $headers = [];
        
        switch ($provider) {
            case '360dialog':
                $headers = [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json'
                ];
                break;
            case 'meta':
                $url = str_replace('{phone_number_id}', $this->phoneNumberId, $this->apiUrl);
                $headers = [
                    'Authorization: Bearer ' . $this->apiKey,
                    'Content-Type: application/json'
                ];
                break;
            case 'ultramsg':
                $url = $this->apiUrl . '?token=' . $this->apiKey;
                $headers = [
                    'Content-Type: application/json'
                ];
                break;
            default:
                return ['success' => false, 'error' => 'Unknown provider'];
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            error_log("WhatsApp API CURL Error: " . $curlError);
            return ['success' => false, 'error' => 'CURL Error: ' . $curlError];
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'response' => $decoded,
                'message_id' => $decoded['messages'][0]['id'] ?? null
            ];
        } else {
            error_log("WhatsApp API Error: HTTP {$httpCode} - Response: " . $response);
            return [
                'success' => false,
                'error' => 'API Error: HTTP ' . $httpCode,
                'response' => $decoded
            ];
        }
    }
    
    /**
     * Send template message (for WhatsApp templates)
     */
    public function sendTemplate($to, $templateName, $parameters = []) {
        if (!$this->isEnabled) {
            return ['success' => false, 'error' => 'WhatsApp service is not enabled'];
        }
        
        $to = $this->formatPhoneNumber($to);
        
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => 'fr']
            ]
        ];
        
        // Add parameters if provided
        if (!empty($parameters)) {
            $payload['template']['components'] = [
                [
                    'type' => 'body',
                    'parameters' => $parameters
                ]
            ];
        }
        
        return $this->makeApiRequest($payload);
    }
}