<?php
session_start();

class Auth {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function register($username, $email, $password, $full_name, $role = 'receptionist') {
        try {
            // Validar que el usuario no exista
            $query = "SELECT id FROM users WHERE username = ? OR email = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$username, $email]);
            
            if($stmt->rowCount() > 0) {
                return "El usuario o email ya existe";
            }
            
            // Encriptar contraseña
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insertar usuario
            $query = "INSERT INTO users (username, email, password, full_name, role) 
                      VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$username, $email, $hashed_password, $full_name, $role]);
            
            return true;
        } catch(PDOException $e) {
            error_log("Register error: " . $e->getMessage());
            return "Error en el registro";
        }
    }
    
    public function login($username, $password) {
        try {
            $query = "SELECT id, username, password, role, full_name FROM users WHERE username = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$username]);
            
            if($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if(password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['logged_in'] = true;
                    
                    return true;
                }
            }
            return false;
        } catch(PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function requireLogin() {
        if(!$this->isLoggedIn()) {
            header("Location: login.php");
            exit();
        }
    }
    
    public function requireRole($required_role) {
        $this->requireLogin();
        
        if($_SESSION['role'] != $required_role && $_SESSION['role'] != 'admin') {
            header("Location: dashboard.php");
            exit();
        }
    }
    
    public function logout() {
        session_destroy();
        header("Location: login.php");
        exit();
    }
}
?>