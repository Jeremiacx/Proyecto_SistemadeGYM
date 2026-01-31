<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Si ya está logueado, redirigir al dashboard
if($auth->isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if($auth->login($username, $password)) {
        header("Location: dashboard.php");
        exit();
    } else {
        $error = 'Usuario o contraseña incorrectos';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Gimnasio</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="login-box">
            <h2>Iniciar Sesión</h2>
            
            <?php if($error): ?>
                <div class="alert error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Usuario:</label>
                    <input type="text" name="username" required>
                </div>
                
                <div class="form-group">
                    <label>Contraseña:</label>
                    <input type="password" name="password" required>
                </div>
                
                <button type="submit" class="btn">Ingresar</button>
            </form>
            
            <p class="text-center">
                ¿No tienes cuenta? <a href="register.php">Regístrate aquí</a>
            </p>
        </div>
    </div>
</body>
</html>