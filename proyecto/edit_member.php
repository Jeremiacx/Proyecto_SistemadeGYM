<?php
/**
 * edit_member.php - Versión Final Corregida
 */

// 1. Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 2. Control de Sesión (Evita el aviso de sesión activa)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Carga de dependencias
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/validation.php';

// 4. Inicialización
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$validation = new Validation($db);

$auth->requireLogin();

// 5. Validar ID de miembro
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID de miembro no especificado";
    header("Location: members.php");
    exit();
}

$member_id = (int)$_GET['id'];
$member = null;
$membership_types = [];
$errors = [];
$success = '';

// 6. Obtener datos actuales
try {
    $query = "SELECT * FROM members WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$member_id]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$member) {
        $_SESSION['error'] = "Miembro no encontrado";
        header("Location: members.php");
        exit();
    }
    
    $query = "SELECT id, name, price FROM membership_types WHERE id != 0";
    $stmt = $db->query($query);
    $membership_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}

// 7. Procesar Formulario de Actualización
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = [
        'first_name' => trim($_POST['first_name']),
        'last_name' => trim($_POST['last_name']),
        'email' => trim($_POST['email']),
        'phone' => trim($_POST['phone']),
        'address' => trim($_POST['address']),
        'status' => $_POST['status'],
        'membership_id' => !empty($_POST['membership_id']) ? $_POST['membership_id'] : null
    ];
    
    // Validación simple
    if (empty($data['first_name'])) $errors[] = 'El nombre es obligatorio';
    if (empty($data['last_name'])) $errors[] = 'El apellido es obligatorio';
    if (empty($data['email'])) $errors[] = 'El email es obligatorio';
    
    if (empty($errors)) {
        try {
            // Nota: Se eliminó 'updated_at' porque no existe en tu tabla 'members'
            $query = "UPDATE members SET 
                        first_name = ?, 
                        last_name = ?, 
                        email = ?, 
                        phone = ?, 
                        address = ?, 
                        membership_id = ?, 
                        status = ? 
                      WHERE id = ?";
            
            $stmt = $db->prepare($query);
            $result = $stmt->execute([
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                $data['phone'],
                $data['address'],
                $data['membership_id'],
                $data['status'],
                $member_id
            ]);
            
            if ($result) {
                $success = '¡Miembro actualizado exitosamente!';
                // Actualizar la variable local para mostrar los cambios en el formulario
                $member = array_merge($member, $data);
            }
        } catch (PDOException $e) {
            $errors[] = 'Error al actualizar: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Miembro - Gym System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .container { max-width: 900px; margin: 30px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-row { display: flex; gap: 20px; margin-bottom: 15px; }
        .form-group { flex: 1; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn { padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 14px; }
        .btn-primary { background: #1a237e; color: white; }
        .btn-secondary { background: #6c757d; color: white; margin-left: 10px; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .header-section { border-bottom: 2px solid #eee; margin-bottom: 20px; padding-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-section">
            <h1>Editar Información del Miembro</h1>
            <p>ID: <?php echo $member_id; ?> | Registro: <?php echo $member['registration_date'] ?? 'N/A'; ?></p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <strong>Error:</strong>
                <ul style="margin: 5px 0 0 20px;">
                    <?php foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($member['first_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Apellido *</label>
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($member['last_name']); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($member['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($member['phone']); ?>">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label>Dirección</label>
                <textarea name="address" rows="2"><?php echo htmlspecialchars($member['address']); ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Membresía</label>
                    <select name="membership_id">
                        <option value="">-- Sin Membresía --</option>
                        <?php foreach ($membership_types as $type): ?>
                            <option value="<?php echo $type['id']; ?>" <?php echo ($member['membership_id'] == $type['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['name'] . " ($" . $type['price'] . ")"); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="status">
                        <option value="active" <?php echo ($member['status'] == 'active') ? 'selected' : ''; ?>>Activo</option>
                        <option value="inactive" <?php echo ($member['status'] == 'inactive') ? 'selected' : ''; ?>>Inactivo</option>
                        <option value="suspended" <?php echo ($member['status'] == 'suspended') ? 'selected' : ''; ?>>Suspendido</option>
                    </select>
                </div>
            </div>

            <div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                <a href="members.php" class="btn btn-secondary">Cancelar y Volver</a>
            </div>
        </form>
    </div>
</body>
</html>