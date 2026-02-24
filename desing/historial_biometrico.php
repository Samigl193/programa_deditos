<?php
include("conexion.php");
/*Esta seccion es donde se van a mostrar los archivos que se subieron para ver o generar los reportes
aca es donde comienza la logica del sistema de reportes, ya que de aca sale toda la info  */



$sql = "
SELECT id_archivo, nombre_archivo, fecha_importacion
FROM archivos_importados
ORDER BY id_archivo DESC
";

$res = $conexion->query($sql);

if(!$res){
    die("❌ Error SQL: ".$conexion->error);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Archivos Biométricos</title>

</head>

<body>

<div class="panel">
<h2>📂 Historial de archivos importados</h2>

<table>
<tr>
<th>ID</th>
<th>Archivo</th>
<th>Fecha</th>
<th>Acciones</th>
</tr>

<?php if($res->num_rows>0): ?>
<?php while($r=$res->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($r['id_archivo']) ?></td>
<td><?= htmlspecialchars($r['nombre_archivo']) ?></td>
<td><?= htmlspecialchars($r['fecha_importacion']) ?></td>
<td>
       <!-- Este boton es para ver los datos que contiene el archivo  -->
    <a class="btn" href="reporte_archivo.php?id_archivo=<?= $r['id_archivo'] ?>">
        📊 Ver detalle
    </a>

    <!--  Eliminar  -->
    <a class="btn btn-del"
       href="archivo_eliminar.php?id_archivo=<?= $r['id_archivo'] ?>"
       onclick="return confirm('¿Esta seguro de eliminar este archivo y sus registros? Al eliminarlo se perdera todo ')">
       
        🗑 Eliminar
    </a>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr>
<td colspan="4">⚠️ No hay archivos cargados.</td>
</tr>
<?php endif; ?>

</table>

<!-- Volver  -->
<div style="text-align:center">
<br>
<a href="empleados.php" class="btn btn-volver">
⬅ Volver al panel de empleados
</a>
</div>

</div>

</body>
</html>



<style>
body{font-family:Arial;background:#f2f2f2}
.panel{
    max-width:1000px;
    margin:40px auto;
    background:white;
    padding:25px;
    border-radius:10px;
    box-shadow:0 6px 18px rgba(0,0,0,.12)
}
table{
    width:100%;
    border-collapse:collapse
}
th{
    background:#5b2a82;
    color:white;
    padding:10px
}
td{
    border:1px solid #ddd;
    padding:10px;
    text-align:center
}
a.btn{
    background:#3498db;
    color:white;
    padding:6px 14px;
    border-radius:6px;
    text-decoration:none;
    font-weight:bold;
    display:inline-block
}
a.btn:hover{opacity:.85}

a.btn-del{
    background:#e74c3c;
}
.btn-volver{
    margin-top:20px;
    background:#2c7be5;
}
</style>