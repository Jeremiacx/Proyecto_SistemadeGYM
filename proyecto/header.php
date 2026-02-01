<?php if (!isset($hide_header)): ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Sistema de Gimnasio'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header style="background:#1a237e;color:white;padding:15px;display:flex;justify-content:space-between;align-items:center;">
        <div>
            <h2>Sistema de Gestión de Gimnasio</h2>
        </div>
        <nav>
            <?php if(isset($_SESSION['logged_in'])): ?>
                <a href="dashboard.php" style="color:white;margin:0 10px;">Dashboard</a>
                <a href="members.php" style="color:white;margin:0 10px;">Miembros</a>
                <a href="payments.php" style="color:white;margin:0 10px;">Pagos</a>
                <a href="attendance.php" style="color:white;margin:0 10px;">Asistencia</a>
                <a href="logout.php" style="color:white;margin:0 10px;background:#dc3545;padding:5px 10px;border-radius:3px;">Salir</a>
            <?php else: ?>
                <a href="login.php" style="color:white;margin:0 10px;">Login</a>
                <a href="register.php" style="color:white;margin:0 10px;">Registro</a>
            <?php endif; ?>
        </nav>
    </header>
    <div class="container">
<?php endif; ?>