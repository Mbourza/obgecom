<?php
// Database connection and session check for super admin
if (file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

// Check if user is logged in and is super admin
if (!Session::exists(Config::get('session/session_name'))) {
    Redirect::to('../../lg'); 
} 

$isLoggedIn = isset($_SESSION['user']);
$user = $db->getThisQuery("SELECT id, `name`, `role`, is_verified, email FROM users WHERE email = ?", [$_SESSION['user']['username']]);

if (!$user || empty($user[0]['id'])) {
    logout(); 
}

if (isset($_GET['logout'])) {
    logout();
}

// Check if user is super admin
if ($user[0]['role'] !== 'super') {
    Redirect::to('../home');
}

function logout() {
    $user = new User();
    $user->logout();
    Redirect::to('../../lg');
}

// Handle actions
$success_message = '';
$error_message = '';

// Handle delete action
if (isset($_POST['delete_id'])) {

    $delete_id = intval($_POST['delete_id']);
    $query = "DELETE FROM shipping_options WHERE id = :id";
    if ($db->query($query, ['id' => $delete_id])) {
        $success_message = "Transporteur supprimé avec succès!";
    } else {
        $error_message = "Erreur lors de la suppression du transporteur.";
    }
}

// Handle toggle status
if (isset($_POST['toggle_id'])) {
    
    $toggle_id = intval($_POST['toggle_id']);
    $current_status = $_POST['current_status'] == '1' ? 0 : 1;
    $query = "UPDATE shipping_options SET is_active = ? WHERE id = ?";
    if ($db->query($query, [$current_status, $toggle_id])) {
        $success_message = "Statut du transporteur mis à jour!";
    } else {
        $error_message = "Erreur lors de la mise à jour du statut.";
    }
}

// Handle add/edit via AJAX-friendly endpoint
// For form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $id = isset($_POST['id']) && !empty($_POST['id']) ? intval($_POST['id']) : null;
    
    $data = [
        'name' => $_POST['name'],
        'slug' => $_POST['slug'],
        'logo_url' => $_POST['logo_url'] ?? null,
        'phone' => $_POST['phone'] ?? null,
        'is_active' => $_POST['is_active'] ?? 1
    ];
    
    if ($id) {
        // Update existing
        $result = $db->update('shipping_options', $id, $data);
        if ($result) {
            $success_message = "Transporteur mis à jour avec succès!";
        } else {
            $error_message = "Erreur lors de la mise à jour.";
        }
    } else {
        // Insert new
        $result = $db->insert('shipping_options', $data);
        if ($result) {
            $success_message = "Transporteur ajouté avec succès!";
        } else {
            $error_message = "Erreur lors de l'ajout.";
        }
    }
    
    // Redirect to refresh the page
    header("Location: shipping_companies.php");
    exit();
}

// Fetch all shipping companies from shipping_options table
$shippingOptions = $db->getThisQuery("SELECT * FROM shipping_options ORDER BY is_active DESC, name ASC");

// Get statistics
$totalCompanies = count($shippingOptions);
$activeCompanies = 0;
foreach ($shippingOptions as $opt) {
    if ($opt['is_active'] == 1) $activeCompanies++;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Gestion des Transporteurs - Super Admin OBG">
    <title>Transporteurs | Super Admin OBG</title>
    <link rel="stylesheet" href="../../assets/css/supperDash.css" />
    <link rel="stylesheet" href="../../assets/css/super.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Custom styles for shipping companies page */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-card-header h3 {
            font-size: 0.9rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .stat-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-card-icon i {
            font-size: 24px;
            color: white;
        }

        .stat-card-icon.shipping {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }

        .stat-card-icon.active {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }

        .stat-card-value {
            font-size: 2rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }

        .stat-card-description {
            font-size: 0.85rem;
            color: #64748b;
        }

        /* Companies Table */
        .companies-container {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .table-header {
            padding: 1.25rem 1.5rem;
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .table-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-add {
            background: linear-gradient(135deg, #5f34d9, #9c80fd);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(95, 52, 217, 0.3);
        }

        .search-box {
            display: flex;
            align-items: center;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 40px;
            padding: 6px 16px;
            gap: 8px;
        }

        .search-box i {
            color: #94a3b8;
        }

        .search-box input {
            border: none;
            outline: none;
            padding: 8px 0;
            width: 250px;
            font-size: 0.9rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 1rem 1.5rem;
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        tr:hover {
            background: #fafbfc;
        }

        .company-logo {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            object-fit: contain;
            background: #f8fafc;
            padding: 6px;
        }

        .company-name {
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 4px;
            font-size: 1rem;
        }

        .company-slug {
            font-size: 0.7rem;
            color: #64748b;
            font-family: monospace;
        }

        .badge {
            display: inline-flex;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-active {
            background: #dcfce7;
            color: #166534;
        }

        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-icon {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-edit {
            background: #e0e7ff;
            color: #4338ca;
        }

        .btn-edit:hover {
            background: #c7d2fe;
        }

        .btn-toggle {
            background: #e2e8f0;
            color: #475569;
        }

        .btn-toggle:hover {
            background: #cbd5e1;
        }

        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
        }

        .btn-delete:hover {
            background: #fecaca;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 2px;
            width: 90%;
            max-width: 550px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 30px 40px rgba(0,0,0,0.2);
        }

        .modal-header {
            padding: 1.5rem 1.75rem;
            border-bottom: 1px solid #eef2f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #94a3b8;
            transition: color 0.2s;
        }

        .modal-close:hover {
            color: #0f172a;
        }

        .modal-body {
            padding: 1.75rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #334155;
            font-size: 0.85rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #5f34d9;
            box-shadow: 0 0 0 3px rgba(95, 52, 217, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .modal-footer {
            padding: 1rem 1.75rem;
            background: #f9fafb;
            border-top: 1px solid #eef2f6;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-save {
            background: linear-gradient(135deg, #5f34d9, #9c80fd);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-cancel {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            padding: 10px 24px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
        }

        .empty-state {
            text-align: center;
            padding: 4rem;
            color: #64748b;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Notification */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 12px;
            color: white;
            font-weight: 500;
            z-index: 10000;
            animation: slideInRight 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notification-success {
            background: #10b981;
        }

        .notification-error {
            background: #ef4444;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Preview logo */
        .logo-preview {
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-preview img {
            max-width: 60px;
            max-height: 60px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 4px;
        }

        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .table-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                width: 100%;
            }
            
            .search-box input {
                width: 100%;
            }
            
            table, thead, tbody, th, td, tr {
                display: block;
            }
            
            thead {
                display: none;
            }
            
            tr {
                margin-bottom: 1rem;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 1rem;
            }
            
            td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.5rem 0;
                border: none;
            }
            
            td::before {
                content: attr(data-label);
                font-weight: 600;
                color: #475569;
                width: 40%;
            }
            
            .action-buttons {
                justify-content: flex-end;
                width: 60%;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php $currentPage = 'shipping_companies';
    require_once('../../assets/sidebarSuper.php'); ?>
    
    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Top Navigation Bar -->
        <nav class="top-navbar">
            <div class="navbar-title">
                <h1>
                    <i class="bi bi-truck"></i>
                    Gestion des Transporteurs
                </h1>
            </div>
            
            <div class="top-navbar-right">
                <div class="user-menu">
                    <button class="user-btn" onclick="toggleUserMenu()">
                        <?php echo strtoupper(substr($user[0]['name'], 0, 1)); ?>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <a href="./profile.php">
                            <i class="bi bi-person"></i>
                            Mon Profil
                        </a>
                        <a href="./settings.php">
                            <i class="bi bi-gear"></i>
                            Paramètres
                        </a>
                        <hr>
                        <a href="?logout">
                            <i class="bi bi-box-arrow-right"></i>
                            Déconnexion
                        </a>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Welcome Message -->
            <div class="welcome-message">
                <div class="welcome-icon admin">
                    <i class="bi bi-truck"></i>
                </div>
                <div class="welcome-content">
                    <h3>Gestion des Transporteurs</h3>
                    <p>Gérez les transporteurs disponibles que les clients pourront intégrer dans leur dashboard</p>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Total Transporteurs</h3>
                        <div class="stat-card-icon shipping">
                            <i class="bi bi-truck"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo $totalCompanies; ?></div>
                    <div class="stat-card-description">
                        <span>Transporteurs disponibles</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Transporteurs Actifs</h3>
                        <div class="stat-card-icon active">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo $activeCompanies; ?></div>
                    <div class="stat-card-description">
                        <span>Visible par les clients</span>
                    </div>
                </div>
            </div>
            
            <!-- Companies List -->
            <div class="companies-container">
                <div class="table-header">
                    <h3>
                        <i class="bi bi-list-ul"></i>
                        Liste des transporteurs
                    </h3>
                    <div style="display: flex; gap: 1rem;">
                        <div class="search-box">
                            <i class="bi bi-search"></i>
                            <input type="text" id="searchInput" placeholder="Rechercher un transporteur...">
                        </div>
                        <button class="btn-add" onclick="openAddModal()">
                            <i class="bi bi-plus-lg"></i>
                            Ajouter un transporteur
                        </button>
                    </div>
                </div>

                <?php if (empty($shippingOptions)): ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <p>Aucun transporteur configuré</p>
                        <button class="btn-add" onclick="openAddModal()" style="margin-top: 1rem;">
                            <i class="bi bi-plus-lg"></i> Ajouter votre premier transporteur
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table id="companiesTable">
                            <thead>
                                <tr>
                                    <th>Logo</th>
                                    <th>Nom / Slug</th>
                                    <th>Téléphone Support</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($shippingOptions as $company): ?>
                                    <tr data-name="<?php echo strtolower(htmlspecialchars($company['name'])); ?>">
                                        <td data-label="Logo">
                                            <?php if (!empty($company['logo_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($company['logo_url']); ?>" 
                                                     class="company-logo" 
                                                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' fill=\'%239c80fd\'%3E%3Cpath d=\'M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm13.5-9l1.96 2.5H17V9h2.5zm-1.5 9c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1z\'/%3E%3C/svg%3E';">
                                            <?php else: ?>
                                                <div class="company-logo" style="background: linear-gradient(135deg, #5f34d9, #9c80fd); display: flex; align-items: center; justify-content: center;">
                                                    <i class="bi bi-truck" style="color: white; font-size: 24px;"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Nom">
                                            <div class="company-name"><?php echo htmlspecialchars($company['name']); ?></div>
                                            <div class="company-slug">slug: <?php echo htmlspecialchars($company['slug']); ?></div>
                                        </td>
                                        <td data-label="Téléphone">
                                            <?php echo !empty($company['phone']) ? htmlspecialchars($company['phone']) : '<span style="color: #94a3b8;">— Non renseigné —</span>'; ?>
                                        </td>
                                        <td data-label="Statut">
                                            <span class="badge <?php echo $company['is_active'] == 1 ? 'badge-active' : 'badge-inactive'; ?>">
                                                <?php echo $company['is_active'] == 1 ? 'Actif' : 'Inactif'; ?>
                                            </span>
                                        </td>
                                        <td data-label="Actions">
                                            <div class="action-buttons">
                                                <button class="btn-icon btn-edit" onclick="editCompany(<?php echo $company['id']; ?>)">
                                                    <i class="bi bi-pencil"></i> Modifier
                                                </button>
                                                <button class="btn-icon btn-toggle" onclick="toggleStatus(<?php echo $company['id']; ?>, <?php echo $company['is_active']; ?>)">
                                                    <i class="bi bi-power"></i> <?php echo $company['is_active'] == 1 ? 'Désactiver' : 'Activer'; ?>
                                                </button>
                                                <button class="btn-icon btn-delete" onclick="deleteCompany(<?php echo $company['id']; ?>, '<?php echo addslashes($company['name']); ?>')">
                                                    <i class="bi bi-trash"></i> Supprimer
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Add/Edit Modal -->
    <div id="companyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Ajouter un transporteur</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="" id="companyForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" id="companyId">
                    
                    <div class="form-group">
                        <label>Nom du transporteur *</label>
                        <input type="text" name="name" id="companyName" required placeholder="Ex: DHL Express, FedEx, UPS">
                        <small style="color: #64748b;">Nom complet du transporteur</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Slug (identifiant unique) *</label>
                        <input type="text" name="slug" id="companySlug" required placeholder="dhl-express">
                        <small style="color: #64748b;">Utilisé dans l'API, sans espaces, en minuscules (ex: dhl-express, fedex)</small>
                    </div>
                    
                    <div class="form-group">
                        <label>URL du logo</label>
                        <input type="url" name="logo_url" id="logoUrl" placeholder="https://exemple.com/logo.png">
                        <div class="logo-preview" id="logoPreview" style="display: none;">
                            <img id="previewImg" src="" alt="Aperçu">
                            <small style="color: #64748b;">Aperçu du logo</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Téléphone support client</label>
                        <input type="text" name="phone" id="phone" placeholder="+212 5xx xxx xxx">
                        <small style="color: #64748b;">Numéro de téléphone du service support du transporteur</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Statut</label>
                        <select name="is_active" id="isActive">
                            <option value="1">Actif (visible par les clients)</option>
                            <option value="0">Inactif (caché)</option>
                        </select>
                        <small style="color: #64748b;">Seuls les transporteurs actifs apparaîtront dans le dashboard client</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Annuler</button>
                    <button type="submit" class="btn-save">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput')?.addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#companiesTable tbody tr');
            
            rows.forEach(row => {
                const name = row.getAttribute('data-name') || '';
                if (name.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Logo preview
        document.getElementById('logoUrl')?.addEventListener('input', function() {
            const preview = document.getElementById('logoPreview');
            const img = document.getElementById('previewImg');
            if (this.value) {
                img.src = this.value;
                preview.style.display = 'flex';
            } else {
                preview.style.display = 'none';
            }
        });

        // Modal functions
        let currentEditId = null;

        function openAddModal() {
            currentEditId = null;
            document.getElementById('modalTitle').textContent = 'Ajouter un transporteur';
            document.getElementById('companyForm').reset();
            document.getElementById('companyId').value = '';
            document.getElementById('isActive').value = '1';
            document.getElementById('logoPreview').style.display = 'none';
            document.getElementById('companyModal').classList.add('active');
        }

        function editCompany(id) {
            // Fetch company data via AJAX
            fetch(`../controllers/get_shipping_company.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentEditId = data.company.id;
                        document.getElementById('modalTitle').textContent = 'Modifier le transporteur';
                        document.getElementById('companyId').value = data.company.id;
                        document.getElementById('companyName').value = data.company.name;
                        document.getElementById('companySlug').value = data.company.slug;
                        document.getElementById('logoUrl').value = data.company.logo_url || '';
                        document.getElementById('phone').value = data.company.phone || '';
                        document.getElementById('isActive').value = data.company.is_active;
                        
                        // Show logo preview if exists
                        if (data.company.logo_url) {
                            document.getElementById('previewImg').src = data.company.logo_url;
                            document.getElementById('logoPreview').style.display = 'flex';
                        } else {
                            document.getElementById('logoPreview').style.display = 'none';
                        }
                        
                        document.getElementById('companyModal').classList.add('active');
                    } else {
                        showNotification('Erreur lors du chargement des données', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Erreur réseau', 'error');
                });
        }

        function toggleStatus(id, currentStatus) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const toggleInput = document.createElement('input');
            toggleInput.name = 'toggle_id';
            toggleInput.value = id;
            
            const statusInput = document.createElement('input');
            statusInput.name = 'current_status';
            statusInput.value = currentStatus;
            
            form.appendChild(toggleInput);
            form.appendChild(statusInput);
            document.body.appendChild(form);
            form.submit();
        }

        function deleteCompany(id, name) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer "${name}" ? Cette action est irréversible.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const input = document.createElement('input');
                input.name = 'delete_id';
                input.value = id;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function closeModal() {
            document.getElementById('companyModal').classList.remove('active');
            currentEditId = null;
        }

        // Auto-generate slug from name
        document.getElementById('companyName')?.addEventListener('input', function() {
            const slugField = document.getElementById('companySlug');
            if (!slugField.value || slugField.value === '' || currentEditId === null) {
                const slug = this.value
                    .toLowerCase()
                    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-|-$/g, '');
                slugField.value = slug;
            }
        });

        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('companyModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        function toggleUserMenu() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('show');
        }

        // Close user dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.querySelector('.user-menu');
            const dropdown = document.getElementById('userDropdown');
            
            if (userMenu && !userMenu.contains(event.target)) {
                dropdown?.classList.remove('show');
            }
        });

        // Show notification if there are messages
        <?php if ($success_message): ?>
            showNotification('<?php echo $success_message; ?>', 'success');
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            showNotification('<?php echo $error_message; ?>', 'error');
        <?php endif; ?>

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    </script>
</body>
</html>