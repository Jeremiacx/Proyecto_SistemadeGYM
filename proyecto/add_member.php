<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/validation.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$validation = new Validation($db);

// Proteger la página
$auth->requireLogin();

// Obtener tipos de membresía
$membership_types = [];
try {
    $query = "SELECT id, name, price FROM membership_types WHERE id != 0";
    $stmt = $db->query($query);
    $membership_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Membership types error: " . $e->getMessage());
}

$errors = [];
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = [
        'first_name' => trim($_POST['first_name']),
        'last_name' => trim($_POST['last_name']),
        'email' => trim($_POST['email']),
        'phone' => trim($_POST['phone']),
        'address' => trim($_POST['address']),
        'birth_date' => $_POST['birth_date'],
        'gender' => $_POST['gender'],
        'membership_id' => $_POST['membership_id'],
        'emergency_contact_name' => trim($_POST['emergency_contact_name']),
        'emergency_contact_phone' => trim($_POST['emergency_contact_phone']),
        'medical_conditions' => trim($_POST['medical_conditions'])
    ];
    
    // Validaciones básicas
    if(empty($data['first_name'])) $errors[] = 'El nombre es obligatorio';
    if(empty($data['last_name'])) $errors[] = 'El apellido es obligatorio';
    if(empty($data['email'])) $errors[] = 'El email es obligatorio';
    if(empty($data['birth_date'])) $errors[] = 'La fecha de nacimiento es obligatoria';
    
    // Validar email único
    try {
        $query = "SELECT id FROM members WHERE email = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$data['email']]);
        if($stmt->rowCount() > 0) {
            $errors[] = 'El email ya está registrado';
        }
    } catch(PDOException $e) {
        $errors[] = 'Error al validar el email';
    }
    
    // Validar fecha de nacimiento (mínimo 16 años)
    $birth_date = new DateTime($data['birth_date']);
    $today = new DateTime();
    $age = $today->diff($birth_date)->y;
    if($age < 16) {
        $errors[] = 'El miembro debe tener al menos 16 años';
    }
    
    // REGLA DE NEGOCIO COMPLEJA: Validar límite de membresía
    if(!empty($data['membership_id'])) {
        $membership_error = $validation->validateMembershipLimit($data['membership_id']);
        if($membership_error) {
            $errors[] = $membership_error;
        }
    }
    
    // Si no hay errores, insertar
    if(empty($errors)) {
        try {
            $query = "INSERT INTO members (
                first_name, last_name, email, phone, address, 
                birth_date, gender, membership_id, emergency_contact_name,
                emergency_contact_phone, medical_conditions, registration_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())";
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                $data['phone'],
                $data['address'],
                $data['birth_date'],
                $data['gender'],
                $data['membership_id'] ?: null,
                $data['emergency_contact_name'],
                $data['emergency_contact_phone'],
                $data['medical_conditions']
            
            ]);
            
            $success = 'Miembro agregado exitosamente';
            
            // Limpiar formulario
            $data = array_fill_keys(array_keys($data), '');
            
        } catch(PDOException $e) {
            error_log("Insert member error: " . $e->getMessage());
            $errors[] = 'Error al agregar el miembro';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Miembro - Sistema de Gimnasio</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="form-container">
            <h1>Agregar Nuevo Miembro</h1>
            
            <?php if(!empty($errors)): ?>
                <div class="alert error">
                    <ul>
                        <?php foreach($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nombre *</label>
                        <input type="text" name="first_name" value="<?php echo $data['first_name'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Apellido *</label>
                        <input type="text" name="last_name" value="<?php echo $data['last_name'] ?? ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" value="<?php echo $data['email'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="tel" name="phone" value="<?php echo $data['phone'] ?? ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Dirección</label>
                    <textarea name="address"><?php echo $data['address'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Fecha de Nacimiento *</label>
                        <input type="date" name="birth_date" value="<?php echo $data['birth_date'] ?? ''; ?>" required max="<?php echo date('Y-m-d', strtotime('-16 years')); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Género *</label>
                        <select name="gender" required>
                            <option value="">Seleccionar</option>
                            <option value="M" <?php echo ($data['gender'] ?? '') == 'M' ? 'selected' : ''; ?>>Masculino</option>
                            <option value="F" <?php echo ($data['gender'] ?? '') == 'F' ? 'selected' : ''; ?>>Femenino</option>
                            <option value="Other" <?php echo ($data['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Otro</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Tipo de Membresía</label>
                    <select name="membership_id">
                        <option value="">Sin membresía</option>
                        <?php foreach($membership_types as $type): ?>
                            <option value="<?php echo $type['id']; ?>" 
                                <?php echo ($data['membership_id'] ?? '') == $type['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['name'] . ' - $' . $type['price']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Contacto de Emergencia</label>
                        <input type="text" name="emergency_contact_name" value="<?php echo $data['emergency_contact_name'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Teléfono Emergencia</label>
                        <input type="tel" name="emergency_contact_phone" value="<?php echo $data['emergency_contact_phone'] ?? ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Condiciones Médicas</label>
                    <textarea name="medical_conditions" placeholder="Alergias, lesiones, etc."><?php echo $data['medical_conditions'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn">Guardar Miembro</button>
                    <a href="members.php" class="btn secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
</body>
</html>