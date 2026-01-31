<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Proteger la página
$auth->requireLogin();

// Paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Obtener miembros
$members = [];
$total_pages = 0;

try {
    // Total de registros
    $query = "SELECT COUNT(*) as total FROM members";
    $stmt = $db->query($query);
    $total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
    // Obtener miembros con paginación
    $query = "SELECT m.*, mt.name as membership_name 
              FROM members m 
              LEFT JOIN membership_types mt ON m.membership_id = mt.id 
              ORDER BY m.registration_date DESC 
              LIMIT :offset, :limit";
    
    $stmt = $db->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Members error: " . $e->getMessage());
    $error = "Error al cargar los miembros";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Miembros - Sistema de Gimnasio</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="header-section">
            <h1>Gestión de Miembros</h1>
            <a href="add_member.php" class="btn">Agregar Nuevo Miembro</a>
        </div>
        
        <?php if(isset($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre Completo</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Membresía</th>
                        <th>Estado</th>
                        <th>Fecha Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($members as $member): ?>
                    <tr>
                        <td><?php echo $member['id']; ?></td>
                        <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                        <td><?php echo htmlspecialchars($member['phone']); ?></td>
                        <td><?php echo htmlspecialchars($member['membership_name'] ?? 'Sin membresía'); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $member['status']; ?>">
                                <?php echo ucfirst($member['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($member['registration_date'])); ?></td>
                        <td class="actions">
                            <a href="edit_member.php?id=<?php echo $member['id']; ?>" class="btn small">Editar</a>
                            <a href="delete_member.php?id=<?php echo $member['id']; ?>" 
                               class="btn small danger" 
                               onclick="return confirm('¿Está seguro de eliminar este miembro?')">
                                Eliminar
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginación -->
        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>">Anterior</a>
            <?php endif; ?>
            
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>">Siguiente</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>