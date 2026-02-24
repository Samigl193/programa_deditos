<?php
include("conexion.php");

/* 
 Esta sección de código es para editar al empleado, si necesita unos cambios o el estado si es activo 
 o inactivo 
 */

$id = (int)$_GET['id'];

$emp = $conexion
    ->query("SELECT * FROM empleados WHERE id_empleado=$id")
    ->fetch_assoc();

$horarios = $conexion->query(
    "SELECT id_horario,nombre FROM horarios WHERE estado=1"
);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombre = $_POST['nombre'];
    $apellido = $_POST['apellido'];
    $codigo = $_POST['codigo_biometrico'];
    $puesto = $_POST['puesto'];
    $horas = (int)$_POST['horas_trabajo'];
    $id_horario = (int)$_POST['id_horario'];
    $estado = (int)$_POST['estado'];

    $sql = "UPDATE empleados SET
        id_horario=?,
        nombre=?,
        apellido=?,
        codigo_biometrico=?,
        puesto=?,
        horas_trabajo=?,
        estado=?
        WHERE id_empleado=?";

    $stmt = $conexion->prepare($sql);

$stmt->bind_param(
    "issssiii",
    $id_horario,
    $nombre,
    $apellido,
    $codigo,
    $puesto,
    $horas,
    $estado,
    $id
);


    $stmt->execute();

    header("Location: empleados.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Editar Empleado</title>


</head>

<body>

<div class="form-box">

<h2>Editar Empleado</h2>


<form method="POST">

<label>Nombre</label>
<input name="nombre" value="<?= $emp['nombre'] ?>" required>

<label>Apellido</label>
<input name="apellido" value="<?= $emp['apellido'] ?>" required>

<label>Código biométrico</label>
<input name="codigo_biometrico" value="<?= $emp['codigo_biometrico'] ?>" required>

<label>Puesto</label>
<input name="puesto" value="<?= $emp['puesto'] ?>">

<label>Horas por día</label>
<input type="number" name="horas_trabajo"
       min="1" max="24"
       value="<?= $emp['horas_trabajo'] ?>" required>

<label>Horario</label>
<select name="id_horario" required>
<?php while($h = $horarios->fetch_assoc()){ ?>
<option value="<?= $h['id_horario'] ?>"
<?= $emp['id_horario']==$h['id_horario']?'selected':'' ?>>
<?= $h['nombre'] ?>
</option>

<?php } ?>
</select>


<label>Estado</label>
<select name="estado">
<option value="1" <?= $emp['estado']==1?'selected':'' ?>>Activo</option>
<option value="0" <?= $emp['estado']==0?'selected':'' ?>>Inactivo</option>
</select>

<div class="actions">
    <button class="btn btn-save">💾 Guardar Cambios</button>
    <a href="empleados.php" class="btn btn-back">⬅ Volver</a>
</div>

</form>

</div>

</body>
</html>



<style>

body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: linear-gradient(135deg, #526513 0%, #06a388 100%);
    min-height: 100vh;
    padding: 20px;
    position: relative;
    overflow-x: hidden;
}

.form-box{
    max-width:650px;
    margin:60px auto;
    background:white;
    padding:30px;
    border-radius:12px;
    box-shadow:0 10px 25px rgba(0,0,0,.18);
}

.form-box h2{
    text-align:center;
    margin-bottom:25px;
    color: #5b2a82;
}

label{
    font-weight:bold;
    display:block;
    margin-top:12px;
}

input, select{
    width:100%;
    padding:11px;
    margin-top:5px;
    border-radius:6px;
    border:1px solid #ccc;
    font-size:14px;
}

input:focus, select:focus{
    outline:none;
    border-color:#5b2a82;
}

.actions{
    margin-top:25px;
    display:flex;
    justify-content:space-between;
    gap:10px;
}

.btn{
    padding:12px 20px;
    border:none;
    border-radius:6px;
    font-weight:bold;
    cursor:pointer;
}

.btn-save{
    background:#3498db;
    color:white;
}

.btn-back{
    background:#777;
    color:white;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    justify-content:center;
}

.btn:hover{
    opacity:.9;
}

</style>
