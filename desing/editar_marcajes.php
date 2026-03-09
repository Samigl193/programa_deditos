<?php
include("../desing/conexion.php");  
session_start();

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

// Mostrar mensaje de éxito si viene de guardado
$mensaje = '';
$error = '';
if (isset($_GET['guardado']) && $_GET['guardado'] == 1) {
    $mensaje = "✅ Cambios guardados correctamente. Los datos se han actualizado.";
}

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

// Crear tabla para historial de cambios
$conexion->query("CREATE TABLE IF NOT EXISTS historial_marcajes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_registro INT,
    id_empleado INT NOT NULL,
    fecha DATE NOT NULL,
    hora_anterior TIME,
    hora_nueva TIME,
    fecha_cambio DATETIME DEFAULT CURRENT_TIMESTAMP,
    usuario VARCHAR(100),
    INDEX (id_registro),
    INDEX (id_empleado, fecha)
)");

// Obtener personalizaciones de situaciones
$personalizaciones = [];
$result_pers = $conexion->query("SELECT * FROM personalizacion_situaciones");
while ($row = $result_pers->fetch_assoc()) {
    $personalizaciones[$row['situacion_original']] = $row;
}

// FILTRAR personalizaciones para evitar duplicados
$personalizaciones_filtradas = [];
foreach ($personalizaciones as $sit => $pers) {
    if (!isset($personalizaciones_filtradas[$sit])) {
        $personalizaciones_filtradas[$sit] = $pers;
    }
}
$personalizaciones = $personalizaciones_filtradas;

// PROCESAR ACCIONES AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        // ACTUALIZAR HORA DE UN MARCAJE
        if (isset($_POST['accion']) && $_POST['accion'] === 'actualizar_hora') {
            $id_registro = intval($_POST['id_registro']);
            $nueva_hora = $_POST['hora'];
            
            // Validar que la hora tenga el formato correcto
            if (strlen($nueva_hora) == 5) {
                $nueva_hora_completa = $nueva_hora . ':00';
            } else {
                $nueva_hora_completa = $nueva_hora;
            }
            
            // Obtener datos actuales del registro
            $sql = "SELECT id_empleado, fecha, hora FROM registros_biometricos WHERE id_registro = ?";
            $stmt = $conexion->prepare($sql);
            $stmt->bind_param("i", $id_registro);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                echo json_encode(['success' => false, 'error' => 'Registro no encontrado']);
                exit;
            }
            
            $registro = $result->fetch_assoc();
            $hora_anterior = $registro['hora'];
            
            // Actualizar la hora
            $update_sql = "UPDATE registros_biometricos SET hora = ? WHERE id_registro = ?";
            $update_stmt = $conexion->prepare($update_sql);
            $update_stmt->bind_param("si", $nueva_hora_completa, $id_registro);
            $update_stmt->execute();
            
            // Guardar en historial
            $historial_sql = "INSERT INTO historial_marcajes (id_registro, id_empleado, fecha, hora_anterior, hora_nueva, usuario) 
                             VALUES (?, ?, ?, ?, ?, ?)";
            $historial_stmt = $conexion->prepare($historial_sql);
            $usuario = $_SESSION['usuario'] ?? 'sistema';
            $historial_stmt->bind_param("iissss", 
                $id_registro, 
                $registro['id_empleado'], 
                $registro['fecha'], 
                $hora_anterior, 
                $nueva_hora_completa, 
                $usuario
            );
            $historial_stmt->execute();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Hora actualizada correctamente',
                'hora_formateada' => date("h:i:s A", strtotime($nueva_hora_completa)),
                'clase' => determinarClasePorTipoYHora($_POST['tipo'], $nueva_hora_completa)
            ]);
            exit;
        }
        
        // AGREGAR NUEVO MARCAJE
        if (isset($_POST['accion']) && $_POST['accion'] === 'agregar_marcaje') {
            $id_empleado = intval($_POST['id_empleado']);
            $fecha = $_POST['fecha'];
            $hora = $_POST['hora'];
            $tipo = $_POST['tipo'];
            
            // Validar que la hora tenga el formato correcto
            if (strlen($hora) == 5) {
                $hora_completa = $hora . ':00';
            } else {
                $hora_completa = $hora;
            }
            
            // Verificar si ya existe un marcaje similar
            $check_sql = "SELECT id_registro FROM registros_biometricos 
                         WHERE id_empleado = ? AND fecha = ? AND hora = ?";
            $check_stmt = $conexion->prepare($check_sql);
            $check_stmt->bind_param("iss", $id_empleado, $fecha, $hora_completa);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                echo json_encode(['success' => false, 'error' => 'Ya existe un marcaje con esta hora']);
                exit;
            }
            
            // Insertar nuevo marcaje
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
            
            $nuevo_id = $conexion->insert_id;
            
            // Insertar en historial para que aparezca el lápiz
            $historial_sql = "INSERT INTO historial_marcajes (id_registro, id_empleado, fecha, hora_anterior, hora_nueva, usuario) 
                             VALUES (?, ?, ?, ?, ?, ?)";
            $historial_stmt = $conexion->prepare($historial_sql);
            $usuario = $_SESSION['usuario'] ?? 'sistema';
            $hora_anterior = '00:00:00'; // No hay hora anterior porque es nuevo
            $historial_stmt->bind_param("iissss", 
                $nuevo_id, 
                $id_empleado, 
                $fecha, 
                $hora_anterior, 
                $hora_completa, 
                $usuario
            );
            $historial_stmt->execute();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Marcaje agregado correctamente',
                'id_registro' => $nuevo_id,
                'hora_formateada' => date("h:i:s A", strtotime($hora_completa)),
                'clase' => determinarClasePorTipoYHora($tipo, $hora_completa)
            ]);
            exit;
        }
        
        // ACTUALIZAR SITUACIÓN - CORREGIDO PARA ACTUALIZACIÓN INMEDIATA
        if (isset($_POST['accion']) && $_POST['accion'] === 'actualizar_situacion') {
            $id_empleado = intval($_POST['id_empleado']);
            $fecha = $_POST['fecha'];
            $situacion = $_POST['situacion'];
            
            $check_sql = "SELECT id FROM situaciones_marcajes WHERE id_empleado = ? AND fecha = ?";
            $check_stmt = $conexion->prepare($check_sql);
            $check_stmt->bind_param("is", $id_empleado, $fecha);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if (!empty($situacion)) {
                if ($check_result->num_rows > 0) {
                    $update_sql = "UPDATE situaciones_marcajes SET situacion = ? WHERE id_empleado = ? AND fecha = ?";
                    $update_stmt = $conexion->prepare($update_sql);
                    $update_stmt->bind_param("sis", $situacion, $id_empleado, $fecha);
                    $update_stmt->execute();
                } else {
                    $insert_sql = "INSERT INTO situaciones_marcajes (id_empleado, fecha, situacion) VALUES (?, ?, ?)";
                    $insert_stmt = $conexion->prepare($insert_sql);
                    $insert_stmt->bind_param("iss", $id_empleado, $fecha, $situacion);
                    $insert_stmt->execute();
                }
                
                // Obtener colores para la situación
                $colores = obtenerColoresPersonalizadosArray($situacion, $personalizaciones);
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Situación actualizada correctamente',
                    'situacion' => $situacion,
                    'texto' => $colores['texto_personalizado'],
                    'color_fondo' => $colores['color_fondo']
                ]);
            } else {
                if ($check_result->num_rows > 0) {
                    $delete_sql = "DELETE FROM situaciones_marcajes WHERE id_empleado = ? AND fecha = ?";
                    $delete_stmt = $conexion->prepare($delete_sql);
                    $delete_stmt->bind_param("is", $id_empleado, $fecha);
                    $delete_stmt->execute();
                }
                echo json_encode(['success' => true, 'message' => 'Situación eliminada']);
            }
            exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Función auxiliar para determinar clase por tipo y hora
function determinarClasePorTipoYHora($tipo, $hora_str) {
    if (empty($hora_str)) return 'faltante';
    
    $hora = strtotime($hora_str);
    
    if ($tipo == 'entrada_manana') {
        if ($hora < strtotime("07:00:00")) return 'naranja';
        if ($hora >= strtotime("09:00:00")) return 'tarde9';
        if ($hora > strtotime("08:00:00")) return 'tarde';
    }
    
    if ($tipo == 'entrada_almuerzo') {
        if ($hora > strtotime("14:00:00")) return 'tarde';
    }
    
    if ($tipo == 'salida_final') {
        if ($hora > strtotime("18:00:00")) return 'verde';
        if ($hora < strtotime("16:00:00")) return 'morado';
    }
    
    return '';
}

// Función para obtener colores personalizados como array simple
function obtenerColoresPersonalizadosArray($situacion, $personalizaciones) {
    $colores_default = [
        'Permiso' => ['color_fondo' => '#57df77', 'color_texto' => '#000000', 'texto_personalizado' => 'Permiso'],
        'Vacación' => ['color_fondo' => '#cec12c', 'color_texto' => '#000000', 'texto_personalizado' => 'Vacación'],
        'Enfermedad' => ['color_fondo' => '#cb1052', 'color_texto' => '#000000', 'texto_personalizado' => 'Enfermedad'],
        'Incapacidad' => ['color_fondo' => '#b727ab', 'color_texto' => '#000000', 'texto_personalizado' => 'Incapacidad'],
        'Día personal' => ['color_fondo' => '#12beb8', 'color_texto' => '#000000', 'texto_personalizado' => 'Día personal'],
        'No se presentó' => ['color_fondo' => '#ec7b7b', 'color_texto' => '#000000', 'texto_personalizado' => 'No se presentó']
    ];
    
    if (isset($personalizaciones[$situacion])) {
        $pers = $personalizaciones[$situacion];
        return [
            'color_fondo' => $pers['color_fondo'] ?? $colores_default[$situacion]['color_fondo'],
            'color_texto' => '#000000',
            'texto_personalizado' => $pers['texto_personalizado'] ?? $situacion
        ];
    }
    
    return $colores_default[$situacion] ?? ['color_fondo' => '#ffffff', 'color_texto' => '#000000', 'texto_personalizado' => $situacion];
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

// FUNCIÓN PARA DETERMINAR LA CLASE DEL MARCAJE SEGÚN LA HORA
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
    // Forzar lectura de datos actualizados
    $conexion->query("SET SESSION query_cache_type = OFF");
    
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
        
        if ($hora >= strtotime("06:00") && $hora <= strtotime("10:00")) {
            if (!$marcajes_por_empleado[$id][$fecha]['entrada_manana']) {
                $marcajes_por_empleado[$id][$fecha]['entrada_manana'] = $row;
            }
        } elseif ($hora >= strtotime("11:30") && $hora <= strtotime("13:30")) {
            if (!$marcajes_por_empleado[$id][$fecha]['salida_almuerzo']) {
                $marcajes_por_empleado[$id][$fecha]['salida_almuerzo'] = $row;
            }
        } elseif ($hora >= strtotime("13:30") && $hora <= strtotime("15:00")) {
            if (!$marcajes_por_empleado[$id][$fecha]['entrada_almuerzo']) {
                $marcajes_por_empleado[$id][$fecha]['entrada_almuerzo'] = $row;
            }
        } elseif ($hora >= strtotime("16:00") && $hora <= strtotime("19:00")) {
            if (!$marcajes_por_empleado[$id][$fecha]['salida_final']) {
                $marcajes_por_empleado[$id][$fecha]['salida_final'] = $row;
            }
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

// Función para formatear hora con segundos
function formatearHoraCompleta($hora) {
    if (empty($hora)) return '';
    return date("h:i:s A", strtotime($hora));
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
    
    $color_fondo = $colores_default[$situacion] ?? '#ffffff';
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
        $texto = $personalizaciones[$situacion]['texto_personalizado'] ?? $texto;
    }
    
    return [
        'color_fondo' => $color_fondo,
        'color_texto' => '#000000',
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

// Obtener historial de cambios para marcar los registros editados
$historial_cambios = [];
if (!empty($ids)) {
    $sql_hist = "SELECT DISTINCT id_registro FROM historial_marcajes 
                 WHERE id_empleado IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
                 AND fecha BETWEEN ? AND ?";
    $stmt_hist = $conexion->prepare($sql_hist);
    $types = str_repeat('i', count($ids)) . 'ss';
    $params = array_merge($ids, [$fecha_inicio, $fecha_fin]);
    $stmt_hist->bind_param($types, ...$params);
    $stmt_hist->execute();
    $result_hist = $stmt_hist->get_result();
    while ($row = $result_hist->fetch_assoc()) {
        $historial_cambios[$row['id_registro']] = true;
    }
}
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
            width: 180px;
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

        /* Estilos para la alerta personalizada en el centro */
        .custom-alert-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 9999;
            animation: fadeIn 0.3s ease;
        }

        .custom-alert {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            z-index: 10000;
            max-width: 400px;
            width: 90%;
            text-align: center;
            animation: slideIn 0.3s ease;
        }

        .custom-alert.visible {
            display: block;
        }

        .custom-alert-overlay.visible {
            display: block;
        }

        .custom-alert h3 {
            color: #5b2a82;
            margin-bottom: 15px;
            font-size: 24px;
            font-weight: 600;
        }

        .custom-alert p {
            color: #666;
            margin-bottom: 25px;
            font-size: 16px;
            line-height: 1.6;
        }

        .custom-alert .alert-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .custom-alert .btn-alert {
            padding: 12px 25px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            min-width: 140px;
        }

        .custom-alert .btn-alert-danger {
            background: #dc3545;
            color: white;
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }

        .custom-alert .btn-alert-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.4);
        }

        .custom-alert .btn-alert-secondary {
            background: #6c757d;
            color: white;
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
        }

        .custom-alert .btn-alert-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(108, 117, 125, 0.4);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translate(-50%, -60%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        /* Estilos para el icono de lápiz en horarios editados/agregados */
        .hora-display {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: bold;
            min-width: 100px;
            margin-bottom: 5px;
            position: relative;
        }

        .hora-display.editado::after {
            content: "✏️";
            position: absolute;
            top: -8px;
            right: -8px;
            font-size: 14px;
            background: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            animation: popIn 0.3s ease;
            border: 2px solid #fff;
        }

        @keyframes popIn {
            0% {
                transform: scale(0) rotate(-180deg);
                opacity: 0;
            }
            80% {
                transform: scale(1.2) rotate(10deg);
            }
            100% {
                transform: scale(1) rotate(0);
            }
        }

        .hora-display.tarde { background: #ed8e6e; color: #760f0f; }
        .hora-display.tarde9 { background: #a3d0eb; color: #004f7a; }
        .hora-display.faltante { background: #f5f178; color: #7a5a00; }
        .hora-display.verde { background: #9ce79c; color: #1f5c1f; }
        .hora-display.naranja { background: #ebc094; color: #7a3d00; }
        .hora-display.morado { background: #ccb8ef; color: #4b2a7a; }

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

        .btn-accion {
            background: #5b2a82;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 6px 10px;
            font-size: 12px;
            cursor: pointer;
            margin: 2px;
            transition: all 0.3s;
        }

        .btn-accion:hover {
            background: #6b3a92;
            transform: scale(1.05);
        }

        .btn-accion.editar {
            background: #3498db;
        }

        .btn-accion.editar:hover {
            background: #2980b9;
        }

        .btn-accion.agregar {
            background: #27ae60;
        }

        .btn-accion.agregar:hover {
            background: #229954;
        }

        .btn-situacion {
            background: #5b2a82;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }

        .btn-situacion:hover {
            background: #6b3a92;
            transform: scale(1.02);
        }

        .situacion-actual {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 8px;
            width: 100%;
            border: 2px solid rgba(0,0,0,0.2);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .situacion-actual:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
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

        .modal-body label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }

        .modal-body input[type="time"] {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            margin-bottom: 15px;
        }

        .modal-body input[type="time"]:focus {
            border-color: #5b2a82;
            outline: none;
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

        .toast {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            color: #333;
            padding: 20px 30px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 15px;
            z-index: 3000;
            animation: fadeIn 0.3s ease;
            min-width: 300px;
            max-width: 500px;
        }

        .toast.success {
            border-left: 5px solid #28a745;
            background: #d4edda;
            color: #155724;
        }

        .toast.error {
            border-left: 5px solid #dc3545;
            background: #f8d7da;
            color: #721c24;
        }

        .toast.info {
            border-left: 5px solid #17a2b8;
            background: #d1ecf1;
            color: #0c5460;
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
            
            .hora-display {
                min-width: 80px;
                font-size: 11px;
                padding: 4px 8px;
            }
            
            .tabla-marcajes th {
                top: 0;
            }
            
            .alert, .toast {
                width: 90%;
                min-width: auto;
            }
            
            .custom-alert .alert-buttons {
                flex-direction: column;
            }
            
            .custom-alert .btn-alert {
                width: 100%;
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
    
    foreach ($situaciones_para_estilos as $sit => $color): 
    ?>
    <style>
        .opcion-situacion[data-situacion="<?= $sit ?>"] {
            background-color: <?= $color ?> !important;
            color: #000000 !important;
        }
        
        .situacion-actual[data-situacion="<?= $sit ?>"] {
            background-color: <?= $color ?> !important;
            color: #000000 !important;
        }
        
        .color-box[data-situacion="<?= $sit ?>"] {
            background-color: <?= $color ?> !important;
        }
    </style>
    <?php endforeach; ?>
</head>
<body>
    <!-- Alerta personalizada para salir sin guardar -->
    <div class="custom-alert-overlay" id="alertOverlay"></div>
    <div class="custom-alert" id="customAlert">
        <h3>¡No guardaste los cambios!</h3>
        <p>Tienes cambios sin guardar. ¿Deseas salir?</p>
        <div class="alert-buttons">
            <button class="btn-alert btn-alert-danger" id="alertConfirmBtn">
                Sí, salir
            </button>
            <button class="btn-alert btn-alert-secondary" id="alertCancelBtn">
                No, seguir editando
            </button>
        </div>
    </div>

    <div class="botones-fijos">
        <button onclick="guardarCambios()" class="boton-minimal btn-guardar-minimal">
            <i class="fas fa-save"></i>
            <span class="btn-texto">Guardar cambios</span>
        </button>
        <a href="<?= $url_reporte ?>" class="boton-minimal btn-volver-minimal" onclick="return verificarCambiosNoGuardados(event)">
            <i class="fas fa-arrow-left"></i>
            <span class="btn-texto">Volver al reporte</span>
        </a>
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
                <?= $mensaje ?>
            </div>
            <script>
                setTimeout(() => {
                    document.querySelector('.alert').style.animation = 'fadeOut 0.3s ease';
                    setTimeout(() => {
                        document.querySelector('.alert').style.display = 'none';
                    }, 300);
                }, 3000);
            </script>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= $error ?>
            </div>
            <script>
                setTimeout(() => {
                    document.querySelector('.alert').style.animation = 'fadeOut 0.3s ease';
                    setTimeout(() => {
                        document.querySelector('.alert').style.display = 'none';
                    }, 300);
                }, 3000);
            </script>
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
                <span>Después de 08:00</span>
            </div>
            <div class="leyenda-item">
                <span class="color-box tarde9"></span>
                <span>Después de 09:00</span>
            </div>
            <div class="leyenda-item">
                <span class="color-box faltante"></span>
                <span>No marcó</span>
            </div>
            <div class="leyenda-item">
                <span class="color-box verde"></span>
                <span>Salió después</span>
            </div>
            <div class="leyenda-item">
                <span class="color-box naranja"></span>
                <span>Llegó antes</span>
            </div>
            <div class="leyenda-item">
                <span class="color-box morado"></span>
                <span>Salió antes</span>
            </div>
            <?php 
            foreach ($situaciones_predefinidas as $sit): 
                $colores = obtenerColoresPersonalizados($sit, $personalizaciones);
                $texto = obtenerTextoPersonalizado($sit, $personalizaciones);
            ?>
            <div class="leyenda-item">
                <span class="color-box" data-situacion="<?= $sit ?>" style="background: <?= $colores['color_fondo'] ?>;"></span>
                <span style="font-weight: 500;"><?= $texto ?></span>
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
        <?php endif; ?>
    </div>

    <!-- Modal para editar hora -->
    <div id="modalEditarHora" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-clock"></i> Editar Hora</h3>
                <span class="close-modal" onclick="cerrarModalEditarHora()">&times;</span>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editIdRegistro">
                <input type="hidden" id="editTipo">
                <input type="hidden" id="editFecha">
                <input type="hidden" id="editIdEmpleado">
                <label for="editHora">Nueva hora:</label>
                <input type="time" id="editHora" step="1" value="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancelar-modal" onclick="cerrarModalEditarHora()">Cancelar</button>
                <button type="button" class="btn-guardar-modal" onclick="guardarEdicionHora()">Guardar</button>
            </div>
        </div>
    </div>

    <div id="modalAgregarMarcaje" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Agregar Marcaje</h3>
                <span class="close-modal" onclick="cerrarModalAgregarMarcaje()">&times;</span>
            </div>
            <div class="modal-body">
                <input type="hidden" id="addIdEmpleado">
                <input type="hidden" id="addFecha">
                <input type="hidden" id="addTipo">
                <label for="addHora">Hora:</label>
                <input type="time" id="addHora" step="1" value="">
                <p style="font-size: 12px; color: #666; margin-top: 5px;">
                    <i class="fas fa-info-circle"></i> Se agregará como nuevo marcaje
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancelar-modal" onclick="cerrarModalAgregarMarcaje()">Cancelar</button>
                <button type="button" class="btn-guardar-modal" onclick="guardarNuevoMarcaje()">Agregar</button>
            </div>
        </div>
    </div>

    <!-- Modal para situación -->
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
                    ?>
                    <div class="opcion-situacion" 
                         data-situacion="<?= $sit ?>"
                         data-color-fondo="<?= $colores['color_fondo'] ?>"
                         data-color-texto="#000000"
                         onclick="seleccionarSituacion('<?= $sit ?>', this)"
                         style="background-color: <?= $colores['color_fondo'] ?>; color: #000000;">
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

    <!-- Toast para notificaciones -->
    <div id="toast" class="toast" style="display: none;"></div>

   <script>
    const empleadosInfo = <?= json_encode($empleados_info) ?>;
    const marcajesPorEmpleado = <?= json_encode($marcajes_por_empleado) ?>;
    const situacionesGuardadas = <?= json_encode($situaciones_guardadas) ?>;
    const personalizaciones = <?= json_encode($personalizaciones) ?>;
    const diasLaborables = <?= json_encode($dias_laborables) ?>;
    const esModoMultiple = <?= $modo_multiple ? 'true' : 'false' ?>;
    const historialCambios = <?= json_encode($historial_cambios) ?>;
    
    const mesesAbrev = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    const diasCorto = ['L', 'M', 'M', 'J', 'V'];
    
    let empleadoActivo = null;
    let fechaSeleccionada = '';
    let idEmpleadoSeleccionado = null;
    let situacionSeleccionada = '';
    
    // Variable para rastrear si hay cambios no guardados
    let hayCambiosNoGuardados = false;
    
    // Variables para la alerta personalizada
    let alertCallbackConfirm = null;
    let alertCallbackCancel = null;

    // Función para mostrar alerta personalizada
    function mostrarAlertaPersonalizada(onConfirm, onCancel) {
        alertCallbackConfirm = onConfirm;
        alertCallbackCancel = onCancel;
        
        document.getElementById('customAlert').classList.add('visible');
        document.getElementById('alertOverlay').classList.add('visible');
    }

    // Función para cerrar alerta personalizada
    function cerrarAlertaPersonalizada() {
        document.getElementById('customAlert').classList.remove('visible');
        document.getElementById('alertOverlay').classList.remove('visible');
        alertCallbackConfirm = null;
        alertCallbackCancel = null;
    }

    // Event listeners para los botones de la alerta
    document.getElementById('alertConfirmBtn').addEventListener('click', function() {
        if (alertCallbackConfirm) {
            alertCallbackConfirm();
        }
        cerrarAlertaPersonalizada();
    });

    document.getElementById('alertCancelBtn').addEventListener('click', function() {
        if (alertCallbackCancel) {
            alertCallbackCancel();
        }
        cerrarAlertaPersonalizada();
    });

    document.getElementById('alertOverlay').addEventListener('click', function() {
        if (alertCallbackCancel) {
            alertCallbackCancel();
        }
        cerrarAlertaPersonalizada();
    });

    // Función para marcar que hay cambios
    function marcarCambios() {
        hayCambiosNoGuardados = true;
        console.log('Cambios detectados');
    }

    // Función para guardar cambios
    function guardarCambios() {
        mostrarToast('Cambios guardados correctamente. Los datos se han actualizado.', 'success');
        hayCambiosNoGuardados = false;
        
        setTimeout(() => {
            window.location.href = window.location.pathname + window.location.search + '&guardado=1';
        }, 1500);
    }

    // Función para verificar cambios antes de volver
    function verificarCambiosNoGuardados(event) {
        if (hayCambiosNoGuardados) {
            event.preventDefault();
            mostrarAlertaPersonalizada(
                function() {
                    window.location.href = event.target.href;
                },
                function() {
                    // El usuario decidió quedarse
                }
            );
            return false;
        }
        return true;
    }

    // Función para mostrar toast centrado
    function mostrarToast(mensaje, tipo = 'success') {
        const toast = document.getElementById('toast');
        toast.innerHTML = mensaje;
        toast.className = `toast ${tipo}`;
        toast.style.display = 'flex';
        
        setTimeout(() => {
            toast.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => {
                toast.style.display = 'none';
                toast.style.animation = '';
            }, 300);
        }, 3000);
    }

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

    function obtenerColoresPersonalizados(situacion) {
        const coloresDefault = {
            'Permiso': { color_fondo: '#57df77', color_texto: '#000000', texto_personalizado: 'Permiso' },
            'Vacación': { color_fondo: '#cec12c', color_texto: '#000000', texto_personalizado: 'Vacación' },
            'Enfermedad': { color_fondo: '#cb1052', color_texto: '#000000', texto_personalizado: 'Enfermedad' },
            'Incapacidad': { color_fondo: '#b727ab', color_texto: '#000000', texto_personalizado: 'Incapacidad' },
            'Día personal': { color_fondo: '#12beb8', color_texto: '#000000', texto_personalizado: 'Día personal' },
            'No se presentó': { color_fondo: '#ec7b7b', color_texto: '#000000', texto_personalizado: 'No se presentó' }
        };
        
        if (personalizaciones[situacion]) {
            const pers = personalizaciones[situacion];
            return {
                color_fondo: pers.color_fondo || coloresDefault[situacion]?.color_fondo || '#ffffff',
                color_texto: '#000000',
                texto_personalizado: pers.texto_personalizado || situacion
            };
        }
        
        return coloresDefault[situacion] || { color_fondo: '#ffffff', color_texto: '#000000', texto_personalizado: situacion };
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

    function formatearHora12(hora) {
        if (!hora) return '';
        const fecha = new Date('1970-01-01T' + hora);
        return fecha.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
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
                const colores = obtenerColoresPersonalizados(situacionDia);
                estiloFila = `style="background-color: ${colores.color_fondo}; color: #000000;"`;
            } else if (!tieneAlgunMarcaje) {
                estiloFila = 'class="fila-sin-marcajes"';
            }
            
            html += `<tr data-fecha="${fecha}" data-id-empleado="${idEmpleado}" data-situacion="${situacionDia}" ${estiloFila}>`;
            html += `<td><strong>${contador++}</strong></td>`;
            html += `<td><strong>${diaCorto}</strong></td>`;
            html += `<td><strong>${fechaFormateada}</strong></td>`;
            
            // Entrada Mañana
            const entradaManana = marcajesDia?.entrada_manana;
            if (entradaManana) {
                const clase = determinarClase('entrada_manana', entradaManana.hora_only);
                const editado = historialCambios[entradaManana.id_registro] ? 'editado' : '';
                html += `<td>
                    <span class="hora-display ${clase} ${editado}" id="hora-${entradaManana.id_registro}">${formatearHora12(entradaManana.hora_only)}</span><br>
                    <button class="btn-accion editar" onclick="abrirModalEditarHora(${entradaManana.id_registro}, '${entradaManana.hora_only.substring(0, 5)}', 'entrada_manana', '${fecha}', ${idEmpleado})">
                        Editar
                    </button>
                </td>`;
            } else {
                html += `<td>
                    <span class="hora-display faltante">No marcó</span><br>
                    <button class="btn-accion agregar" onclick="abrirModalAgregarMarcaje(${idEmpleado}, '${fecha}', 'entrada_manana')">
                        Agregar
                    </button>
                </td>`;
            }
            
            // Salida Almuerzo
            const salidaAlmuerzo = marcajesDia?.salida_almuerzo;
            if (salidaAlmuerzo) {
                const clase = determinarClase('salida_almuerzo', salidaAlmuerzo.hora_only);
                const editado = historialCambios[salidaAlmuerzo.id_registro] ? 'editado' : '';
                html += `<td>
                    <span class="hora-display ${clase} ${editado}" id="hora-${salidaAlmuerzo.id_registro}">${formatearHora12(salidaAlmuerzo.hora_only)}</span><br>
                    <button class="btn-accion editar" onclick="abrirModalEditarHora(${salidaAlmuerzo.id_registro}, '${salidaAlmuerzo.hora_only.substring(0, 5)}', 'salida_almuerzo', '${fecha}', ${idEmpleado})">
                        Editar
                    </button>
                </td>`;
            } else {
                html += `<td>
                    <span class="hora-display">-</span><br>
                    <button class="btn-accion agregar" onclick="abrirModalAgregarMarcaje(${idEmpleado}, '${fecha}', 'salida_almuerzo')">
                        Agregar
                    </button>
                </td>`;
            }
            
            // Entrada Almuerzo
            const entradaAlmuerzo = marcajesDia?.entrada_almuerzo;
            if (entradaAlmuerzo) {
                const clase = determinarClase('entrada_almuerzo', entradaAlmuerzo.hora_only);
                const editado = historialCambios[entradaAlmuerzo.id_registro] ? 'editado' : '';
                html += `<td>
                    <span class="hora-display ${clase} ${editado}" id="hora-${entradaAlmuerzo.id_registro}">${formatearHora12(entradaAlmuerzo.hora_only)}</span><br>
                    <button class="btn-accion editar" onclick="abrirModalEditarHora(${entradaAlmuerzo.id_registro}, '${entradaAlmuerzo.hora_only.substring(0, 5)}', 'entrada_almuerzo', '${fecha}', ${idEmpleado})">
                        Editar
                    </button>
                </td>`;
            } else {
                html += `<td>
                    <span class="hora-display">-</span><br>
                    <button class="btn-accion agregar" onclick="abrirModalAgregarMarcaje(${idEmpleado}, '${fecha}', 'entrada_almuerzo')">
                        Agregar
                    </button>
                </td>`;
            }
            
            // Salida Final
            const salidaFinal = marcajesDia?.salida_final;
            if (salidaFinal) {
                const clase = determinarClase('salida_final', salidaFinal.hora_only);
                const editado = historialCambios[salidaFinal.id_registro] ? 'editado' : '';
                html += `<td>
                    <span class="hora-display ${clase} ${editado}" id="hora-${salidaFinal.id_registro}">${formatearHora12(salidaFinal.hora_only)}</span><br>
                    <button class="btn-accion editar" onclick="abrirModalEditarHora(${salidaFinal.id_registro}, '${salidaFinal.hora_only.substring(0, 5)}', 'salida_final', '${fecha}', ${idEmpleado})">
                        Editar
                    </button>
                </td>`;
            } else {
                html += `<td>
                    <span class="hora-display">-</span><br>
                    <button class="btn-accion agregar" onclick="abrirModalAgregarMarcaje(${idEmpleado}, '${fecha}', 'salida_final')">
                        Agregar
                    </button>
                </td>`;
            }
            
            // Situación
            html += `<td>`;
            if (situacionDia) {
                const colores = obtenerColoresPersonalizados(situacionDia);
                const textoMostrar = colores.texto_personalizado || situacionDia;
                html += `<span class="situacion-actual" id="situacion-${fecha}-${idEmpleado}" data-situacion="${situacionDia}" style="background-color: ${colores.color_fondo}; color: #000000; border: 2px solid ${colores.color_fondo};">${textoMostrar}</span><br>`;
            } else {
                html += `<span class="situacion-actual" id="situacion-${fecha}-${idEmpleado}" style="display: none;"></span>`;
            }
            html += `<button type="button" class="btn-situacion" onclick="abrirModal('${fecha}', ${idEmpleado})">${situacionDia ? 'Cambiar' : 'Asignar'}</button>`;
            html += `</td>`;
            html += `</tr>`;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
        `;
        
        document.getElementById('contenedor-tabla').innerHTML = html;
    }

    // Funciones para el modal de editar hora
    function abrirModalEditarHora(idRegistro, horaActual, tipo, fecha, idEmpleado) {
        document.getElementById('editIdRegistro').value = idRegistro;
        document.getElementById('editHora').value = horaActual;
        document.getElementById('editTipo').value = tipo;
        document.getElementById('editFecha').value = fecha;
        document.getElementById('editIdEmpleado').value = idEmpleado;
        document.getElementById('modalEditarHora').style.display = 'block';
    }

    function cerrarModalEditarHora() {
        document.getElementById('modalEditarHora').style.display = 'none';
    }

    function guardarEdicionHora() {
        const idRegistro = document.getElementById('editIdRegistro').value;
        const nuevaHora = document.getElementById('editHora').value;
        const tipo = document.getElementById('editTipo').value;
        const fecha = document.getElementById('editFecha').value;
        const idEmpleado = document.getElementById('editIdEmpleado').value;
        
        if (!nuevaHora) {
            mostrarToast('Debes seleccionar una hora', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('accion', 'actualizar_hora');
        formData.append('id_registro', idRegistro);
        formData.append('hora', nuevaHora);
        formData.append('tipo', tipo);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarToast('Hora actualizada correctamente');
                marcarCambios();
                cerrarModalEditarHora();
                
                // Agregar al historial de cambios
                historialCambios[idRegistro] = true;
                
                // Actualizar la hora en la tabla
                const horaSpan = document.getElementById(`hora-${idRegistro}`);
                if (horaSpan) {
                    horaSpan.textContent = data.hora_formateada;
                    horaSpan.className = `hora-display ${data.clase} editado`;
                }
                
                // Verificar si ahora la fila tiene marcajes y quitar clase sin-marcajes si es necesario
                const fila = document.querySelector(`tr[data-fecha="${fecha}"][data-id-empleado="${idEmpleado}"]`);
                if (fila && fila.classList.contains('fila-sin-marcajes') && !fila.dataset.situacion) {
                    const inputsHora = fila.querySelectorAll('.hora-display');
                    let tieneAlgunMarcaje = false;
                    inputsHora.forEach(span => {
                        if (span.textContent !== 'No marcó' && span.textContent !== '-') {
                            tieneAlgunMarcaje = true;
                        }
                    });
                    if (tieneAlgunMarcaje) {
                        fila.classList.remove('fila-sin-marcajes');
                    }
                }
            } else {
                mostrarToast('Error: ' + data.error, 'error');
            }
        })
        .catch(error => {
            mostrarToast('Error al actualizar: ' + error, 'error');
        });
    }

    // Funciones para el modal de agregar marcaje
    function abrirModalAgregarMarcaje(idEmpleado, fecha, tipo) {
        document.getElementById('addIdEmpleado').value = idEmpleado;
        document.getElementById('addFecha').value = fecha;
        document.getElementById('addTipo').value = tipo;
        document.getElementById('addHora').value = '';
        document.getElementById('modalAgregarMarcaje').style.display = 'block';
    }

    function cerrarModalAgregarMarcaje() {
        document.getElementById('modalAgregarMarcaje').style.display = 'none';
    }

    function guardarNuevoMarcaje() {
        const idEmpleado = document.getElementById('addIdEmpleado').value;
        const fecha = document.getElementById('addFecha').value;
        const hora = document.getElementById('addHora').value;
        const tipo = document.getElementById('addTipo').value;
        
        if (!hora) {
            mostrarToast('Debes seleccionar una hora', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('accion', 'agregar_marcaje');
        formData.append('id_empleado', idEmpleado);
        formData.append('fecha', fecha);
        formData.append('hora', hora);
        formData.append('tipo', tipo);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarToast('Marcaje agregado correctamente');
                marcarCambios();
                cerrarModalAgregarMarcaje();
                
                // Agregar el nuevo ID al historial de cambios para que tenga el lápiz
                historialCambios[data.id_registro] = true;
                
                // Actualizar el objeto marcajesPorEmpleado con el nuevo registro
                const nuevoRegistro = {
                    id_registro: data.id_registro,
                    id_empleado: parseInt(idEmpleado),
                    fecha: fecha,
                    hora_only: hora + ':00'
                };
                
                if (!marcajesPorEmpleado[idEmpleado][fecha]) {
                    marcajesPorEmpleado[idEmpleado][fecha] = {
                        entrada_manana: null,
                        salida_almuerzo: null,
                        entrada_almuerzo: null,
                        salida_final: null,
                        registros: []
                    };
                }
                
                // Determinar el tipo de marcaje según la hora
                const horaDate = new Date('1970-01-01T' + nuevoRegistro.hora_only);
                if (horaDate >= new Date('1970-01-01T06:00') && horaDate <= new Date('1970-01-01T10:00')) {
                    if (!marcajesPorEmpleado[idEmpleado][fecha].entrada_manana) {
                        marcajesPorEmpleado[idEmpleado][fecha].entrada_manana = nuevoRegistro;
                    }
                } else if (horaDate >= new Date('1970-01-01T11:30') && horaDate <= new Date('1970-01-01T13:30')) {
                    if (!marcajesPorEmpleado[idEmpleado][fecha].salida_almuerzo) {
                        marcajesPorEmpleado[idEmpleado][fecha].salida_almuerzo = nuevoRegistro;
                    }
                } else if (horaDate >= new Date('1970-01-01T13:30') && horaDate <= new Date('1970-01-01T15:00')) {
                    if (!marcajesPorEmpleado[idEmpleado][fecha].entrada_almuerzo) {
                        marcajesPorEmpleado[idEmpleado][fecha].entrada_almuerzo = nuevoRegistro;
                    }
                } else if (horaDate >= new Date('1970-01-01T16:00') && horaDate <= new Date('1970-01-01T19:00')) {
                    if (!marcajesPorEmpleado[idEmpleado][fecha].salida_final) {
                        marcajesPorEmpleado[idEmpleado][fecha].salida_final = nuevoRegistro;
                    }
                }
                
                marcajesPorEmpleado[idEmpleado][fecha].registros.push(nuevoRegistro);
                
                // Recargar la tabla para mostrar el nuevo marcaje con el lápiz
                mostrarEmpleado(empleadoActivo);
            } else {
                mostrarToast('Error: ' + data.error, 'error');
            }
        })
        .catch(error => {
            mostrarToast('Error al agregar: ' + error, 'error');
        });
    }

    // Funciones para el modal de situación
    function abrirModal(fecha, idEmpleado) {
        fechaSeleccionada = fecha;
        idEmpleadoSeleccionado = idEmpleado;
        
        const fila = document.querySelector(`tr[data-fecha="${fecha}"][data-id-empleado="${idEmpleado}"]`);
        const situacionActual = fila ? fila.dataset.situacion : '';
        
        document.getElementById('modalFecha').value = fecha;
        document.getElementById('modalIdEmpleado').value = idEmpleado;
        
        document.querySelectorAll('.opcion-situacion').forEach(opt => {
            opt.classList.remove('selected');
        });
        
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
        document.querySelectorAll('.opcion-situacion').forEach(opt => {
            opt.classList.remove('selected');
        });
        
        elemento.classList.add('selected');
        situacionSeleccionada = situacion;
    }

    function seleccionarNinguno(elemento) {
        document.querySelectorAll('.opcion-situacion').forEach(opt => {
            opt.classList.remove('selected');
        });
        
        elemento.classList.add('selected');
        situacionSeleccionada = '';
    }

    function guardarSituacion() {
        const fecha = fechaSeleccionada;
        const idEmpleado = idEmpleadoSeleccionado;
        
        const formData = new FormData();
        formData.append('accion', 'actualizar_situacion');
        formData.append('id_empleado', idEmpleado);
        formData.append('fecha', fecha);
        formData.append('situacion', situacionSeleccionada);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                mostrarToast(data.message);
                marcarCambios();
                cerrarModal();
                
                // ACTUALIZACIÓN DIRECTA DE LA INTERFAZ SIN RECARGAR
                const fila = document.querySelector(`tr[data-fecha="${fecha}"][data-id-empleado="${idEmpleado}"]`);
                if (fila) {
                    // Actualizar el atributo data-situacion de la fila
                    fila.dataset.situacion = situacionSeleccionada;
                    
                    // Actualizar el estilo de fondo de la fila
                    fila.style.backgroundColor = '';
                    fila.style.color = '';
                    fila.classList.remove('fila-sin-marcajes');
                    
                    if (situacionSeleccionada) {
                        fila.style.backgroundColor = data.color_fondo;
                        fila.style.color = '#000000';
                    }
                    
                    // Obtener la celda de situación (la última celda de la fila)
                    const celdaSituacion = fila.lastElementChild;
                    
                    // Limpiar el contenido actual de la celda
                    celdaSituacion.innerHTML = '';
                    
                    if (situacionSeleccionada) {
                        // Crear el span para la situación
                        const nuevoSpan = document.createElement('span');
                        nuevoSpan.id = `situacion-${fecha}-${idEmpleado}`;
                        nuevoSpan.className = 'situacion-actual';
                        nuevoSpan.setAttribute('data-situacion', situacionSeleccionada);
                        nuevoSpan.style.backgroundColor = data.color_fondo;
                        nuevoSpan.style.color = '#000000';
                        nuevoSpan.style.border = `2px solid ${data.color_fondo}`;
                        nuevoSpan.textContent = data.texto;
                        
                        // Crear el botón "Cambiar"
                        const nuevoBoton = document.createElement('button');
                        nuevoBoton.type = 'button';
                        nuevoBoton.className = 'btn-situacion';
                        nuevoBoton.setAttribute('onclick', `abrirModal('${fecha}', ${idEmpleado})`);
                        nuevoBoton.textContent = 'Cambiar';
                        
                        // Agregar los elementos a la celda
                        celdaSituacion.appendChild(nuevoSpan);
                        celdaSituacion.appendChild(document.createElement('br'));
                        celdaSituacion.appendChild(nuevoBoton);
                    } else {
                        // Crear el span oculto
                        const spanOculto = document.createElement('span');
                        spanOculto.id = `situacion-${fecha}-${idEmpleado}`;
                        spanOculto.className = 'situacion-actual';
                        spanOculto.style.display = 'none';
                        
                        // Crear el botón "Asignar"
                        const nuevoBoton = document.createElement('button');
                        nuevoBoton.type = 'button';
                        nuevoBoton.className = 'btn-situacion';
                        nuevoBoton.setAttribute('onclick', `abrirModal('${fecha}', ${idEmpleado})`);
                        nuevoBoton.textContent = 'Asignar';
                        
                        // Agregar los elementos a la celda
                        celdaSituacion.appendChild(spanOculto);
                        celdaSituacion.appendChild(document.createElement('br'));
                        celdaSituacion.appendChild(nuevoBoton);
                    }
                    
                    // Actualizar situacionesGuardadas
                    if (!situacionesGuardadas[idEmpleado]) {
                        situacionesGuardadas[idEmpleado] = {};
                    }
                    
                    if (situacionSeleccionada) {
                        situacionesGuardadas[idEmpleado][fecha] = situacionSeleccionada;
                    } else {
                        delete situacionesGuardadas[idEmpleado][fecha];
                    }
                }
            } else {
                mostrarToast('Error: ' + data.error, 'error');
            }
        })
        .catch(error => {
            mostrarToast('Error al guardar situación: ' + error, 'error');
        });
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
    }

    window.onclick = function(event) {
        const modalSituacion = document.getElementById('modalSituacion');
        const modalEditar = document.getElementById('modalEditarHora');
        const modalAgregar = document.getElementById('modalAgregarMarcaje');
        
        if (event.target == modalSituacion) {
            cerrarModal();
        }
        if (event.target == modalEditar) {
            cerrarModalEditarHora();
        }
        if (event.target == modalAgregar) {
            cerrarModalAgregarMarcaje();
        }
    }
</script>
</body>
</html>

