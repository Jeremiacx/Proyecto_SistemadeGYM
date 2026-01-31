<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    
    // Validaciones
    if(empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'Todos los campos son obligatorios';
    } elseif($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden';
    } elseif(strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } else {
        $result = $auth->register($username, $email, $password, $full_name);
        if($result === true) {
            $success = 'Usuario registrado exitosamente. Puede iniciar sesión.';
        } else {
            $error = $result;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Sistema de Gimnasio</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="login-box">
            <h2>Registro de Usuario</h2>
            
            <?php if($error): ?>
                <div class="alert error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Nombre Completo:</label>
                    <input type="text" name="full_name" required>
                </div>
                
                <div class="form-group">
                    <label>Nombre de Usuario:</label>
                    <input type="text" name="username" required>
                </div>
                
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label>Contraseña:</label>
                    <input type="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label>Confirmar Contraseña:</label>
                    <input type="password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn">Registrarse</button>
            </form>
            
            <p class="text-center">
                ¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a>
            </p>
        </div>
    </div>
</body>
</html>