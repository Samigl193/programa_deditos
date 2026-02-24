<?php
session_start();
include("conexion.php");

$id=(int)$_GET['id'];

$res=$conexion->query(
 "SELECT * FROM usuarios WHERE id_usuario=$id"
);

$user=$res->fetch_assoc();

/* Aca es donde se puede editar al usuario del empleado */
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Editar Usuario</title>

<style>
body{font-family:Arial;background:#f2f2f2}
.form{
 max-width:400px;margin:50px auto;background:white;
 padding:25px;border-radius:10px
}
input,select{width:100%;padding:10px;margin-bottom:12px}
button{padding:10px;background:#3498db;border:none;color:white}
</style>
</head>

<body>

<div class="form">

<h2>Editar Usuario</h2>

<form method="POST" action="usuario_guardar.php">

<input type="hidden" name="id" value="<?= $id ?>">

<label>Usuario</label>
<input name="usuario" value="<?= $user['usuario'] ?>">

<label>Estado</label>
<select name="estado">
<option value="1" <?= $user['estado']?'selected':'' ?>>Activo</option>
<option value="0" <?= !$user['estado']?'selected':'' ?>>Inactivo</option>
</select>

<button>Guardar</button>

</form>

</div>
</body>
</html>





