<?php
/**
 * payments.php - Gestión de pagos de membresías
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// ========== PROCESAR PAGO ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_payment'])) {
    $member_id = (int)$_POST['member_id'];
    $membership_id = (int)$_POST['membership_id'];
    $amount = (float)$_POST['amount'];
    $payment_method = $_POST['payment_method'];
    $payment_date = $_POST['payment_date'];
    $due_date = date('Y-m-d', strtotime($payment_date . ' +30 days'));
    
    try {
        $query = "INSERT INTO payments (member_id, membership_id, amount, payment_date, due_date, payment_method, status) 
                  VALUES (?, ?, ?, ?, ?, ?, 'paid')";
        $stmt = $db->prepare($query);
        $stmt->execute([$member_id, $membership_id, $amount, $payment_date, $due_date, $payment_method]);
        
        $query = "UPDATE members SET status = 'active' WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$member_id]);
        
        $_SESSION['success'] = "✅ Pago registrado exitosamente";
        header("Location: payments.php");
        exit();
    } catch(PDOException $e) {
        $_SESSION['error'] = "❌ Error: " . $e->getMessage();
    }
}

// ========== DATOS PARA EL FORMULARIO ==========
$all_members = $db->query("SELECT id, first_name, last_name FROM members ORDER BY first_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$membership_types = $db->query("SELECT id, name, price FROM membership_types")->fetchAll(PDO::FETCH_ASSOC);

// ========== OBTENER PAGOS (CON FILTROS) ==========
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$page = (int)($_GET['page'] ?? 1);
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

try {
    $query = "SELECT p.*, m.first_name, m.last_name, m.email, mt.name as membership_name 
              FROM payments p 
              JOIN members m ON p.member_id = m.id 
              JOIN membership_types mt ON p.membership_id = mt.id 
              WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $query .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR m.email LIKE ?)";
        $term = "%$search%";
        array_push($params, $term, $term, $term);
    }
    if ($status !== 'all' && !empty($status)) {
        $query .= " AND p.status = ?";
        $params[] = $status;
    }

    $query .= " ORDER BY p.payment_date DESC LIMIT $offset, $records_per_page";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_records = $db->query("SELECT COUNT(*) FROM payments")->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch(PDOException $e) { $error = "Error al cargar datos"; }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagos - Gym System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); }
        .modal-content { background:#fff; margin:5% auto; padding:25px; width:40%; border-radius:8px; }
        .stats-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:15px; margin-bottom:20px; }
        .stat-card { background:#fff; padding:15px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1); border-left:4px solid #1a237e; }
        .status-paid { background:#d4edda; color:#155724; padding:3px 8px; border-radius:10px; font-size:12px; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container" style="max-width:1100px; margin:20px auto;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h1>💰 Gestión de Pagos</h1>
            <button onclick="document.getElementById('paymentModal').style.display='block'" class="btn btn-success">＋ Nuevo Pago</button>
        </div>

        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <div class="table-container" style="margin-top:20px;">
            <table>
                <thead>
                    <tr>
                        <th>Miembro</th>
                        <th>Membresía</th>
                        <th>Monto</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($payments as $p): ?>
                    <tr>
                        <td><?php echo $p['first_name'].' '.$p['last_name']; ?></td>
                        <td><?php echo $p['membership_name']; ?></td>
                        <td>$<?php echo number_format($p['amount'], 2); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($p['payment_date'])); ?></td>
                        <td><span class="status-paid"><?php echo $p['status']; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <h3>Registrar Nuevo Pago</h3>
            <form method="POST">
                <input type="hidden" name="add_payment" value="1">
                <div class="form-group">
                    <label>Seleccionar Miembro</label>
                    <select name="member_id" required>
                        <?php foreach($all_members as $m): ?>
                            <option value="<?php echo $m['id']; ?>"><?php echo $m['first_name'].' '.$m['last_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tipo de Membresía</label>
                    <select name="membership_id" required>
                        <?php foreach($membership_types as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo $t['name'].' ($'.$t['price'].')'; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Monto pagado</label>
                    <input type="number" step="0.01" name="amount" required>
                </div>
                <div class="form-group">
                    <label>Método de Pago</label>
                    <select name="payment_method">
                        <option value="efectivo">Efectivo</option>
                        <option value="tarjeta">Tarjeta</option>
                        <option value="transferencia">Transferencia</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fecha de Pago</label>
                    <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div style="margin-top:20px;">
                    <button type="submit" class="btn btn-primary">Guardar Pago</button>
                    <button type="button" onclick="document.getElementById('paymentModal').style.display='none'" class="btn btn-secondary">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (file_exists('includes/footer.php')) include 'includes/footer.php'; ?>
</body>
</html>