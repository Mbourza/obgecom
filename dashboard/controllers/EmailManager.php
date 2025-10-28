<?php
require_once 'config/e_config.php';
require_once '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailManager {
    private $imap;
    private $connected = false;
    private $db;
    
    public function __construct() {
        // Initialize database connection if needed
        if(file_exists(stream_resolve_include_path("../core/init.php"))) {
            require_once("../core/init.php");
            $this->db = DB::getInstance();
        }
    }
    
    public function connect() {
        try {
            $mailbox = "{" . IMAP_HOST . ":" . IMAP_PORT . "/imap/ssl}" . INBOX_FOLDER;
            $this->imap = imap_open($mailbox, EMAIL_USERNAME, EMAIL_PASSWORD);
            $this->connected = ($this->imap !== false);
            
            if (!$this->connected) {
                error_log("IMAP Connection Failed: " . imap_last_error());
            }
            
            return $this->connected;
        } catch (Exception $e) {
            error_log("IMAP Connection Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function disconnect() {
        if ($this->connected && $this->imap) {
            imap_close($this->imap);
            $this->connected = false;
        }
    }
    
    public function getEmails($limit = 50, $page = 1) {
        if (!$this->connect()) return [];
        
        $emails = [];
        $total = imap_num_msg($this->imap);
        $start = max(1, $total - ($limit * $page) + 1);
        $end = max(1, $total - ($limit * ($page - 1)));
        
        for ($i = $end; $i >= $start; $i--) {
            try {
                $header = imap_headerinfo($this->imap, $i);
                $body = imap_body($this->imap, $i);
                $overview = imap_fetch_overview($this->imap, "$i:$i");
                
                $emails[] = [
                    'id' => $i,
                    'message_id' => $header->message_id ?? '',
                    'subject' => $header->subject ?? 'No Subject',
                    'from' => $header->from[0]->mailbox . "@" . $header->from[0]->host,
                    'from_name' => isset($header->from[0]->personal) ? $this->decodeMimeHeader($header->from[0]->personal) : '',
                    'date' => date('Y-m-d H:i:s', $header->udate),
                    'read' => $overview[0]->seen ?? false,
                    'body_preview' => $this->getBodyPreview($body),
                    'attachments' => $this->getAttachments($i),
                    'priority' => $this->getEmailPriority($header)
                ];
            } catch (Exception $e) {
                error_log("Error processing email $i: " . $e->getMessage());
                continue;
            }
        }
        
        $this->disconnect();
        return $emails;
    }
    
    public function getEmailById($emailId) {
        if (!$this->connect()) return null;
        
        try {
            $header = imap_headerinfo($this->imap, $emailId);
            $structure = imap_fetchstructure($this->imap, $emailId);
            $body = $this->getFullMessageBody($emailId, $structure);
            
            // Get email overview to check read status
            $overview = imap_fetch_overview($this->imap, "$emailId:$emailId");
            $isRead = isset($overview[0]) ? (bool)$overview[0]->seen : false;
            
            $email = [
                'id' => $emailId,
                'message_id' => $header->message_id ?? '',
                'subject' => $header->subject ?? 'No Subject',
                'from' => $header->from[0]->mailbox . "@" . $header->from[0]->host,
                'from_name' => isset($header->from[0]->personal) ? $this->decodeMimeHeader($header->from[0]->personal) : '',
                'to' => $this->getRecipients($header->to),
                'cc' => isset($header->cc) ? $this->getRecipients($header->cc) : [],
                'date' => date('Y-m-d H:i:s', $header->udate),
                'read' => $isRead, // Added read status
                'body' => $body,
                'html_body' => $this->getHtmlBody($emailId, $structure),
                'attachments' => $this->getAttachmentsWithContent($emailId),
                'headers' => imap_fetchheader($this->imap, $emailId),
                'priority' => $this->getEmailPriority($header),
                'size' => imap_msgno($this->imap, $emailId)
            ];
            
            return $email;
            
        } catch (Exception $e) {
            error_log("Error getting email $emailId: " . $e->getMessage());
            return null;
        } finally {
            $this->disconnect();
        }
    }
    
    public function sendEmail($to, $subject, $message, $cc = [], $bcc = [], $attachments = []) {
        try {
            $mail = new PHPMailer(true);
            
            // Configuration SMTP
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = EMAIL_USERNAME;
            $mail->Password = EMAIL_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->CharSet = 'UTF-8';
            
            // Expéditeur
            $mail->setFrom(EMAIL_USERNAME, 'OBG Support');
            $mail->addReplyTo(EMAIL_USERNAME, 'OBG Support');
            
            // Destinataires
            if (is_string($to)) $to = array_map('trim', explode(',', $to));
            foreach ($to as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $mail->addAddress($email);
                }
            }
            
            // CC
            if (is_string($cc)) $cc = array_map('trim', explode(',', $cc));
            foreach ($cc as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $mail->addCC($email);
                }
            }
            
            // BCC
            if (is_string($bcc)) $bcc = array_map('trim', explode(',', $bcc));
            foreach ($bcc as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $mail->addBCC($email);
                }
            }
            
            // Pièces jointes
            foreach ($attachments as $attachment) {
                if (isset($attachment['tmp_name']) && file_exists($attachment['tmp_name'])) {
                    $mail->addAttachment($attachment['tmp_name'], $attachment['name']);
                } elseif (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $mail->addAttachment($attachment['path'], $attachment['name']);
                }
            }
            
            // Contenu
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $this->wrapEmailTemplate($message);
            $mail->AltBody = strip_tags($message);
            
            if ($mail->send()) {
                // Log the sent email in database if needed
                $this->logSentEmail($to, $subject, $message);
                return ['success' => true, 'message' => 'Email envoyé avec succès'];
            } else {
                return ['success' => false, 'message' => 'Erreur lors de l\'envoi de l\'email'];
            }
            
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    public function deleteEmail($emailId) {
        if (!$this->connect()) {
            return ['success' => false, 'message' => 'Impossible de se connecter au serveur email'];
        }
        
        try {
            // Mark email for deletion
            imap_delete($this->imap, $emailId);
            // Expunge to permanently delete
            imap_expunge($this->imap);
            
            $this->disconnect();
            return ['success' => true, 'message' => 'Email supprimé avec succès'];
        } catch (Exception $e) {
            $this->disconnect();
            error_log("Email deletion error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()];
        }
    }
    
    public function markAsRead($emailId) {
        if (!$this->connect()) return false;
        
        try {
            $result = imap_setflag_full($this->imap, $emailId, "\\Seen");
            $this->disconnect();
            return $result;
        } catch (Exception $e) {
            $this->disconnect();
            error_log("Mark as read error: " . $e->getMessage());
            return false;
        }
    }
    
    public function markAsUnread($emailId) {
        if (!$this->connect()) return false;
        
        try {
            $result = imap_clearflag_full($this->imap, $emailId, "\\Seen");
            $this->disconnect();
            return $result;
        } catch (Exception $e) {
            $this->disconnect();
            error_log("Mark as unread error: " . $e->getMessage());
            return false;
        }
    }
    
    public function moveEmail($emailId, $folder) {
        if (!$this->connect()) return false;
        
        try {
            $result = imap_mail_move($this->imap, $emailId, $folder);
            imap_expunge($this->imap);
            $this->disconnect();
            return $result;
        } catch (Exception $e) {
            $this->disconnect();
            error_log("Move email error: " . $e->getMessage());
            return false;
        }
    }
    
    public function searchEmails($query, $field = 'all') {
        if (!$this->connect()) return [];
        
        $searchCriteria = '';
        switch ($field) {
            case 'subject':
                $searchCriteria = 'SUBJECT "' . imap_utf8($query) . '"';
                break;
            case 'from':
                $searchCriteria = 'FROM "' . imap_utf8($query) . '"';
                break;
            case 'body':
                $searchCriteria = 'BODY "' . imap_utf8($query) . '"';
                break;
            case 'to':
                $searchCriteria = 'TO "' . imap_utf8($query) . '"';
                break;
            default:
                $searchCriteria = 'TEXT "' . imap_utf8($query) . '"';
        }
        
        $emails = [];
        $messageIds = imap_search($this->imap, $searchCriteria);
        
        if ($messageIds) {
            rsort($messageIds); // Most recent first
            foreach ($messageIds as $messageId) {
                try {
                    $header = imap_headerinfo($this->imap, $messageId);
                    $body = imap_body($this->imap, $messageId);
                    $overview = imap_fetch_overview($this->imap, "$messageId:$messageId");
                    
                    $emails[] = [
                        'id' => $messageId,
                        'subject' => $header->subject ?? 'No Subject',
                        'from' => $header->from[0]->mailbox . "@" . $header->from[0]->host,
                        'from_name' => isset($header->from[0]->personal) ? $this->decodeMimeHeader($header->from[0]->personal) : '',
                        'date' => date('Y-m-d H:i:s', $header->udate),
                        'read' => $overview[0]->seen ?? false,
                        'body_preview' => $this->getBodyPreview($body)
                    ];
                } catch (Exception $e) {
                    error_log("Error processing search result $messageId: " . $e->getMessage());
                    continue;
                }
            }
        }
        
        $this->disconnect();
        return $emails;
    }
    
    public function getEmailStats() {
        if (!$this->connect()) return ['total' => 0, 'unread' => 0, 'read' => 0];
        
        $total = imap_num_msg($this->imap);
        $unread = 0;
        
        if ($total > 0) {
            $overview = imap_fetch_overview($this->imap, "1:$total");
            foreach ($overview as $email) {
                if (!$email->seen) $unread++;
            }
        }
        
        $this->disconnect();
        return [
            'total' => $total,
            'unread' => $unread,
            'read' => $total - $unread
        ];
    }
    
    public function getFolders() {
        if (!$this->connect()) return [];
        
        $folders = imap_list($this->imap, "{" . IMAP_HOST . ":" . IMAP_PORT . "}", "*");
        $this->disconnect();
        
        return $folders ?: [];
    }
    
    public function getUnreadCount() {
        $stats = $this->getEmailStats();
        return $stats['unread'];
    }
    
    public function downloadAttachment($emailId, $attachmentPart) {
        if (!$this->connect()) return null;
        
        try {
            $attachment = imap_fetchbody($this->imap, $emailId, $attachmentPart);
            $this->disconnect();
            return base64_decode($attachment);
        } catch (Exception $e) {
            $this->disconnect();
            error_log("Attachment download error: " . $e->getMessage());
            return null;
        }
    }
    
    // Private helper methods
    
    private function getFullMessageBody($messageId, $structure, $partNumber = '') {
        $body = '';
        
        if ($structure->type == 0) {
            // Simple message - texte brut
            $body = imap_body($this->imap, $messageId);
            
            // Décoder si encodé
            if (isset($structure->encoding)) {
                $body = $this->decodeBody($body, $structure->encoding);
            }
            
        } elseif ($structure->type == 1) {
            // Multipart message - parcourir les parties
            foreach ($structure->parts as $index => $part) {
                $partId = $partNumber ? $partNumber . '.' . ($index + 1) : ($index + 1);
                
                // Priorité au HTML
                if ($part->subtype == 'HTML') {
                    $htmlBody = $this->getPartBody($messageId, $partId, $part);
                    if (!empty($htmlBody)) {
                        return $htmlBody;
                    }
                }
            }
            
            // Si pas de HTML, prendre le texte
            foreach ($structure->parts as $index => $part) {
                $partId = $partNumber ? $partNumber . '.' . ($index + 1) : ($index + 1);
                
                if ($part->subtype == 'PLAIN') {
                    $textBody = $this->getPartBody($messageId, $partId, $part);
                    if (!empty($textBody)) {
                        return $textBody;
                    }
                }
            }
            
            // Sinon prendre la première partie disponible
            foreach ($structure->parts as $index => $part) {
                $partId = $partNumber ? $partNumber . '.' . ($index + 1) : ($index + 1);
                $body .= $this->getFullMessageBody($messageId, $part, $partId);
            }
        }
        
        return $body;
    }

    private function getPartBody($messageId, $partId, $part) {
        $body = imap_fetchbody($this->imap, $messageId, $partId);
        
        if (isset($part->encoding)) {
            $body = $this->decodeBody($body, $part->encoding);
        }
        
        return $body;
    }
    
    private function getHtmlBody($messageId, $structure, $partNumber = '') {
        if ($structure->type == 1) {
            // Multipart message
            foreach ($structure->parts as $index => $part) {
                $partId = $partNumber ? $partNumber . '.' . ($index + 1) : ($index + 1);
                
                if ($part->subtype == 'HTML') {
                    $body = imap_fetchbody($this->imap, $messageId, $partId);
                    if (isset($part->encoding)) {
                        $body = $this->decodeBody($body, $part->encoding);
                    }
                    return $body;
                }
                
                // Vérifier les sous-parties
                if ($part->type == 1) {
                    $html = $this->getHtmlBody($messageId, $part, $partId);
                    if (!empty($html)) return $html;
                }
            }
        }
        
        return '';
    }
    
    private function decodeBody($body, $encoding) {
        switch ($encoding) {
            case 3: // BASE64
                return base64_decode($body);
            case 4: // QUOTED-PRINTABLE
                return quoted_printable_decode($body);
            case 1: // 8BIT
            case 2: // BINARY
            default:
                return $body;
        }
    }
    
    private function getBodyPreview($body, $length = 150) {
        $text = strip_tags($body);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        return mb_substr($text, 0, $length) . (mb_strlen($text) > $length ? '...' : '');
    }
    
    private function getAttachments($messageId) {
        $attachments = [];
        $structure = imap_fetchstructure($this->imap, $messageId);
        
        if (isset($structure->parts)) {
            foreach ($structure->parts as $partIndex => $part) {
                $attachment = $this->processPart($part, $partIndex + 1);
                if ($attachment) {
                    $attachments[] = $attachment;
                }
            }
        }
        
        return $attachments;
    }
    
    private function getAttachmentsWithContent($messageId) {
        $attachments = [];
        $structure = imap_fetchstructure($this->imap, $messageId);
        
        if (isset($structure->parts)) {
            foreach ($structure->parts as $partIndex => $part) {
                $attachment = $this->processPart($part, $partIndex + 1, true, $messageId);
                if ($attachment) {
                    $attachments[] = $attachment;
                }
            }
        }
        
        return $attachments;
    }
    
    private function processPart($part, $partNumber, $withContent = false, $messageId = null) {
        $filename = '';
        $isAttachment = false;
        
        // Check for filename in dparameters
        if ($part->ifdparameters) {
            foreach ($part->dparameters as $param) {
                if (strtolower($param->attribute) == 'filename') {
                    $filename = $this->decodeMimeHeader($param->value);
                    $isAttachment = true;
                    break;
                }
            }
        }
        
        // Check for filename in parameters
        if (!$filename && $part->ifparameters) {
            foreach ($part->parameters as $param) {
                if (strtolower($param->attribute) == 'name') {
                    $filename = $this->decodeMimeHeader($param->value);
                    $isAttachment = true;
                    break;
                }
            }
        }
        
        if ($isAttachment && $filename) {
            $attachment = [
                'filename' => $filename,
                'part' => $partNumber,
                'size' => $part->bytes ?? 0,
                'type' => $part->type ?? 0,
                'subtype' => $part->subtype ?? ''
            ];
            
            if ($withContent && $messageId) {
                $content = imap_fetchbody($this->imap, $messageId, $partNumber);
                if (isset($part->encoding)) {
                    $content = $this->decodeBody($content, $part->encoding);
                }
                $attachment['content'] = $content;
            }
            
            return $attachment;
        }
        
        return null;
    }
    
    private function getRecipients($addresses) {
        $recipients = [];
        if ($addresses) {
            foreach ($addresses as $address) {
                $recipients[] = [
                    'email' => $address->mailbox . "@" . $address->host,
                    'name' => isset($address->personal) ? $this->decodeMimeHeader($address->personal) : ''
                ];
            }
        }
        return $recipients;
    }
    
    private function getEmailPriority($header) {
        if (isset($header->importance)) {
            return $header->importance;
        }
        
        if (isset($header->priority)) {
            return $header->priority;
        }
        
        // Check X-Priority header
        if (isset($header->x_priority)) {
            return $header->x_priority;
        }
        
        return 'normal';
    }
    
    private function decodeMimeHeader($header) {
        $decoded = imap_utf8($header);
        if (preg_match('/=\?([^?]+)\?([QB])\?([^?]*)\?=/i', $decoded)) {
            $decoded = imap_mime_header_decode($decoded);
            $result = '';
            foreach ($decoded as $part) {
                $result .= $part->text;
            }
            return $result;
        }
        return $decoded;
    }
    
    private function wrapEmailTemplate($content) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; }
                .email-header { background: linear-gradient(135deg, #2a1669, #6631e1); color: white; padding: 30px 20px; text-align: center; }
                .email-content { padding: 30px 20px; background: #f8fafc; }
                .email-footer { text-align: center; padding: 20px; color: #64748b; font-size: 14px; background: #ffffff; border-top: 1px solid #e5e7eb; }
                .button { display: inline-block; padding: 12px 24px; background: #2a1669; color: white; text-decoration: none; border-radius: 8px; margin: 10px 0; }
                .signature { margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='email-header'>
                    <h1 style='margin: 0; font-size: 24px;'>OBG ECOM Support</h1>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>Votre partenaire de confiance</p>
                </div>
                <div class='email-content'>
                    {$content}
                    <div class='signature'>
                        <p><strong>L'équipe OBG ECOM</strong><br>
                        Email: support@obg-ecom.com<br>
                        Téléphone: +33 1 23 45 67 89</p>
                    </div>
                </div>
                <div class='email-footer'>
                    <p>&copy; " . date('Y') . " OBG ECOM. Tous droits réservés.</p>
                    <p>Cet email a été envoyé par le système de support OBG ECOM</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function logSentEmail($to, $subject, $message) {
        // Log sent emails in database if needed
        if ($this->db) {
            try {
                $this->db->insert('sent_emails', [
                    'recipients' => is_array($to) ? json_encode($to) : $to,
                    'subject' => $subject,
                    'message' => $message,
                    'sent_at' => date('Y-m-d H:i:s')
                ]);
            } catch (Exception $e) {
                error_log("Email logging error: " . $e->getMessage());
            }
        }
    }
    
    public function __destruct() {
        $this->disconnect();
    }
}
?>