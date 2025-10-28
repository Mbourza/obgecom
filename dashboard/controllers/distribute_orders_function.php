<?php
/**
 * Enhanced Order Distribution System
 * 
 * Intelligently distributes orders to agents based on multiple performance metrics
 * with progressive complexity (starts simple, becomes smarter as data accumulates)
 */

class OrderDistributor {
    private $db;
    private $user_id;
    private $store_id;
    private $debug = true;

    public function __construct($db, $user_id, $store_id = null) {
        $this->db = $db;
        $this->user_id = $user_id;
        $this->store_id = $store_id;
    }

    /**
     * Main distribution method
     */
    public function distributeOrders() {
        try {
            $this->db->beginTransaction();

            // 1. Get agents with comprehensive performance data
            $agents = $this->getAgentsWithPerformanceData();
            
            if (empty($agents)) {
                throw new Exception("No active agents available for distribution");
            }

            // 2. Get unassigned orders with priority calculation
            $orders = $this->getUnassignedOrders();
            
            if (empty($orders)) {
                $this->db->commit();
                return $this->createResponse(true, 'No orders available for distribution');
            }

            // 3. Determine distribution strategy based on available data
            $total_confirmed = array_sum(array_column($agents, 'total_confirmations'));
            $strategy = $this->determineStrategy($total_confirmed, count($agents));

            // 4. Distribute orders according to selected strategy
            $distribution_result = $this->executeDistribution($agents, $orders, $strategy);

            $this->db->commit();
            
            return $this->createResponse(
                true,
                "Distributed {$distribution_result['distributed']} orders using {$strategy} strategy",
                $distribution_result
            );

        } catch (Exception $e) {
            $this->db->rollback();
            $this->logError("Distribution failed: " . $e->getMessage());
            return $this->createResponse(false, $e->getMessage());
        }
    }

    /**
     * Get agents with comprehensive performance metrics
     */
    private function getAgentsWithPerformanceData() {
        $query = "
            SELECT 
                a.id, a.name, 
                COALESCE(a.confirmation_rate, 0) as confirmation_rate,
                COALESCE(a.score, 0) as score,
                COUNT(DISTINCT ac.id) as total_confirmations,
                SUM(CASE WHEN o.status = 'confirmed' THEN 1 ELSE 0 END) as successful_confirmations,
                SUM(CASE WHEN o.status = 'unreachable' THEN 1 ELSE 0 END) as failed_confirmations,
                AVG(TIMESTAMPDIFF(HOUR, aoa.assigned_at, aoa.confirmed_at)) as avg_confirmation_time,
                COUNT(DISTINCT CASE WHEN aoa.status = 'expired' THEN aoa.id END) as expired_assignments
            FROM agents a
            LEFT JOIN agent_order_assignments aoa ON a.id = aoa.agent_id
            LEFT JOIN orders o ON aoa.order_id = o.id AND o.confirmed_by_agent = a.id
            LEFT JOIN agent_confirmations ac ON a.id = ac.agent_id
            WHERE a.user_id = ?
            GROUP BY a.id
            ORDER BY confirmation_rate DESC, score DESC
        ";

        return $this->db->getThisQuery($query, [$this->user_id]);
    }

    /**
     * Get unassigned orders with calculated priorities
     */
    private function getUnassignedOrders() {
        $where = "o.user_id = ? AND o.confirmed_by_agent IS NULL";
        $params = [$this->user_id];

        if ($this->store_id) {
            $where .= " AND o.store_id = ?";
            $params[] = $this->store_id;
        }

        $query = "
            SELECT 
                o.id, o.total_amount, o.customer_ville, o.order_date, o.store_id,
                (
                    (o.total_amount / 1000) * 10 + 
                    (DATEDIFF(NOW(), o.order_date) * -1) + 
                    CASE 
                        WHEN o.customer_ville IN ('Casablanca', 'Rabat', 'Marrakech') THEN 5
                        ELSE 0
                    END
                ) as priority_score
            FROM orders o
            WHERE $where
            AND o.id NOT IN (
                SELECT order_id FROM agent_order_assignments 
                WHERE user_id = ? AND status NOT IN ('expired', 'rejected')
            )
            ORDER BY priority_score DESC, order_date ASC
        ";

        return $this->db->getThisQuery($query, array_merge($params, [$this->user_id]));
    }

    /**
     * Determine the best distribution strategy based on available data
     */
    private function determineStrategy($total_confirmed, $agent_count) {
        // Not enough data - use simple equal distribution
        if ($total_confirmed < ($agent_count * 10)) {
            return 'equal';
        }

        // Moderate data - use weighted distribution
        if ($total_confirmed < ($agent_count * 50)) {
            return 'weighted';
        }

        // Enough data - use advanced performance-based distribution
        return 'performance';
    }

    /**
     * Execute the distribution based on selected strategy
     */
    private function executeDistribution($agents, $orders, $strategy) {
        $distribution_plan = [];
        $total_orders = count($orders);

        switch ($strategy) {
            case 'equal':
                $distribution_plan = $this->createEqualDistribution($agents, $total_orders);
                break;

            case 'weighted':
                $distribution_plan = $this->createWeightedDistribution($agents, $total_orders);
                break;

            case 'performance':
                $distribution_plan = $this->createPerformanceDistribution($agents, $total_orders);
                break;
        }

        // Execute the distribution
        $distributed = 0;
        $distribution_details = [];

        foreach ($distribution_plan as $agent_id => $order_count) {
            $assigned_orders = array_slice($orders, $distributed, $order_count);
            $distributed += $order_count;

            foreach ($assigned_orders as $order) {
                $this->assignOrderToAgent(
                    $order['id'],
                    $agent_id,
                    $order['store_id'],
                    $order['priority_score']
                );
            }

            $distribution_details[] = [
                'agent_id' => $agent_id,
                'orders_assigned' => $order_count,
                'agent_name' => $this->getAgentName($agents, $agent_id)
            ];
        }

        return [
            'strategy' => $strategy,
            'distributed' => $distributed,
            'distribution' => $distribution_details
        ];
    }

    /**
     * Distribution Strategies
     */
    private function createEqualDistribution($agents, $total_orders) {
        $distribution = [];
        $base_count = floor($total_orders / count($agents));
        $remaining = $total_orders % count($agents);

        foreach ($agents as $agent) {
            $distribution[$agent['id']] = $base_count + ($remaining-- > 0 ? 1 : 0);
        }

        return $distribution;
    }

    private function createWeightedDistribution($agents, $total_orders) {
        $weights = [];
        $total_weight = 0;

        // Calculate weights based on confirmation rate and score
        foreach ($agents as $agent) {
            $weight = ($agent['confirmation_rate'] / 100) * 0.7 + 
                     ($agent['score'] / 100) * 0.3;
            $weight = max($weight, 0.1); // Minimum weight
            $weights[$agent['id']] = $weight;
            $total_weight += $weight;
        }

        return $this->distributeByWeights($weights, $total_orders, $total_weight);
    }

    private function createPerformanceDistribution($agents, $total_orders) {
        $weights = [];
        $total_weight = 0;

        foreach ($agents as $agent) {
            // Complex weight calculation using multiple metrics
            $success_rate = $agent['successful_confirmations'] / 
                           ($agent['successful_confirmations'] + $agent['failed_confirmations']);
            
            $time_factor = 1 - min($agent['avg_confirmation_time'] / 72, 1); // Normalize 0-72 hours
            
            $weight = ($agent['confirmation_rate'] / 100) * 0.5 +
                     ($success_rate * 0.25) +
                     ($time_factor * 0.15) +
                     (1 - ($agent['expired_assignments'] / ($agent['total_confirmations'] + 1)) * 0.1);
                     
            $weight = max($weight, 0.05); // Absolute minimum
            $weights[$agent['id']] = $weight;
            $total_weight += $weight;
        }

        return $this->distributeByWeights($weights, $total_orders, $total_weight);
    }

    private function distributeByWeights($weights, $total_orders, $total_weight) {
        $distribution = [];
        $remaining = $total_orders;
        $min_per_agent = max(1, floor($total_orders / count($weights) / 2));

        // First pass - assign minimum to everyone
        foreach ($weights as $agent_id => $weight) {
            $distribution[$agent_id] = $min_per_agent;
            $remaining -= $min_per_agent;
        }

        // Second pass - distribute remaining by weights
        if ($remaining > 0) {
            $remaining_weights = $weights;
            $remaining_total_weight = $total_weight;

            while ($remaining > 0 && $remaining_total_weight > 0) {
                foreach ($remaining_weights as $agent_id => $weight) {
                    $share = $weight / $remaining_total_weight;
                    $to_assign = min(ceil($share * $remaining), $remaining);
                    
                    $distribution[$agent_id] += $to_assign;
                    $remaining -= $to_assign;
                    
                    if ($remaining <= 0) break;
                }
            }
        }

        return $distribution;
    }

    /**
     * Helper Methods
     */
    private function assignOrderToAgent($order_id, $agent_id, $store_id, $priority_score) {
        $assignment = [
            'agent_id' => $agent_id,
            'order_id' => $order_id,
            'user_id' => $this->user_id,
            'store_id' => $store_id,
            'priority_score' => $priority_score,
            'assigned_at' => date('Y-m-d H:i:s'),
            'status' => 'pending'
        ];

        return $this->db->insert('agent_order_assignments', $assignment);
    }

    private function getAgentName($agents, $agent_id) {
        foreach ($agents as $agent) {
            if ($agent['id'] == $agent_id) {
                return $agent['name'];
            }
        }
        return 'Unknown';
    }

    private function createResponse($success, $message, $data = []) {
        return array_merge([
            'success' => $success,
            'message' => $message,
            'distributed' => $data['distributed'] ?? 0
        ], $data);
    }

    private function logError($message) {
        error_log("[OrderDistributor] " . $message);
        if ($this->debug) {
            // Additional debug logging if needed
        }
    }
} ?>