<?php 

class SignupHandler
{
    private Firebase $firebase;
    private DB $db;
    private ?string $validationError = null;

    public function __construct(Firebase $firebase, DB $db)
    {
        $this->firebase = $firebase;
        $this->db = $db;
    }

    public function handleEmailSignup(array $data): array
    {
        try {
            if (!$this->validateInput($data)) {
                return ['success' => false, 'error' => $this->getValidationError()];
            }

            $result = $this->firebase->signUpWithEmail($data['email'], $data['password'], $data['username']);
            if (!$result['success']) {
                return ['success' => false, 'error' => "Erreur d'inscription: " . $result['error']];
            }

            $this->saveAdditionalUserData($result['user']['uid'], $data, 3);
            return ['success' => true, 'message' => "Compte créé avec succès! Vous pouvez maintenant vous connecter."];
        } catch (Exception $e) {
            error_log('SignupHandler error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Une erreur inattendue s\'est produite. Veuillez réessayer plus tard.'];
        }
    }

    public function handleGoogleSignin(string $idToken): array
    {
        $result = $this->firebase->signInWithGoogle($idToken);
        if (!$result['success']) {
            return ['success' => false, 'error' => "Erreur de connexion Google: " . $result['error']];
        }

        if (!$this->userHasAdditionalData($result['user']['uid'])) {
            $this->saveAdditionalUserData($result['user']['uid'], [
                'username' => $result['user']['displayName'],
                'email' => $result['user']['email'],
                'avatar' => $result['user']['photoUrl'] ?? null
            ], 3); // Set the default role to 3 (regular user)
        }

        $redirectUrl = $this->getRedirectUrl($result['user']['uid']);
        return ['success' => true, 'message' => "Connexion réussie avec Google!", 'redirect_url' => $redirectUrl];
    }

    public function handleEmailLogin(string $email, string $password): array
    {
        $result = $this->firebase->signInWithEmail($email, $password);
        if (!$result['success']) {
            return ['success' => false, 'error' => "Login error: " . $result['error']];
        }

        $redirectUrl = $this->getRedirectUrl($result['user']['uid']);
        return ['success' => true, 'message' => "Login successful!", 'redirect_url' => $redirectUrl];
    }

    private function getRedirectUrl(string $userId): string
    {
        $role = $this->db->getThisQuery("SELECT permission FROM users WHERE id = :userId", ['userId' => $userId])[0]['permission'];
        switch ($role) {
            case 1:
                return './admin/index.php';
            case 2:
                return './admin/index.php';
            case 3:
                return './index.php';
            default:
                return './includes/error.php';
        }
    }

    private function validateInput(array $data): bool
    {
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            $this->validationError = "Tous les champs sont obligatoires.";
            return false;
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->validationError = "Format d'email invalide.";
            return false;
        }

        if (strlen($data['password']) < 8) {
            $this->validationError = "Le mot de passe doit contenir au moins 8 caractères.";
            return false;
        }

        if (!preg_match("/[A-Z]/", $data['password']) ||
            !preg_match("/[a-z]/", $data['password']) ||
            !preg_match("/[0-9]/", $data['password']) ||
            !preg_match("/[^A-Za-z0-9]/", $data['password'])) {
            $this->validationError = "Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial.";
            return false;
        }

        return true;
    }

    private function saveAdditionalUserData(string $uid, array $data, int $role): void
    {
        $userData = array_merge([
            'id' => $uid,
            'is_reliable' => 0,
            'points' => 0,
            'permission' => $role,
            'updated_at' => date('Y-m-d H:i:s')
        ], $data);
    
        $this->db->insert('users', $userData);
    }

    private function userHasAdditionalData(string $uid): bool
    {
        $query = "SELECT 1 FROM users WHERE id = :id AND (address IS NOT NULL OR city IS NOT NULL OR phone IS NOT NULL)";
        $result = $this->db->getThisQuery($query, [$uid]);
        return (bool)$result;
    }

    private function getValidationError(): string
    {
        return $this->validationError ?? '';
    }
} ?>