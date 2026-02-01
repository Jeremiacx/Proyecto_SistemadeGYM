<?php
/**
 * users.php - Gestión de usuarios del sistema (solo admin)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Manejo de sesión para evitar avisos de duplicidad
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// 2. Proteger la página - solo admin
$auth->requireRole('admin');

// 3. Procesar acciones (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // ACCIÓN: AGREGAR USUARIO
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $full_name = trim($_POST['full_name']);
        $role = $_POST['role'];
        
        // Validación de coincidencia de contraseña en el servidor
        if ($password !== $confirm_password) {
            $_SESSION['error'] = "Las contraseñas no coinciden.";
        } elseif (strlen($password) < 8) {
            $_SESSION['error'] = "La contraseña debe tener al menos 8 caracteres.";
        } else {
            $result = $auth->register($username, $email, $password, $full_name, $role);
            if ($result === true) {
                $_SESSION['success'] = "Usuario creado exitosamente";
            } else {
                $_SESSION['error'] = $result;
            }
        }
    }
    
    // ACCIÓN: ELIMINAR USUARIO
    if (isset($_POST['delete_user'])) {
        $user_id = (int)$_POST['user_id'];
        
        if ($user_id == $_SESSION['user_id']) {
            $_SESSION['error'] = "No puede eliminar su propio usuario";
        } else {
            try {
                $query = "DELETE FROM users WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$user_id]);
                $_SESSION['success'] = "Usuario eliminado exitosamente";
            } catch(PDOException $e) {
                $_SESSION['error'] = "Error al eliminar usuario: " . $e->getMessage();
            }
        }
    }
    
    // ACCIÓN: ACTUALIZAR ROL
    if (isset($_POST['update_role'])) {
        $user_id = (int)$_POST['user_id'];
        $new_role = $_POST['new_role'];
        
        try {
            $query = "UPDATE users SET role = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$new_role, $user_id]);
            $_SESSION['success'] = "Rol actualizado exitosamente";
        } catch(PDOException $e) {
            $_SESSION['error'] = "Error al actualizar rol";
        }
    }
    
    header("Location: users.php");
    exit();
}

// 4. Obtener lista de usuarios para la tabla
$users = [];
try {
    $query = "SELECT id, username, email, full_name, role, created_at 
              FROM users 
              ORDER BY created_at DESC";
    $stmt = $db->query($query);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Users error: " . $e->getMessage());
    $error = "Error al cargar usuarios";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Gym System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .role-