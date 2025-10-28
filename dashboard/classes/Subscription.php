<?php
class Subscription {
    private $db;
    private $user_id;
    
    public function __construct($user_id) {
        $this->db = DB::getInstance();
        $this->user_id = $user_id;
    }
    
    /**
     * Vérifie le statut de l'abonnement de l'utilisateur
     */
    public function getSubscriptionStatus() {
        try {
            $query = "SELECT s.*, p.name as plan_name, p.price, p.is_custom 
                     FROM subscriptions s 
                     LEFT JOIN plans p ON s.plan_id = p.id 
                     WHERE s.user_id = ? 
                     AND s.status IN ('active', 'trial')
                     ORDER BY s.expires_at DESC 
                     LIMIT 1";
            
            $subscription = $this->db->getThisQuery($query, [$this->user_id]);
            
            if (!$subscription || empty($subscription[0])) {
                return [
                    'has_subscription' => false,
                    'status' => 'no_subscription',
                    'message' => 'Aucun abonnement actif'
                ];
            }
            
            $sub = $subscription[0];
            $now = new DateTime();
            $expires_at = !empty($sub['expires_at']) ? new DateTime($sub['expires_at']): null;
            
            // Calcul des jours restants
            $days_remaining = $now->diff($expires_at)->days;
            $is_expired = $expires_at < $now;
            
            if ($is_expired) {
                return [
                    'has_subscription' => false,
                    'status' => 'expired',
                    'expired_at' => $sub['expires_at'],
                    'days_expired' => $days_remaining,
                    'plan_name' => $sub['plan_name'],
                    'message' => 'Votre abonnement a expiré'
                ];
            }
            
            // Vérification si l'abonnement expire bientôt (7 jours ou moins)
            $is_expiring_soon = $days_remaining <= 7;
            
            return [
                'has_subscription' => true,
                'status' => 'active',
                'subscription' => $sub,
                'expires_at' => $sub['expires_at'],
                'days_remaining' => $days_remaining,
                'is_expiring_soon' => $is_expiring_soon,
                'plan_name' => $sub['plan_name'],
                'interval' => $sub['is_custom']
            ];
            
        } catch (Exception $e) {
            error_log("Subscription check error: " . $e->getMessage());
            return [
                'has_subscription' => false,
                'status' => 'error',
                'message' => 'Erreur de vérification d\'abonnement'
            ];
        }
    }
    
    /**
     * Vérifie si l'utilisateur peut accéder aux fonctionnalités
    */
    public function canAccessFeatures() {
        $status = $this->getSubscriptionStatus();
        return $status['has_subscription'] && $status['status'] === 'active';
    }
    
    /**
     * Récupère les alertes d'abonnement à afficher
     */
    public function getSubscriptionAlerts() {
        $status = $this->getSubscriptionStatus();
        $alerts = [];
        
        switch ($status['status']) {
            case 'no_subscription':
                $alerts[] = [
                    'type' => 'warning',
                    'title' => 'Abonnement requis',
                    'message' => 'Vous n\'avez pas d\'abonnement actif. Veuillez souscrire à un plan pour continuer à utiliser nos services.',
                    'action_url' => '../plans.php',
                    'action_text' => 'Choisir un plan',
                    'priority' => 'high'
                ];
                break;
                
            case 'expired':
                $alerts[] = [
                    'type' => 'danger',
                    'title' => 'Abonnement expiré',
                    'message' => "Votre abonnement {$status['plan_name']} a expiré il y a {$status['days_expired']} jours.",
                    'action_url' => '../plans.php',
                    'action_text' => 'Renouveler',
                    'priority' => 'high'
                ];
                break;
                
            case 'active':
                if ($status['is_expiring_soon']) {
                    $alerts[] = [
                        'type' => 'warning',
                        'title' => 'Abonnement bientôt expiré',
                        'message' => "Votre abonnement {$status['plan_name']} expire dans {$status['days_remaining']} jours.",
                        'action_url' => '../plans.php',
                        'action_text' => 'Renouveler maintenant',
                        'priority' => 'medium'
                    ];
                }
                break;
        }
        
        return $alerts;
    }
} ?>