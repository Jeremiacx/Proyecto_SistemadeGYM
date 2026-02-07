<?php
/**
 * delete_member.php - Eliminar miembro con confirmación segura
 */

// Activar mostrar errores para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();

// Cargar configuración
require_once 'config/database.php';
require_once 'includes/auth.php';

// Inicializar objetos
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Proteger la página - requiere login
$auth->requireLogin();

// Verificar que sea admin o recepcionista
if ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'receptionist') {
    $_SESSION['error'] = "No tienes permiso para eliminar miembros";
    header("Location: members.php");
    exit();
}

// Verificar que se haya proporcionado un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID de miembro no especificado";
    header("Location: members.php");
    exit();
}

$member_id = (int)$_GET['id'];

// Obtener información del miembro para mostrar en la confirmación
$member = null;
try {
    $query = "SELECT id, first_name, last_name, email, status FROM members WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$member_id]);
    
    if ($stmt->rowCount() == 0) {
        $_SESSION['error'] = "Miembro no encontrado";
        header("Location: members.php");
        exit();
    }
    
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error al cargar miembro: " . $e->getMessage());
    $_SESSION['error'] = "Error al cargar información del miembro";
    header("Location: members.php");
    exit();
}

// Variables para mensajes
$error = '';
$confirm_delete = false;

// Procesar confirmación de eliminación
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verificar token CSRF (seguridad adicional)
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token de seguridad inválido";
    } else {
        // Verificar que el checkbox de confirmación esté marcado
        if (!isset($_POST['confirm_delete']) || $_POST['confirm_delete'] != 'yes') {
            $error = "Debes confirmar la eliminación marcando la casilla";
        } else {
            // Procesar eliminación
            try {
                // Iniciar transacción para asegurar integridad de datos
                $db->beginTransaction();
                
                // 1. Primero, verificar si tiene pagos y asistencia registrados
                $check_payments = $db->prepare("SELECT COUNT(*) FROM payments WHERE member_id = ?");
                $check_payments->execute([$member_id]);
                $payment_count = $check_payments->fetchColumn();
                
                $check_attendance = $db->prepare("SELECT COUNT(*) FROM attendance WHERE member_id = ?");
                $check_attendance->execute([$member_id]);
                $attendance_count = $check_attendance->fetchColumn();
                
                // 2. Opción 1: Eliminar en cascada (depende de configuración BD)
                // Opción 2: Primero eliminar registros relacionados manualmente
                
                // Eliminar asistencia relacionada
                $delete_attendance = $db->prepare("DELETE FROM attendance WHERE member_id = ?");
                $delete_attendance->execute([$member_id]);
                
                // Eliminar pagos relacionados
                $delete_payments = $db->prepare("DELETE FROM payments WHERE member_id = ?");
                $delete_payments->execute([$member_id]);
                
                // 3. Finalmente eliminar el miembro
                $delete_member = $db->prepare("DELETE FROM members WHERE id = ?");
                $delete_member->execute([$member_id]);
                
                // Verificar si se eliminó correctamente
                if ($delete_member->rowCount() > 0) {
                    // Confirmar transacción
                    $db->commit();
                    
                    // Registrar la acción en log de actividades (si existe la tabla)
                    try {
                        $log_query = "INSERT INTO activity_log (user_id, action, description, ip_address) 
                                     VALUES (?, ?, ?, ?)";
                        $log_stmt = $db->prepare($log_query);
                        $log_stmt->execute([
                            $_SESSION['user_id'],
                            'delete_member',
                            "Eliminó miembro ID: $member_id - " . $member['first_name'] . " " . $member['last_name'],
                            $_SERVER['REMOTE_ADDR']
                        ]);
                    } catch (Exception $e) {
                        // Si falla el log, continuar igual
                        error_log("Error en log de actividad: " . $e->getMessage());
                    }
                    
                    $_SESSION['success'] = "Miembro " . $member['first_name'] . " " . $member['last_name'] . 
                                          " eliminado exitosamente. Se eliminaron $payment_count pagos y $attendance_count registros de asistencia.";
                    
                    // Redirigir a la lista de miembros
                    header("Location: members.php");
                    exit();
                    
                } else {
                    // Revertir transacción
                    $db->rollBack();
                    $error = "No se pudo eliminar el miembro. Puede que ya haya sido eliminado.";
                }
                
            } catch (PDOException $e) {
                // Revertir en caso de error
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                
                error_log("Error al eliminar miembro: " . $e->getMessage());
                
                // Mensajes específicos según el error
                if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                    $error = "No se puede eliminar el miembro porque tiene registros relacionados. " .
                            "Intenta desactivarlo en lugar de eliminarlo.";
                } else {
                    $error = "Error al eliminar el miembro: " . $e->getMessage();
                }
            }
        }
    }
}

// Generar token CSRF para protección
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Miembro - Sistema de Gimnasio</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .delete-container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .delete-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 25px;
            text-align: center;
        }
        
        .delete-header h1 {
            margin: 0;
            font-size: 1.8rem;
        }
        
        .delete-header .warning-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
        }
        
        .delete-content {
            padding: 30px;
        }
        
        .member-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid #dc3545;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .info-item {
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: bold;
            color: #495057;
            display: block;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #212529;
            padding: 8px;
            background: white;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        
        .danger-zone {
            background: #fff5f5;
            border: 2px solid #f8d7da;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        
        .danger-zone h3 {
            color: #dc3545;
            margin-top: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .warning-list {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .warning-list ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .warning-list li {
            margin-bottom: 5px;
        }
        
        .confirm-checkbox {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 20px 0;
            padding: 15px;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
        }
        
        .confirm-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-top: 2px;
        }
        
        .confirm-checkbox label {
            font-weight: bold;
            color: #856404;
            cursor: pointer;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
            flex: 1;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 5px;
            text-align: center;
            flex: 1;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .related-data {
            background: #e8f4fd;
            border: 1px solid #b8daff;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .related-data h4 {
            color: #004085;
            margin-top: 0;
        }
        
        @media (max-width: 768px) {
            .delete-container {
                margin: 20px;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="delete-container">
            <div class="delete-header">
                <div class="warning-icon">⚠️</div>
                <h1>Eliminar Miembro</h1>
                <p>Esta acción no se puede deshacer</p>
            </div>
            
            <div class="delete-content">
                <?php if ($error): ?>
                    <div class="alert error">
                        <h4>Error:</h4>
                        <p><?php echo htmlspecialchars($error); ?></p>
                        <?php if (strpos($error, 'foreign key constraint') !== false): ?>
                            <p style="margin-top: 10px;">
                                <strong>Sugerencia:</strong> Cambia el estado del miembro a "inactivo" en lugar de eliminarlo.
                                <a href="edit_member.php?id=<?php echo $member_id; ?>" class="btn small" style="margin-left: 10px;">
                                    Ir a Editar Miembro
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="member-info">
                    <h3>Información del Miembro a Eliminar</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">ID:</span>
                            <span class="info-value">#<?php echo $member['id']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Nombre Completo:</span>
                            <span class="info-value"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($member['email']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Estado Actual:</span>
                            <span class="info-value">
                                <span class="status-badge status-<?php echo $member['status']; ?>">
                                    <?php echo ucfirst($member['status']); ?>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Mostrar datos relacionados si existen -->
                <?php
                try {
                    // Verificar datos relacionados
                    $payments_count = $db->prepare("SELECT COUNT(*) FROM payments WHERE member_id = ?");
                    $payments_count->execute([$member_id]);
                    $total_payments = $payments_count->fetchColumn();
                    
                    $attendance_count = $db->prepare("SELECT COUNT(*) FROM attendance WHERE member_id = ?");
                    $attendance_count->execute([$member_id]);
                    $total_attendance = $attendance_count->fetchColumn();
                    
                    if ($total_payments > 0 || $total_attendance > 0):
                ?>
                <div class="related-data">
                    <h4>⚠️ Datos Relacionados que se Eliminarán</h4>
                    <ul>
                        <?php if ($total_payments > 0): ?>
                            <li><strong><?php echo $total_payments; ?> registros de pagos</strong> serán eliminados permanentemente</li>
                        <?php endif; ?>
                        <?php if ($total_attendance > 0): ?>
                            <li><strong><?php echo $total_attendance; ?> registros de asistencia</strong> serán eliminados permanentemente</li>
                        <?php endif; ?>
                    </ul>
                    <p style="margin-top: 10px; font-size: 0.9rem; color: #666;">
                        <strong>Nota:</strong> Todos los datos relacionados se eliminarán en cascada.
                    </p>
                </div>
                <?php endif; } catch (Exception $e) { error_log("Error checking related data: " . $e->getMessage()); } ?>
                
                <div class="danger-zone">
                    <h3>⚠️ Zona de Peligro</h3>
                    
                    <div class="warning-list">
                        <p><strong>ADVERTENCIA: Esta acción es permanente e irreversible.</strong></p>
                        <ul>
                            <li>Toda la información del miembro será eliminada permanentemente</li>
                            <li>Los registros de pagos y asistencia asociados también se eliminarán</li>
                            <li>Esta acción afectará todos los reportes históricos</li>
                            <li>No podrás recuperar esta información después</li>
                        </ul>
                        <p><strong>Considera cambiar el estado a "inactivo" en lugar de eliminar.</strong></p>
                    </div>
                    
                    <form method="POST" action="" id="deleteForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="confirm-checkbox">
                            <input type="checkbox" name="confirm_delete" id="confirm_delete" value="yes" 
                                   required onchange="updateButtonState()">
                            <label for="confirm_delete">
                                Sí, entiendo las consecuencias y deseo eliminar permanentemente a 
                                <strong><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></strong> 
                                y todos sus datos relacionados.
                            </label>
                        </div>
                        
                        <div class="form-actions">
                            <a href="members.php" class="btn-secondary">
                                ← Cancelar y Volver
                            </a>
                            <button type="submit" id="deleteButton" class="btn-danger" disabled>
                                🗑️ Eliminar Permanentemente
                            </button>
                        </div>
                    </form>
                </div>
                
                <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 5px; font-size: 0.9rem;">
                    <p><strong>Alternativa recomendada:</strong> En lugar de eliminar, puedes:</p>
                    <ul style="margin: 10px 0 10px 20px;">
                        <li><a href="edit_member.php?id=<?php echo $member_id; ?>">Cambiar el estado a "inactivo"</a></li>
                        <li><a href="edit_member.php?id=<?php echo $member_id; ?>">Cambiar el estado a "suspendido"</a></li>
                        <li><a href="#" onclick="alert('Feature en desarrollo: Archivar miembro manteniendo datos históricos')">Archivar el miembro</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        // Habilitar/deshabilitar botón según checkbox
        function updateButtonState() {
            const checkbox = document.getElementById('confirm_delete');
            const button = document.getElementById('deleteButton');
            button.disabled = !checkbox.checked;
        }
        
        // Confirmación adicional al enviar el formulario
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            const checkbox = document.getElementById('confirm_delete');
            
            if (!checkbox.checked) {
                e.preventDefault();
                alert('Debes confirmar la eliminación marcando la casilla.');
                return false;
            }
            
            // Confirmación final con nombre del miembro
            const memberName = "<?php echo addslashes($member['first_name'] . ' ' . $member['last_name']); ?>";
            const finalConfirm = confirm(`¿ESTÁS ABSOLUTAMENTE SEGURO?\n\nVas a eliminar permanentemente a:\n${memberName}\n\nEsta acción NO se puede deshacer.`);
            
            if (!finalConfirm) {
                e.preventDefault();
                return false;
            }
            
            // Mostrar loading en el botón
            const button = document.getElementById('deleteButton');
            button.innerHTML = '⏳ Eliminando...';
            button.disabled = true;
            
            return true;
        });
        
        // Prevenir envío accidental con Enter
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !document.getElementById('confirm_delete').checked) {
                e.preventDefault();
                alert('Primero debes confirmar la eliminación marcando la casilla.');
            }
        });
        
        // Inicializar estado del botón
        document.addEventListener('DOMContentLoaded', function() {
            updateButtonState();
        });
    </script>
</body>
</html>