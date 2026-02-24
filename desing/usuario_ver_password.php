<?php
include("conexion.php");

// Verificar si se recibió el ID del usuario
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de usuario no válido");
}

$id_usuario = intval($_GET['id']);

// Consulta para obtener los datos del usuario
$sql = "
SELECT u.id_usuario, u.usuario, u.password, u.estado, r.nombre AS nombre_rol
FROM usuarios u
LEFT JOIN roles r ON u.id_rol = r.id_rol
WHERE u.id_usuario = ?
";

$stmt = $conexion->prepare($sql);
if (!$stmt) {
    die("Error en la consulta: " . $conexion->error);
}

$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Usuario no encontrado");
}

$usuario = $result->fetch_assoc();
$stmt->close();

// Asegurarnos de que los campos existan
$id = isset($usuario['id_usuario']) ? $usuario['id_usuario'] : '';
$username = isset($usuario['usuario']) ? $usuario['usuario'] : '';
$password_visible = isset($usuario['password']) ? $usuario['password'] : 'No disponible';
$estado = isset($usuario['estado']) ? $usuario['estado'] : 0;
$rol = isset($usuario['nombre_rol']) ? $usuario['nombre_rol'] : 'Sin rol asignado';

// Para depuración - muestra lo que obtienes de la base de datos
// echo "<pre>";
// print_r($usuario);
// echo "</pre>";
// exit();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ver Contraseña</title>
<style>
body {
    font-family: Arial;
    background: #f2f2f2;
    margin: 0;
    padding: 20px;
}

.container {
    max-width: 600px;
    margin: 30px auto;
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 8px 18px rgba(0,0,0,.15);
}

.header {
    text-align: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #243a5e;
}

.header h1 {
    color: #243a5e;
    margin-bottom: 10px;
}

.info-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 25px;
}

.info-table td {
    padding: 12px 15px;
    border: 1px solid #ddd;
}

.info-table tr:nth-child(even) {
    background-color: #f9f9f9;
}

.label {
    font-weight: bold;
    width: 40%;
    background-color: #f5f5f5;
}

.password-box {
    background-color: #fff3cd;
    border: 2px solid #ffeaa7;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    margin: 20px 0;
    position: relative;
}

.password-text {
    font-family: 'Courier New', monospace;
    font-size: 1.5rem;
    font-weight: bold;
    letter-spacing: 3px;
    color: #856404;
    padding: 10px;
    margin: 10px 0;
}

.eye-toggle {
    background: none;
    border: none;
    font-size: 1.2rem;
    color: #718096;
    cursor: pointer;
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
}

.actions {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-top: 25px;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    font-weight: bold;
    color: white;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-primary {
    background: #27ae60;
}

.btn-secondary {
    background: #243a5e;
}

.btn:hover {
    opacity: 0.9;
    transform: translateY(-2px);
    transition: all 0.3s;
}

.warning {
    background-color: #f8d7da;
    color: #721c24;
    padding: 15px;
    border-radius: 6px;
    border: 1px solid #f5c6cb;
    margin-top: 20px;
    font-size: 0.9rem;
}

.error-box {
    background-color: #f8d7da;
    color: #721c24;
    padding: 15px;
    border-radius: 6px;
    border: 1px solid #f5c6cb;
    margin: 20px 0;
}
</style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>🔑 Ver Contraseña de Usuario</h1>
        <p>Información confidencial - Use con precaución</p>
    </div>
    
    <?php if(empty($username)): ?>
    <div class="error-box">
        <strong>Error:</strong> No se pudieron obtener los datos del usuario. Verifique que el usuario exista en la base de datos.
    </div>
    <?php endif; ?>
    
    <table class="info-table">
        <tr>
            <td class="label">ID Usuario:</td>
            <td><?php echo htmlspecialchars($id); ?></td>
        </tr>
        <tr>
            <td class="label">Usuario:</td>
            <td><?php echo htmlspecialchars($username); ?></td>
        </tr>
        <tr>
            <td class="label">Rol:</td>
            <td><?php echo htmlspecialchars($rol); ?></td>
        </tr>
        <tr>
            <td class="label">Estado:</td>
            <td><?php echo $estado ? '<span style="color:green;">Activo</span>' : '<span style="color:red;">Inactivo</span>'; ?></td>
        </tr>
    </table>
    
    <div class="password-box">
        <h3>Contraseña del usuario</h3>
        <div class="password-text" id="passwordText"><?php echo htmlspecialchars($password_visible); ?></div>
        <button class="eye-toggle" id="togglePassword" title="Mostrar/ocultar contraseña">
            👁️
        </button>
        <p><small>Haga clic en el botón del ojo para mostrar/ocultar</small></p>
    </div>
    
    <button onclick="copiarContraseña()" style="width:100%;padding:12px;background:#4a5568;color:white;border:none;border-radius:6px;cursor:pointer;margin-bottom:15px;">
        📋 Copiar contraseña al portapapeles
    </button>
    
    <div class="warning">
        <strong>⚠ ADVERTENCIA:</strong> Esta es información sensible. Asegúrese de protegerla y no compartirla con personas no autorizadas.
    </div>
    
    <div class="actions">
        <a href="usuarios.php" class="btn btn-secondary">⬅ Volver a Usuarios</a>
        <a href="usuario_editar.php?id=<?php echo $id; ?>" class="btn btn-primary">✏ Editar este usuario</a>
    </div>
</div>

<script>
// Función para alternar visibilidad de la contraseña
const togglePassword = document.getElementById('togglePassword');
const passwordText = document.getElementById('passwordText');
let passwordVisible = true;

togglePassword.addEventListener('click', function() {
    if (passwordVisible) {
        // Ocultar contraseña
        const originalLength = "<?php echo strlen($password_visible); ?>";
        passwordText.textContent = '•'.repeat(originalLength > 0 ? originalLength : 8);
        togglePassword.textContent = '👁️‍🗨️';
        togglePassword.title = "Mostrar contraseña";
        passwordVisible = false;
    } else {
        // Mostrar contraseña
        passwordText.textContent = "<?php echo htmlspecialchars($password_visible); ?>";
        togglePassword.textContent = '👁️';
        togglePassword.title = "Ocultar contraseña";
        passwordVisible = true;
    }
});

// Función para copiar la contraseña al portapapeles
function copiarContraseña() {
    const password = "<?php echo htmlspecialchars($password_visible); ?>";
    
    // Crear un elemento de texto temporal
    const tempInput = document.createElement("input");
    tempInput.value = password;
    document.body.appendChild(tempInput);
    
    // Seleccionar y copiar el texto
    tempInput.select();
    tempInput.setSelectionRange(0, 99999); // Para dispositivos móviles
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            alert("Contraseña copiada al portapapeles");
        } else {
            alert("No se pudo copiar la contraseña. Intente manualmente.");
        }
    } catch (err) {
        alert("Error al copiar: " + err);
    }
    
    // Eliminar el elemento temporal
    document.body.removeChild(tempInput);
}
</script>

</body>
</html>