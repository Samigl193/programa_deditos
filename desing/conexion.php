<?php

// CONEXIÓN A MYSQL - XAMPP


$host = "localhost";
$usuario = "root";
$password = ""; 
$base_datos = "sistema_deditos";

// Crear conexión
$conexion = new mysqli($host, $usuario, $password, $base_datos);

// Verificar conexión
if ($conexion->connect_error) {
    die("❌ Error de conexión: " . $conexion->connect_error);
}

$conexion->set_charset("utf8mb4");
?>
