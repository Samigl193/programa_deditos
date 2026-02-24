<?php
include("conexion.php");

/* Usuarios */

$sql = "
SELECT u.id_usuario, u.usuario, u.estado, r.nombre AS rol
FROM usuarios u
JOIN roles r ON u.id_rol = r.id_rol
ORDER BY u.id_usuario DESC
";

$res = $conexion->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Usuarios</title>
</head>

<body>

<div class="panel">

<h1>Usuarios del sistema</h1>

<div class="actions">

<button class="btn btn-add" onclick="crear()">➕ Crear usuario</button>

<button class="btn btn-edit" onclick="editar()">✏ Editar</button>

<button class="btn btn-pass" onclick="passw()">🔑 Cambiar contraseña</button>

<button class="btn btn-elim" onclick="eliminar()">🗑 Eliminar</button>

<button class="btn btn-ver" onclick="ver()">👁 Ver contraseña</button>

<button class="btn btn-volv" onclick="volv()">⬅ Volver al panel</button>

</div>

<table>
<tr>
<th></th>
<th>ID</th>
<th>Usuario</th>
<th>Rol</th>
<th>Estado</th>
</tr>

<?php while($row=$res->fetch_assoc()): ?>
<tr>
<td><input type="radio" name="u" value="<?= $row['id_usuario'] ?>"></td>
<td><?= $row['id_usuario'] ?></td>
<td><?= htmlspecialchars($row['usuario']) ?></td>
<td><?= $row['rol'] ?></td>
<td><?= $row['estado'] ? 'Activo' : 'Inactivo' ?></td>
</tr>
<?php endwhile; ?>

</table>

</div>

</body>
</html>

<script>
function sel(){
 return document.querySelector("input[name=u]:checked")?.value;
}

/* ======================
   BOTONES
====================== */

function editar(){
 const id=sel();
 if(!id) return alert("Seleccione usuario");
 location="usuario_editar.php?id="+id;
}

function passw(){
 const id=sel();
 if(!id) return alert("Seleccione usuario");
 location="usuario_password.php?id="+id;
}

function eliminar(){
 const id=sel();
 if(!id) return alert("Seleccione usuario");

 if(confirm("¿Seguro que desea eliminar este usuario?")){
   location="usuario_eliminar.php?id="+id;
 }
}

function ver(){
 const id=sel();
 if(!id) return alert("Seleccione usuario");
 location="usuario_ver_password.php?id="+id;
}

function crear(){
 location="crear_usuario.php";
}

function volv(){
 location="panel.php";
}
</script>

<style>
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: linear-gradient(135deg, #526513 0%, #06a388 100%);
    min-height: 100vh;
    padding: 20px;
    position: relative;
    overflow-x: hidden;
}

.panel{
 max-width:1100px;
 margin:40px auto;
 background:white;
 padding:30px;
 border-radius:10px;
 box-shadow:0 8px 18px rgba(0,0,0,.15)
}

.actions{
 display:flex;
 flex-wrap:wrap;
 gap:10px;
 margin-bottom:15px
}

.btn{
 padding:9px 15px;
 border:none;
 border-radius:6px;
 font-weight:bold;
 color:white;
 cursor:pointer
}

.btn-add{background:#27ae60}
.btn-edit{background:#4846b9}
.btn-pass{background:#c631b2}
.btn-elim{background:#a01a1a}
.btn-ver {background:#b0d820;color:black}
.btn-volv{background:#555}

table{
 width:100%;
 border-collapse:collapse
}

th{
 background:#243a5e;
 color:white;
 padding:10px
}

td{
 border:1px solid #ddd;
 padding:10px;
 text-align:center
}
</style>
