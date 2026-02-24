<?php
session_start();
include("conexion.php"); 

/* Aca es donde se maneja el acceso y los usuarios */

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $usuario  = trim($_POST["usuario"]);
    $password = $_POST["password"];

    $sql = "SELECT u.id_usuario, u.usuario, u.password_hash, u.estado, u.id_rol,
                   r.nombre AS rol
            FROM usuarios u
            INNER JOIN roles r ON u.id_rol = r.id_rol
            WHERE u.usuario = ? AND u.estado = 1";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($fila = $resultado->fetch_assoc()) {

        if (password_verify($password, $fila['password_hash'])) {

            $_SESSION['id_usuario'] = $fila['id_usuario'];
            $_SESSION['usuario']   = $fila['usuario'];
            $_SESSION['rol']       = $fila['rol'];
            $_SESSION['id_rol']    = $fila['id_rol']; // Guardamos también el ID del rol

            // Redirección según el rol
            // Asumiendo que rol 1 = MASTER, rol 2 = ADMIN, otros roles = EMPLEADO
            if ($fila['id_rol'] == 1) {
                // Usuario MASTER
                header("Location: panel.php");
                exit;
            } elseif ($fila['id_rol'] == 2) {
                // Usuario ADMIN
                header("Location: panel_admin.php");
                exit;
            } else {
                // Usuario EMPLEADO normal
                header("Location: panel_empleado.php");
                exit;
            }

        } else {
            $error = "Contraseña incorrecta";
        }

    } else {
        $error = "Usuario no encontrado o inactivo";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>

<!-- El login  -->
<meta charset="UTF-8">
<title>Login - Sistema Deditos</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../estilo/style.css">
</head>

<body>

<div class="login-box">

    <img src="../img/deditos.png" class="logo-vina" alt="Logo">

    <h1>Sistema Deditos</h1>

    <?php if ($error): ?>
        <p style="color:#ffb3b3;text-align:center;margin-bottom:15px;">
            <?= $error ?>
        </p>
    <?php endif; ?>

    <form method="POST">

        <div class="form-group">
            <label>Usuario</label>
            <input type="text" name="usuario" required>
        </div>

        <div class="form-group password-box">
            <label>Contraseña</label>
            <input type="password" name="password" id="password" required>
            <span class="toggle-password" onclick="togglePassword()">VER</span>
        </div>

        <button type="submit">Ingresar</button>

    </form>

    <div class="footer-text">
        Sistema institucional - Deditos
    </div>

</div>

<script>
function togglePassword() {
    const input = document.getElementById("password");
    input.type = input.type === "password" ? "text" : "password";
}
</script>

</body>
</html>