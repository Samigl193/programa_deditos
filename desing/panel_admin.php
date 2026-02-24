<?php
session_start();



include("../desing/conexion.php");

// Obtener estadísticas para mostrar en el panel
$total_empleados = $conexion->query("SELECT COUNT(*) as total FROM empleados")->fetch_assoc()['total'];
$total_archivos = $conexion->query("SELECT COUNT(*) as total FROM archivos_importados")->fetch_assoc()['total'];
$total_registros_hoy = $conexion->query("SELECT COUNT(*) as total FROM registros_biometricos WHERE fecha = CURDATE()")->fetch_assoc()['total'];
$total_situaciones = $conexion->query("SELECT COUNT(*) as total FROM situaciones_marcajes WHERE fecha = CURDATE()")->fetch_assoc()['total'];

// Obtener nombre del admin ( validaria la sesión)
$nombre_admin = "Administrador";

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Panel Administrador - Sistema Viña</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<!-- Font  para iconos -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<!-- Chart.js para gráficas -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


</head>
<body>

<div class="panel-container">
    <div class="panel-box">

        <!-- Encabezado -->
        <div class="panel-header">
            <h1><i class="fas fa-user-shield"></i> Panel de Administración</h1>  
            <p><i class="fas fa-building"></i> Sistema de gestión de personal - Viña</p>
        </div>

        <!-- Tarjeta de bienvenida -->
        <div class="welcome-card">
            <div class="welcome-text">
                <h2><i class="fas fa-hand-peace"></i> ¡Bienvenido, <?= htmlspecialchars($nombre_admin) ?>!</h2>
                <p><i class="fas fa-chart-line"></i> Panel de control y administración general del sistema</p>
            </div>
            <div class="date-badge">
                <i class="fas fa-calendar-alt"></i> <?= date('d/m/Y') ?>
            </div>
        </div>

        <!-- Estadísticas rápidas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?= $total_empleados ?></div>
                <div class="stat-label">Empleados</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-file-import"></i></div>
                <div class="stat-number"><?= $total_archivos ?></div>
                <div class="stat-label">Archivos Importados</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-fingerprint"></i></div>
                <div class="stat-number"><?= $total_registros_hoy ?></div>
                <div class="stat-label">Marcajes Hoy</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-tag"></i></div>
                <div class="stat-number"><?= $total_situaciones ?></div>
                <div class="stat-label">Situaciones Hoy</div>
            </div>
        </div>

        <!-- Módulos principales del administrador -->
        <div class="sections">

            <!-- Tarjeta de EMPLEADOS -->
            <div class="card">
                <i class="fas fa-user-tie"></i>
                <h3>Empleados</h3>
                <p>Gestión completa de empleados: alta, baja, modificación y consulta. Administra la información laboral.</p>
                <a href="empleados.php">
                    <i class="fas fa-arrow-right" style="font-size: 14px; margin-right: 8px;"></i>
                    Gestionar Empleados
                </a>
            </div>

            <!-- Tarjeta de MARCACIONES -->
            <div class="card">
                <i class="fas fa-clock"></i>
                <h3>Marcaciones</h3>
                <p>Visualiza y edita las marcaciones biométricas. Control de asistencia y horarios de los empleados.</p>
                <a href="marcaciones.php">
                    <i class="fas fa-arrow-right" style="font-size: 14px; margin-right: 8px;"></i>
                    Ver Marcaciones
                </a>
            </div>

            <!-- Tarjeta de REPORTES -->
            <div class="card">
                <i class="fas fa-chart-bar"></i>
                <h3>Reportes</h3>
                <p>Genera reportes detallados de asistencia, tardanzas, permisos y situaciones especiales.</p>
                <a href="reportes_admin.php">
                    <i class="fas fa-arrow-right" style="font-size: 14px; margin-right: 8px;"></i>
                    Generar Reportes
                </a>
            </div>

            <!-- Tarjeta de SITUACIONES -->
            <div class="card">
                <i class="fas fa-tags"></i>
                <h3>Situaciones</h3>
                <p>Administra las situaciones de los empleados: permisos, vacaciones, enfermedades, incapacidades.</p>
                <a href="situaciones.php">
                    <i class="fas fa-arrow-right" style="font-size: 14px; margin-right: 8px;"></i>
                    Gestionar Situaciones
                </a>
            </div>

            <!-- Tarjeta de PERSONALIZACIÓN -->
            <div class="card">
                <i class="fas fa-palette"></i>
                <h3>Personalización</h3>
                <p>Personaliza colores y textos de las situaciones para una mejor identificación visual.</p>
                <a href="personalizar_situaciones.php">
                    <i class="fas fa-arrow-right" style="font-size: 14px; margin-right: 8px;"></i>
                    Personalizar
                </a>
            </div>

            <!-- Tarjeta de CONFIGURACIÓN -->
            <div class="card">
                <i class="fas fa-cogs"></i>
                <h3>Configuración</h3>
                <p>Ajustes del sistema, horarios, parámetros generales y administración de usuarios.</p>
                <a href="configuracion.php">
                    <i class="fas fa-arrow-right" style="font-size: 14px; margin-right: 8px;"></i>
                    Configurar
                </a>
            </div>
        </div>

        <!-- Gráfica de ejemplo -->
        <div class="chart-container">
            <h3><i class="fas fa-chart-pie"></i> Actividad de la Semana</h3>
            <div class="chart-wrapper">
                <canvas id="activityChart"></canvas>
            </div>
        </div>

        <!-- Accesos rápidos -->
        <div class="quick-actions">
            <h3><i class="fas fa-bolt"></i> Accesos Rápidos</h3>
            <div class="actions-grid">
                <a href="importar_archivo.php" class="action-btn">
                    <i class="fas fa-file-upload"></i>
                    Importar Archivo
                </a>
                <a href="reporte_empleado.php?id=<?= $total_empleados > 0 ? 1 : 0 ?>" class="action-btn">
                    <i class="fas fa-file-pdf"></i>
                    Ver Reporte
                </a>
                <a href="editar_marcajes.php" class="action-btn">
                    <i class="fas fa-pen"></i>
                    Editar Marcajes
                </a>
                <a href="nuevo_empleado.php" class="action-btn">
                    <i class="fas fa-user-plus"></i>
                    Nuevo Empleado
                </a>
                <a href="horarios.php" class="action-btn">
                    <i class="fas fa-calendar-alt"></i>
                    Horarios
                </a>
                <a href="backup.php" class="action-btn">
                    <i class="fas fa-database"></i>
                    Backup
                </a>
            </div>
        </div>

        <!-- Botón de cerrar sesión -->
        <div style="text-align: center; margin-top: 30px;">
            <a href="logout.php" class="btn-logout">
                <i class="fas fa-sign-out-alt" style="margin-right: 8px;"></i>
                Cerrar Sesión
            </a>
        </div>

    </div>
</div>

<script>
    // Gráfica de actividad semanal
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('activityChart').getContext('2d');
        
        // Datos de ejemplo - en producción vendrían de la BD
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
                datasets: [
                    {
                        label: 'Marcajes',
                        data: [65, 72, 68, 85, 90, 45],
                        borderColor: '#4299e1',
                        backgroundColor: 'rgba(66, 153, 225, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#4299e1',
                        pointBorderColor: 'white',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    },
                    {
                        label: 'Situaciones',
                        data: [12, 8, 15, 10, 18, 5],
                        borderColor: '#ffd700',
                        backgroundColor: 'rgba(255, 215, 0, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#ffd700',
                        pointBorderColor: 'white',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 6
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    });
</script>

</body>
</html>


<style>
    
    /* Estilo general */
    body {
        margin: 0;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #1e3c72, #2a5298);
        min-height: 100vh;
    }

    /* contenedor principal */
    .panel-container {
        min-height: 100vh;
        padding: 30px 20px;
    }

    .panel-box {
        max-width: 1400px;
        width: 100%;
        margin: 0 auto;
    }

    /* encabezado */
    .panel-header {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        padding: 30px;
        border-radius: 20px;
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
        margin-bottom: 30px;
        text-align: center;
        border-bottom: 5px solid #ffd700;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .panel-header h1 {
        margin: 0;
        font-size: 42px;
        color: white;
        font-weight: 600;
        letter-spacing: 1px;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    }

    .panel-header p {
        margin-top: 15px;
        color: #e0e0e0;
        font-size: 18px;
        font-weight: 300;
    }

    .panel-header i {
        color: #ffd700;
        margin-right: 10px;
    }

    /* Tarjeta de bienvenida */
    .welcome-card {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 20px;
        padding: 25px 30px;
        margin-bottom: 30px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 20px;
        border-left: 8px solid #ffd700;
    }

    .welcome-text h2 {
        margin: 0;
        color: #1e3c72;
        font-size: 28px;
        font-weight: 600;
    }

    .welcome-text p {
        margin: 10px 0 0;
        color: #666;
        font-size: 16px;
    }

    .welcome-text i {
        color: #ffd700;
        margin-right: 8px;
    }

    .date-badge {
        background: #1e3c72;
        color: white;
        padding: 12px 25px;
        border-radius: 50px;
        font-size: 16px;
        font-weight: 500;
        box-shadow: 0 5px 15px rgba(30, 60, 114, 0.3);
    }

    .date-badge i {
        color: #ffd700;
        margin-right: 8px;
    }

    /* Tarjetas de estadísticas */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 25px;
        margin-bottom: 40px;
    }

    .stat-card {
        background: white;
        padding: 25px;
        border-radius: 20px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        text-align: center;
        transition: all 0.3s ease;
        border-bottom: 5px solid transparent;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: rgba(255, 215, 0, 0.03);
        transform: rotate(45deg);
        transition: all 0.6s ease;
    }

    .stat-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 35px rgba(0,0,0,0.2);
        border-bottom: 5px solid #ffd700;
    }

    .stat-card:nth-child(1) { border-bottom-color: #4299e1; }
    .stat-card:nth-child(2) { border-bottom-color: #48bb78; }
    .stat-card:nth-child(3) { border-bottom-color: #ed8936; }
    .stat-card:nth-child(4) { border-bottom-color: #9f7aea; }

    .stat-icon {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        font-size: 30px;
        position: relative;
        z-index: 1;
    }

    .stat-card:nth-child(1) .stat-icon {
        background: rgba(66, 153, 225, 0.1);
        color: #4299e1;
    }
    .stat-card:nth-child(2) .stat-icon {
        background: rgba(72, 187, 120, 0.1);
        color: #48bb78;
    }
    .stat-card:nth-child(3) .stat-icon {
        background: rgba(237, 137, 54, 0.1);
        color: #ed8936;
    }
    .stat-card:nth-child(4) .stat-icon {
        background: rgba(159, 122, 234, 0.1);
        color: #9f7aea;
    }

    .stat-number {
        font-size: 36px;
        font-weight: 700;
        color: #1e3c72;
        margin-bottom: 5px;
        position: relative;
        z-index: 1;
    }

    .stat-label {
        color: #666;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 1px;
        position: relative;
        z-index: 1;
    }

    /* grid para las tarjetas de módulos - 3 columnas */
    .sections {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 30px;
        margin-bottom: 40px;
    }

    /* responsive para móviles */
    @media (max-width: 1024px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .sections {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 600px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        .sections {
            grid-template-columns: 1fr;
        }
        
        .panel-header h1 {
            font-size: 28px;
        }
    }

    /* tarjetas de módulos - estilo mejorado */
    .card {
        background: white;
        padding: 35px 25px;
        border-radius: 20px;
        box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        transition: all 0.4s ease;
        text-align: center;
        border-left: 8px solid #4299e1;
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        align-items: center;
        height: 100%;
    }

    .card::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: rgba(66, 153, 225, 0.03);
        transform: rotate(45deg);
        transition: all 0.6s ease;
        z-index: 0;
    }

    .card:hover {
        transform: translateY(-10px) scale(1.02);
        box-shadow: 0 25px 40px rgba(0,0,0,0.25);
        border-left: 8px solid #ffd700;
    }

    .card:hover::before {
        background: rgba(255, 215, 0, 0.05);
    }

    .card i {
        font-size: 60px;
        color: #4299e1;
        margin-bottom: 20px;
        transition: all 0.4s ease;
        position: relative;
        z-index: 1;
    }

    .card:hover i {
        color: #ffd700;
        transform: scale(1.1);
    }

    .card h3 {
        margin: 15px 0 10px;
        color: #1e3c72;
        font-size: 24px;
        font-weight: 600;
        position: relative;
        z-index: 1;
    }

    .card p {
        color: #666;
        font-size: 14px;
        line-height: 1.6;
        margin-bottom: 25px;
        position: relative;
        z-index: 1;
        flex-grow: 1;
    }

    .card a {
        display: inline-block;
        margin-top: 10px;
        padding: 12px 30px;
        background: #4299e1;
        color: white;
        border-radius: 50px;
        text-decoration: none;
        font-weight: bold;
        font-size: 15px;
        transition: all 0.3s ease;
        position: relative;
        z-index: 1;
        border: 2px solid transparent;
        box-shadow: 0 5px 15px rgba(66, 153, 225, 0.3);
        width: 80%;
    }

    .card a:hover {
        background: #ffd700;
        color: #1e3c72;
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(255, 215, 0, 0.4);
        border-color: white;
    }

    /* Gráfica */
    .chart-container {
        background: white;
        border-radius: 20px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }

    .chart-container h3 {
        margin: 0 0 20px;
        color: #1e3c72;
        font-size: 20px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .chart-container h3 i {
        color: #ffd700;
    }

    .chart-wrapper {
        height: 300px;
        position: relative;
    }

    /* Accesos rápidos */
    .quick-actions {
        background: white;
        border-radius: 20px;
        padding: 25px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }

    .quick-actions h3 {
        margin: 0 0 20px;
        color: #1e3c72;
        font-size: 20px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .quick-actions h3 i {
        color: #ffd700;
    }

    .actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .action-btn {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 15px 20px;
        background: #f7fafc;
        border-radius: 12px;
        text-decoration: none;
        color: #1e3c72;
        font-weight: 500;
        transition: all 0.3s ease;
        border: 1px solid #e2e8f0;
    }

    .action-btn:hover {
        background: #ffd700;
        color: #1e3c72;
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(255, 215, 0, 0.3);
        border-color: #ffd700;
    }

    .action-btn i {
        font-size: 20px;
        color: #4299e1;
    }

    .action-btn:hover i {
        color: #1e3c72;
    }

    /* botón de cerrar sesión */
    .btn-logout {
        display: inline-block;
        margin-top: 20px;
        padding: 12px 30px;
        background: rgba(255, 255, 255, 0.15);
        color: white;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
        border: 1px solid rgba(255, 255, 255, 0.3);
        backdrop-filter: blur(5px);
    }

    .btn-logout:hover {
        background: rgba(255, 215, 0, 0.3);
        color: white;
        transform: translateY(-2px);
        border-color: #ffd700;
    }

    /* Animación de entrada */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .stat-card, .card {
        animation: fadeInUp 0.6s ease forwards;
    }

    .stat-card:nth-child(2) { animation-delay: 0.1s; }
    .stat-card:nth-child(3) { animation-delay: 0.2s; }
    .stat-card:nth-child(4) { animation-delay: 0.3s; }
    .card:nth-child(1) { animation-delay: 0.1s; }
    .card:nth-child(2) { animation-delay: 0.2s; }
    .card:nth-child(3) { animation-delay: 0.3s; }
</style>