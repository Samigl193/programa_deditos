<?php
include("conexion.php");

/* 
 Esta sección del codigo lo que hace es guardar el empleado, aca valida si todo esta bien y si es asi 
 se guarda automaticamente y si no, muestra error 
 */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: empleados.php");
    exit;
}

$nombre   = trim($_POST['nombre'] ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$codigo   = trim($_POST['codigo_biometrico'] ?? '');
$puesto   = trim($_POST['puesto'] ?? '');
$horas    = (int)($_POST['horas_trabajo'] ?? 0);
$idHorario= (int)($_POST['id_horario'] ?? 0);
$estado   = (int)($_POST['estado'] ?? 1);

if (
    $nombre === '' ||
    $apellido === '' ||
    $codigo === '' ||
    $puesto === '' ||
    $idHorario <= 0
) {

/* 
   Si los datos estan mal este error es el que muestra
 */
    die("❌ Datos incompletos.");
}

$sql = "
INSERT INTO empleados
(id_horario, nombre, apellido, codigo_biometrico, puesto, horas_trabajo, estado)
VALUES (?, ?, ?, ?, ?, ?, ?)
";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    die("❌ Error prepare: " . $conexion->error);
}


$stmt->bind_param(
    "issssii",   
    $idHorario,
    $nombre,
    $apellido,
    $codigo,
    $puesto,
    $horas,
    $estado
);

if (!$stmt->execute()) {
    die("❌ Error al guardar empleado: " . $stmt->error);
}

$stmt->close();

header("Location: empleados.php?ok=1");
exit;
