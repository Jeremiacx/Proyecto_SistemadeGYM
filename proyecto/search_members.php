<?php
/**
 * search_members.php - Búsqueda AJAX de miembros para asistencia
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();

require_once 'config/database.php';
require_once 'includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Verificar sesión
if (!$auth->isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    exit('No autorizado');
}

// Obtener término de búsqueda
$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($searchTerm) < 2) {
    echo json_encode([]);
    exit();
}

try {
    // Buscar miembros activos
    $query = "SELECT id, first_name, last_name, email, phone 
              FROM members 
              WHERE status = 'active' 
                AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)
              ORDER BY first_name, last_name 
              LIMIT 10";
    
    $searchParam = "%$searchTerm%";
    $stmt = $db->prepare($query);
    $stmt->execute([$searchParam, $searchParam, $searchParam]);
    
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar membresía activa para cada miembro
    foreach ($members as &$member) {
        $query = "SELECT mt.name, p.due_date 
                  FROM payments p 
                  JOIN membership_types mt ON p.membership_id = mt.id 
                  WHERE p.member_id = ? 
                    AND p.status = 'paid' 
                    AND p.due_date >= CURDATE() 
                  ORDER BY p.due_date DESC 
                  LIMIT 1";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$member['id']]);
        $membership = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $member['has_active_membership'] = !empty($membership);
        $member['membership_name'] = $membership['name'] ?? null;
        $member['due_date'] = $membership['due_date'] ?? null;
    }
    
    header('Content-Type: application/json');
    echo json_encode($members);
    
} catch(PDOException $e) {
    error_log("Search members error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Error en la búsqueda']);
}
?>