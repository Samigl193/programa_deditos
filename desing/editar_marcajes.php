<?php
include("../desing/conexion.php");  

// Activar manejo de excepciones para mysqli
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Procesar parámetros
$ids = [];
if (isset($_GET['ids']) && !empty($_GET['ids'])) {
    $ids = array_map('intval', explode(',', $_GET['ids']));
} elseif (isset($_GET['id']) && !empty($_GET['id'])) {
    $ids = [intval($_GET['id'])];
}

// Obtener fechas de los parámetros GET o usar valores por defecto
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date("Y-m-d", strtotime("-30 days"));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date("Y-m-d");

// Construir parámetros para el enlace de reporte
function construirUrlReporte($ids, $fecha_inicio, $fecha_fin) {
    if (count($ids) > 1) {
        return "../desing/reporte_empleado.php?id=" . implode(',', $ids) . "&fecha_inicio=" . urlencode($fecha_inicio) . "&fecha_fin=" . urlencode($fecha_fin);
    } else {
        return "../desing/reporte_empleado.php?id=" . $ids[0] . "&fecha_inicio=" . urlencode($fecha_inicio) . "&fecha_fin=" . urlencode($fecha_fin);
    }
}

$url_reporte = construirUrlReporte($ids, $fecha_inicio, $fecha_fin);
$modo_multiple = count($ids) > 1;
$url_volver = "../desing/empleados.php";

// Obtener un id_archivo válido existente
function obtenerIdArchivoValido($conexion) {
    $sql = "SELECT id_archivo FROM archivos_importados ORDER BY id_archivo DESC LIMIT 1";
    $result = $conexion->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['id_archivo'];
    }
    
    $sql_insert = "INSERT INTO archivos_importados (nombre_archivo, fecha_importacion) 
                   VALUES ('edicion_manual', NOW())";
    
    if ($conexion->query($sql_insert)) {
        return $conexion->insert_id;
    }
    
    return 1;
}

$id_archivo_valido = obtenerIdArchivoValido($conexion);

// Crear tabla para situaciones si no existe
$conexion->query("CREATE TABLE IF NOT EXISTS situaciones_marcajes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_empleado INT NOT NULL,
    fecha DATE NOT NULL,
    situacion VARCHAR(100),
    INDEX (id_empleado, fecha)
)");

// Crear tabla para personalización de situaciones
$conexion->query("CREATE TABLE IF NOT EXISTS personalizacion_situaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    situacion_original VARCHAR(100) NOT NULL,
    texto_personalizado VARCHAR(100),
    color_fondo VARCHAR(20),
    color_texto VARCHAR(20),
    UNIQUE KEY (situacion_original)
)");

// Obtener personalizaciones de situaciones
$personalizaciones = [];
$result_pers = $conexion->query("SELECT * FROM personalizacion_situaciones");
while ($row = $result_pers->fetch_assoc()) {
    $personalizaciones[$row['situacion_original']] = $row;
}

//  FILTRAR personalizaciones para evitar duplicados
$personalizaciones_filtradas = [];
foreach ($personalizaciones as $sit => $pers) {
    if (!isset($personalizaciones_filtradas[$sit])) {
        $personalizaciones_filtradas[$sit] = $pers;
    }
}
$personalizaciones = $personalizaciones_filtradas;

// Procesar guardado de cambios
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_marcajes'])) {
    $conexion->begin_transaction();
    
    try {
        // Actualizar marcajes existentes
        if (isset($_POST['marcajes']) && is_array($_POST['marcajes'])) {
            foreach ($_POST['marcajes'] as $id_registro => $datos) {
                foreach ($datos as $campo => $valor) {
                    if (!empty($valor)) {
                        $nuevo_valor = $valor . ':00';
                        
                        // Verificar que el registro existe
                        $check_sql = "SELECT id_registro, id_empleado, fecha FROM registros_biometricos WHERE id_registro = ?";
                        $check_stmt = $conexion->prepare($check_sql);
                        $check_stmt->bind_param("i", $id_registro);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();
                        
                        if ($check_result->num_rows > 0) {
                            $registro = $check_result->fetch_assoc();
                            
                            // Verificar duplicados
                            $dup_sql = "SELECT id_registro FROM registros_biometricos 
                                       WHERE id_empleado = ? AND fecha = ? AND hora = ? 
                                       AND id_registro != ?";
                            $dup_stmt = $conexion->prepare($dup_sql);
                            $dup_stmt->bind_param("issi", 
                                $registro['id_empleado'], 
                                $registro['fecha'], 
                                $nuevo_valor,
                                $id_registro
                            );
                            $dup_stmt->execute();
                            $dup_result = $dup_stmt->get_result();
                            
                            if ($dup_result->num_rows == 0) {
                                $update_sql = "UPDATE registros_biometricos SET hora = ? WHERE id_registro = ?";
                                $update_stmt = $conexion->prepare($update_sql);
                                $update_stmt->bind_param("si", $nuevo_valor, $id_registro);
                                $update_stmt->execute();
                            }
                        }
                    }
                }
            }
        }
        
        // Agregar nuevos marcajes
        if (isset($_POST['nuevos']) && is_array($_POST['nuevos'])) {
            foreach ($_POST['nuevos'] as $id_empleado => $fechas) {
                foreach ($fechas as $fecha => $marcajes) {
                    foreach ($marcajes as $tipo => $hora) {
                        if (!empty($hora)) {
                            $hora_completa = $hora . ':00';
                            
                            $check_sql = "SELECT id_registro FROM registros_biometricos 
                                         WHERE id_empleado = ? AND fecha = ? AND hora = ?";
                            $check_stmt = $conexion->prepare($check_sql);
                            $check_stmt->bind_param("iss", $id_empleado, $fecha, $hora_completa);
                            $check_stmt->execute();
                            $check_result = $check_stmt->get_result();
                            
                            if ($check_result->num_rows == 0) {
                                $insert_sql = "INSERT INTO registros_biometricos (id_empleado, fecha, hora, id_archivo) 
                                              VALUES (?, ?, ?, ?)";
                                $insert_stmt = $conexion->prepare($insert_sql);
                                $insert_stmt->bind_param("issi", 
                                    $id_empleado, 
                                    $fecha, 
                                    $hora_completa,
                                    $id_archivo_valido
                                );
                                $insert_stmt->execute();
                            }
                        }
                    }
                }
            }
        }
        
        // Guardar situaciones
        if (isset($_POST['situaciones']) && is_array($_POST['situaciones'])) {
            foreach ($_POST['situaciones'] as $id_empleado => $fechas) {
                foreach ($fechas as $fecha => $situacion) {
                    $fecha_mysql = $fecha;
                    
                    $check_sql = "SELECT id FROM situaciones_marcajes WHERE id_empleado = ? AND fecha = ?";
                    $check_stmt = $conexion->prepare($check_sql);
                    $check_stmt->bind_param("is", $id_empleado, $fecha_mysql);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if (!empty($situacion)) {
                        if ($check_result->num_rows > 0) {
                            $update_sql = "UPDATE situaciones_marcajes SET situacion = ? WHERE id_empleado = ? AND fecha = ?";
                            $update_stmt = $conexion->prepare($update_sql);
                            $update_stmt->bind_param("sis", $situacion, $id_empleado, $fecha_mysql);
                            $update_stmt->execute();
                        } else {
                            $insert_sql = "INSERT INTO situaciones_marcajes (id_empleado, fecha, situacion) VALUES (?, ?, ?)";
                            $insert_stmt = $conexion->prepare($insert_sql);
                            $insert_stmt->bind_param("iss", $id_empleado, $fecha_mysql, $situacion);
                            $insert_stmt->execute();
                        }
                    } else {
                        if ($check_result->num_rows > 0) {
                            $delete_sql = "DELETE FROM situaciones_marcajes WHERE id_empleado = ? AND fecha = ?";
                            $delete_stmt = $conexion->prepare($delete_sql);
                            $delete_stmt->bind_param("is", $id_empleado, $fecha_mysql);
                            $delete_stmt->execute();
                        }
                    }
                }
            }
        }
        
        // Guardar personalizaciones de situaciones (solo si hay cambios)
        if (isset($_POST['personalizar_situacion']) && is_array($_POST['personalizar_situacion'])) {
            foreach ($_POST['personalizar_situacion'] as $id_unico => $datos) {
                $situacion_original = $conexion->real_escape_string($datos['situacion_original']);
                $texto_personalizado = $conexion->real_escape_string($datos['texto_personalizado']);
                $color_fondo = $conexion->real_escape_string($datos['color_fondo']);
                $color_texto = $conexion->real_escape_string($datos['color_texto']);
                
                // Antes de insertar, eliminar cualquier duplicado existente
                $conexion->query("DELETE FROM personalizacion_situaciones WHERE situacion_original = '$situacion_original'");
                
                // Insertar el nuevo registro
                $conexion->query("INSERT INTO personalizacion_situaciones 
                    (situacion_original, texto_personalizado, color_fondo, color_texto) 
                    VALUES ('$situacion_original', '$texto_personalizado', '$color_fondo', '$color_texto')");
            }
        }
        
        $conexion->commit();
        $mensaje = " Marcajes, situaciones y personalizaciones actualizados correctamente";
        
    } catch (Exception $e) {
        $conexion->rollback();
        $error = "❌ Error: " . $e->getMessage();
    }
}

// Obtener información de empleados
$empleados_info = [];
foreach ($ids as $id) {
    $sql = "SELECT e.*, h.nombre AS horario_nombre 
            FROM empleados e 
            LEFT JOIN horarios h ON e.id_horario = h.id_horario 
            WHERE e.id_empleado = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($emp = $result->fetch_assoc()) {
        $empleados_info[$id] = $emp;
    }
}

// Obtener situaciones guardadas
$situaciones_guardadas = [];
if (!empty($ids)) {
    $sql_sit = "SELECT id_empleado, fecha, situacion FROM situaciones_marcajes 
                WHERE id_empleado IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
                AND fecha BETWEEN ? AND ?";
    $stmt_sit = $conexion->prepare($sql_sit);
    $types = str_repeat('i', count($ids)) . 'ss';
    $params = array_merge($ids, [$fecha_inicio, $fecha_fin]);
    $stmt_sit->bind_param($types, ...$params);
    $stmt_sit->execute();
    $result_sit = $stmt_sit->get_result();
    while ($row = $result_sit->fetch_assoc()) {
        $situaciones_guardadas[$row['id_empleado']][$row['fecha']] = $row['situacion'];
    }
}

function determinarClaseMarcaje($tipo, $hora_str) {
    if (empty($hora_str)) {
        return "faltante";
    }
    
    $hora = strtotime($hora_str);
    
    if ($tipo == 'entrada_manana') {
        if ($hora < strtotime("07:00:00")) {
            return "naranja";
        } elseif ($hora >= strtotime("09:00:00")) {
            return "tarde9";
        } elseif ($hora > strtotime("08:00:00")) {
            return "tarde";
        }
    }
    
    if ($tipo == 'entrada_almuerzo') {
        if ($hora > strtotime("14:00:00")) {
            return "tarde";
        }
    }
    
    if ($tipo == 'salida_final') {
        if ($hora > strtotime("18:00:00")) {
            return "verde";
        } elseif ($hora < strtotime("16:00:00")) {
            return "morado";
        }
    }
    
    return "";
}

$marcajes_por_empleado = [];
foreach ($ids as $id) {
    $sql = "SELECT rb.*, DATE(rb.fecha) as fecha_only, TIME(rb.hora) as hora_only
            FROM registros_biometricos rb
            WHERE rb.id_empleado = ? 
            AND rb.fecha BETWEEN ? AND ?
            ORDER BY rb.fecha, rb.hora";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("iss", $id, $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $marcajes_por_empleado[$id] = [];
    while ($row = $result->fetch_assoc()) {
        $fecha = $row['fecha_only'];
        if (!isset($marcajes_por_empleado[$id][$fecha])) {
            $marcajes_por_empleado[$id][$fecha] = [
                'entrada_manana' => null,
                'salida_almuerzo' => null,
                'entrada_almuerzo' => null,
                'salida_final' => null,
                'registros' => []
            ];
        }
        
        $hora = strtotime($row['hora_only']);
        if ($hora >= strtotime("07:00") && $hora <= strtotime("10:00")) {
            $marcajes_por_empleado[$id][$fecha]['entrada_manana'] = $row;
        } elseif ($hora >= strtotime("12:00") && $hora <= strtotime("13:30")) {
            $marcajes_por_empleado[$id][$fecha]['salida_almuerzo'] = $row;
        } elseif ($hora >= strtotime("13:30") && $hora <= strtotime("15:00")) {
            $marcajes_por_empleado[$id][$fecha]['entrada_almuerzo'] = $row;
        } elseif ($hora >= strtotime("16:00") && $hora <= strtotime("18:30")) {
            $marcajes_por_empleado[$id][$fecha]['salida_final'] = $row;
        }
        
        $marcajes_por_empleado[$id][$fecha]['registros'][] = $row;
    }
}

// Función para obtener los días laborables del período (Lunes a Viernes)
function obtenerDiasLaborables($fecha_inicio, $fecha_fin) {
    $inicio = new DateTime($fecha_inicio);
    $fin = new DateTime($fecha_fin);
    $fin->modify('+1 day');
    
    $intervalo = new DateInterval('P1D');
    $periodo = new DatePeriod($inicio, $intervalo, $fin);
    
    $dias_laborables = [];
    foreach ($periodo as $fecha) {
        $dia_semana = $fecha->format('N');
        if ($dia_semana <= 5) {
            $dias_laborables[] = $fecha->format('Y-m-d');
        }
    }
    return $dias_laborables;
}

$dias_laborables = obtenerDiasLaborables($fecha_inicio, $fecha_fin);

// Función para obtener el nombre corto del día
function obtenerDiaCorto($fecha) {
    $dia_numero = date('N', strtotime($fecha));
    $dias = ['L', 'M', 'M', 'J', 'V'];
    return $dias[$dia_numero - 1];
}

// Función para formatear fecha con mes abreviado
function formatearFechaMes($fecha) {
    $timestamp = strtotime($fecha);
    $dia = date('d', $timestamp);
    $mes_numero = date('n', $timestamp);
    $anio = date('Y', $timestamp);
    
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $mes_abrev = $meses[$mes_numero - 1];
    
    return $dia . '/' . $mes_abrev . '/' . $anio;
}

// Función para obtener el texto personalizado de una situación
function obtenerTextoPersonalizado($situacion, $personalizaciones) {
    if (isset($personalizaciones[$situacion]['texto_personalizado']) && 
        !empty($personalizaciones[$situacion]['texto_personalizado'])) {
        return $personalizaciones[$situacion]['texto_personalizado'];
    }
    return $situacion;
}

// Función para obtener los colores personalizados de una situación
function obtenerColoresPersonalizados($situacion, $personalizaciones) {

    $colores_default = [
        'Permiso' => '#57df77',
        'Vacación' => '#cec12c',
        'Enfermedad' => '#cb1052',
        'Incapacidad' => '#b727ab',
        'Día personal' => '#12beb8',
        'No se presentó' => '#ec7b7b'  
    ];
    
    $colores_texto_default = [
        'Permiso' => '#000000',
        'Vacación' => '#000000',
        'Enfermedad' => '#000000',
        'Incapacidad' => '#000000',
        'Día personal' => '#000000',
        'No se presentó' => '#000000'  
    ];
    
    $color_fondo = $colores_default[$situacion] ?? '#ffffff';
    $color_texto = $colores_texto_default[$situacion] ?? '#000000';
    $texto = $situacion;
    
  
    if ($situacion === 'Vacación') {
        return [
            'color_fondo' => '#cec12c',
            'color_texto' => '#000000',
            'texto_personalizado' => 'Vacación'
        ];
    }
    
    
    if (isset($personalizaciones[$situacion])) {
        $color_fondo = $personalizaciones[$situacion]['color_fondo'] ?? $color_fondo;
        $color_texto = $personalizaciones[$situacion]['color_texto'] ?? $color_texto;
        $texto = $personalizaciones[$situacion]['texto_personalizado'] ?? $texto;
    }
    
    return [
        'color_fondo' => $color_fondo,
        'color_texto' => $color_texto,
        'texto_personalizado' => $texto
    ];
}

// Verificar si hay resultados para mostrar
$tiene_datos = false;
foreach ($marcajes_por_empleado as $empleado_marcajes) {
    if (!empty($empleado_marcajes)) {
        $tiene_datos = true;
        break;
    }
}

// Si es modo individual, mostrar la tabla directamente
$mostrar_directo = !$modo_multiple && $tiene_datos;

$situaciones_predefinidas = [
    'Permiso',
    'Vacación',
    'Enfermedad',
    'Incapacidad',
    'Día personal',
    'No se presentó'
];

$iconos_situacion = [
    'Permiso' => '',
    'Vacación' => '',
    'Enfermedad' => '',
    'Incapacidad' => '',
    'Día personal' => '',
    'No se presentó' => ''
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Marcajes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .botones-fijos {
            position: fixed;
            right: 10px;
            top: 10px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .boton-minimal {
            width: 44px;
            height: 44px;
            border-radius: 22px;
            background: #5b2a82;
            color: white;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            text-decoration: none;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            padding-left: 12px;
            gap: 8px;
        }

        .boton-minimal i {
            font-size: 18px;
            min-width: 20px;
            text-align: center;
        }

        .boton-minimal .btn-texto {
            white-space: nowrap;
            opacity: 0;
            transform: translateX(-10px);
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
            visibility: hidden;
        }

        .botones-fijos:hover .boton-minimal {
            width: 160px;
            padding-right: 15px;
        }

        .botones-fijos:hover .boton-minimal .btn-texto {
            opacity: 1;
            transform: translateX(0);
            visibility: visible;
        }

        .btn-guardar-minimal {
            background: #28a745;
        }

        .btn-guardar-minimal:hover {
            background: #34ce57;
        }

        .btn-volver-minimal:hover {
            background: #6b3a92;
        }

        .header, .alert, .leyenda-colores, .empleados-grid, 
        .empleado-seccion, .no-datos, .mensaje-seleccion {
            margin-right: 60px;
        }

        .header {
            background: white;
            border-radius: 15px;
            padding: 20px 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header h1 {
            color: #5b2a82;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 5px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 5px solid #9d0d1c;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 5px solid #ffc107;
        }

        .leyenda-colores {
            background: white;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 25px;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .leyenda-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .color-box {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .color-box.tarde { background: #ed8e6e; }
        .color-box.tarde9 { background: #a3d0eb; }
        .color-box.faltante { background: #f5f178; }
        .color-box.verde { background: #9ce79c; }
        .color-box.naranja { background: #ebc094; }
        .color-box.morado { background: #ccb8ef; }

        .empleados-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .empleado-tarjeta {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border-left: 5px solid #5b2a82;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .empleado-tarjeta:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(91,42,130,0.15);
        }

        .empleado-tarjeta.active {
            border-left: 5px solid #28a745;
            background: #f0f9ff;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.2);
        }

        .empleado-tarjeta h3 {
            color: #5b2a82;
            margin-bottom: 15px;
            font-size: 18px;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .empleado-tarjeta.active h3 {
            color: #28a745;
        }

        .empleado-tarjeta p {
            margin: 8px 0;
            font-size: 14px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .empleado-tarjeta i {
            width: 20px;
            color: #5b2a82;
        }

        .badge-dias {
            background: #5b2a82;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            position: absolute;
            top: 15px;
            right: 15px;
        }

        .empleado-seccion {
            margin-bottom: 30px;
            background: white;
            border-radius: 10px;
            overflow: visible;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            width: 100%;
        }

        .seccion-titulo {
            background: linear-gradient(135deg, #5b2a82 0%, #764ba2 100%);
            color: white;
            padding: 18px 25px;
            font-size: 20px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 20;
            border-radius: 10px 10px 0 0;
        }

        .btn-cerrar {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .btn-cerrar:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.1);
        }

        .tabla-marcajes {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .tabla-marcajes th {
            background: #5b2a82;
            color: white;
            padding: 15px;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
            border: 1px solid #6b3a92;
            position: sticky;
            top: 73px;
            z-index: 15;
            box-shadow: 0 2px 2px -1px rgba(0,0,0,0.1);
        }

        .tabla-marcajes td {
            padding: 12px;
            border: 1px solid #dee2e6;
            text-align: center;
            vertical-align: middle;
            background: white;
        }

        .tabla-marcajes tbody tr:hover td {
            background: #f8f9fa;
        }

        .fila-sin-marcajes {
            background-color: #f5f178 !important;
        }

        .fila-sin-marcajes td {
            background-color: #f5f178 !important;
            color: #7a5a00;
            font-weight: bold;
        }

        .fila-sin-marcajes .input-hora {
            background-color: #f5f178;
            border-color: #f1c40f;
            color: #7a5a00;
            font-weight: bold;
        }

        .input-hora {
            width: 100px;
            padding: 8px;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            text-align: center;
            font-size: 13px;
            transition: all 0.3s;
        }

        .input-hora:focus {
            border-color: #5b2a82;
            outline: none;
            box-shadow: 0 0 0 3px rgba(91,42,130,0.1);
        }

        .input-hora.tarde {
            background: #ed8e6e;
            border-color: #d35400;
            color: #760f0f;
            font-weight: bold;
        }

        .input-hora.tarde9 {
            background: #a3d0eb;
            border-color: #3498db;
            color: #004f7a;
            font-weight: bold;
        }

        .input-hora.faltante {
            background: #f5f178;
            border-color: #f1c40f;
            color: #7a5a00;
            font-weight: bold;
        }

        .input-hora.verde {
            background: #9ce79c;
            border-color: #27ae60;
            color: #1f5c1f;
            font-weight: bold;
        }

        .input-hora.naranja {
            background: #ebc094;
            border-color: #e67e22;
            color: #7a3d00;
            font-weight: bold;
        }

        .input-hora.morado {
            background: #ccb8ef;
            border-color: #8e44ad;
            color: #4b2a7a;
            font-weight: bold;
        }

        .btn-situacion {
            background: #5b2a82;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            white-space: nowrap;
            width: 100%;
            justify-content: center;
        }

        .btn-situacion:hover {
            background: #6b3a92;
            transform: scale(1.02);
        }

        .situacion-actual {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-right: 5px;
            margin-bottom: 5px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 25px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.3);
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #5b2a82;
        }

        .modal-header h3 {
            color: #5b2a82;
            font-size: 20px;
        }

        .close-modal {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close-modal:hover {
            color: #5b2a82;
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .opciones-situacion {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }

        .opcion-situacion {
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 12px;
            cursor: pointer;
            text-align: center;
            font-weight: bold;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .opcion-situacion:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .opcion-situacion.selected {
            border: 3px solid #28a745;
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.2);
        }

        .opcion-situacion.ninguno {
            background-color: #ffffff !important;
            color: #000000 !important;
            border: 2px dashed #999;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-guardar-modal {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-cancelar-modal {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }

        .no-datos {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 10px;
            color: #666;
        }

        .mensaje-seleccion {
            text-align: center;
            padding: 80px 40px;
            background: white;
            border-radius: 10px;
            color: #666;
            font-size: 16px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin: 20px 0;
        }

        .mensaje-seleccion i {
            font-size: 80px;
            color: #5b2a82;
            margin-bottom: 20px;
            display: block;
        }

        .mensaje-seleccion h3 {
            color: #5b2a82;
            margin-bottom: 10px;
            font-size: 24px;
        }

        @media (max-width: 768px) {
            .botones-fijos {
                position: static;
                flex-direction: row;
                justify-content: center;
                margin-bottom: 20px;
            }
            
            .boton-minimal {
                width: 44px;
                height: 44px;
                justify-content: center;
                padding-left: 0;
            }
            
            .botones-fijos:hover .boton-minimal {
                width: 44px;
                justify-content: center;
                padding-left: 0;
            }
            
            .botones-fijos:hover .boton-minimal .btn-texto {
                display: none;
            }
            
            .header, .alert, .leyenda-colores, .empleados-grid, 
            .empleado-seccion, .no-datos, .mensaje-seleccion {
                margin-right: 0;
            }
            
            .leyenda-colores {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .empleados-grid {
                grid-template-columns: 1fr;
            }
            
            .input-hora {
                width: 90px;
                padding: 6px;
                font-size: 12px;
            }
            
            .tabla-marcajes th {
                top: 0;
            }
        }
    </style>
    <?php 

    $situaciones_para_estilos = [
        'Permiso' => '#57df77',
        'Vacación' => '#cec12c',
        'Enfermedad' => '#cb1052',
        'Incapacidad' => '#b727ab',
        'Día personal' => '#12beb8',
        'No se presentó' => '#ec7b7b'  
    ];
    
    $textos_fijos = [
        'Permiso' => '#000000',
        'Vacación' => '#000000',
        'Enfermedad' => '#000000',
        'Incapacidad' => '#000000',
        'Día personal' => '#000000',
        'No se presentó' => '#000000'
    ];
    
    foreach ($situaciones_para_estilos as $sit => $color): 
    ?>
    <style>


        .opcion-situacion[data-situacion="<?= $sit ?>"] {
            background-color: <?= $color ?> !important;
            color: <?= $textos_fijos[$sit] ?> !important;
        }
        
        .situacion-actual[data-situacion="<?= $sit ?>"] {
            background-color: <?= $color ?> !important;
            color: <?= $textos_fijos[$sit] ?> !important;
        }
        
        .color-box[data-situacion="<?= $sit ?>"] {
            background-color: <?= $color ?> !important;
        }
    </style>
    <?php endforeach; ?>
</head>
<body>
    <div class="botones-fijos">
        <a href="<?= $url_reporte ?>" class="boton-minimal btn-volver-minimal">
            <i class="fas fa-arrow-left"></i>
            <span class="btn-texto">Volver al reporte</span>
        </a>
        <button type="submit" form="form-marcajes" name="guardar_marcajes" value="1" class="boton-minimal btn-guardar-minimal" id="btn-guardar" <?= $mostrar_directo ? '' : 'style="display: none;"' ?>>
            <i class="fas fa-save"></i>
            <span class="btn-texto">Guardar cambios</span>
        </button>
    </div>

    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-edit"></i>
                Editar Marcajes
            </h1>
            <div style="font-size: 14px; color: #666;">
                <i class="fas fa-calendar"></i> <?= formatearFechaMes($fecha_inicio) ?> - <?= formatearFechaMes($fecha_fin) ?>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= $mensaje ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if (!$tiene_datos): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>No hay datos</strong> para mostrar en el período seleccionado. 
                <a href="<?= $url_volver ?>" style="color: #856404; font-weight: bold;">Volver a empleados</a>
            </div>
        <?php endif; ?>

        <div class="leyenda-colores">
            <div class="leyenda-item">
                <span class="color-box tarde"></span>
                <span style="color: #000000;">Después de 08:00</span>
            </div>
            <div class="leyenda-item">
                <span class="color-box tarde9"></span>
                <span style="color: #000000;">Después de 09:00</span>
            </div>
            <div class="leyenda-item">
                <span class="color-box faltante"></span>
                <span style="color: #000000;">No marcó</span>
            </div>
            <div class="leyenda-item">
                <span class="color-box verde"></span>
                <span style="color: #000000;">Salió después</span>
            </div>
            <div class="leyenda-item">
                <span class="color-box naranja"></span>
                <span style="color: #000000;">Llegó antes</span>
            </div>
            <div class="leyenda-item">
                <span class="color-box morado"></span>
                <span style="color: #000000;">Salió antes</span>
            </div>
            <?php 


            foreach ($situaciones_predefinidas as $sit): 
                $colores = obtenerColoresPersonalizados($sit, $personalizaciones);
                $texto = obtenerTextoPersonalizado($sit, $personalizaciones);
                $color_texto_leyenda = '#000000';
            ?>
            <div class="leyenda-item">
                <span class="color-box" data-situacion="<?= $sit ?>" style="background: <?= $colores['color_fondo'] ?>;"></span>
                <span style="color: <?= $color_texto_leyenda ?>; font-weight: 500;"><?= $texto ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($modo_multiple && $tiene_datos): ?>
            <div class="empleados-grid">
                <?php foreach ($empleados_info as $id_emp => $emp): ?>
                    <?php 
                    $tiene_marcajes_emp = !empty($marcajes_por_empleado[$id_emp]);
                    $total_dias_con_datos = $tiene_marcajes_emp ? count($marcajes_por_empleado[$id_emp]) : 0;
                    ?>
                    <div class="empleado-tarjeta <?= $tiene_marcajes_emp ? '' : 'sin-datos' ?>" 
                         onclick="mostrarEmpleado(<?= $id_emp ?>)"
                         id="tarjeta-<?= $id_emp ?>">
                        <h3>
                            <i class="fas fa-user"></i>
                            <?= htmlspecialchars($emp['nombre'] . ' ' . $emp['apellido']) ?>
                        </h3>
                        <p><i class="fas fa-id-card"></i> <?= htmlspecialchars($emp['codigo_biometrico'] ?? 'N/A') ?></p>
                        <p><i class="fas fa-briefcase"></i> <?= htmlspecialchars($emp['puesto'] ?? 'N/A') ?></p>
                        <?php if ($tiene_marcajes_emp): ?>
                            <span class="badge-dias">
                                <?= $total_dias_con_datos ?> días
                            </span>
                        <?php else: ?>
                            <span class="badge-dias" style="background: #dc3545;">
                                Sin datos
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!$tiene_datos): ?>
            <div class="no-datos">
                <i class="fas fa-calendar-times" style="font-size: 48px; color: #ccc; margin-bottom: 20px;"></i>
                <h3>No hay marcajes para editar</h3>
                <p>No se encontraron registros en el período seleccionado.</p>
            </div>
        <?php else: ?>
            <div id="contenedor-tabla">
                <?php if ($modo_multiple): ?>
                    <div class="mensaje-seleccion" id="mensaje-seleccion">
                        <i class="fas fa-hand-pointer"></i>
                        <h3>Selecciona un empleado</h3>
                        <p>Haz clic en cualquier tarjeta para editar sus marcajes</p>
                    </div>
                <?php else: ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            mostrarEmpleado(<?= $ids[0] ?>);
                        });
                    </script>
                <?php endif; ?>
            </div>

            <form method="POST" id="form-marcajes">
                <div id="inputs-ocultos"></div>
            </form>
        <?php endif; ?>
    </div>

    <div id="modalSituacion" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-tag"></i> Seleccionar Situación</h3>
                <span class="close-modal" onclick="cerrarModal()">&times;</span>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modalFecha">
                <input type="hidden" id="modalIdEmpleado">
                
                <div class="opciones-situacion" id="opcionesSituacion">
                    <div class="opcion-situacion ninguno" 
                         data-situacion=""
                         onclick="seleccionarNinguno(this)">
                         Ninguno
                    </div>
                    
                    <?php 

                    foreach ($situaciones_predefinidas as $sit): 
                        $colores = obtenerColoresPersonalizados($sit, $personalizaciones);
                        $texto = obtenerTextoPersonalizado($sit, $personalizaciones);
                        $color_texto_modal = '#000000';
                    ?>
                    <div class="opcion-situacion" 
                         data-situacion="<?= $sit ?>"
                         data-color-fondo="<?= $colores['color_fondo'] ?>"
                         data-color-texto="<?= $color_texto_modal ?>"
                         onclick="seleccionarSituacion('<?= $sit ?>', this)"
                         style="background-color: <?= $colores['color_fondo'] ?>; color: <?= $color_texto_modal ?>;">
                        <?= $texto ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancelar-modal" onclick="cerrarModal()">Cancelar</button>
                <button type="button" class="btn-guardar-modal" onclick="guardarSituacion()">Aplicar</button>
            </div>
        </div>
    </div>

    <script>
        const empleadosInfo = <?= json_encode($empleados_info) ?>;
        const marcajesPorEmpleado = <?= json_encode($marcajes_por_empleado) ?>;
        const situacionesGuardadas = <?= json_encode($situaciones_guardadas) ?>;
        const personalizaciones = <?= json_encode($personalizaciones) ?>;
        const diasLaborables = <?= json_encode($dias_laborables) ?>;
        const esModoMultiple = <?= $modo_multiple ? 'true' : 'false' ?>;
        
        const mesesAbrev = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        const diasCorto = ['L', 'M', 'M', 'J', 'V'];
        
        let empleadoActivo = null;
        let fechaSeleccionada = '';
        let idEmpleadoSeleccionado = null;
        let situacionSeleccionada = '';

        function formatearFechaConMes(fecha) {
            const [anio, mes, dia] = fecha.split('-');
            return dia + '/' + mesesAbrev[parseInt(mes) - 1] + '/' + anio;
        }

        function obtenerDiaCorto(fecha) {
            const fechaObj = new Date(fecha + 'T00:00:00');
            const diaSemana = fechaObj.getDay();
            const diaIndex = diaSemana === 0 ? 6 : diaSemana - 1;
            return diasCorto[diaIndex];
        }

        function obtenerColoresPersonalizados(situacion, personalizaciones) {
            const coloresDefault = {
                'Permiso': { color_fondo: '#57df77', color_texto: '#000000' },
                'Vacación': { color_fondo: '#cec12c', color_texto: '#000000' },
                'Enfermedad': { color_fondo: '#cb1052', color_texto: '#000000' },
                'Incapacidad': { color_fondo: '#b727ab', color_texto: '#000000' },
                'Día personal': { color_fondo: '#12beb8', color_texto: '#000000' },
                'No se presentó': { color_fondo: '#ec7b7b', color_texto: '#000000' }  
            };
            
            if (situacion === 'Vacación') {
                return {
                    color_fondo: '#cec12c',
                    color_texto: '#000000',
                    texto_personalizado: 'Vacación'
                };
            }
            
            let resultado = {
                color_fondo: coloresDefault[situacion]?.color_fondo || '#ffffff',
                color_texto: '#000000',
                texto_personalizado: situacion
            };
            
            if (personalizaciones[situacion]) {
                if (personalizaciones[situacion].color_fondo) {
                    resultado.color_fondo = personalizaciones[situacion].color_fondo;
                }
                if (personalizaciones[situacion].texto_personalizado) {
                    resultado.texto_personalizado = personalizaciones[situacion].texto_personalizado;
                }
            }
            
            return resultado;
        }

        function mostrarEmpleado(idEmpleado) {
            if (esModoMultiple) {
                document.querySelectorAll('.empleado-tarjeta').forEach(tarjeta => {
                    tarjeta.classList.remove('active');
                });
                const tarjeta = document.getElementById('tarjeta-' + idEmpleado);
                if (tarjeta) tarjeta.classList.add('active');
            }
            
            if (empleadoActivo === idEmpleado) return;
            empleadoActivo = idEmpleado;
            
            const empleado = empleadosInfo[idEmpleado];
            const marcajes = marcajesPorEmpleado[idEmpleado] || {};
            const situaciones = situacionesGuardadas[idEmpleado] || {};
            
            let html = `
                <div class="empleado-seccion" id="seccion-${idEmpleado}">
                    <div class="seccion-titulo">
                        <span>
                            <i class="fas fa-calendar-alt"></i>
                            ${empleado.nombre} ${empleado.apellido}
                        </span>
                        ${esModoMultiple ? `
                        <button type="button" class="btn-cerrar" onclick="cerrarEmpleado()">
                            <i class="fas fa-times"></i>
                        </button>
                        ` : ''}
                    </div>
                    <table class="tabla-marcajes">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Día</th>
                                <th>Fecha</th>
                                <th>Entrada</th>
                                <th>Salida Alm</th>
                                <th>Entrada Alm</th>
                                <th>Salida</th>
                                <th>Situación</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            let contador = 1;
            diasLaborables.forEach(fecha => {
                const marcajesDia = marcajes[fecha] || null;
                const situacionDia = situaciones[fecha] || '';
                const diaCorto = obtenerDiaCorto(fecha);
                const fechaFormateada = formatearFechaConMes(fecha);
                
                const tieneAlgunMarcaje = marcajesDia && (
                    marcajesDia.entrada_manana || 
                    marcajesDia.salida_almuerzo || 
                    marcajesDia.entrada_almuerzo || 
                    marcajesDia.salida_final
                );
                
                let estiloFila = '';
                
                if (situacionDia) {
                    const colores = obtenerColoresPersonalizados(situacionDia, personalizaciones);
                    estiloFila = `style="background-color: ${colores.color_fondo}; color: #000000;"`;
                } else if (!tieneAlgunMarcaje) {
                    estiloFila = 'class="fila-sin-marcajes"';
                }
                
                html += `<tr data-fecha="${fecha}" data-id-empleado="${idEmpleado}" data-situacion="${situacionDia}" ${estiloFila}>`;
                html += `<td><strong>${contador++}</strong></td>`;
                html += `<td><strong>${diaCorto}</strong></td>`;
                html += `<td><strong>${fechaFormateada}</strong></td>`;
                
                const entradaManana = marcajesDia?.entrada_manana;
                const claseEntrada = entradaManana ? determinarClase('entrada_manana', entradaManana.hora_only) : 'faltante';
                const nombreEntrada = entradaManana ? 
                    `marcajes[${entradaManana.id_registro}][entrada_manana]` : 
                    `nuevos[${idEmpleado}][${fecha}][entrada_manana]`;
                const valorEntrada = entradaManana ? entradaManana.hora_only.substring(0, 5) : '';
                
                html += `<td><input type="time" name="${nombreEntrada}" value="${valorEntrada}" class="input-hora ${claseEntrada}" data-tipo="entrada_manana"></td>`;
                
                // Salida Almuerzo
                const salidaAlmuerzo = marcajesDia?.salida_almuerzo;
                const claseSalidaAlm = salidaAlmuerzo ? determinarClase('salida_almuerzo', salidaAlmuerzo.hora_only) : '';
                const nombreSalidaAlm = salidaAlmuerzo ? 
                    `marcajes[${salidaAlmuerzo.id_registro}][salida_almuerzo]` : 
                    `nuevos[${idEmpleado}][${fecha}][salida_almuerzo]`;
                const valorSalidaAlm = salidaAlmuerzo ? salidaAlmuerzo.hora_only.substring(0, 5) : '';
                
                html += `<td><input type="time" name="${nombreSalidaAlm}" value="${valorSalidaAlm}" class="input-hora ${claseSalidaAlm}" data-tipo="salida_almuerzo"></td>`;
                
                // Entrada Almuerzo
                const entradaAlmuerzo = marcajesDia?.entrada_almuerzo;
                const claseEntradaAlm = entradaAlmuerzo ? determinarClase('entrada_almuerzo', entradaAlmuerzo.hora_only) : '';
                const nombreEntradaAlm = entradaAlmuerzo ? 
                    `marcajes[${entradaAlmuerzo.id_registro}][entrada_almuerzo]` : 
                    `nuevos[${idEmpleado}][${fecha}][entrada_almuerzo]`;
                const valorEntradaAlm = entradaAlmuerzo ? entradaAlmuerzo.hora_only.substring(0, 5) : '';
                
                html += `<td><input type="time" name="${nombreEntradaAlm}" value="${valorEntradaAlm}" class="input-hora ${claseEntradaAlm}" data-tipo="entrada_almuerzo"></td>`;
                
                // Salida Final
                const salidaFinal = marcajesDia?.salida_final;
                const claseSalidaFin = salidaFinal ? determinarClase('salida_final', salidaFinal.hora_only) : '';
                const nombreSalidaFin = salidaFinal ? 
                    `marcajes[${salidaFinal.id_registro}][salida_final]` : 
                    `nuevos[${idEmpleado}][${fecha}][salida_final]`;
                const valorSalidaFin = salidaFinal ? salidaFinal.hora_only.substring(0, 5) : '';
                
                html += `<td><input type="time" name="${nombreSalidaFin}" value="${valorSalidaFin}" class="input-hora ${claseSalidaFin}" data-tipo="salida_final"></td>`;
                
                // Botón de situación
                html += `<td>`;
                if (situacionDia) {
                    const colores = obtenerColoresPersonalizados(situacionDia, personalizaciones);
                    const textoMostrar = colores.texto_personalizado || situacionDia;
                    html += `<span class="situacion-actual" data-situacion="${situacionDia}" style="background-color: ${colores.color_fondo}; color: #000000;">${textoMostrar}</span><br>`;
                }
                html += `<button type="button" class="btn-situacion" onclick="abrirModal('${fecha}', ${idEmpleado})"><i class="fas fa-tag"></i> ${situacionDia ? 'Cambiar' : 'Asignar'}</button>`;
                html += `</td>`;
                html += `</tr>`;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
            
            document.getElementById('contenedor-tabla').innerHTML = html;
            document.getElementById('btn-guardar').style.display = 'flex';
        }

        function abrirModal(fecha, idEmpleado) {
            fechaSeleccionada = fecha;
            idEmpleadoSeleccionado = idEmpleado;
            
            const fila = document.querySelector(`tr[data-fecha="${fecha}"][data-id-empleado="${idEmpleado}"]`);
            const situacionActual = fila.dataset.situacion || '';
            
            document.getElementById('modalFecha').value = fecha;
            document.getElementById('modalIdEmpleado').value = idEmpleado;
            
            // Resetear selección
            document.querySelectorAll('.opcion-situacion').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Si hay situación actual, seleccionarla
            if (situacionActual) {
                const opcion = document.querySelector(`.opcion-situacion[data-situacion="${situacionActual}"]`);
                if (opcion) {
                    opcion.classList.add('selected');
                    situacionSeleccionada = situacionActual;
                } else {
                    situacionSeleccionada = '';
                }
            } else {
                situacionSeleccionada = '';
            }
            
            document.getElementById('modalSituacion').style.display = 'block';
        }

        function seleccionarSituacion(situacion, elemento) {
            // Remover selección anterior
            document.querySelectorAll('.opcion-situacion').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Marcar la nueva selección
            elemento.classList.add('selected');
            situacionSeleccionada = situacion;
        }

        function seleccionarNinguno(elemento) {
            // Remover selección anterior
            document.querySelectorAll('.opcion-situacion').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Marcar ninguno
            elemento.classList.add('selected');
            situacionSeleccionada = '';
        }

        function guardarSituacion() {
            const fecha = fechaSeleccionada;
            const idEmpleado = idEmpleadoSeleccionado;
            
            // Eliminar input anterior si existe
            const inputsAnteriores = document.querySelectorAll(`input[name="situaciones[${idEmpleado}][${fecha}]"]`);
            inputsAnteriores.forEach(input => input.remove());
            
            // Si hay situación seleccionada (no es ninguno), crear input
            if (situacionSeleccionada) {
                const inputSituacion = document.createElement('input');
                inputSituacion.type = 'hidden';
                inputSituacion.name = `situaciones[${idEmpleado}][${fecha}]`;
                inputSituacion.value = situacionSeleccionada;
                document.getElementById('inputs-ocultos').appendChild(inputSituacion);
            }
            
            // Actualizar la fila en la tabla
            const fila = document.querySelector(`tr[data-fecha="${fecha}"][data-id-empleado="${idEmpleado}"]`);
            if (fila) {
                fila.dataset.situacion = situacionSeleccionada;
                
                // Remover estilos y clases
                fila.style.backgroundColor = '';
                fila.style.color = '';
                fila.classList.remove('fila-sin-marcajes');
                
                // Verificar si tiene marcajes
                const inputsHora = fila.querySelectorAll('input[type="time"]');
                let tieneAlgunMarcaje = false;
                inputsHora.forEach(input => {
                    if (input.value) tieneAlgunMarcaje = true;
                });
                
                // Aplicar nuevos colores si hay situación seleccionada
                if (situacionSeleccionada) {
                    const colores = obtenerColoresPersonalizados(situacionSeleccionada, personalizaciones);
                    fila.style.backgroundColor = colores.color_fondo;
                    fila.style.color = '#000000';
                } else if (!tieneAlgunMarcaje) {
                    fila.classList.add('fila-sin-marcajes');
                }
                
                // Actualizar el contenido de la celda
                const btnCell = fila.lastElementChild;
                
                btnCell.innerHTML = '';
                if (situacionSeleccionada) {
                    const colores = obtenerColoresPersonalizados(situacionSeleccionada, personalizaciones);
                    const textoMostrar = colores.texto_personalizado || situacionSeleccionada;
                    btnCell.innerHTML += `<span class="situacion-actual" data-situacion="${situacionSeleccionada}" style="background-color: ${colores.color_fondo}; color: #000000;">${textoMostrar}</span><br>`;
                }
                btnCell.innerHTML += `<button type="button" class="btn-situacion" onclick="abrirModal('${fecha}', ${idEmpleado})"><i class="fas fa-tag"></i> ${situacionSeleccionada ? 'Cambiar' : 'Asignar'}</button>`;
            }
            
            cerrarModal();
        }

        function cerrarModal() {
            document.getElementById('modalSituacion').style.display = 'none';
            situacionSeleccionada = '';
        }

        function cerrarEmpleado() {
            empleadoActivo = null;
            document.querySelectorAll('.empleado-tarjeta').forEach(tarjeta => {
                tarjeta.classList.remove('active');
            });
            
            document.getElementById('contenedor-tabla').innerHTML = `
                <div class="mensaje-seleccion" id="mensaje-seleccion">
                    <i class="fas fa-hand-pointer"></i>
                    <h3>Selecciona un empleado</h3>
                    <p>Haz clic en cualquier tarjeta para editar sus marcajes</p>
                </div>
            `;
            
            document.getElementById('btn-guardar').style.display = 'none';
            document.getElementById('inputs-ocultos').innerHTML = '';
        }

        function determinarClase(tipo, hora) {
            if (!hora) return 'faltante';
            
            const horaDate = new Date('1970-01-01T' + hora);
            
            if (tipo === 'entrada_manana') {
                if (horaDate < new Date('1970-01-01T07:00:00')) return 'naranja';
                if (horaDate >= new Date('1970-01-01T09:00:00')) return 'tarde9';
                if (horaDate > new Date('1970-01-01T08:00:00')) return 'tarde';
            }
            
            if (tipo === 'entrada_almuerzo') {
                if (horaDate > new Date('1970-01-01T14:00:00')) return 'tarde';
            }
            
            if (tipo === 'salida_final') {
                if (horaDate > new Date('1970-01-01T18:00:00')) return 'verde';
                if (horaDate < new Date('1970-01-01T16:00:00')) return 'morado';
            }
            
            return '';
        }

        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('input-hora')) {
                const fila = e.target.closest('tr');
                const inputsFila = fila.querySelectorAll('input[type="time"]');
                let tieneAlgunValor = false;
                
                inputsFila.forEach(input => {
                    if (input.value) tieneAlgunValor = true;
                });
                
                if (tieneAlgunValor && !fila.dataset.situacion) {
                    fila.classList.remove('fila-sin-marcajes');
                }
            }
        });

        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('modalSituacion');
            if (event.target == modal) {
                cerrarModal();
            }
        }

        // Antes de enviar el formulario
        document.getElementById('form-marcajes')?.addEventListener('submit', function(e) {
            const inputsFaltantes = document.querySelectorAll('.input-hora.faltante');
            if (inputsFaltantes.length > 0) {
                if (!confirm('Hay marcajes faltantes. ¿Deseas continuar de todas formas?')) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>