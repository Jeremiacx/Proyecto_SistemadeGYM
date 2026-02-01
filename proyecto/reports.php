<?php
/**
 * reports.php - Sistema de reportes del gimnasio
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

$auth->requireLogin();

// Parámetros de fecha
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'monthly';

// Obtener estadísticas
$stats = [];
$monthly_data = [];
$top_members = [];

try {
    // Estadísticas generales
    $query = "SELECT 
                (SELECT COUNT(*) FROM members WHERE status = 'active') as active_members,
                (SELECT COUNT(*) FROM attendance WHERE DATE(check_in) = CURDATE()) as today_attendance,
                (SELECT SUM(amount) FROM payments WHERE payment_date = CURDATE()) as today_income,
                (SELECT COUNT(*) FROM payments WHERE status = 'pending' AND due_date < CURDATE()) as overdue_payments";
    
    $stmt = $db->query($query);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Datos mensuales para gráfico
    $query = "SELECT 
                DATE_FORMAT(payment_date, '%Y-%m') as month,
                SUM(amount) as total_income,
                COUNT(*) as payment_count
              FROM payments 
              WHERE payment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND CURDATE()
                AND status = 'paid'
              GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
              ORDER BY month";
    
    $stmt = $db->query($query);
    $monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Miembros más frecuentes
    $query = "SELECT 
                m.first_name, 
                m.last_name,
                COUNT(a.id) as visit_count
              FROM attendance a
              JOIN members m ON a.member_id = m.id
              WHERE a.check_in BETWEEN ? AND ?
              GROUP BY m.id
              ORDER BY visit_count DESC
              LIMIT 10";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$start_date, $end_date]);
    $top_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Membresías más populares
    $query = "SELECT 
                mt.name,
                COUNT(p.id) as sale_count,
                SUM(p.amount) as total_amount
              FROM payments p
              JOIN membership_types mt ON p.membership_id = mt.id
              WHERE p.payment_date BETWEEN ? AND ?
                AND p.status = 'paid'
              GROUP BY mt.id
              ORDER BY sale_count DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$start_date, $end_date]);
    $popular_memberships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    error_log("Reports error: " . $e->getMessage());
    $error = "Error al generar reportes";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Sistema de Gimnasio</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .date-filter {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #1a237e;
        }
        
        .stat-label {
            color: #666;
            margin-top: 10px;
        }
        
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .chart-title {
            margin-bottom: 20px;
            color: #333;
        }
        
        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .report-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .report-table th,
        .report-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .report-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .trend-up {
            color: #28a745;
        }
        
        .trend-down {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="report-header">
            <h1>Reportes y Estadísticas</h1>
            <div class="export-buttons">
                <button class="btn secondary" onclick="printReport()">Imprimir</button>
                <button class="btn" onclick="exportToPDF()">Exportar PDF</button>
            </div>
        </div>
        
        <!-- Filtros de fecha -->
        <div class="date-filter">
            <h3>Filtrar por fecha</h3>
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label>Fecha inicio:</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Fecha fin:</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Tipo de reporte:</label>
                    <select name="report_type">
                        <option value="monthly" <?php echo $report_type == 'monthly' ? 'selected' : ''; ?>>Mensual</option>
                        <option value="weekly" <?php echo $report_type == 'weekly' ? 'selected' : ''; ?>>Semanal</option>
                        <option value="daily" <?php echo $report_type == 'daily' ? 'selected' : ''; ?>>Diario</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn">Generar Reporte</button>
                    <button type="button" class="btn secondary" onclick="resetFilters()">Hoy</button>
                </div>
            </form>
        </div>
        
        <!-- Estadísticas principales -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['active_members'] ?? 0; ?></div>
                <div class="stat-label">Miembros Activos</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['today_attendance'] ?? 0; ?></div>
                <div class="stat-label">Asistencias Hoy</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($stats['today_income'] ?? 0, 2); ?></div>
                <div class="stat-label">Ingresos Hoy</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['overdue_payments'] ?? 0; ?></div>
                <div class="stat-label">Pagos Vencidos</div>
            </div>
        </div>
        
        <!-- Gráfico de ingresos -->
        <div class="chart-container">
            <h3 class="chart-title">Ingresos Mensuales (Últimos 6 meses)</h3>
            <canvas id="incomeChart" height="100"></canvas>
        </div>
        
        <!-- Reportes detallados -->
        <div class="report-grid">
            <!-- Miembros más frecuentes -->
            <div class="report-card">
                <h3>Miembros Más Frecuentes</h3>
                <p>Período: <?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?></p>
                
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Miembro</th>
                            <th>Visitas</th>
                            <th>% Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_visits = array_sum(array_column($top_members, 'visit_count'));
                        foreach($top_members as $member): 
                            $percentage = $total_visits > 0 ? round(($member['visit_count'] / $total_visits) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                            <td><?php echo $member['visit_count']; ?></td>
                            <td><?php echo $percentage; ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Membresías más populares -->
            <div class="report-card">
                <h3>Membresías Más Vendidas</h3>
                <p>Período: <?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?></p>
                
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Membresía</th>
                            <th>Ventas</th>
                            <th>Ingresos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($popular_memberships as $membership): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($membership['name']); ?></td>
                            <td><?php echo $membership['sale_count']; ?></td>
                            <td>$<?php echo number_format($membership['total_amount'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Reporte de crecimiento -->
        <div class="report-card">
            <h3>Crecimiento Mensual</h3>
            <div style="overflow-x: auto;">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Mes</th>
                            <th>Nuevos Miembros</th>
                            <th>Ingresos</th>
                            <th>Asistencias</th>
                            <th>Tendencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            // Obtener datos de crecimiento mensual
                            $query = "SELECT 
                                        DATE_FORMAT(registration_date, '%Y-%m') as month,
                                        COUNT(*) as new_members,
                                        (SELECT SUM(amount) FROM payments 
                                         WHERE DATE_FORMAT(payment_date, '%Y-%m') = DATE_FORMAT(registration_date, '%Y-%m')) as income,
                                        (SELECT COUNT(*) FROM attendance 
                                         WHERE DATE_FORMAT(check_in, '%Y-%m') = DATE_FORMAT(registration_date, '%Y-%m')) as attendance
                                      FROM members
                                      WHERE registration_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                                      GROUP BY DATE_FORMAT(registration_date, '%Y-%m')
                                      ORDER BY month DESC
                                      LIMIT 6";
                            
                            $stmt = $db->query($query);
                            $growth_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach($growth_data as $data):
                                $trend_class = 'trend-up';
                                $trend_icon = '↑';
                        ?>
                        <tr>
                            <td><?php echo date('F Y', strtotime($data['month'] . '-01')); ?></td>
                            <td><?php echo $data['new_members']; ?></td>
                            <td>$<?php echo number_format($data['income'] ?? 0, 2); ?></td>
                            <td><?php echo $data['attendance'] ?? 0; ?></td>
                            <td class="<?php echo $trend_class; ?>"><?php echo $trend_icon; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php } catch(Exception $e) { ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #666;">Error al cargar datos</td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        // Gráfico de ingresos
        const monthlyData = <?php echo json_encode($monthly_data); ?>;
        const months = monthlyData.map(item => {
            const date = new Date(item.month + '-01');
            return date.toLocaleString('es-ES', { month: 'short', year: 'numeric' });
        });
        const incomes = monthlyData.map(item => parseFloat(item.total_income));
        
        const ctx = document.getElementById('incomeChart').getContext('2d');
        const incomeChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Ingresos ($)',
                    data: incomes,
                    borderColor: '#1a237e',
                    backgroundColor: 'rgba(26, 35, 126, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Ingresos: $${context.raw.toFixed(2)}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                }
            }
        });
        
        // Funciones de exportación
        function printReport() {
            window.print();
        }
        
        function exportToPDF() {
            alert('La exportación a PDF se implementaría con una librería como jsPDF');
            // En una implementación real, usar jsPDF o enviar al servidor
        }
        
        function resetFilters() {
            window.location.href = 'reports.php';
        }
        
        // Actualizar automáticamente cada 5 minutos
        setTimeout(() => {
            window.location.reload();
        }, 300000); // 5 minutos
        
        // Tooltips para tablas
        document.querySelectorAll('.report-table td').forEach(cell => {
            if (cell.textContent.length > 30) {
                cell.title = cell.textContent;
                cell.style.cursor = 'help';
            }
        });
    </script>
    
    <style media="print">
        @media print {
            header, footer, .date-filter, .export-buttons {
                display: none !important;
            }
            
            .container {
                padding: 0;
                margin: 0;
            }
            
            .report-card, .chart-container, .stat-card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                page-break-inside: avoid;
            }
            
            .stat-card {
                display: inline-block !important;
                width: 48% !important;
                margin: 1% !important;
            }
            
            h1 {
                text-align: center;
            }
            
            .report-header h1::after {
                content: " - <?php echo date('d/m/Y H:i'); ?>";
                font-size: 0.8em;
                color: #666;
            }
        }
    </style>
</body>
</html>