<?php
require_once("conexion.php");

$id_archivo = intval($_GET['id_archivo'] ?? 0);

if($id_archivo <= 0){
    die("❌ Archivo no válido.");
}

/*
   Resumen de empleados 
 */

$sqlResumen = "
SELECT 
 e.codigo_biometrico,

 e.nombre,
 e.apellido,

 r.fecha,

 MIN(r.hora) AS hora_entrada,
 MAX(r.hora) AS hora_salida,

 TIMESTAMPDIFF(
   MINUTE,
   CONCAT(r.fecha,' ',MIN(r.hora)),
   CONCAT(r.fecha,' ',MAX(r.hora))
 ) AS minutos_trabajados

FROM registros_biometricos r
JOIN empleados e ON r.id_empleado = e.id_empleado

WHERE r.id_archivo = $id_archivo

GROUP BY e.id_empleado, r.fecha

ORDER BY r.fecha, e.nombre
";

$resResumen = $conexion->query($sqlResumen);

if(!$resResumen){
    die(" Error SQL resumen: ".$conexion->error);
}

/* 
   Esto lo organiza por meses, para tener un buen control 
 */

$porMes = [];

while($row = $resResumen->fetch_assoc()){
    $mesKey = date("Y-m", strtotime($row['fecha']));
    $porMes[$mesKey][] = $row;
}

$meses = [
    "01"=>"enero",
    "02"=>"febrero",
    "03"=>"marzo",
    "04"=>"abril",
    "05"=>"mayo",
    "06"=>"junio",
    "07"=>"julio",
    "08"=>"agosto",
    "09"=>"septiembre",
    "10"=>"octubre",
    "11"=>"noviembre",
    "12"=>"diciembre"
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Reporte Biométrico</title>


</head>

<body>

<div class="panel">

<!-- Boton superior -->
<div class="top-actions">
    <a href="historial_biometrico.php" class="btn-volver">
        ⬅ Volver al panel
    </a>
</div>

<h2>📊 Reporte biométrico – Archivo #<?= $id_archivo ?></h2>

<h3>Resumen diario por empleado</h3>

<?php if(count($porMes)>0): ?>

<?php foreach($porMes as $mes=>$rows):

    $anio = substr($mes,0,4);
    $numMes = substr($mes,5,2);
    $mesTxt = ucfirst($meses[$numMes])." ".$anio;
?>

<h3 class="mes-title">📅 <?= $mesTxt ?></h3>

<table>
<tr>
<th>ID Biométrico</th>
<th>Empleado</th>
<th>Fecha</th>
<th>Entrada</th>
<th>Salida</th>
<th>Horas trabajadas</th>
</tr>

<?php foreach($rows as $r):

    $horas = floor($r['minutos_trabajados']/60);
    $min   = $r['minutos_trabajados']%60;
?>
<tr>
<td><?= htmlspecialchars($r['codigo_biometrico']) ?></td>
<td><?= htmlspecialchars($r['nombre']." ".$r['apellido']) ?></td>
<td><?= $r['fecha'] ?></td>
<td><?= $r['hora_entrada'] ?></td>
<td><?= $r['hora_salida'] ?></td>
<td>
    <span class="badge"><?= $horas ?>h <?= $min ?>m</span>
</td>
</tr>
<?php endforeach; ?>

</table>

<?php endforeach; ?>

<?php else: ?>

<p>⚠️ No hay datos para este archivo.</p>

<?php endif; ?>

<!-- Boton inferior -->
<div class="bottom-actions">
    <a href="historial_biometrico.php" class="btn-volver">
        ⬅ Volver al panel
    </a>
</div>

</div>

</body>
</html>


<style>
body{font-family:Arial;background:#f2f2f2}

.panel{
    max-width:1200px;
    margin:40px auto;
    background:white;
    padding:30px;
    border-radius:10px;
    box-shadow:0 6px 18px rgba(0,0,0,.15)
}

table{
    width:100%;
    border-collapse:collapse;
    margin-bottom:40px
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

h2,h3{
    color:#5b2a82
}

.mes-title{
    margin-top:40px;
    background:#eef0f6;
    padding:12px;
    border-left:6px solid #5b2a82;
    font-size:20px
}

.badge{
    background:#3498db;
    color:white;
    padding:5px 10px;
    border-radius:6px;
    font-weight:bold
}

.btn-volver{
    display:inline-block;
    background:#2c7be5;
    color:white;
    padding:10px 20px;
    border-radius:6px;
    text-decoration:none;
    font-weight:bold;
}
.top-actions{
    display:flex;
    justify-content:flex-end;
    margin-bottom:15px;
}
.bottom-actions{
    text-align:center;
    margin-top:25px;
}
</style>
