<?php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

// Check if responsable is logged in via session
if(!Session::exists(Config::get('session/session_name'))) {
    Redirect::to('../../login'); 
}

// Get responsable info from session
$responsable_id = isset($_SESSION['responsable_id']) ? $_SESSION['responsable_id'] : null;
$responsable_email = isset($_SESSION['responsable_email']) ? $_SESSION['responsable_email'] : null;

// If no session data, try to get it from username in session
if (!$responsable_id && isset($_SESSION['user']['username'])) {
    $responsable_email = $_SESSION['user']['username'];
    $responsable = $db->getThisQuery("SELECT * FROM responsables WHERE email = ?", [$responsable_email]);
    
    if ($responsable && !empty($responsable)) {
        $responsable = $responsable[0];
        $responsable_id = $responsable['id'];
        
        // Store in session for future use
        $_SESSION['responsable_id'] = $responsable_id;
        $_SESSION['responsable_email'] = $responsable_email;
        $_SESSION['responsable_name'] = $responsable['name'];
    }
}

// If still no responsable ID, redirect to login
if (!$responsable_id) {
    session_destroy();
    Redirect::to('../../login');
}

// Get responsable details
$responsable = $db->getThisQuery("SELECT * FROM responsables WHERE id = ?", [$responsable_id]);
if (empty($responsable)) {
    session_destroy();
    Redirect::to('../../login');
}

$responsable = $responsable[0];

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_settings_data':
            echo getSettingsData($db, $responsable_id);
            exit;
        case 'update_profile':
            echo updateProfile($db, $responsable_id, $_POST);
            exit;
        case 'change_password':
            echo changePassword($db, $responsable_id, $_POST);
            exit;
        case 'create_team':
            echo createTeam($db, $responsable_id, $_POST);
            exit;
        case 'update_team':
            echo updateTeam($db, $_POST);
            exit;
        case 'delete_team':
            echo deleteTeam($db, $responsable_id, $_POST['team_id'] ?? 0);
            exit;
        case 'assign_agent_to_team':
            echo assignAgentToTeam($db, $_POST);
            exit;
        case 'update_agent_status':
            echo updateAgentStatus($db, $_POST);
            exit;
        case 'get_agent_details':
            echo getAgentDetails($db, $_POST['agent_id'] ?? 0);
            exit;
        case 'upload_profile_picture':
            echo uploadProfilePicture($db, $responsable_id);
            exit;
        case 'get_team_members':
            echo getTeamMembers($db, $_POST['team_id'] ?? 0);
            exit;
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    Redirect::to('../../login.php');
}

function getSettingsData($db, $responsable_id) {
    try {
        // Get responsable profile
        $responsable_query = "SELECT * FROM responsables WHERE id = ?";
        $responsable = $db->getThisQuery($responsable_query, [$responsable_id]);
        $responsable = !empty($responsable) ? $responsable[0] : [];
        
        // Get teams
        $teams_query = "
            SELECT 
                t.*,
                COUNT(DISTINCT a.id) as agent_count,
                COUNT(DISTINCT CASE WHEN a.is_active = 1 THEN a.id END) as active_agents,
                a2.name as leader_name
            FROM teams t
            LEFT JOIN agents a ON t.id = a.team_id
            LEFT JOIN agents a2 ON t.team_leader_id = a2.id
            WHERE t.responsable_id = ?
            GROUP BY t.id
            ORDER BY t.created_at DESC
        ";
        
        $teams = $db->getThisQuery($teams_query, [$responsable_id]);
        
        // Get all agents
        $agents_query = "
            SELECT 
                a.*,
                r.name as region_name,
                t.name as team_name,
                t.color as team_color,
                DATE_FORMAT(a.created_at, '%d/%m/%Y') as created_date
            FROM agents a
            LEFT JOIN regions r ON a.region_id = r.id
            LEFT JOIN teams t ON a.team_id = t.id
            WHERE a.responsable_id = ?
            ORDER BY a.is_active DESC, a.name ASC
        ";
        
        $agents = $db->getThisQuery($agents_query, [$responsable_id]);
        
        // Get regions for agent assignment
        $regions_query = "SELECT id, name FROM regions WHERE responsable_id = ? ORDER BY name ASC";
        $regions = $db->getThisQuery($regions_query, [$responsable_id]);
        
        // Get dashboard statistics
        $stats_query = "
            SELECT 
                COUNT(DISTINCT a.id) as total_agents,
                COUNT(DISTINCT CASE WHEN a.is_active = 1 THEN a.id END) as active_agents,
                COUNT(DISTINCT t.id) as total_teams,
                AVG(a.confirmation_rate) as avg_confirmation_rate
            FROM agents a
            LEFT JOIN teams t ON a.team_id = t.id
            WHERE a.responsable_id = ?
        ";
        
        $stats = $db->getThisQuery($stats_query, [$responsable_id]);
        $stats = !empty($stats) ? $stats[0] : [];
        
        return json_encode([
            'success' => true,
            'responsable' => $responsable,
            'teams' => $teams,
            'agents' => $agents,
            'regions' => $regions,
            'stats' => $stats
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function updateProfile($db, $responsable_id, $data) {
    try {
        $name = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $phone = $data['phone'] ?? '';
        
        if (empty($name) || empty($email)) {
            return json_encode([
                'success' => false,
                'message' => 'Le nom et l\'email sont requis'
            ]);
        }
        
        // Check if email is already taken by another responsable
        $check_email = "SELECT id FROM responsables WHERE email = ? AND id != ?";
        $existing = $db->getThisQuery($check_email, [$email, $responsable_id]);
        
        if (!empty($existing)) {
            return json_encode([
                'success' => false,
                'message' => 'Cet email est déjà utilisé par un autre responsable'
            ]);
        }
        
        // Update profile
        $query = "
            UPDATE responsables 
            SET name = ?, email = ?, phone = ?, updated_at = NOW()
            WHERE id = ?
        ";
        
        $db->getThisQuery($query, [$name, $email, $phone, $responsable_id]);
        
        // Update session data
        $_SESSION['responsable_name'] = $name;
        $_SESSION['responsable_email'] = $email;
        
        return json_encode([
            'success' => true,
            'message' => 'Profil mis à jour avec succès'
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function changePassword($db, $responsable_id, $data) {
    try {
        $current_password = $data['current_password'] ?? '';
        $new_password = $data['new_password'] ?? '';
        $confirm_password = $data['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            return json_encode([
                'success' => false,
                'message' => 'Tous les champs sont requis'
            ]);
        }
        
        if ($new_password !== $confirm_password) {
            return json_encode([
                'success' => false,
                'message' => 'Les nouveaux mots de passe ne correspondent pas'
            ]);
        }
        
        if (strlen($new_password) < 8) {
            return json_encode([
                'success' => false,
                'message' => 'Le mot de passe doit contenir au moins 8 caractères'
            ]);
        }
        
        // Get current password hash
        $get_password = "SELECT password FROM responsables WHERE id = ?";
        $result = $db->getThisQuery($get_password, [$responsable_id]);
        
        if (empty($result)) {
            return json_encode([
                'success' => false,
                'message' => 'Responsable non trouvé'
            ]);
        }
        
        $current_hash = $result[0]['password'];
        
        // Verify current password
        if (!password_verify($current_password, $current_hash)) {
            return json_encode([
                'success' => false,
                'message' => 'Mot de passe actuel incorrect'
            ]);
        }
        
        // Hash new password
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password
        $query = "UPDATE responsables SET password = ?, updated_at = NOW() WHERE id = ?";
        $db->getThisQuery($query, [$new_hash, $responsable_id]);
        
        return json_encode([
            'success' => true,
            'message' => 'Mot de passe changé avec succès'
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function createTeam($db, $responsable_id, $data) {
    try {
        $name = $data['name'] ?? '';
        $color = $data['color'] ?? '#667eea';
        
        if (empty($name)) {
            return json_encode([
                'success' => false,
                'message' => 'Le nom de l\'équipe est requis'
            ]);
        }
        
        // Check if team name already exists for this responsable
        $check_name = "SELECT id FROM teams WHERE name = ? AND responsable_id = ?";
        $existing = $db->getThisQuery($check_name, [$name, $responsable_id]);
        
        if (!empty($existing)) {
            return json_encode([
                'success' => false,
                'message' => 'Une équipe avec ce nom existe déjà'
            ]);
        }
        
        // Create team
        $query = "
            INSERT INTO teams 
            (responsable_id, name, color, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ";
        
        $db->getThisQuery($query, [
            $responsable_id,
            $name,
            $color
        ]);
        
        $team_id = $db->lastInsertId();
        
        return json_encode([
            'success' => true,
            'message' => 'Équipe créée avec succès',
            'team_id' => $team_id
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function updateTeam($db, $data) {
    try {
        $team_id = $data['team_id'] ?? 0;
        $name = $data['name'] ?? '';
        $color = $data['color'] ?? '';
        
        if (!$team_id) {
            return json_encode([
                'success' => false,
                'message' => 'ID d\'équipe requis'
            ]);
        }
        
        // Update team
        $query = "
            UPDATE teams 
            SET name = ?, color = ?, updated_at = NOW()
            WHERE id = ?
        ";
        
        $db->getThisQuery($query, [
            $name,
            $color,
            $team_id
        ]);
        
        return json_encode([
            'success' => true,
            'message' => 'Équipe mise à jour avec succès'
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function deleteTeam($db, $responsable_id, $team_id) {
    try {
        if (!$team_id) {
            return json_encode([
                'success' => false,
                'message' => 'ID d\'équipe requis'
            ]);
        }
        
        // Check if team has agents
        $check_agents = "SELECT COUNT(*) as agent_count FROM agents WHERE team_id = ?";
        $result = $db->getThisQuery($check_agents, [$team_id]);
        
        if (!empty($result) && $result[0]['agent_count'] > 0) {
            return json_encode([
                'success' => false,
                'message' => 'Impossible de supprimer une équipe qui contient des agents'
            ]);
        }
        
        // Delete team
        $query = "DELETE FROM teams WHERE id = ? AND responsable_id = ?";
        $db->getThisQuery($query, [$team_id, $responsable_id]);
        
        return json_encode([
            'success' => true,
            'message' => 'Équipe supprimée avec succès'
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function assignAgentToTeam($db, $data) {
    try {
        $agent_id = $data['agent_id'] ?? 0;
        $team_id = $data['team_id'] ?? null;
        
        if (!$agent_id) {
            return json_encode([
                'success' => false,
                'message' => 'ID d\'agent requis'
            ]);
        }
        
        // If team_id is null, remove from team
        if ($team_id === null || $team_id === '') {
            $query = "UPDATE agents SET team_id = NULL, updated_at = NOW() WHERE id = ?";
            $db->getThisQuery($query, [$agent_id]);
            
            return json_encode([
                'success' => true,
                'message' => 'Agent retiré de l\'équipe'
            ]);
        }
        
        // Update agent team assignment
        $query = "UPDATE agents SET team_id = ?, updated_at = NOW() WHERE id = ?";
        $db->getThisQuery($query, [$team_id, $agent_id]);
        
        return json_encode([
            'success' => true,
            'message' => 'Agent assigné à l\'équipe avec succès'
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function updateAgentStatus($db, $data) {
    try {
        $agent_id = $data['agent_id'] ?? 0;
        $is_active = isset($data['is_active']) ? ($data['is_active'] == 1 ? 1 : 0) : null;
        
        if (!$agent_id || $is_active === null) {
            return json_encode([
                'success' => false,
                'message' => 'Données invalides'
            ]);
        }
        
        $query = "UPDATE agents SET is_active = ?, updated_at = NOW() WHERE id = ?";
        $db->getThisQuery($query, [$is_active, $agent_id]);
        
        $status_text = $is_active == 1 ? 'activé' : 'désactivé';
        
        return json_encode([
            'success' => true,
            'message' => 'Agent ' . $status_text . ' avec succès'
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function getAgentDetails($db, $agent_id) {
    try {
        if (!$agent_id) {
            return json_encode([
                'success' => false,
                'message' => 'ID d\'agent requis'
            ]);
        }
        
        $query = "
            SELECT 
                a.*,
                r.name as region_name,
                t.name as team_name,
                t.color as team_color
            FROM agents a
            LEFT JOIN regions r ON a.region_id = r.id
            LEFT JOIN teams t ON a.team_id = t.id
            WHERE a.id = ?
        ";
        
        $agent = $db->getThisQuery($query, [$agent_id]);
        
        if (empty($agent)) {
            return json_encode([
                'success' => false,
                'message' => 'Agent non trouvé'
            ]);
        }
        
        return json_encode([
            'success' => true,
            'agent' => $agent[0]
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function uploadProfilePicture($db, $responsable_id) {
    try {
        if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            return json_encode([
                'success' => false,
                'message' => 'Erreur lors du téléchargement du fichier'
            ]);
        }
        
        $file = $_FILES['profile_picture'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowed_types)) {
            return json_encode([
                'success' => false,
                'message' => 'Type de fichier non autorisé. Seuls JPG, PNG et GIF sont acceptés'
            ]);
        }
        
        if ($file['size'] > $max_size) {
            return json_encode([
                'success' => false,
                'message' => 'Fichier trop volumineux. Maximum 5MB'
            ]);
        }
        
        // Create upload directory if it doesn't exist
        $upload_dir = '../uploads/profiles/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'profile_' . $responsable_id . '_' . time() . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return json_encode([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement du fichier'
            ]);
        }
        
        // Update database
        $query = "UPDATE responsables SET profile_picture = ?, updated_at = NOW() WHERE id = ?";
        $db->getThisQuery($query, [$filename, $responsable_id]);
        
        return json_encode([
            'success' => true,
            'message' => 'Photo de profil mise à jour avec succès',
            'filename' => $filename,
            'url' => str_replace('../', '', $filepath)
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function getTeamMembers($db, $team_id) {
    try {
        if (!$team_id) {
            return json_encode([
                'success' => false,
                'message' => 'ID d\'équipe requis'
            ]);
        }
        
        $query = "
            SELECT 
                a.*,
                r.name as region_name
            FROM agents a
            LEFT JOIN regions r ON a.region_id = r.id
            WHERE a.team_id = ?
            ORDER BY a.is_active DESC, a.name ASC
        ";
        
        $members = $db->getThisQuery($query, [$team_id]);
        
        return json_encode([
            'success' => true,
            'members' => $members
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres | Dashboard Responsable</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS Framework -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --secondary: #10b981;
            --secondary-dark: #059669;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --border-radius: 12px;
            --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--light);
            color: var(--dark);
            line-height: 1.6;
        }

        /* Layout */
        .app-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, var(--dark) 0%, #0f172a 100%);
            color: white;
            display: flex;
            flex-direction: column;
            transition: var(--transition);
            box-shadow: 2px 0 20px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 22px;
            font-weight: 700;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .user-profile {
            padding: 24px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 50%;
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 600;
            color: white;
            border: 4px solid rgba(255, 255, 255, 0.2);
            background-size: cover;
            background-position: center;
        }

        .user-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .user-role {
            font-size: 14px;
            color: var(--gray-400);
        }

        .nav-menu {
            flex: 1;
            padding: 20px 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 24px;
            color: var(--gray-400);
            text-decoration: none;
            transition: var(--transition);
            border-left: 4px solid transparent;
        }

        .nav-item:hover {
            color: white;
            background: rgba(255, 255, 255, 0.05);
        }

        .nav-item.active {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            border-left-color: var(--primary);
        }

        .nav-icon {
            width: 20px;
            text-align: center;
            font-size: 18px;
        }

        .sidebar-footer {
            padding: 20px 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            overflow-y: auto;
            background: var(--light);
        }

        /* Header */
        .main-header {
            background: white;
            padding: 20px 30px;
            border-bottom: 1px solid var(--gray-200);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .header-title h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header-subtitle {
            color: var(--gray-500);
            font-size: 14px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 30px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 24px;
            box-shadow: var(--box-shadow);
            border: 1px solid var(--gray-200);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            font-size: 24px;
            color: white;
        }

        .stat-icon.primary { background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%); }
        .stat-icon.success { background: linear-gradient(135deg, #34d399 0%, var(--secondary) 100%); }
        .stat-icon.warning { background: linear-gradient(135deg, #fbbf24 0%, var(--warning) 100%); }
        .stat-icon.danger { background: linear-gradient(135deg, #f87171 0%, var(--danger) 100%); }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--gray-500);
            font-size: 14px;
            font-weight: 500;
        }

        /* Settings Tabs */
        .settings-tabs {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin: 0 30px 30px;
            overflow: hidden;
        }

        .tabs-header {
            display: flex;
            border-bottom: 1px solid var(--gray-200);
            background: var(--gray-100);
        }

        .tab-btn {
            padding: 20px 30px;
            background: none;
            border: none;
            font-size: 16px;
            font-weight: 500;
            color: var(--gray-500);
            cursor: pointer;
            transition: var(--transition);
            border-bottom: 3px solid transparent;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .tab-btn:hover {
            color: var(--primary);
            background: white;
        }

        .tab-btn.active {
            color: var(--primary);
            background: white;
            border-bottom-color: var(--primary);
        }

        .tab-content {
            padding: 40px;
        }

        .tab-pane {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-pane.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Form Styles */
        .form-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--primary);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 8px;
            display: block;
        }

        .form-control, .form-select {
            padding: 12px 16px;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
            background: white;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        /* Profile Picture */
        .profile-picture-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-picture-wrapper {
            position: relative;
            display: inline-block;
        }

        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 60px;
            font-weight: 600;
            border: 4px solid white;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
            background-size: cover;
            background-position: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .profile-picture:hover {
            transform: scale(1.05);
        }

        .profile-picture-upload {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: var(--primary);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            border: 3px solid white;
        }

        .profile-picture-upload:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
        }

        .profile-picture-input {
            display: none;
        }

        /* Teams Grid */
        .teams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .team-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            border: 2px solid transparent;
            transition: var(--transition);
            position: relative;
        }

        .team-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }

        .team-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .team-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            flex-shrink: 0;
        }

        .team-info {
            flex: 1;
        }

        .team-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .team-stats {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--gray-200);
        }

        .team-stat {
            text-align: center;
            flex: 1;
        }

        .team-stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
            display: block;
        }

        .team-stat-label {
            font-size: 12px;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .team-actions {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 8px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .team-card:hover .team-actions {
            opacity: 1;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            background: var(--gray-100);
            color: var(--gray-500);
        }

        .btn-icon:hover {
            background: var(--primary);
            color: white;
        }

        /* Create Team Form */
        .create-team-form {
            background: var(--gray-100);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-top: 30px;
        }

        .color-picker {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .color-option {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            border: 3px solid transparent;
        }

        .color-option:hover {
            transform: scale(1.1);
        }

        .color-option.selected {
            border-color: var(--dark);
            transform: scale(1.1);
        }

        /* Agents Table */
        .agents-table-container {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }

        .agents-table {
            width: 100%;
            border-collapse: collapse;
        }

        .agents-table th {
            padding: 16px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: var(--gray-500);
            border-bottom: 2px solid var(--gray-200);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: var(--gray-100);
        }

        .agents-table td {
            padding: 20px 12px;
            border-bottom: 1px solid var(--gray-200);
            transition: background-color 0.2s ease;
        }

        .agents-table tbody tr:hover td {
            background-color: var(--gray-100);
        }

        .agent-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .agent-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
            flex-shrink: 0;
        }

        .agent-info h5 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
            color: var(--dark);
        }

        .agent-info p {
            font-size: 12px;
            color: var(--gray-500);
            margin: 0;
        }

        .team-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: var(--gray-100);
            color: var(--gray-600);
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active {
            background: #d1fae5;
            color: var(--secondary);
        }

        .status-inactive {
            background: #fee2e2;
            color: var(--danger);
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--secondary) 0%, var(--secondary-dark) 100%);
            color: white;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--gray-300);
            color: var(--gray-700);
        }

        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Modal */
        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            padding: 25px 30px;
            border: none;
        }

        .modal-title {
            font-weight: 600;
            font-size: 20px;
        }

        /* Loading */
        .loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 3px solid var(--gray-200);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Toast */
        .toast-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 9999;
        }

        .toast {
            background: white;
            border-radius: var(--border-radius);
            padding: 16px 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 10px;
            animation: slideIn 0.3s ease;
            border-left: 4px solid var(--primary);
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .app-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                padding: 20px;
            }

            .settings-tabs {
                margin: 20px;
            }

            .tabs-header {
                flex-direction: column;
            }

            .tab-btn {
                justify-content: center;
            }

            .teams-grid {
                grid-template-columns: 1fr;
            }

            .agents-table {
                display: block;
                overflow-x: auto;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--gray-400);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <span>TeamManager</span>
                </div>
            </div>

            <div class="user-profile">
                <div class="user-avatar" id="user-avatar">
                    <?php echo strtoupper(substr($responsable['name'], 0, 1)); ?>
                </div>
                <div class="user-name"><?php echo htmlspecialchars($responsable['name']); ?></div>
                <div class="user-role">Responsable d'Agents</div>
            </div>

            <nav class="nav-menu">
                <a href="./home" class="nav-item">
                    <i class="nav-icon fas fa-users"></i>
                    <span>Mes Agents</span>
                </a>
                <a href="./performance" class="nav-item">
                    <i class="nav-icon fas fa-chart-line"></i>
                    <span>Performance</span>
                </a>
                <a href="./planning" class="nav-item">
                    <i class="nav-icon fas fa-calendar-alt"></i>
                    <span>Planning</span>
                </a>
                <a href="./settings" class="nav-item active">
                    <i class="nav-icon fas fa-cog"></i>
                    <span>Paramètres</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="?logout" class="nav-item">
                    <i class="nav-icon fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="main-header">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="header-title">
                                <h1>Paramètres</h1>
                                <div class="header-subtitle">
                                    <i class="fas fa-sliders-h me-2"></i>
                                    Gérez votre profil, équipes et agents
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="header-actions text-end">
                                <button class="btn btn-primary" onclick="loadSettingsData()">
                                    <i class="fas fa-sync-alt me-2"></i>Actualiser
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Stats Overview -->
            <div class="stats-grid" id="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value" id="total-agents">0</div>
                    <div class="stat-label">Agents Totaux</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-value" id="active-agents">0</div>
                    <div class="stat-label">Agents Actifs</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="stat-value" id="total-teams">0</div>
                    <div class="stat-label">Équipes</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value" id="avg-confirmation">0%</div>
                    <div class="stat-label">Taux Moyen</div>
                </div>
            </div>

            <!-- Settings Tabs -->
            <div class="settings-tabs">
                <div class="tabs-header">
                    <button class="tab-btn active" onclick="showTab('profile')">
                        <i class="fas fa-user"></i>
                        Profil
                    </button>
                    <button class="tab-btn" onclick="showTab('teams')">
                        <i class="fas fa-users"></i>
                        Équipes
                    </button>
                    <button class="tab-btn" onclick="showTab('agents')">
                        <i class="fas fa-user-friends"></i>
                        Gestion Agents
                    </button>
                </div>

                <div class="tab-content">
                    <!-- Profile Tab -->
                    <div class="tab-pane active" id="profile-tab">
                        <div class="profile-picture-container">
                            <div class="profile-picture-wrapper">
                                <div class="profile-picture" id="profile-picture" onclick="document.getElementById('profile-picture-input').click()">
                                    <?php echo strtoupper(substr($responsable['name'], 0, 1)); ?>
                                </div>
                                <div class="profile-picture-upload" onclick="document.getElementById('profile-picture-input').click()">
                                    <i class="fas fa-camera"></i>
                                </div>
                                <input type="file" id="profile-picture-input" class="profile-picture-input" 
                                       accept="image/*" onchange="uploadProfilePicture(this.files[0])">
                            </div>
                        </div>

                        <div class="form-card">
                            <div class="section-title">
                                <i class="fas fa-user-circle"></i>
                                Informations Personnelles
                            </div>
                            
                            <form id="profile-form">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Nom complet</label>
                                            <input type="text" class="form-control" id="profile-name" 
                                                   value="<?php echo htmlspecialchars($responsable['name']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Adresse email</label>
                                            <input type="email" class="form-control" id="profile-email" 
                                                   value="<?php echo htmlspecialchars($responsable['email']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Téléphone</label>
                                            <input type="tel" class="form-control" id="profile-phone" 
                                                   value="<?php echo htmlspecialchars($responsable['phone'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-end">
                                    <button type="button" class="btn btn-primary" onclick="updateProfile()">
                                        <i class="fas fa-save me-2"></i>Enregistrer les modifications
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="form-card">
                            <div class="section-title">
                                <i class="fas fa-key"></i>
                                Sécurité
                            </div>
                            
                            <form id="password-form">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Mot de passe actuel</label>
                                            <input type="password" class="form-control" id="current-password">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Nouveau mot de passe</label>
                                            <input type="password" class="form-control" id="new-password">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Confirmer le mot de passe</label>
                                            <input type="password" class="form-control" id="confirm-password">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-end">
                                    <button type="button" class="btn btn-primary" onclick="changePassword()">
                                        <i class="fas fa-key me-2"></i>Changer le mot de passe
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Teams Tab -->
                    <div class="tab-pane" id="teams-tab">
                        <div class="form-card">
                            <div class="section-title">
                                <i class="fas fa-plus-circle"></i>
                                Créer une nouvelle équipe
                            </div>
                            
                            <form id="team-form" class="create-team-form">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Nom de l'équipe</label>
                                            <input type="text" class="form-control" id="team-name" 
                                                   placeholder="Ex: Équipe Nord">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">Couleur de l'équipe</label>
                                            <div class="color-picker">
                                                <div class="color-option selected" style="background: #667eea;" 
                                                     onclick="selectColor('#667eea', this)"></div>
                                                <div class="color-option" style="background: #10b981;" 
                                                     onclick="selectColor('#10b981', this)"></div>
                                                <div class="color-option" style="background: #f59e0b;" 
                                                     onclick="selectColor('#f59e0b', this)"></div>
                                                <div class="color-option" style="background: #ef4444;" 
                                                     onclick="selectColor('#ef4444', this)"></div>
                                                <div class="color-option" style="background: #8b5cf6;" 
                                                     onclick="selectColor('#8b5cf6', this)"></div>
                                                <div class="color-option" style="background: #06b6d4;" 
                                                     onclick="selectColor('#06b6d4', this)"></div>
                                            </div>
                                            <input type="hidden" id="team-color" value="#667eea">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-end">
                                    <button type="button" class="btn btn-primary" onclick="createTeam()">
                                        <i class="fas fa-plus me-2"></i>Créer l'équipe
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="section-title">
                            <i class="fas fa-layer-group"></i>
                            Vos Équipes
                        </div>
                        
                        <div id="teams-list">
                            <div class="loading">
                                <div class="spinner"></div>
                                <p>Chargement des équipes...</p>
                            </div>
                        </div>
                    </div>

                    <!-- Agents Management Tab -->
                    <div class="tab-pane" id="agents-tab">
                        <div class="form-card">
                            <div class="section-title">
                                <i class="fas fa-user-friends"></i>
                                Gestion des Agents
                            </div>
                            <p class="text-muted mb-4">Gérez les agents, modifiez leur statut et assignez-les aux équipes.</p>
                        </div>

                        <div id="agents-management">
                            <div class="loading">
                                <div class="spinner"></div>
                                <p>Chargement des agents...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Team Modal -->
    <div class="modal fade" id="editTeamModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier l'équipe</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="edit-team-form">
                        <input type="hidden" id="edit-team-id">
                        <div class="form-group mb-3">
                            <label class="form-label">Nom de l'équipe</label>
                            <input type="text" class="form-control" id="edit-team-name">
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label">Couleur</label>
                            <div class="color-picker">
                                <div class="color-option" style="background: #667eea;" onclick="selectEditColor('#667eea', this)"></div>
                                <div class="color-option" style="background: #10b981;" onclick="selectEditColor('#10b981', this)"></div>
                                <div class="color-option" style="background: #f59e0b;" onclick="selectEditColor('#f59e0b', this)"></div>
                                <div class="color-option" style="background: #ef4444;" onclick="selectEditColor('#ef4444', this)"></div>
                            </div>
                            <input type="hidden" id="edit-team-color" value="#667eea">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" onclick="updateTeam()">Enregistrer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Team Members Modal -->
    <div class="modal fade" id="teamMembersModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="team-members-title">Membres de l'équipe</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="team-members-content">
                        <!-- Content loaded by JavaScript -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Agent Modal -->
    <div class="modal fade" id="editAgentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Gérer l'agent</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="edit-agent-content">
                        <!-- Content loaded by JavaScript -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <button type="button" class="btn btn-primary" onclick="saveAgentChanges()">Enregistrer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        let currentSettingsData = null;
        let currentAgentId = null;
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadSettingsData();
            initSelect2();
        });
        
        function showTab(tabId) {
            // Hide all tabs
            document.querySelectorAll('.tab-pane').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabId + '-tab').classList.add('active');
            
            // Activate tab button
            event.target.classList.add('active');
            
            // Load tab-specific data if needed
            switch(tabId) {
                case 'teams':
                    loadTeams();
                    break;
                case 'agents':
                    loadAgentsManagement();
                    break;
            }
        }
        
        function loadSettingsData() {
            const statsGrid = document.getElementById('stats-grid');
            statsGrid.innerHTML = `
                <div class="stat-card">
                    <div class="loading">
                        <div class="spinner"></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="loading">
                        <div class="spinner"></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="loading">
                        <div class="spinner"></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="loading">
                        <div class="spinner"></div>
                    </div>
                </div>
            `;
            
            const formData = new FormData();
            formData.append('action', 'get_settings_data');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentSettingsData = data;
                    updateStats(data.stats);
                    updateProfileSection(data);
                    loadTeams(data);
                    loadAgentsManagement(data);
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur de connexion', 'danger');
            });
        }
        
        function updateStats(stats) {
            document.getElementById('total-agents').textContent = stats.total_agents || 0;
            document.getElementById('active-agents').textContent = stats.active_agents || 0;
            document.getElementById('total-teams').textContent = stats.total_teams || 0;
            document.getElementById('avg-confirmation').textContent = 
                (stats.avg_confirmation_rate ? Math.round(stats.avg_confirmation_rate) : 0) + '%';
        }
        
        function updateProfileSection(data) {
            const profilePicture = document.getElementById('profile-picture');
            const userAvatar = document.getElementById('user-avatar');
            
            if (data.responsable.profile_picture) {
                const url = `../uploads/profiles/${data.responsable.profile_picture}`;
                profilePicture.style.backgroundImage = `url('${url}')`;
                userAvatar.style.backgroundImage = `url('${url}')`;
                profilePicture.textContent = '';
                userAvatar.textContent = '';
            } else {
                const initial = data.responsable.name ? data.responsable.name.charAt(0).toUpperCase() : 'R';
                profilePicture.textContent = initial;
                userAvatar.textContent = initial;
                profilePicture.style.backgroundImage = 'none';
                userAvatar.style.backgroundImage = 'none';
            }
        }
        
        function uploadProfilePicture(file) {
            if (!file) return;
            
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                showToast('Type de fichier non autorisé. Seuls JPG, PNG et GIF sont acceptés', 'danger');
                return;
            }
            
            // Validate file size (5MB)
            if (file.size > 5 * 1024 * 1024) {
                showToast('Fichier trop volumineux (max 5MB)', 'danger');
                return;
            }
            
            const formData = new FormData();
            formData.append('profile_picture', file);
            formData.append('action', 'upload_profile_picture');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    // Update profile picture display
                    const profilePicture = document.getElementById('profile-picture');
                    const userAvatar = document.getElementById('user-avatar');
                    profilePicture.style.backgroundImage = `url('${data.url}')`;
                    userAvatar.style.backgroundImage = `url('${data.url}')`;
                    profilePicture.textContent = '';
                    userAvatar.textContent = '';
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur de connexion', 'danger');
            });
        }
        
        function updateProfile() {
            const name = document.getElementById('profile-name').value;
            const email = document.getElementById('profile-email').value;
            const phone = document.getElementById('profile-phone').value;
            
            if (!name || !email) {
                showToast('Le nom et l\'email sont requis', 'warning');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'update_profile');
            formData.append('name', name);
            formData.append('email', email);
            formData.append('phone', phone);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    // Update avatar initial if no profile picture
                    const profilePicture = document.getElementById('profile-picture');
                    const userAvatar = document.getElementById('user-avatar');
                    if (!profilePicture.style.backgroundImage || profilePicture.style.backgroundImage === 'none') {
                        const initial = name.charAt(0).toUpperCase();
                        profilePicture.textContent = initial;
                        userAvatar.textContent = initial;
                    }
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur de connexion', 'danger');
            });
        }
        
        function changePassword() {
            const currentPassword = document.getElementById('current-password').value;
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            
            if (!currentPassword || !newPassword || !confirmPassword) {
                showToast('Veuillez remplir tous les champs', 'warning');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                showToast('Les mots de passe ne correspondent pas', 'danger');
                return;
            }
            
            if (newPassword.length < 8) {
                showToast('Le mot de passe doit contenir au moins 8 caractères', 'warning');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'change_password');
            formData.append('current_password', currentPassword);
            formData.append('new_password', newPassword);
            formData.append('confirm_password', confirmPassword);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    // Clear password fields
                    document.getElementById('current-password').value = '';
                    document.getElementById('new-password').value = '';
                    document.getElementById('confirm-password').value = '';
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur de connexion', 'danger');
            });
        }
        
        function initSelect2() {
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });
        }
        
        function selectColor(color, element) {
            document.querySelectorAll('#team-form .color-picker .color-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            element.classList.add('selected');
            document.getElementById('team-color').value = color;
        }
        
        function selectEditColor(color, element) {
            document.querySelectorAll('#edit-team-form .color-picker .color-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            element.classList.add('selected');
            document.getElementById('edit-team-color').value = color;
        }
        
        function createTeam() {
            const name = document.getElementById('team-name').value;
            const color = document.getElementById('team-color').value;
            
            if (!name) {
                showToast('Veuillez entrer un nom pour l\'équipe', 'warning');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'create_team');
            formData.append('name', name);
            formData.append('color', color);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    // Clear form
                    document.getElementById('team-name').value = '';
                    // Reload teams
                    loadTeams();
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur de connexion', 'danger');
            });
        }
        
        function loadTeams(data = null) {
            const teamsList = document.getElementById('teams-list');
            
            if (!data) {
                teamsList.innerHTML = `
                    <div class="loading">
                        <div class="spinner"></div>
                        <p>Chargement des équipes...</p>
                    </div>
                `;
                
                const formData = new FormData();
                formData.append('action', 'get_settings_data');
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderTeams(data.teams);
                    }
                })
                .catch(console.error);
            } else {
                renderTeams(data.teams);
            }
        }
        
        function renderTeams(teams) {
            const teamsList = document.getElementById('teams-list');
            
            if (!teams || teams.length === 0) {
                teamsList.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-users fa-2x mb-3"></i>
                        <p>Aucune équipe créée</p>
                        <p class="small">Commencez par créer votre première équipe</p>
                    </div>
                `;
                return;
            }
            
            teamsList.innerHTML = `
                <div class="teams-grid">
                    ${teams.map(team => `
                        <div class="team-card">
                            <div class="team-header">
                                <div class="team-icon" style="background: ${team.color || '#667eea'}">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="team-info">
                                    <div class="team-name">${escapeHtml(team.name)}</div>
                                    ${team.leader_name ? `
                                        <div class="text-muted small">
                                            <i class="fas fa-crown"></i> Chef: ${escapeHtml(team.leader_name)}
                                        </div>
                                    ` : ''}
                                </div>
                                <div class="team-actions">
                                    <button class="btn-icon" onclick="viewTeamMembers(${team.id})" title="Voir les membres">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon" onclick="editTeam(${team.id})" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon" onclick="deleteTeam(${team.id})" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="team-stats">
                                <div class="team-stat">
                                    <span class="team-stat-value">${team.agent_count || 0}</span>
                                    <span class="team-stat-label">Agents</span>
                                </div>
                                <div class="team-stat">
                                    <span class="team-stat-value">${team.active_agents || 0}</span>
                                    <span class="team-stat-label">Actifs</span>
                                </div>
                                <div class="team-stat">
                                    <span class="team-stat-value">${team.agent_count ? Math.round((team.active_agents / team.agent_count) * 100) : 0}%</span>
                                    <span class="team-stat-label">Taux</span>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        }
        
        function editTeam(teamId) {
            if (!currentSettingsData) return;
            
            const team = currentSettingsData.teams.find(t => t.id == teamId);
            if (!team) return;
            
            document.getElementById('edit-team-id').value = team.id;
            document.getElementById('edit-team-name').value = team.name;
            document.getElementById('edit-team-color').value = team.color || '#667eea';
            
            // Select color
            document.querySelectorAll('#edit-team-form .color-picker .color-option').forEach(opt => {
                opt.classList.remove('selected');
                if (opt.style.background === team.color || 
                    (!team.color && opt.style.background === 'rgb(102, 126, 234)')) {
                    opt.classList.add('selected');
                }
            });
            
            const modal = new bootstrap.Modal(document.getElementById('editTeamModal'));
            modal.show();
        }
        
        function updateTeam() {
            const teamId = document.getElementById('edit-team-id').value;
            const name = document.getElementById('edit-team-name').value;
            const color = document.getElementById('edit-team-color').value;
            
            if (!teamId || !name) {
                showToast('Données invalides', 'warning');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'update_team');
            formData.append('team_id', teamId);
            formData.append('name', name);
            formData.append('color', color);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('editTeamModal')).hide();
                    loadSettingsData();
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur de connexion', 'danger');
            });
        }
        
        function deleteTeam(teamId) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer cette équipe ?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_team');
            formData.append('team_id', teamId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    loadSettingsData();
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur de connexion', 'danger');
            });
        }
        
        function viewTeamMembers(teamId) {
            if (!currentSettingsData) return;
            
            const team = currentSettingsData.teams.find(t => t.id == teamId);
            if (!team) return;
            
            const formData = new FormData();
            formData.append('action', 'get_team_members');
            formData.append('team_id', teamId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showTeamMembersModal(team, data.members);
                }
            })
            .catch(console.error);
        }
        
        function showTeamMembersModal(team, members) {
            const modal = new bootstrap.Modal(document.getElementById('teamMembersModal'));
            const content = document.getElementById('team-members-content');
            const title = document.getElementById('team-members-title');
            
            title.textContent = `Membres de l'équipe ${escapeHtml(team.name)}`;
            
            if (!members || members.length === 0) {
                content.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-users fa-2x mb-3"></i>
                        <p>Aucun agent dans cette équipe</p>
                    </div>
                `;
            } else {
                content.innerHTML = `
                    <div class="agents-table-container">
                        <table class="agents-table">
                            <thead>
                                <tr>
                                    <th>Agent</th>
                                    <th>Email</th>
                                    <th>Téléphone</th>
                                    <th>Région</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${members.map(member => {
                                    const initials = member.name ? member.name.charAt(0).toUpperCase() : 'A';
                                    const isActive = member.is_active == 1;
                                    
                                    return `
                                        <tr>
                                            <td>
                                                <div class="agent-cell">
                                                    <div class="agent-avatar" style="background: ${team.color || '#667eea'}">
                                                        ${initials}
                                                    </div>
                                                    <div class="agent-info">
                                                        <h5>${escapeHtml(member.name)}</h5>
                                                        <p>Inscrit le: ${member.created_date || 'N/A'}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>${escapeHtml(member.email || '')}</td>
                                            <td>${escapeHtml(member.phone || '')}</td>
                                            <td>${escapeHtml(member.region_name || 'Non spécifié')}</td>
                                            <td>
                                                ${isActive ? 
                                                    '<span class="status-badge status-active">Actif</span>' : 
                                                    '<span class="status-badge status-inactive">Inactif</span>'}
                                            </td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            }
            
            modal.show();
        }
        
        function loadAgentsManagement(data = null) {
            const agentsManagement = document.getElementById('agents-management');
            
            if (!data) {
                agentsManagement.innerHTML = `
                    <div class="loading">
                        <div class="spinner"></div>
                        <p>Chargement des agents...</p>
                    </div>
                `;
                
                const formData = new FormData();
                formData.append('action', 'get_settings_data');
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderAgentsManagement(data.agents, data.teams);
                    }
                })
                .catch(console.error);
            } else {
                renderAgentsManagement(data.agents, data.teams);
            }
        }
        
        function renderAgentsManagement(agents, teams) {
            const agentsManagement = document.getElementById('agents-management');
            
            if (!agents || agents.length === 0) {
                agentsManagement.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-user-friends fa-2x mb-3"></i>
                        <p>Aucun agent disponible</p>
                    </div>
                `;
                return;
            }
            
            agentsManagement.innerHTML = `
                <div class="agents-table-container">
                    <table class="agents-table">
                        <thead>
                            <tr>
                                <th>Agent</th>
                                <th>Équipe</th>
                                <th>Région</th>
                                <th>Taux</th>
                                <th>Score</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${agents.map(agent => {
                                const initials = agent.name ? agent.name.charAt(0).toUpperCase() : 'A';
                                const teamColor = agent.team_color || '#667eea';
                                const isActive = agent.is_active == 1;
                                
                                return `
                                    <tr>
                                        <td>
                                            <div class="agent-cell">
                                                <div class="agent-avatar" style="background: ${teamColor}">
                                                    ${initials}
                                                </div>
                                                <div class="agent-info">
                                                    <h5>${escapeHtml(agent.name)}</h5>
                                                    <p>${escapeHtml(agent.email)}</p>
                                                    <p class="small">Inscrit le: ${agent.created_date || 'N/A'}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            ${agent.team_name ? `
                                                <div class="team-badge">
                                                    <i class="fas fa-users"></i>
                                                    ${escapeHtml(agent.team_name)}
                                                </div>
                                            ` : '<span class="text-muted">Aucune équipe</span>'}
                                        </td>
                                        <td>${escapeHtml(agent.region_name || 'Non spécifié')}</td>
                                        <td>${agent.confirmation_rate || 0}%</td>
                                        <td>${agent.score || 0}</td>
                                        <td>
                                            ${isActive ? 
                                                '<span class="status-badge status-active">Actif</span>' : 
                                                '<span class="status-badge status-inactive">Inactif</span>'}
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-outline-primary" onclick="editAgent(${agent.id})">
                                                    <i class="fas fa-edit"></i> Gérer
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }
        
        function editAgent(agentId) {
            currentAgentId = agentId;
            
            const formData = new FormData();
            formData.append('action', 'get_agent_details');
            formData.append('agent_id', agentId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showEditAgentModal(data.agent);
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(console.error);
        }
        
        function showEditAgentModal(agent) {
            const modal = new bootstrap.Modal(document.getElementById('editAgentModal'));
            const content = document.getElementById('edit-agent-content');
            
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-4">
                        <div class="text-center mb-4">
                            <div class="agent-avatar-large mb-3" style="background: ${agent.team_color || '#667eea'}; width: 100px; height: 100px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 48px; font-weight: 600; margin: 0 auto;">
                                ${agent.name ? agent.name.charAt(0).toUpperCase() : 'A'}
                            </div>
                            <h4>${escapeHtml(agent.name)}</h4>
                            <p class="text-muted">${escapeHtml(agent.email)}</p>
                            <div class="mt-3">
                                <span class="status-badge ${agent.is_active == 1 ? 'status-active' : 'status-inactive'}">
                                    ${agent.is_active == 1 ? 'Actif' : 'Inactif'}
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <button class="btn ${agent.is_active == 1 ? 'btn-danger' : 'btn-success'} w-100" 
                                    onclick="toggleAgentStatus(${agent.id}, ${agent.is_active})">
                                <i class="fas fa-power-off me-2"></i>
                                ${agent.is_active == 1 ? 'Désactiver l\'agent' : 'Activer l\'agent'}
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <form id="agent-settings-form">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="agent-phone" 
                                           value="${agent.phone || ''}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Commission (%)</label>
                                    <input type="number" class="form-control" id="agent-service-fee" 
                                           value="${agent.service_fee || ''}" min="0" max="100" step="0.1">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Assignations max/jour</label>
                                    <input type="number" class="form-control" id="agent-max-assignments" 
                                           value="${agent.max_assignments || ''}" min="1" max="50">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Région</label>
                                    <select class="form-select" id="agent-region">
                                        <option value="0">Non spécifié</option>
                                        ${currentSettingsData.regions.map(region => `
                                            <option value="${region.id}" ${agent.region_id == region.id ? 'selected' : ''}>
                                                ${escapeHtml(region.name)}
                                            </option>
                                        `).join('')}
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Équipe</label>
                                    <select class="form-select select2" id="agent-team">
                                        <option value="">Aucune équipe</option>
                                        ${currentSettingsData.teams.map(team => `
                                            <option value="${team.id}" ${agent.team_id == team.id ? 'selected' : ''}>
                                                ${escapeHtml(team.name)}
                                            </option>
                                        `).join('')}
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Heures de travail</label>
                                    <input type="text" class="form-control" id="agent-working-hours" 
                                           value="${agent.working_hours || ''}" placeholder="Ex: 9h-12h, 14h-18h">
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            // Reinitialize Select2
            $('#agent-team').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });
            
            modal.show();
        }
        
        function toggleAgentStatus(agentId, currentStatus) {
            const newStatus = currentStatus == 1 ? 0 : 1;
            
            const formData = new FormData();
            formData.append('action', 'update_agent_status');
            formData.append('agent_id', agentId);
            formData.append('is_active', newStatus);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('editAgentModal')).hide();
                    loadSettingsData();
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur de connexion', 'danger');
            });
        }
        
        function saveAgentChanges() {
            if (!currentAgentId) return;
            
            const phone = document.getElementById('agent-phone').value;
            const serviceFee = document.getElementById('agent-service-fee').value;
            const maxAssignments = document.getElementById('agent-max-assignments').value;
            const regionId = document.getElementById('agent-region').value;
            const teamId = document.getElementById('agent-team').value;
            const workingHours = document.getElementById('agent-working-hours').value;
            
            // Update agent details
            const formData = new FormData();
            formData.append('action', 'assign_agent_to_team');
            formData.append('agent_id', currentAgentId);
            formData.append('team_id', teamId || '');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Agent mis à jour avec succès', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('editAgentModal')).hide();
                    loadSettingsData();
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur de connexion', 'danger');
            });
        }
        
        // Utility functions
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function showToast(message, type = 'info') {
            const container = document.querySelector('.toast-container') || createToastContainer();
            const icon = type === 'success' ? 'fa-check-circle' : 
                        type === 'danger' ? 'fa-exclamation-circle' : 
                        type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
            
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.style.borderLeftColor = `var(--${type})`;
            
            toast.innerHTML = `
                <i class="fas ${icon} text-${type}"></i>
                <span>${escapeHtml(message)}</span>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        function createToastContainer() {
            const container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
            return container;
        }
    </script>
</body>
</html>