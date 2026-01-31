<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión de Gimnasio</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <div class="header-content">
            <div class="logo">
                <h1>Gym Management System</h1>
            </div>
            
            <nav>
                <ul>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="members.php">Miembros</a></li>
                    <li><a href="payments.php">Pagos</a></li>
                    <li><a href="attendance.php">Asistencia</a></li>
                    <li><a href="reports.php">Reportes</a></li>
                    <?php if($_SESSION['role'] == 'admin'): ?>
                        <li><a href="users.php">Usuarios</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <div class="user-info">
                <span>Hola, <?php echo $_SESSION['full_name']; ?></span>
                <span>(<?php echo $_SESSION['role']; ?>)</span>
                <a href="logout.php" class="btn small">Cerrar Sesión</a>
            </div>
        </div>
    </header>