<?php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

if (in_array($_POST['action'], ['assign_agent', 'reassign_agent'])) {
    $db = DB::getInstance();
    $user_id = getCurrentUserId($db);
    $order_id = $_POST['order_id'];
    $agent_id = $_POST['agent_id'];
    $notes = $_POST['notes'] ?? '';
    $priority = $_POST['priority'] ?? 'medium';
    
    try {
        // First, get the store_id from the orders table
        $order_data = $db->getThisQuery("
            SELECT store_id 
            FROM orders 
            WHERE id = ? AND user_id = ?
            LIMIT 1
        ", [$order_id, $user_id]);
        
        if (empty($order_data)) {
            echo json_encode([
                'success' => false,
                'message' => 'Commande non trouvée'
            ]);
            exit;
        }
        
        $store_id = $order_data[0]['store_id'];
        
        if ($_POST['action'] === 'assign_agent') {
            // Check if assignment already exists
            $existing = $db->getThisQuery("
                SELECT id FROM agent_order_assignments 
                WHERE order_id = ? AND user_id = ?
            ", [$order_id, $user_id]);
            
            if (!empty($existing)) {

                echo json_encode([
                    'success' => false,
                    'message' => 'Cette commande a déjà un agent assigné'
                ]);
                exit;
            }
            
            // Create new assignment with store_id
            $db->insert('agent_order_assignments', [
                'order_id' => $order_id,
                'user_id' => $user_id,
                'agent_id' => $agent_id,
                'store_id' => $store_id,
                'status' => 'pending',
                'priority_score' => $priority,
                'notes' => $notes,
                'assigned_at' => date('Y-m-d H:i:s')
            ]);
            
        } else { // reassign_agent
                // Update existing assignment (store_id remains the same)
                $sql = "UPDATE agent_order_assignments 
                SET agent_id = ?, priority_score = ?, notes = ?, assigned_at = ?
                WHERE order_id = ? AND user_id = ?";

                $db->update_this($sql, [$agent_id, $priority, $notes, date('Y-m-d H:i:s'), $order_id, $user_id]);
            
            }
        
        echo json_encode([
            'success' => true,
            'message' => $_POST['action'] === 'assign_agent' ? 'Agent assigné avec succès' : 'Agent réassigné avec succès'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function getCurrentUserId($db) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['username'])) {
        return null;
    }

    // This can be username, email, or phone
    $identifier = $_SESSION['user']['username'];

    // Prefer explicit ID if available
    if (!empty($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }

    // Try to match identifier against username, email, or phone
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
?>