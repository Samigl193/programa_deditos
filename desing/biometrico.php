<?php
session_start();
include("conexion.php");
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Importar Biométrico</title>


</head>

<body>

<div class="box">

<h2>📥 Importar archivo biométrico para reporte</h2>

<form method="POST"
      enctype="multipart/form-data"
      action="biometrico_importar.php">

    <input type="file" name="archivo" required>

    <br>

    <button type="submit">Importar archivo</button>

</form>

<!-- Volver -->
<br>
<button class="btn-volver" onclick="location.href='empleados.php'">
⬅ Volver al panel de empleados
</button>

</div>

</body>
</html>


<style>
body{
    font-family:Arial;
    background:#f2f2f2;
    padding:40px
}
.box{
    max-width:500px;
    margin:auto;
    background:white;
    padding:30px;
    border-radius:10px;
    box-shadow:0 8px 20px rgba(0,0,0,.15);
    text-align:center
}
input{margin:15px 0}
button{
    background:#5b2a82;
    color:white;
    padding:12px 25px;
    border:none;
    border-radius:6px;
    cursor:pointer;
}


.btn-volver{
    margin-top:20px;
    background:#3498db;
}
</style>
