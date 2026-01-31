<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Proteger la página
$auth->requireLogin();

// Verificar que sea admin
if($_SESSION['role'] != 'admin') {
    header("Location: members.php");
    exit();
}

if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: members.php");
    exit();
}

$id = (int)$_GET['id'];

// Verificar si existe el miembro
try {
    $query = "SELECT first_name, last_name FROM members WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    
    if($stmt->rowCount() == 0) {
        $_SESSION['error'] = "Miembro no encontrado";
        header("Location: members.php");
        exit();
    }
    
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Eliminar el miembro
    $query = "DELETE FROM members WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$id]);
    
    $_SESSION['success'] = "Miembro " . $member['first_name'] . " " . $member['last_name'] . " eliminado exitosamente";
    
} catch(PDOException $e) {
    error_log("Delete member error: " . $e->getMessage());
    $_SESSION['error'] = "Error al eliminar el miembro";
}

header("Location: members.php");
exit();
?>