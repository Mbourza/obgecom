<?php

class LocationBasedServices {
    private $db;
    private $maxDistance = 10; // Maximum distance in kilometers
    private $resultsLimit = 20; // Maximum number of results to return
    private $fallbackRadius = 50; // Radius to search for nearby cities in km
    private $defaultLat = 31.6295; // Default latitude for Marrakech
    private $defaultLng = -7.9811; // Default longitude for Marrakech

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Set maximum search distance
     * 
     * @param float $distance Distance in kilometers
     */
    public function setMaxDistance($distance) {
        $this->maxDistance = $distance;
    }

    /**
     * Set maximum number of results
     * 
     * @param int $limit Maximum number of results to return
     */
    public function setResultsLimit($limit) {
        $this->resultsLimit = $limit;
    }

    /**
     * Get services with location context and fallback to default location
     * 
     * @param float|null $userLat User's latitude (optional)
     * @param float|null $userLng User's longitude (optional)
     * @param int $typeId Service type ID (default: 1 for restaurants)
     * @return array Contains services, current city, and status information
     */

    /**
     * Get user's current city based on coordinates
     * 
     * @param float $userLat User's latitude
     * @param float $userLng User's longitude
     * @return array|null City information or null if not found
     */
    public function getCurrentCity($userLat, $userLng) {
        $sql = "SELECT 
                *,
                (6371 * acos(
                    cos(radians(?)) * 
                    cos(radians(latitude)) * 
                    cos(radians(longitude) - radians(?)) + 
                    sin(radians(?)) * 
                    sin(radians(latitude))
                )) AS distance
            FROM cities
            HAVING distance <= 5
            ORDER BY distance ASC
            LIMIT 1";

        $params = [$userLat, $userLng, $userLat];
        $result = $this->db->getThisQuery($sql, $params);
        
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Get nearby cities when no services are found in current city
     * 
     * @param float $userLat User's latitude
     * @param float $userLng User's longitude
     * @return array Array of nearby cities
     */
    private function getNearbyCities($userLat, $userLng) {
        $sql = "SELECT 
                *,
                (6371 * acos(
                    cos(radians(?)) * 
                    cos(radians(latitude)) * 
                    cos(radians(longitude) - radians(?)) + 
                    sin(radians(?)) * 
                    sin(radians(latitude))
                )) AS distance
            FROM cities
            HAVING distance <= ?
            ORDER BY distance ASC
            LIMIT 5";

        $params = [$userLat, $userLng, $userLat, $this->fallbackRadius];
        return $this->db->getThisQuery($sql, $params);
    }

    /**
     * Get services with location context and fallback
     * 
     * @param float $userLat User's latitude
     * @param float $userLng User's longitude
     * @param int $typeId Service type ID (default: 1 for restaurants)
     * @return array Contains services, current city, and status information
     */
    public function getServicesWithLocation($userLat = null, $userLng = null, $typeId = 1) {
        // Use default Marrakech coordinates if none provided
        $latitude = $userLat ?? $this->defaultLat;
        $longitude = $userLng ?? $this->defaultLng;

        $response = [
            'services' => [],
            'currentCity' => null,
            'status' => 'success',
            'message' => '',
            'searchRadius' => $this->maxDistance,
            'isDefaultLocation' => ($userLat === null || $userLng === null)
        ];

        // First, get the current city
        $currentCity = $this->getCurrentCity($latitude, $longitude);
        $response['currentCity'] = $currentCity;

        // Try to get services in the immediate area
        $services = $this->getNearbyServices($latitude, $longitude, $typeId);

        if (!empty($services)) {
            $response['services'] = $services;
            $response['message'] = $currentCity 
                ? "Services trouvés à {$currentCity['cityName']}" 
                : ($response['isDefaultLocation'] ? "Services à Marrakech" : "Services trouvés à proximité");
            return $response;
        }

        // If no services found, try nearby cities
        $nearbyCities = $this->getNearbyCities($latitude, $longitude);
        foreach ($nearbyCities as $city) {
            $services = $this->getServicesInCity($city['id'], $typeId);
            if (!empty($services)) {
                $response['services'] = $services;
                $response['message'] = "Services trouvés à {$city['cityName']}";
                $response['searchRadius'] = $city['distance'];
                return $response;
            }
        }

        // If still no services found, return services from the nearest city
        if (!empty($nearbyCities)) {
            $nearestCity = $nearbyCities[0];
            $services = $this->getServicesInCity($nearestCity['id'], $typeId);
            $response['services'] = $services;
            $response['message'] = "Affichage des services à {$nearestCity['cityName']}";
            $response['searchRadius'] = $nearestCity['distance'];
        }

        return $response;
    }

    /**
     * Get nearby services
     * 
     * @param float $userLat User's latitude
     * @param float $userLng User's longitude
     * @param int $typeId Service type ID
     * @return array Array of services
     */
    private function getNearbyServices($userLat, $userLng, $typeId) {
        $sql = "SELECT 
                    s.*,
                    c.cityName,
                    (6371 * acos(
                        cos(radians(?)) * 
                        cos(radians(s.latitude)) * 
                        cos(radians(s.longitude) - radians(?)) + 
                        sin(radians(?)) * 
                        sin(radians(s.latitude))
                    )) AS distance
                FROM services s
                LEFT JOIN cities c ON s.city_id = c.id
                WHERE 
                    s.type_id = ?
                    AND s.availability_status = 'available'
                    AND s.latitude IS NOT NULL 
                    AND s.longitude IS NOT NULL
                HAVING distance <= ?
                ORDER BY distance ASC, rating DESC, popularity DESC
                LIMIT 10";
    
        // Bind parameters as key-value pairs for the placeholders
        $values = [$userLat, $userLng, $userLat, $typeId, $this->maxDistance];
    
        return $this->db->getThisQuery($sql, $values);
    }        

    /**
     * Get services in a specific city
     * 
     * @param int $cityId City ID
     * @param int $typeId Service type ID
     * @return array Array of services
     */
    private function getServicesInCity($cityId, $typeId) {
        $sql = "SELECT 
                s.*,
                c.cityName,
                0 as distance
            FROM services s
            LEFT JOIN cities c ON s.city_id = c.id
            WHERE 
                s.type_id = ?
                AND s.city_id = ?
                AND s.availability_status = 1
            ORDER BY s.rating DESC, s.popularity DESC
            LIMIT ?";

        $params = [$typeId, $cityId, $this->resultsLimit];
        return $this->db->getThisQuery($sql, $params);
    }
} ?>