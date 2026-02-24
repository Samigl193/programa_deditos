<?php
include("conexion.php");

/* Seccion para guardar */

$id=(int)$_POST['id'];

if(isset($_POST['password'])){

 $hash=password_hash($_POST['password'],PASSWORD_DEFAULT);

 $stmt=$conexion->prepare(
   "UPDATE usuarios SET password_hash=? WHERE id_usuario=?"
 );
 $stmt->bind_param("si",$hash,$id);
 $stmt->execute();

}else{

 $usuario=$_POST['usuario'];
 $estado=(int)$_POST['estado'];

 $stmt=$conexion->prepare(
   "UPDATE usuarios SET usuario=?,estado=? WHERE id_usuario=?"
 );

 $stmt->bind_param("sii",$usuario,$estado,$id);
 $stmt->execute();
}

header("Location: usuarios.php");
