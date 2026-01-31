<?php
/**
 * index.php - Página principal del Sistema de Gestión de Gimnasio
 * Controla el routing básico y redirige según la sesión
 */

// Activar mostrar errores para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();

// Definir constante para rutas
define('BASE_PATH', __DIR__);

// Cargar configuración y clases
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/auth.php';

// Inicializar objetos
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// Determinar a dónde redirigir
if ($auth->isLoggedIn()) {
    // Si ya está logueado, ir al dashboard
    header("Location: dashboard.php");
    exit();
} else {
    // Si no está logueado, mostrar página de inicio pública
    // o redirigir al login
    header("Location: login.php");
    exit();
}

// En caso de que no redirija, mostrar contenido de respaldo
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión de Gimnasio</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .landing-page {
            min-height: 100vh;
            background: linear-gradient(135deg, #1a237e 0%, #4a148c 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 20px;
        }
        
        .landing-content {
            max-width: 800px;
            background: rgba(255, 255, 255, 0.1);
            padding: 40px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .landing-logo {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 20px;
            color: #fff;
        }
        
        .landing-tagline {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .landing-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 40px 0;
        }
        
        .feature {
            background: rgba(255, 255, 255, 0.15);
            padding: 20px;
            border-radius: 10px;
            transition: transform 0.3s, background 0.3s;
        }
        
        .feature:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.2);
        }
        
        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .landing-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .landing-btn {
            padding: 15px 30px;
            background: #ff4081;
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: bold;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .landing-btn:hover {
            background: transparent;
            border-color: #ff4081;
            transform: scale(1.05);
        }
        
        .landing-btn.secondary {
            background: transparent;
            border: 2px solid white;
        }
        
        .landing-btn.secondary:hover {
            background: white;
            color: #1a237e;
        }
    </style>
</head>
<body>
    <div class="landing-page">
        <div class="landing-content">
            <div class="landing-logo">
                💪 GymPro Manager
            </div>
            
            <div class="landing-tagline">
                Sistema profesional de gestión para gimnasios. Control de miembros, pagos, asistencia y más.
            </div>
            
            <div class="landing-features">
                <div class="feature">
                    <div class="feature-icon">👥</div>
                    <h3>Gestión de Miembros</h3>
                    <p>Registro completo con historial médico y contactos de emergencia</p>
                </div>
                
                <div class="feature">
                    <div class="feature-icon">💰</div>
                    <h3>Control de Pagos</h3>
                    <p>Seguimiento de membresías y alertas de pagos pendientes</p>
                </div>
                
                <div class="feature">
                    <div class="feature-icon">📊</div>
                    <h3>Asistencia Digital</h3>
                    <p>Registro de entrada/salida con límites de visitas</p>
                </div>
                
                <div class="feature">
                    <div class="feature-icon">📈</div>
                    <h3>Reportes Avanzados</h3>
                    <p>Estadísticas y análisis para toma de decisiones</p>
                </div>
            </div>
            
            <div class="landing-buttons">
                <a href="login.php" class="landing-btn">Iniciar Sesión</a>
                <a href="register.php" class="landing-btn secondary">Registrarse</a>
                <a href="diagnostic.php" class="landing-btn secondary">Diagnóstico del Sistema</a>
            </div>
            
            <div style="margin-top: 40px; font-size: 0.9rem; opacity: 0.7;">
                <p>Proyecto Universitario - Sistema de Gestión para Gimnasio</p>
                <p>PHP 7.4+ | MySQL | Bootstrap | PDO</p>
            </div>
        </div>
    </div>
    
    <script>
        // Animación simple para los features
        document.addEventListener('DOMContentLoaded', function() {
            const features = document.querySelectorAll('.feature');
            features.forEach((feature, index) => {
                feature.style.opacity = '0';
                feature.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    feature.style.transition = 'opacity 0.5s, transform 0.5s';
                    feature.style.opacity = '1';
                    feature.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>