<?php

session_start();
include("../desing/conexion.php");

// Verificar que la conexión existe
if (!$conexion) {
    die("Error de conexión a la base de datos");
}

// Obtener estadísticas básicas con manejo de errores
$total_empleados = 0;
$total_archivos = 0;
$total_registros_hoy = 0;
$total_situaciones = 0;

// Consultas con verificación
$result_empleados = $conexion->query("SELECT COUNT(*) as total FROM empleados");
if ($result_empleados) {
    $total_empleados = $result_empleados->fetch_assoc()['total'];
}

$result_archivos = $conexion->query("SELECT COUNT(*) as total FROM archivos_importados");
if ($result_archivos) {
    $total_archivos = $result_archivos->fetch_assoc()['total'];
}

$result_registros = $conexion->query("SELECT COUNT(*) as total FROM registros_biometricos WHERE fecha = CURDATE()");
if ($result_registros) {
    $total_registros_hoy = $result_registros->fetch_assoc()['total'];
}

$result_situaciones = $conexion->query("SELECT COUNT(*) as total FROM situaciones_marcajes WHERE fecha = CURDATE()");
if ($result_situaciones) {
    $total_situaciones = $result_situaciones->fetch_assoc()['total'];
}

// Obtener nombre del admin si existe en la sesión
$nombre_admin = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Administrador - Sistema Viña</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

</head>
<body>

<div class="panel-container">
    <div class="panel-box">

    
        <div class="panel-header">
            <h1><i class="fas fa-user-shield"></i> Panel de Administración</h1>  
            <p><i class="fas fa-building"></i> Sistema de gestión de personal - Viña / Usuario ADMIN </p>
            <div class="current-date">
                <i class="fas fa-calendar-alt"></i> <?= date('d/m/Y') ?>
            </div>
        </div>

        <!-- Estadísticas rápidas -->
        <div class="stats-row">
            <div class="stat-item">
                <span class="stat-number"><?= $total_empleados ?></span>
                <span class="stat-label"><i class="fas fa-users"></i> Empleados</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?= $total_archivos ?></span>
                <span class="stat-label"><i class="fas fa-file-import"></i> Archivos</span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?= $total_situaciones ?></span>
                <span class="stat-label"><i class="fas fa-tag"></i> Situaciones</span>
            </div>
        </div>

        <!-- Módulos principales -->
        <div class="sections">
            <div class="card">
                <h3>Empleados</h3>
                <p>Gestión completa del personal.</p>
                <a href="empleados.php">Administrar</a>
            </div>

            <div class="card">
                <h3> Permisos </h3>
                <p>Auscencias, vacaciones, incapacidades, etc. </p>
                <a href="situaciones.php">Gestionar</a>
            </div>

            <div class="card">
                <h3>Configuración</h3>
                <p>Ajustes del sistema y parámetros generales.</p>
                <a href="configuracion.php">Configurar</a>
            </div>

            <div class="card">
                <h3>Importar</h3>
                <p>Carga de archivos biométricos al sistema.</p>
                <a href="importar_archivo.php">Importar</a>
            </div>

            <div class="card">
                <h3>Horarios</h3>
                <p>Gestión de turnos y jornadas laborales.</p>
                <a href="horarios.php">Gestionar</a>
            </div>

            <!-- Botón de cerrar sesión -->
            <div class="logout-container">
                <a href="logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    Cerrar Sesión
                </a>
            </div>
        </div>

    </div>
</div>

</body>
</html>



    <style>
        /* Estilo basado en el panel general */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: linear-gradient(135deg, #526513, #06a388);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        /* contenedor principal */
        .panel-container {
            min-height: 100vh;
            padding: 40px 20px;
        }

        .panel-box {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* encabezado - estilo del panel general */
        .panel-header {
            background: rgb(16, 40, 40);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(204, 206, 108, 0.55);
            margin-bottom: 30px;
            text-align: center;
        }

        .panel-header h1 {
            margin: 0;
            font-size: 34px;
            color: #fdfeff;
        }

        .panel-header h1 i {
            color: #ffd700;
            margin-right: 10px;
        }

        .panel-header p {
            margin-top: 10px;
            color: #f4ecec;
            font-size: 16px;
        }

        /* Estadísticas en fila - estilo simplificado */
        .stats-row {
            display: flex;
            justify-content: space-around;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            flex-wrap: wrap;
            gap: 15px;
        }

        .stat-item {
            text-align: center;
            color: white;
            min-width: 120px;
        }

        .stat-number {
            display: block;
            font-size: 32px;
            font-weight: bold;
            color: #ffd700;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .stat-label i {
            margin-right: 5px;
            color: #ffd700;
        }

        /* grid 4x2 como el panel general */
        .sections {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
        }

        /* tarjetas - estilo del panel general sin iconos */
        .card {
            background: white;
            padding: 30px 20px;
            border-radius: 14px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.18);
            transition: transform 0.3s, box-shadow 0.3s;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .card:hover {
            transform: translateY(-6px);
            box-shadow: 0 14px 30px rgba(0,0,0,0.3);
        }

        .card h3 {
            margin: 0 0 10px 0;
            color: #2c7be5;
            font-size: 22px;
            font-weight: 600;
        }

        .card p {
            color: #555;
            font-size: 14px;
            margin: 0 0 20px 0;
            flex-grow: 1;
            line-height: 1.5;
        }

        .card a {
            display: inline-block;
            padding: 10px 25px;
            background: #2c7be5;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.3s ease;
            width: 80%;
            border: none;
            cursor: pointer;
        }

        .card a:hover {
            background: #1a68d1;
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(44, 123, 229, 0.4);
        }

        /* Contenedor del botón de cerrar sesión */
        .logout-container {
            grid-column: span 4;
            text-align: center;
            margin-top: 20px;
            padding: 20px 0;
        }

        .btn-logout {
            display: inline-block;
            padding: 12px 40px;
            background: rgba(255,255,255,0.15);
            color: white;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            border: 2px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(5px);
        }

        .btn-logout:hover {
            background: rgba(255,99,71,0.8);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            border-color: white;
        }

        .btn-logout i {
            margin-right: 10px;
        }

        /* Mensaje de bienvenida opcional */
        .welcome-message {
            color: white;
            text-align: center;
            margin-bottom: 20px;
            font-size: 18px;
        }

        .welcome-message i {
            color: #ffd700;
            margin-right: 8px;
        }

        /* Fecha actual */
        .current-date {
            color: #ffd700;
            font-size: 14px;
            margin-top: 10px;
        }

        .current-date i {
            margin-right: 5px;
        }

        /* Responsive */
        @media (max-width: 1100px) {
            .sections {
                grid-template-columns: repeat(2, 1fr);
            }
            .logout-container {
                grid-column: span 2;
            }
        }

        @media (max-width: 600px) {
            .sections {
                grid-template-columns: 1fr;
            }
            .logout-container {
                grid-column: span 1;
            }
            .stats-row {
                flex-direction: column;
                align-items: center;
            }
            .stat-item {
                width: 100%;
            }
            .panel-header h1 {
                font-size: 24px;
            }
            .panel-header {
                padding: 20px;
            }
            .card {
                padding: 25px 15px;
            }
            .card h3 {
                font-size: 20px;
            }
            .card a {
                width: 90%;
                padding: 10px 15px;
            }
        }

        /* Animación sutil */
        .card, .stat-item {
            animation: fadeIn 0.5s ease forwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>