<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Panel - Sistema Viña</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">


<link rel="stylesheet" href="../estilo/style.css">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">


</head>

<body>

<div class="panel-container">

    <div class="panel-box">

        <div class="panel-header">
            <h1>Bienvenido al Sistema Viña</h1>  
            <p>Panel principal de administración institucional / Usuario MASTER </p>
        </div>

        <!-- Secciones del panel -->
        <div class="sections">

            <div class="card">
                <h3>Empleados</h3>
                <p>Gestión del personal.</p>
                <a href="empleados.php">Administrar</a>
            </div>

            <div class="card">
                <h3>Usuarios</h3>
                <p>Cuentas y permisos.</p>
                <a href="usuarios.php">Administrar</a>
            </div>

            <div class="card">
                <h3>Reportes</h3>
                <p>Informes y estadísticas.</p>
                <a href="#">Ver</a>
            </div>

            <div class="card">
                <h3>Asistencia</h3>
                <p>Entradas, salidas, ausencias, permisos</p>
                <a href="#">Consultar</a>
            </div>

            <div class="card">
                <h3>Horarios</h3>
                <p>Turnos y jornadas.</p>
                <a href="horarios.html">Gestionar</a>
            </div>

            <div class="card">
                <h3>Configuración</h3>
                <p>Ajustes institucionales.</p>
                <a href="#">Configurar</a>
            </div>

            <div class="card">
                <h3>Permisos</h3>
                <p>justificaciones</p>
                <a href="#">Ver</a>
            </div>

            <div class="card">
                <h3>Registro biometrico</h3>
                <p>datos del sistema biometrico.</p>
                <a href="#">Gestionar</a>
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
/* Estilo */
body {
    margin: 0;
    background: linear-gradient(135deg, #526513, #06a388);
}

/* contenedor principal */
.panel-container {
    min-height: 100vh;
    padding: 40px 20px;
}

.panel-box {
    max-width: 1200px;
    margin: auto;
}

/* encabezado */
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

.panel-header p {
    margin-top: 10px;
    color: #f4ecec;
    font-size: 16px;
}

/* grid 4x2 */
.sections {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 25px;
}

/* responsive */
@media (max-width: 1100px) {
    .sections {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    .sections {
        grid-template-columns: 1fr;
    }
}

/* tarjetas */
.card {
    background: white;
    padding: 25px;
    border-radius: 14px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.18);
    transition: transform 0.3s, box-shadow 0.3s;
    text-align: center;
}

.card:hover {
    transform: translateY(-6px);
    box-shadow: 0 14px 30px rgba(0,0,0,0.3);
}

.card h3 {
    margin-top: 0;
    color: #2c7be5;
}

.card p {
    color: #555;
}

.card a {
    display: inline-block;
    margin-top: 15px;
    padding: 10px 18px;
    background: #2c7be5;
    color: white;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
}

.card a:hover {
    background: #1a68d1;
}

/* Estilos para el botón de cerrar sesión */
.logout-container {
    grid-column: span 4;
    text-align: center;
    margin-top: 20px;
    padding: 20px 0;
}

.btn-logout {
    display: inline-block;
    padding: 12px 30px;
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

@media (max-width: 1100px) {
    .logout-container {
        grid-column: span 2;
    }
}

@media (max-width: 600px) {
    .logout-container {
        grid-column: span 1;
    }
}
</style>