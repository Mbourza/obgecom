<?php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');

$db = DB::getInstance();

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Validate required fields
if (!isset($_POST['company-id']) || empty($_POST['company-id'])) {
    echo json_encode(['success' => false, 'message' => 'Company ID is required']);
    exit;
}

$company_id = intval($_POST['company-id']);

// Get user ID from session
$user_id = 1; // Default user_id if session is not set

if (isset($_SESSION['user'])) {
    $username = $_SESSION['user']['username'];

    // Get user_id from the database
    $user = $db->getThisQuery("SELECT id FROM utilisateur WHERE username = ?", [$username]);

    if ($user && isset($user[0]['id'])) {
        $user_id = $user[0]['id'];
    }
}

try {
    // Check if company exists
    $company_query = "SELECT * FROM shipping_companies WHERE id = ?";
    $company = $db->getThisQuery($company_query, [$company_id]);
    
    if (empty($company)) {
        echo json_encode(['success' => false, 'message' => 'Company not found']);
        exit;
    }

    // Check if company is being used in any orders (optional safety check)
    $orders_check = "SELECT COUNT(*) as count FROM orders WHERE shipping_company_id = ?";
    $orders_result = $db->getThisQuery($orders_check, [$company_id]);
    
    if (!empty($orders_result) && $orders_result[0]['count'] > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot delete company: it is being used in existing orders'
        ]);
        exit;
    }

    // Delete user's shipping settings for this company
    $settings_delete_query = "DELETE FROM shipping_settings WHERE user_id = ? AND shipping_company_id = ?";
    $settings_result = $db->query($settings_delete_query, [$user_id, $company_id]);

    // Delete the shipping company
    $company_delete_query = "DELETE FROM shipping_companies WHERE id = ?";
    $company_result = $db->query($company_delete_query, [$company_id]);

    if ($company_result) {
        // Check if user has any remaining shipping companies
        $remaining_companies_query = "SELECT COUNT(*) as count FROM shipping_companies WHERE user_id = ?";
        $remaining_companies = $db->getThisQuery($remaining_companies_query, [$user_id]);
        
        $has_remaining_companies = !empty($remaining_companies) && $remaining_companies[0]['count'] > 0;
        // If no shipping companies left, reset onboarding progress
        if (!$has_remaining_companies) {

            updateOnboardingShippingStep($db, $user_id, 0);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Shipping company deleted successfully',
            'id' => $company_id,
            'has_remaining_companies' => $has_remaining_companies,
            'onboarding_reset' => !$has_remaining_companies
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete shipping company'
        ]);
    }

} catch (Exception $e) {
    error_log("Error in delete_shipping_company.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while deleting shipping company'
    ]);
}

function updateOnboardingShippingStep($db, $user_id, $completed) {
    try {
        $current_time = date('Y-m-d H:i:s');
        
        // Check if onboarding record exists
        $existing_onboarding = $db->getThisQuery(
            "SELECT id FROM user_onboarding WHERE user_id = ?",
            [$user_id]
        );
        
        if (!empty($existing_onboarding)) {
            $onboarding_id = $existing_onboarding[0]['id'];
            
            $update_data = [
                'step_shipping' => 0,
                'completed' => 0,
                'updated_at' => $current_time
            ];
            
            $updated = $db->update('user_onboarding', $onboarding_id, $update_data);
            
            if ($updated) {
                error_log("Onboarding shipping step updated for user {$user_id}: " . ($completed ? 'completed' : 'not completed'));
                return true;
            }
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Exception in updateOnboardingShippingStep: " . $e->getMessage());
        return false;
    }
}
?>