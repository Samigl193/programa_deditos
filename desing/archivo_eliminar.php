


<?php
include("conexion.php");

if (!isset($_GET['id_archivo'])) {
    die("ID de archivo no recibido");
}

$id_archivo = (int)$_GET['id_archivo'];

/* 
   ELIMINAR REGISTROS BIOMETRICOS
*/

$stmt1 = $conexion->prepare("
    DELETE FROM registros_biometricos
    WHERE id_archivo = ?
");
$stmt1->bind_param("i", $id_archivo);
$stmt1->execute();

/* 
   ELIMINAR ARCHIVO
 */

$stmt2 = $conexion->prepare("
    DELETE FROM archivos_importados
    WHERE id_archivo = ?
");
$stmt2->bind_param("i", $id_archivo);
$stmt2->execute();

/* 
   REDIRIGIR
*/

header("Location: historial_biometrico.php");
exit;


