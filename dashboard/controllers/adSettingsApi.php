<?php
// api/settings.php

if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$db = DB::getInstance();

// Fonction pour récupérer l'ID utilisateur
function getCurrentUserId($db) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['username'])) {
        return null;
    }

    $identifier = $_SESSION['user']['username'];

    if (!empty($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }

    $user = $db->getThisQuery("
        SELECT id 
        FROM users 
        WHERE username = ? OR email = ? OR phone = ?
        LIMIT 1
    ", [$identifier, $identifier, $identifier]);

    if ($user && isset($user[0]['id'])) {
        return (int)$user[0]['id'];
    }

    return null;
}

// Récupérer l'ID utilisateur
$user_id = getCurrentUserId($db);

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Utilisateur non authentifié']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetRequest($db, $user_id);
        break;
    case 'POST':
    case 'PUT':
        handlePostPutRequest($db, $user_id);
        break;
    case 'DELETE':
        handleDeleteRequest($db, $user_id);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
}

// ============================
// FONCTIONS DE GESTION DES REQUÊTES
// ============================

function handleGetRequest($db, $user_id) {
    try {
        // Si un paramètre spécifique est demandé
        if (isset($_GET['key'])) {
            $key = $_GET['key'];
            $setting = getSetting($db, $user_id, $key);
            
            if ($setting) {
                // Parser la valeur selon le type
                $setting['parsed_value'] = parseSettingValue($setting);
                echo json_encode(['success' => true, 'data' => $setting]);
            } else {
                echo json_encode(['success' => true, 'data' => null]);
            }
        } 
        // Si plusieurs clés sont demandées
        elseif (isset($_GET['keys'])) {
            $keys = explode(',', $_GET['keys']);
            $settings = [];
            
            foreach ($keys as $key) {
                $key = trim($key);
                $setting = getSetting($db, $user_id, $key);
                if ($setting) {
                    $settings[$key] = parseSettingValue($setting);
                } else {
                    $settings[$key] = null;
                }
            }
            
            echo json_encode(['success' => true, 'data' => $settings]);
        }
        // Sinon, récupérer tous les paramètres de l'utilisateur
        else {
            $settings = getAllSettings($db, $user_id);
            echo json_encode(['success' => true, 'data' => $settings]);
        }
    } catch (Exception $e) {
        error_log("Error in GET /api/settings: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
    }
}

function handlePostPutRequest($db, $user_id) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Données JSON invalides']);
            exit;
        }
        
        // Si plusieurs paramètres à sauvegarder sous forme de tableau
        if (isset($data['settings']) && is_array($data['settings'])) {
            $results = [];
            $successCount = 0;
            
            foreach ($data['settings'] as $setting) {
                if (isset($setting['key']) && isset($setting['value'])) {
                    $success = saveSetting(
                        $db, 
                        $user_id, 
                        $setting['key'], 
                        $setting['value'], 
                        $setting['type'] ?? 'string',
                        $setting['description'] ?? null
                    );
                    
                    $results[$setting['key']] = $success;
                    if ($success) $successCount++;
                }
            }
            
            echo json_encode([
                'success' => $successCount > 0,
                'message' => "{$successCount} paramètre(s) sauvegardé(s)",
                'results' => $results
            ]);
        } 
        // Si un seul paramètre
        elseif (isset($data['key']) && isset($data['value'])) {
            $success = saveSetting(
                $db, 
                $user_id, 
                $data['key'], 
                $data['value'], 
                $data['type'] ?? 'string',
                $data['description'] ?? null
            );
            
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Paramètre sauvegardé avec succès' : 'Erreur lors de la sauvegarde'
            ]);
        }
        // Pour sauvegarder les paramètres d'assignation - NETTOYER D'ABORD LES ANCIENS
        elseif (isset($data['assignment_settings']) && is_array($data['assignment_settings'])) {
            // Supprimer d'abord tous les anciens paramètres d'assignation pour cet utilisateur
            deleteAssignmentSettings($db, $user_id);
            
            // Puis sauvegarder les nouveaux
            $results = saveAssignmentSettings($db, $user_id, $data['assignment_settings']);
            $successCount = count(array_filter($results));
            
            echo json_encode([
                'success' => $successCount > 0,
                'message' => "{$successCount} paramètre(s) d'assignation sauvegardé(s)",
                'results' => $results
            ]);
        }
        // Pour sauvegarder les préférences de traitement - NETTOYER D'ABORD LES ANCIENS
        elseif (isset($data['processing_preferences']) && is_array($data['processing_preferences'])) {
            // Supprimer d'abord tous les anciens paramètres de traitement pour cet utilisateur
            deleteProcessingPreferences($db, $user_id);
            
            // Puis sauvegarder les nouveaux
            $results = saveProcessingPreferences($db, $user_id, $data['processing_preferences']);
            $successCount = count(array_filter($results));
            
            echo json_encode([
                'success' => $successCount > 0,
                'message' => "{$successCount} préférence(s) de traitement sauvegardée(s)",
                'results' => $results
            ]);
        }
        else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Données invalides']);
        }
    } catch (Exception $e) {
        error_log("Error in POST/PUT /api/settings: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleDeleteRequest($db, $user_id) {
    try {
        if (isset($_GET['key'])) {
            $key = $_GET['key'];
            $success = deleteSetting($db, $user_id, $key);
            
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Paramètre supprimé avec succès' : 'Erreur lors de la suppression'
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Clé du paramètre requise']);
        }
    } catch (Exception $e) {
        error_log("Error in DELETE /api/settings: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
    }
}

// ============================
// FONCTIONS SPÉCIFIQUES POUR LES PARAMÈTRES D'ASSIGNATION
// ============================

function deleteAssignmentSettings($db, $user_id) {
    $keys = [
        'assignment_method',
        'assignment_granularity',
        'assignment_auto_reassign',
        'assignment_allow_manual',
        'assignment_include_offline',
        'performance_metrics',
        'performance_min_threshold'
    ];
    
    $deletedCount = 0;
    foreach ($keys as $key) {
        if (deleteSetting($db, $user_id, $key)) {
            $deletedCount++;
        }
    }
    
    return $deletedCount;
}

function deleteProcessingPreferences($db, $user_id) {
    $keys = [
        'processing_auto_confirm_delay',
        'processing_auto_cancel_hours',
        'processing_merge_duplicates',
        'processing_send_notification'
    ];
    
    $deletedCount = 0;
    foreach ($keys as $key) {
        if (deleteSetting($db, $user_id, $key)) {
            $deletedCount++;
        }
    }
    
    return $deletedCount;
}

function saveAssignmentSettings($db, $user_id, $settings) {
    $results = [];
    
    // Méthode d'assignation (toujours présente)
    if (isset($settings['method'])) {
        $results['assignment_method'] = saveSetting(
            $db, $user_id, 
            'assignment_method', 
            $settings['method'], 
            'string',
            'Méthode d\'assignation des commandes'
        );
    }
    
    // Paramètres région (si méthode = region)
    if ($settings['method'] === 'region') {
        if (isset($settings['granularity'])) {
            $results['assignment_granularity'] = saveSetting(
                $db, $user_id,
                'assignment_granularity',
                $settings['granularity'],
                'string',
                'Niveau de granularité pour l\'assignation par région'
            );
        }
        
        if (isset($settings['autoReassign'])) {
            $results['assignment_auto_reassign'] = saveSetting(
                $db, $user_id,
                'assignment_auto_reassign',
                $settings['autoReassign'],
                'string',
                'Délai de réassignation automatique'
            );
        }
        
        if (isset($settings['allowManual'])) {
            $results['assignment_allow_manual'] = saveSetting(
                $db, $user_id,
                'assignment_allow_manual',
                $settings['allowManual'],
                'boolean',
                'Permettre la réassignation manuelle'
            );
        }
        
        if (isset($settings['includeOffline'])) {
            $results['assignment_include_offline'] = saveSetting(
                $db, $user_id,
                'assignment_include_offline',
                $settings['includeOffline'],
                'boolean',
                'Inclure les agents hors-ligne'
            );
        }
    }
    // Paramètres performance (si méthode = performance)
    elseif ($settings['method'] === 'performance') {
        // Métriques
        if (isset($settings['metrics']) && is_array($settings['metrics'])) {
            $results['performance_metrics'] = saveSetting(
                $db, $user_id,
                'performance_metrics',
                $settings['metrics'],
                'json',
                'Métriques de performance pour l\'assignation'
            );
        }
        
        // Seuil minimum
        if (isset($settings['minThreshold'])) {
            $results['performance_min_threshold'] = saveSetting(
                $db, $user_id,
                'performance_min_threshold',
                $settings['minThreshold'],
                'number',
                'Seuil minimum de performance'
            );
        }
    }
    
    return $results;
}

function saveProcessingPreferences($db, $user_id, $preferences) {
    $results = [];
    
    if (isset($preferences['autoConfirmDelay'])) {
        $results['processing_auto_confirm_delay'] = saveSetting(
            $db, $user_id,
            'processing_auto_confirm_delay',
            $preferences['autoConfirmDelay'],
            'number',
            'Délai de confirmation automatique (minutes)'
        );
    }
    
    if (isset($preferences['autoCancelHours'])) {
        $results['processing_auto_cancel_hours'] = saveSetting(
            $db, $user_id,
            'processing_auto_cancel_hours',
            $preferences['autoCancelHours'],
            'number',
            'Auto-annulation après (heures)'
        );
    }
    
    if (isset($preferences['mergeDuplicates'])) {
        $results['processing_merge_duplicates'] = saveSetting(
            $db, $user_id,
            'processing_merge_duplicates',
            $preferences['mergeDuplicates'],
            'boolean',
            'Fusionner automatiquement les commandes en double'
        );
    }
    
    if (isset($preferences['sendNotification'])) {
        $results['processing_send_notification'] = saveSetting(
            $db, $user_id,
            'processing_send_notification',
            $preferences['sendNotification'],
            'boolean',
            'Envoyer notification au client après confirmation'
        );
    }
    
    return $results;
}

// ============================
// FONCTIONS UTILITAIRES
// ============================

function getSetting($db, $user_id, $key) {
    $result = $db->getThisQuery("
        SELECT * FROM system_settings 
        WHERE setting_key = ? AND user_id = ?
        LIMIT 1
    ", [$key, $user_id]);
    
    return !empty($result) ? $result[0] : null;
}

function getAllSettings($db, $user_id) {
    $results = $db->getThisQuery("
        SELECT * FROM system_settings 
        WHERE user_id = ?
        ORDER BY setting_key
    ", [$user_id]);
    
    $settings = [];
    foreach ($results as $row) {
        $settings[$row['setting_key']] = parseSettingValue($row);
    }
    
    return $settings;
}

function saveSetting($db, $user_id, $key, $value, $type = 'string', $description = null) {
    try {
        // Préparer la valeur selon le type
        $processedValue = processSettingValue($value, $type);
        
        // Insérer directement (avec ON DUPLICATE KEY UPDATE implicite)
        $insert_data = [
            'user_id' => $user_id,
            'setting_key' => $key,
            'setting_value' => $processedValue,
            'setting_type' => $type,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        return $db->insert('system_settings', $insert_data);
    } catch (Exception $e) {
        // Si insertion échoue (duplicate key), on met à jour
        try {
            $update_data = [
                'setting_key' => $key,
                'setting_value' => $processedValue,
                'setting_type' => $type,
                'description' => $description,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            return $db->update('system_settings', $update_data, [
                'setting_key' => $key,
                'user_id' => $user_id
            ]);
        } catch (Exception $updateError) {
            error_log("Error in saveSetting for key '{$key}': " . $updateError->getMessage());
            return false;
        }
    }
}

function deleteSetting($db, $user_id, $key) {
    try {
        return $db->delete('system_settings', [
            'setting_key' => $key,
            'user_id' => $user_id
        ]);
    } catch (Exception $e) {
        error_log("Error in deleteSetting: " . $e->getMessage());
        return false;
    }
}

function processSettingValue($value, $type) {
    switch ($type) {
        case 'json':
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        case 'boolean':
            return $value ? '1' : '0';
        case 'number':
            return strval($value);
        default:
            return strval($value);
    }
}

function parseSettingValue($row) {
    $value = $row['setting_value'];
    
    switch ($row['setting_type']) {
        case 'json':
            return json_decode($value, true);
        case 'boolean':
            return $value === '1' || $value === 'true';
        case 'number':
            return is_numeric($value) ? floatval($value) : $value;
        default:
            return $value;
    }
} ?>