<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Proteger la página
$auth->requireLogin();

// Obtener estadísticas
$stats = [];
try {
    // Total de miembros
    $query = "SELECT COUNT(*) as total FROM members";
    $stmt = $db->query($query);
    $stats['total_members'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Miembros activos
    $query = "SELECT COUNT(*) as total FROM members WHERE status = 'active'";
    $stmt = $db->query($query);
    $stats['active_members'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pagos pendientes
    $query = "SELECT COUNT(*) as total FROM payments WHERE status = 'pending' AND due_date < CURDATE()";
    $stmt = $db->query($query);
    $stats['overdue_payments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Asistencias hoy
    $query = "SELECT COUNT(*) as total FROM attendance WHERE DATE(check_in) = CURDATE()";
    $stmt = $db->query($query);
    $stats['today_attendance'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch(PDOException $e) {
    error_log("Stats error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Gimnasio</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="container">
        <div class="welcome">
            <h1>Bienvenido, <?php echo $_SESSION['full_name']; ?></h1>
            <p>Panel de control del gimnasio</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Miembros</h3>
                <p class="stat-number"><?php echo $stats['total_members']; ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Miembros Activos</h3>
                <p class="stat-number"><?php echo $stats['active_members']; ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Pagos Vencidos</h3>
                <p class="stat-number"><?php echo $stats['overdue_payments']; ?></p>
            </div>
            
            <div class="stat-card">
                <h3>Asistencias Hoy</h3>
                <p class="stat-number"><?php echo $stats['today_attendance']; ?></p>
            </div>
        </div>
        
        <div class="quick-actions">
            <h2>Acciones Rápidas</h2>
            <div class="action-buttons">
                <a href="add_member.php" class="btn">Nuevo Miembro</a>
                <a href="members.php" class="btn secondary">Ver Miembros</a>
                <a href="attendance.php" class="btn secondary">Registrar Asistencia</a>
                <a href="reports.php" class="btn secondary">Reportes</a>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>