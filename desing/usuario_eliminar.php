<?php
session_start();
include("conexion.php");

#seccion para eliminar

if($_SESSION['rol']!="MASTER"){
 die("Solo MASTER puede eliminar");
}

$id=(int)$_GET['id'];

$stmt=$conexion->prepare(
 "DELETE FROM usuarios WHERE id_usuario=?"
);

$stmt->bind_param("i",$id);
$stmt->execute();

header("Location: usuarios.php");
