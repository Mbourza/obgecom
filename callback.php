<?php
// callback.php - CMI Server-to-server callback handler

require_once("./core/init.php");
require_once('./dashboard/controllers/cmi/cmi_config.php');

// Extra logging (debug file)
file_put_contents(__DIR__ . "/callback.log", date('c') . " - " . json_encode($_POST) . PHP_EOL, FILE_APPEND);

try {
    $db = DB::getInstance();

    // Collect and sort POST parameters
    $postParams = array_keys($_POST);
    natcasesort($postParams);

    $hashString = "";
    foreach ($postParams as $param) {
        $paramValue = html_entity_decode(preg_replace("/\n$/", "", $_POST[$param]), ENT_QUOTES, 'UTF-8');
        $escapedParamValue = str_replace("\\", "\\\\", str_replace("|", "\\|", $paramValue));

        $lowerParam = strtolower($param);
        if ($lowerParam !== "hash" && $lowerParam !== "encoding") {
            $hashString .= $escapedParamValue . "|";
        }
    }

    // Append store key
    $escapedStoreKey = str_replace("\\", "\\\\", str_replace("|", "\\|", CMI_SECRET_KEY));
    $hashString .= $escapedStoreKey;

    $calculatedHash = base64_encode(pack('H*', hash('sha512', $hashString)));
    $retrievedHash  = $_POST["HASH"] ?? ($_POST["hash"] ?? '');

    if ($retrievedHash === $calculatedHash) {
        $oid = $_POST["oid"] ?? '';
        $amount = isset($_POST["amount"]) ? number_format((float)$_POST["amount"], 2, '.', '') : '';
        $procReturnCode = $_POST["ProcReturnCode"] ?? '';

        if (!empty($oid)) {
            // Find the transaction
            $payment = $db->get('payment_attempts', ['transaction_id', '=', $oid]);

            if ($payment->count()) {
                $paymentData = $payment->first();
                $dbAmount = number_format((float)$paymentData->amount, 2, '.', '');

                if ($dbAmount === $amount) {
                    if ($procReturnCode === "00") {
                        
                        // Start database transaction for consistency
                        $db->query("START TRANSACTION");
                        
                        try {
                            // Update payment status
                            $db->update('payment_attempts', $paymentData->id, [
                                'status' => 'completed',
                                'proc_return_code' => $procReturnCode,
                                'gateway_response' => json_encode($_POST),
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);

                            // Get user data
                            $user = $db->get('users', ['id', '=', $paymentData->user_id]);
                            
                            if ($user->count()) {
                                $userData = $user->first();
                                
                                // Calculate evaluation end date
                                $evaluationEndDate = date(
                                    'Y-m-d H:i:s', strtotime('+' . (int)$paymentData->duration_months . ' month')
                                );

                                // Update user account - activate and set evaluation period
                                $userUpdates = [
                                    'is_active' => 1,
                                    'active' => 1,
                                    'status' => 'active',
                                    'evaluation_end_date' => $evaluationEndDate,
                                    'updated_at' => date('Y-m-d H:i:s')
                                ];
                                
                                $db->update('users', $paymentData->user_id, $userUpdates);
                                
                                // Update or create subscription record
                                $existingSubscription = $db->get('subscriptions', ['user_id', '=', $paymentData->user_id]);
                                
                                $subscriptionData = [
                                    'user_id' => $paymentData->user_id,
                                    'plan_id' => $paymentData->plan_id,
                                    'status' => 'active',
                                    'started_at' => date('Y-m-d H:i:s'),
                                    'expires_at' => $evaluationEndDate,
                                    'created_at' => date('Y-m-d H:i:s')
                                ];
                                
                                if ($existingSubscription->count()) {
                                    // Update existing subscription
                                    $subscriptionData['updated_at'] = date('Y-m-d H:i:s');
                                    unset($subscriptionData['created_at']); // Don't update created_at for existing records
                                    $db->update('subscriptions', $existingSubscription->first()->id, $subscriptionData);
                                } else {
                                    // Create new subscription
                                    $db->insert('subscriptions', $subscriptionData);
                                }
                                
                                // Commit the transaction
                                $db->query("COMMIT");
                                
                                file_put_contents(__DIR__ . "/callback.log", 
                                    "SUCCESS: $oid - User {$paymentData->user_id} activated until $evaluationEndDate" . PHP_EOL, 
                                    FILE_APPEND
                                );
                                
                                // Send detailed payment confirmation email
                                sendPaymentConfirmationEmail($userData, $paymentData, $evaluationEndDate, $_POST);
                                
                            } else {
                                // User not found - still update payment but log warning
                                $db->query("COMMIT");
                                file_put_contents(__DIR__ . "/callback.log", 
                                    "WARNING: $oid - Payment successful but user {$paymentData->user_id} not found" . PHP_EOL, 
                                    FILE_APPEND
                                );
                            }
                            
                            echo "ACTION=POSTAUTH"; // ✅ DEMANDE LE DÉBIT AUTOMATIQUE
                            exit;
                            
                        } catch (Exception $e) {
                            // Rollback on error
                            $db->query("ROLLBACK");
                            file_put_contents(__DIR__ . "/callback.log", 
                                "ERROR in transaction: $oid - " . $e->getMessage() . PHP_EOL, 
                                FILE_APPEND
                            );
                            echo "FAILURE";
                            exit;
                        }
                        
                    } else {
                        // FAILED - Payment failed
                        $db->update('payment_attempts', $paymentData->id, [
                            'status' => 'failed',
                            'proc_return_code' => $procReturnCode,
                            'gateway_response' => json_encode($_POST),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                        
                        // Optional: Update user status to reflect failed payment
                        $user = $db->get('users', ['id', '=', $paymentData->user_id]);
                        if ($user->count()) {
                            $db->update('users', $paymentData->user_id, [
                                'status' => 'payment_failed',
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);
                        }

                        file_put_contents(__DIR__ . "./callback.log", 
                            "FAILED: $oid - Code $procReturnCode" . PHP_EOL, 
                            FILE_APPEND
                        );
                        
                        echo "APPROVED";
                        exit;
                    }
                } else {
                    file_put_contents(__DIR__ . "/callback.log", 
                        "AMOUNT MISMATCH: $oid - DB:$dbAmount CMI:$amount" . PHP_EOL, 
                        FILE_APPEND
                    );

                    $db->update('payment_attempts', $paymentData->id, [
                        'status' => 'failed',
                        'proc_return_code' => $procReturnCode,
                        'gateway_response' => json_encode($_POST),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);

                    echo "FAILURE";
                    exit;
                }
            } else {
                file_put_contents(__DIR__ . "/callback.log", "NOT FOUND: $oid" . PHP_EOL, FILE_APPEND);
                echo "FAILURE";
                exit;
            }
        } else {
            file_put_contents(__DIR__ . "/callback.log", "NO OID" . PHP_EOL, FILE_APPEND);
            echo "FAILURE";
            exit;
        }
    } else {
        file_put_contents(__DIR__ . "/callback.log", "HASH FAIL" . PHP_EOL, FILE_APPEND);
        echo "FAILURE";
        exit;
    }
} catch (Exception $e) {
    file_put_contents(__DIR__ . "/callback.log", "EXCEPTION: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    echo "FAILURE";
    exit;
}

/**
 * Send detailed payment confirmation email with all transaction info
 */
function sendPaymentConfirmationEmail($userData, $paymentData, $evaluationEndDate, $gatewayResponse) {
    $email = $userData->email;
    $name = $userData->name;
    $username = $userData->username;
    
    // Get plan name
    $planNames = [
        1 => 'Starter',
        2 => 'Professional',
        3 => 'Growth',
        4 => 'Business'
    ];
    
    $planName = $planNames[$paymentData->plan_id] ?? 'Plan Inconnu';
    
    // Format dates
    $paymentDate = date('d/m/Y à H:i', strtotime($paymentData->created_at));
    $expirationFormatted = date('d/m/Y à H:i', strtotime($evaluationEndDate));
    
    // Format amount
    $amount = number_format($paymentData->amount, 2, ',', ' ');
    $currency = strtoupper($paymentData->currency ?? 'MAD');
    
    // Email subject
    $subject = "✅ Confirmation de paiement - Compte activé | Transaction #{$paymentData->transaction_id}";
    
    // Create HTML email content
    $htmlMessage = createPaymentEmailHTML($userData, $paymentData, $evaluationEndDate, $gatewayResponse, $planName, $paymentDate, $expirationFormatted, $amount, $currency);
    
    // Create plain text version
    $textMessage = createPaymentEmailText($userData, $paymentData, $evaluationEndDate, $planName, $paymentDate, $expirationFormatted, $amount, $currency);
    
    // Email headers for HTML + text
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"boundary123\"\r\n";
    $headers .= "From: Équipe Support <noreply@obgecom.com>\r\n";
    $headers .= "Reply-To: support@obgecom.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    
    // Multipart message
    $message = "--boundary123\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $textMessage . "\r\n\r\n";
    
    $message .= "--boundary123\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $htmlMessage . "\r\n\r\n";
    $message .= "--boundary123--";
    
    // Send email
    $emailSent = mail($email, $subject, $message, $headers);
    
    // Log email attempt
    file_put_contents(__DIR__ . "/callback.log", 
        "EMAIL " . ($emailSent ? "SENT" : "FAILED") . ": {$paymentData->transaction_id} to $email" . PHP_EOL, 
        FILE_APPEND
    );
    
    return $emailSent;
}

/**
 * Create HTML email content
 */
function createPaymentEmailHTML($userData, $paymentData, $evaluationEndDate, $gatewayResponse, $planName, $paymentDate, $expirationFormatted, $amount, $currency) {
    return "
    <!DOCTYPE html>
    <html lang='fr'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Confirmation de paiement</title>
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
            .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #4CAF50, #45a049); color: white; padding: 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; }
            .success-icon { font-size: 48px; margin: 10px 0; }
            .content { padding: 30px; }
            .section { margin-bottom: 25px; }
            .section-title { font-size: 18px; font-weight: bold; color: #2c3e50; border-bottom: 2px solid #ecf0f1; padding-bottom: 8px; margin-bottom: 15px; }
            .info-grid { display: table; width: 100%; }
            .info-row { display: table-row; }
            .info-label { display: table-cell; padding: 8px 15px 8px 0; font-weight: 600; color: #7f8c8d; width: 40%; }
            .info-value { display: table-cell; padding: 8px 0; color: #2c3e50; }
            .amount { font-size: 24px; font-weight: bold; color: #27ae60; }
            .transaction-id { font-family: 'Courier New', monospace; background: #ecf0f1; padding: 5px 8px; border-radius: 4px; }
            .status-badge { background: #d5edf0; color: #27ae60; padding: 4px 12px; border-radius: 20px; font-size: 14px; font-weight: 500; }
            .cta-button { display: inline-block; background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px; font-weight: bold; margin: 20px 0; }
            .footer { background: #34495e; color: white; padding: 20px; text-align: center; font-size: 14px; }
            .highlight-box { background: #e8f5e8; border-left: 4px solid #27ae60; padding: 15px; border-radius: 4px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='success-icon'>🎉</div>
                <h1>Paiement Confirmé !</h1>
                <p>Votre compte a été activé avec succès</p>
            </div>
            
            <div class='content'>
                <div class='highlight-box'>
                    <h3 style='margin-top: 0; color: #27ae60;'>✅ Félicitations {$userData->name} !</h3>
                    <p>Votre paiement a été traité avec succès. Votre compte <strong>{$userData->username}</strong> est maintenant actif et vous avez accès à toutes les fonctionnalités du <strong>{$planName}</strong>.</p>
                </div>
                
                <div class='section'>
                    <h3 class='section-title'>📋 Détails de la Transaction</h3>
                    <div class='info-grid'>
                        <div class='info-row'>
                            <div class='info-label'>ID de Transaction:</div>
                            <div class='info-value'><span class='transaction-id'>{$paymentData->transaction_id}</span></div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Montant:</div>
                            <div class='info-value'><span class='amount'>{$amount} {$currency}</span></div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Date de paiement:</div>
                            <div class='info-value'>{$paymentDate}</div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Méthode:</div>
                            <div class='info-value'>" . ucfirst($paymentData->payment_method ?? 'Carte bancaire') . "</div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Statut:</div>
                            <div class='info-value'><span class='status-badge'>✅ Confirmé</span></div>
                        </div>
                    </div>
                </div>
                
                <div class='section'>
                    <h3 class='section-title'>👤 Informations du Compte</h3>
                    <div class='info-grid'>
                        <div class='info-row'>
                            <div class='info-label'>Nom:</div>
                            <div class='info-value'>{$userData->name}</div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Nom d'utilisateur:</div>
                            <div class='info-value'>{$userData->username}</div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Email:</div>
                            <div class='info-value'>{$userData->email}</div>
                        </div>
                        " . ($userData->phone ? "<div class='info-row'><div class='info-label'>Téléphone:</div><div class='info-value'>{$userData->phone}</div></div>" : "") . "
                        <div class='info-row'>
                            <div class='info-label'>Statut du compte:</div>
                            <div class='info-value'><span class='status-badge'>🟢 Actif</span></div>
                        </div>
                    </div>
                </div>
                
                <div class='section'>
                    <h3 class='section-title'>⭐ Détails de l'Abonnement</h3>
                    <div class='info-grid'>
                        <div class='info-row'>
                            <div class='info-label'>Plan souscrit:</div>
                            <div class='info-value'><strong>{$planName}</strong></div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Date d'activation:</div>
                            <div class='info-value'>{$paymentDate}</div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Valable jusqu'au:</div>
                            <div class='info-value'><strong>{$expirationFormatted}</strong></div>
                        </div>
                        <div class='info-row'>
                            <div class='info-label'>Durée:</div>
                            <div class='info-value'>1 mois (période d'évaluation)</div>
                        </div>
                    </div>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='https://obgecom.com/dashboard' class='cta-button'>🚀 Accéder à mon compte</a>
                </div>
                
                <div class='section'>
                    <h3 class='section-title'>📞 Support</h3>
                    <p>Si vous avez des questions concernant votre paiement ou votre compte, n'hésitez pas à nous contacter :</p>
                    <ul>
                        <li>📧 Email: support@obgecom.com</li>
                        <li>📱 Téléphone: +212 XXX XXX XXX</li>
                        <li>💬 Chat en ligne: Disponible sur votre tableau de bord</li>
                    </ul>
                </div>
            </div>
            
            <div class='footer'>
                <p><strong>Merci de votre confiance !</strong></p>
                <p>© 2024 Votre Entreprise. Tous droits réservés.</p>
                <p style='font-size: 12px; margin-top: 10px;'>
                    Cet email a été envoyé automatiquement suite à votre paiement.<br>
                    Transaction ID: {$paymentData->transaction_id}
                </p>
            </div>
        </div>
    </body>
    </html>";
}

/**
 * Create plain text email content
 */
function createPaymentEmailText($userData, $paymentData, $evaluationEndDate, $planName, $paymentDate, $expirationFormatted, $amount, $currency) {
    return "
=== CONFIRMATION DE PAIEMENT ===

Bonjour {$userData->name},

Félicitations ! Votre paiement a été traité avec succès et votre compte est maintenant actif.

DÉTAILS DE LA TRANSACTION
------------------------
ID de Transaction: {$paymentData->transaction_id}
Montant: {$amount} {$currency}
Date de paiement: {$paymentDate}
Méthode: " . ucfirst($paymentData->payment_method ?? 'Carte bancaire') . "
Statut: ✅ Confirmé

INFORMATIONS DU COMPTE
---------------------
Nom: {$userData->name}
Nom d'utilisateur: {$userData->username}
Email: {$userData->email}
" . ($userData->phone ? "Téléphone: {$userData->phone}\n" : "") . "Statut du compte: 🟢 Actif

DÉTAILS DE L'ABONNEMENT
----------------------
Plan souscrit: {$planName}
Date d'activation: {$paymentDate}
Valable jusqu'au: {$expirationFormatted}
Durée: 1 mois (période d'évaluation)

ACCÈS À VOTRE COMPTE
-------------------
Vous pouvez maintenant accéder à votre compte à l'adresse :
https://obgecom/dashboard

SUPPORT
-------
Si vous avez des questions :
📧 Email: support@obgecom.com
📱 Téléphone: +212 XXX XXX XXX

Merci de votre confiance !

L'équipe OBGECOM
© 2025 - Tous droits réservés

Transaction ID: {$paymentData->transaction_id}
";
}
?>