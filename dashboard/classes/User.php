<?php 

class User {

    private $_db,
            $_data,
            $_sessionName,
            $_isLoggedIn,
            $_userType; // 'user' or 'agent'

    public function __construct($user = null){

        $this->_db = DB::getInstance();
        
        $this->_sessionName = Config::get('session/session_name');
        
        if(!$user) {
            
            if(Session::exists($this->_sessionName)) {
                
                $sessionData = Session::get($this->_sessionName);
                
                // Check if session data contains user type info
                if(is_array($sessionData)) {
                    $user = $sessionData['username'];
                    $this->_userType = $sessionData['type'];
                } else {
                    $user = $sessionData;
                }
                
                if($this->find($user)) {
                    
                    $this->_isLoggedIn = true;
                } else{
                    
                    $this->logOut();
                }
            }
            
        } else{
                
            $this->find($user);
        }
    }

    // find user in both tables with multiple login options for users
    public function find($user = null){
        
        if($user){
            
            // First try to find in users table with multiple fields (username, email, phone)
            $data = $this->findInUsersTable($user);

            
            if($data){  
                $this->_data = $data;
                $this->_userType = 'user';
                return true;
            }
            
            // If not found in users, try agents table (email only)
            $data = $this->_db->get('agents', array('email', '=', $user));
            
            if($data->count()){  
                $this->_data = $data->first();
                $this->_userType = 'agent';
                return true;
            }
        }
        
        return false;
    }
    
    // Helper method to find user by username, email, or phone
    private function findInUsersTable($identifier) {
        
        // If it's numeric, could be ID or phone
        if(is_numeric($identifier)) {
            // First try as ID
            $data = $this->_db->get('users', array('id', '=', $identifier));
            if($data->count()) {
                return $data->first();
            }
            
            // Then try as phone
            $data = $this->_db->get('users', array('phone', '=', $identifier));
            if($data->count()) {
                return $data->first();
            }
        }
        
        // Try as username
        $data = $this->_db->get('users', array('username', '=', $identifier));
        if($data->count()) {
            return $data->first();
        }
        
        // Try as email
        $data = $this->_db->get('users', array('email', '=', $identifier));
        if($data->count()) {
            return $data->first();
        }
        
        return null;
    }
    
    // log in user or agent
    public function login($username = null, $password = null, $remember = false) {

        if (!$username && !$password && $this->exists()) {
            $sessionData = [
                'username' => $this->getUserIdentifier(),
                'type' => $this->_userType
            ];
            Session::put($this->_sessionName, $sessionData);
            return true;

        } else {

            $user = $this->find($username);
        
            if ($user) {
                // Check if account is active
                if(!$this->isAccountActive()) {
                    return false;
                }
                
                // Compare raw password with the hashed one using password_verify
                if (password_verify($password, $this->data()->password)) {
                    $sessionData = [
                        'username' => $this->getUserIdentifier(),
                        'type' => $this->_userType
                    ];

                    Session::put($this->_sessionName, $sessionData);
                    $this->_isLoggedIn = true;
        
                    if ($remember) {
                        $this->setRememberMe($this->data()->id);
                    }
        
                    return true;
                }
            }
        }
        return false;
    }

    // Get user identifier based on type
    private function getUserIdentifier() {
        if($this->_userType === 'agent') {
            return $this->data()->email; // Agents use email as identifier
        } else {
            return $this->data()->email; // Users use username
        }
    }

    // Check if account is active
    private function isAccountActive() {

        if($this->_userType === 'agent') {
            return $this->data()->is_active == 1;
        } else {
            
            if(isset($this->data()->is_active)) {
                return $this->data()->is_active == 1;
            }
            if(isset($this->data()->active)) {
                return $this->data()->active == 1;
            }
            return true; 
        }
    }

    private function setRememberMe($userId) {
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', time() + (15 * 24 * 60 * 60)); // 15 days
        
        // Store remember token in database with user type
        $fields = array(
            'user_id' => $userId,
            'user_type' => $this->_userType,
            'token' => hash('sha256', $token),
            'expires_at' => $expiry,
            'created_at' => date('Y-m-d H:i:s')
        );
        
        $this->_db->insert('remember_tokens', $fields);
        
        // Set cookie
        setcookie('remember_token', $token, time() + (15 * 24 * 60 * 60), '/', '', true, true);
    }

    public function checkRememberMe() {
        if(!$this->isLoggedIn() && isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            $hashedToken = hash('sha256', $token);
            
            // Check remember token and join with appropriate table
            $sql = "SELECT rt.*, 
                    CASE 
                        WHEN rt.user_type = 'user' THEN u.username
                        WHEN rt.user_type = 'agent' THEN a.email
                    END as identifier,
                    rt.user_type
                    FROM remember_tokens rt 
                    LEFT JOIN users u ON rt.user_id = u.id AND rt.user_type = 'user'
                    LEFT JOIN agents a ON rt.user_id = a.id AND rt.user_type = 'agent'
                    WHERE rt.token = ? AND rt.expires_at > NOW()";
                    
            if($this->_db->query($sql, array($hashedToken))) {

                if($this->_db->count() > 0) {
                    $tokenData = $this->_db->first();
                    
                    // Find the user/agent based on type
                    $this->_userType = $tokenData->user_type;
                    if($this->find($tokenData->identifier)) {
                        // Log user in
                        $sessionData = [
                            'username' => $tokenData->identifier,
                            'type' => $this->_userType
                        ];
                        Session::put($this->_sessionName, $sessionData);
                        $this->_isLoggedIn = true;
                        
                        // Refresh the remember token
                        $this->setRememberMe($tokenData->user_id);
                        
                        return true;
                    }
                }
            }
            
            // Invalid token, remove cookie
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
        return false;
    }
    
    public function hasPermission($key) {
        // For 'user' type
        if ($this->_userType === 'user') {
            // 1. Get permission by name
            $permission = $this->_db->get('permissions', ['name', '=', $key]);
    
            if ($permission->count()) {
                $permissionId = $permission->first()->id;
    
                // 2. Check if user has that permission
                $userPermission = $this->_db->get('user_permissions', [
                    ['user_id', '=', $this->data()->id],
                    ['permission_id', '=', $permissionId]
                ]);
    
                if ($userPermission->count()) {
                    return true;
                }
            }
        }
    
        // For 'agent' type
        elseif ($this->_userType === 'agent') {
            return $this->data()->role === 'admin' || $key === 'basic_access';
        }
    
        return false;
    }    

    public function usernameExists($username) {
        // Check in users table
        $sql = "SELECT id FROM users WHERE username = ?";
        
        if($this->_db->query($sql, array($username))) {
            if($this->_db->count() > 0) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if phone exists in database (users table only)
     */
    public function phoneExists($phone) {
        $sql = "SELECT id FROM users WHERE phone = ?";
        
        if($this->_db->query($sql, array($phone))) {
            if($this->_db->count() > 0) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if email exists in database (both tables)
     */
    public function emailExists($email) {
        // Check in users table
        $sql = "SELECT id FROM users WHERE email = ?";
        
        if($this->_db->query($sql, array($email))) {
            if($this->_db->count() > 0) {
                return true;
            }
        }
        
        // Check in agents table
        $sql = "SELECT id FROM agents WHERE email = ?";
        
        if($this->_db->query($sql, array($email))) {
            if($this->_db->count() > 0) {
                return true;
            }
        }
        
        return false;
    }

    public function storePasswordResetToken($email, $token, $expiry) {
        try {
            // First, clean up expired tokens
            $this->_db->query("DELETE FROM password_resets WHERE expires_at < NOW()");
            
            // Check if user exists in users table
            $sql = "SELECT id, 'user' as type FROM users WHERE email = ? 
                    UNION 
                    SELECT id, 'agent' as type FROM agents WHERE email = ?";
                    
            if($this->_db->query($sql, array($email, $email))) {
                if($this->_db->count() > 0) {
                    $userData = $this->_db->first();
                    $userId = $userData->id;
                    $userType = $userData->type;
                    
                    // Delete any existing reset tokens for this user
                    $this->_db->query("DELETE FROM password_resets WHERE user_id = ? AND user_type = ?", 
                                     array($userId, $userType));
                    
                    // Insert new reset token
                    $fields = array(
                        'user_id' => $userId,
                        'user_type' => $userType,
                        'email' => $email,
                        'token' => $token,
                        'expires_at' => $expiry,
                        'created_at' => date('Y-m-d H:i:s')
                    );
                    
                    return $this->_db->insert('password_resets', $fields);
                }
            }
        } catch(Exception $e) {
            throw $e;
        }
        return false;
    }

    public function verifyResetToken($token) {
        try {
            $sql = "SELECT pr.*, 
                    CASE 
                        WHEN pr.user_type = 'user' THEN u.username
                        WHEN pr.user_type = 'agent' THEN a.name
                    END as name,
                    pr.email
                    FROM password_resets pr 
                    LEFT JOIN users u ON pr.user_id = u.id AND pr.user_type = 'user'
                    LEFT JOIN agents a ON pr.user_id = a.id AND pr.user_type = 'agent'
                    WHERE pr.token = ? AND pr.expires_at > NOW()";
                    
            if($this->_db->query($sql, array($token))) {
                if($this->_db->count() > 0) {
                    return $this->_db->first();
                }
            }
        } catch(Exception $e) {
            throw $e;
        }
        return false;
    }

    public function resetPasswordWithToken($token, $hashedPassword) {
        try {
            // Verify token first
            $resetData = $this->verifyResetToken($token);
            if(!$resetData) {
                return false;
            }
            
            // Update password in appropriate table
            $table = ($resetData->user_type === 'user') ? 'users' : 'agents';
            $sql = "UPDATE {$table} SET password = ? WHERE id = ?";
            
            if($this->_db->query($sql, array($hashedPassword, $resetData->user_id))) {
                // Delete the used reset token
                $this->_db->query("DELETE FROM password_resets WHERE token = ?", array($token));
                return true;
            }
        } catch(Exception $e) {
            throw $e;
        }
        return false;
    }
    
    /**
     * Verify email with token (mainly for users)
     */
    public function verifyEmail($token) {
        try {
            $sql = "UPDATE users SET is_verified = 1, verification_token = NULL WHERE verification_token = ?";
            if($this->_db->query($sql, array($token))) {
                return $this->_db->count() > 0;
            }
        } catch(Exception $e) {
            throw $e;
        }
        return false;
    }
    
    public function exists(){
        return (!empty($this->_data)) ? true : false;
    }

    public function logOut(){
        if($this->isLoggedIn()) {
            $this->_db->query("DELETE FROM remember_tokens WHERE user_id = ? AND user_type = ?", 
                             array($this->data()->id, $this->_userType));
        }

        if(isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }

        Session::delete($this->_sessionName);
        $this->_data = null;
        $this->_isLoggedIn = false;
        $this->_userType = null;
    }
    
    public function data(){
        return $this->_data;
    }
    
    public function isLoggedIn(){
        return $this->_isLoggedIn;
    }
    
    public function getUserType(){
        return $this->_userType;
    }
    
    public function isUser(){
        return $this->_userType === 'user';
    }
    
    public function isAgent(){
        return $this->_userType === 'agent';
    }
} ?>