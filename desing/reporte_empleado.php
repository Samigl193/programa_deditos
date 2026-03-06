<?php 
include("../desing/conexion.php");

// Función para verificar si una fecha tiene marcajes modificados manualmente
function tieneMarcajesModificados($id_empleado, $fecha, $conexion) {
    $sql = "SELECT COUNT(*) as total FROM registros_biometricos 
            WHERE id_empleado = ? AND fecha = ? AND id_archivo = 1";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("is", $id_empleado, $fecha);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'] > 0;
}

// Función para verificar si un marcaje específico fue editado
function marcajeFueEditado($id_registro, $conexion) {
    $sql = "SELECT COUNT(*) as total FROM historial_marcajes WHERE id_registro = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $id_registro);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'] > 0;
}

// Obtener personalizaciones de situaciones
$personalizaciones = [];
$result_pers = $conexion->query("SELECT * FROM personalizacion_situaciones");
while ($row = $result_pers->fetch_assoc()) {
    $personalizaciones[$row['situacion_original']] = $row;
}

// Situaciones predefinidas
$situaciones_predefinidas = [
    'Permiso',
    'Vacación',
    'Enfermedad',
    'Incapacidad',
    'Día personal',
    'No se presentó' 
];

// Función para obtener el color de una situación
function obtenerColorSituacion($situacion, $personalizaciones) {
    if ($situacion === 'Permiso') {
        return '#57df77';
    }
    
    if (isset($personalizaciones[$situacion]) && !empty($personalizaciones[$situacion]['color_fondo'])) {
        return $personalizaciones[$situacion]['color_fondo'];
    }
    
    $colores_default = [
        'Permiso' => '#57df77',
        'Vacación' => '#cec12c',
        'Enfermedad' => '#cb1052',
        'Incapacidad' => '#b727ab',
        'Día personal' => '#12beb8',
        'No se presentó' => '#ec7b7b'
    ];
    
    return $colores_default[$situacion] ?? '#ffffff';
}

// Función para obtener color de texto (siempre negro)
function obtenerColorTextoSituacion($situacion, $personalizaciones) {
    return '#000000';
}

// Recibe y procesa los multiples IDS para los reportes 
$ids_array = [];

if (isset($_GET['id']) && is_array($_GET['id'])) {
    $ids_array = array_map('intval', $_GET['id']);
} elseif (isset($_GET['id']) && is_string($_GET['id'])) {
    $ids_array = array_map('intval', explode(',', $_GET['id']));
}

$ids_array = array_filter($ids_array);
$total_empleados = count($ids_array);

if ($total_empleados == 0) {
    die("❌ No se seleccionaron empleados. <a href='empleados.php'>Volver</a>");
}

$modo_multiple = ($total_empleados > 1);
$id_actual = $ids_array[0];

// Detecta si se envio correctamente el formulario  
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';

if ($fecha_inicio === '' || $fecha_fin === '') {
    $fecha_inicio = date("Y-m-d", strtotime("-30 days"));
    $fecha_fin = date("Y-m-d");
    $mostrar = false;
} else {
    $mostrar = true;
}

// Función para calcular días laborables del periodo
function calcular_dias_laborables($fecha_inicio, $fecha_fin) {
    $inicio = new DateTime($fecha_inicio);
    $fin = new DateTime($fecha_fin);
    $fin->modify('+1 day');
    
    $intervalo = new DateInterval('P1D');
    $periodo = new DatePeriod($inicio, $intervalo, $fin);
    
    $dias_laborables = 0;
    $fechas_laborables = [];
    foreach ($periodo as $fecha) {
        $dia_semana = $fecha->format('N');
        if ($dia_semana >= 1 && $dia_semana <= 5) {
            $dias_laborables++;
            $fechas_laborables[] = $fecha->format('Y-m-d');
        }
    }
    return ['total' => $dias_laborables, 'fechas' => $fechas_laborables];
}

// FUNCIÓN MODIFICADA: Ahora devuelve el nombre completo del día
function obtenerDiaCompleto($fecha) {
    $dia_numero = date('N', strtotime($fecha));
    $dias = [
        1 => 'Lunes',
        2 => 'Martes', 
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes'
    ];
    return $dias[$dia_numero];
}

// FUNCIÓN PARA FORMATO CORTO (con abreviatura de mes) - PARA EL ENCABEZADO
function formatearFechaCorto($fecha) {
    $timestamp = strtotime($fecha);
    $dia = date('d', $timestamp);
    $mes_numero = date('n', $timestamp);
    $anio = date('Y', $timestamp);
    
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $mes_abrev = $meses[$mes_numero - 1];
    
    return $dia . '/' . $mes_abrev . '/' . $anio;
}

// FUNCIÓN PARA FORMATO LARGO (con mes numérico) - PARA LA TABLA
function formatearFechaLarga($fecha) {
    $timestamp = strtotime($fecha);
    $dia = date('d', $timestamp);
    $mes = date('m', $timestamp);
    $anio = date('Y', $timestamp);
    
    return $dia . '/' . $mes . '/' . $anio;
}

function formato_12h($hora_decimal) {
    if ($hora_decimal === null) return 'N/A';
    
    $hora = floor($hora_decimal);
    $minutos = round(($hora_decimal - $hora) * 60);
    $ampm = ($hora >= 12) ? 'PM' : 'AM';
    $hora_12 = $hora % 12;
    if ($hora_12 == 0) $hora_12 = 12;
    
    return sprintf("%d:%02d %s", $hora_12, $minutos, $ampm);
}

function calcular_promedio($arr) {
    $sum = 0;
    $count = 0;
    foreach ($arr as $val) {
        if ($val !== null) {
            $sum += floatval($val);
            $count++;
        }
    }
    
    if ($count > 0) {
        return round($sum / $count, 2);
    }
    return null;
}

function procesarRegistrosEmpleado($id_empleado, $conexion, $fecha_inicio, $fecha_fin) {
    $conexion->query("SET SESSION query_cache_type = OFF");
    
    $sqlReg = "
    SELECT *
    FROM registros_biometricos
    WHERE id_empleado = ?
    AND fecha BETWEEN ? AND ?
    ORDER BY fecha, hora
    ";
    $stmt = $conexion->prepare($sqlReg);
    $stmt->bind_param("iss", $id_empleado, $fecha_inicio, $fecha_fin);
    $stmt->execute();
    return $stmt->get_result();
}

function obtenerSituacionesEmpleado($id_empleado, $conexion, $fecha_inicio, $fecha_fin) {
    $sqlSit = "
    SELECT fecha, situacion
    FROM situaciones_marcajes
    WHERE id_empleado = ?
    AND fecha BETWEEN ? AND ?
    ";
    $stmt = $conexion->prepare($sqlSit);
    $stmt->bind_param("iss", $id_empleado, $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $situaciones = [];
    while ($row = $result->fetch_assoc()) {
        $situaciones[$row['fecha']] = $row['situacion'];
    }
    return $situaciones;
}

function clasificarMarcajes($regs) {
    $dias = [];
    $porDia = [];
    $totalMarcajes = 0;
    
    while ($r = $regs->fetch_assoc()) {
        $fecha = $r['fecha'];
        $horaStr = $r['hora'];
        $hora = strtotime($horaStr);
        $dias[$fecha] = true;
        $totalMarcajes++;
        
        if (!isset($porDia[$fecha])) {
            $porDia[$fecha] = [
                'entrada_manana'   => null,
                'salida_almuerzo'  => null,
                'entrada_almuerzo' => null,
                'salida_final'     => null,
                'archivo'          => $r['id_archivo'],
                'id_registro_entrada' => null,
                'id_registro_salida_alm' => null,
                'id_registro_entrada_alm' => null,
                'id_registro_salida_fin' => null
            ];
        }
        
        if ($hora >= strtotime("07:00") && $hora <= strtotime("10:00")) {
            if (!$porDia[$fecha]['entrada_manana']) {
                $porDia[$fecha]['entrada_manana'] = $horaStr;
                $porDia[$fecha]['id_registro_entrada'] = $r['id_registro'];
            }
        } elseif ($hora >= strtotime("12:00") && $hora <= strtotime("13:30")) {
            $porDia[$fecha]['salida_almuerzo'] = $horaStr;
            $porDia[$fecha]['id_registro_salida_alm'] = $r['id_registro'];
        } elseif ($hora >= strtotime("13:30") && $hora <= strtotime("15:00")) {
            $porDia[$fecha]['entrada_almuerzo'] = $horaStr;
            $porDia[$fecha]['id_registro_entrada_alm'] = $r['id_registro'];
        } elseif ($hora >= strtotime("16:00") && $hora <= strtotime("18:30")) {
            $porDia[$fecha]['salida_final'] = $horaStr;
            $porDia[$fecha]['id_registro_salida_fin'] = $r['id_registro'];
        } elseif ($hora < strtotime("07:00:00")) {
            // Si el marcaje es antes de las 7 AM, lo consideramos como entrada temprana
            if (!$porDia[$fecha]['entrada_manana']) {
                $porDia[$fecha]['entrada_manana'] = $horaStr;
                $porDia[$fecha]['id_registro_entrada'] = $r['id_registro'];
            }
        } elseif ($hora > strtotime("15:00") && $hora < strtotime("16:00")) {
            // Marcajes entre las 15:00 y 16:00 - los consideramos como salida temprana
            $porDia[$fecha]['salida_final'] = $horaStr;
            $porDia[$fecha]['id_registro_salida_fin'] = $r['id_registro'];
        } elseif ($hora > strtotime("18:30") && $hora <= strtotime("23:59")) {
            // Marcajes después de las 18:30 - los consideramos como salida tarde
            $porDia[$fecha]['salida_final'] = $horaStr;
            $porDia[$fecha]['id_registro_salida_fin'] = $r['id_registro'];
        }
    }
    
    return [
        'dias' => $dias,
        'porDia' => $porDia,
        'totalMarcajes' => $totalMarcajes
    ];
}

function calcularTiempoTardanza($porDia) {
    $segundosTarde = 0;
    foreach ($porDia as $dia) {
        if (!empty($dia['entrada_manana']) && strtotime($dia['entrada_manana']) > strtotime("08:00:00")) {
            $segundosTarde += strtotime($dia['entrada_manana']) - strtotime("08:00:00");
        }
        if (!empty($dia['entrada_almuerzo']) && strtotime($dia['entrada_almuerzo']) > strtotime("14:00:00")) {
            $segundosTarde += strtotime($dia['entrada_almuerzo']) - strtotime("14:00:00");
        }
    }
    $horas = floor($segundosTarde / 3600);
    $minutos = floor(($segundosTarde % 3600) / 60);
    $segundos = $segundosTarde % 60;
    return sprintf("%02d:%02d:%02d", $horas, $minutos, $segundos);
}

function prepararDatosGraficas($porDia) {
    $labels = [];
    $gEntrada = [];
    $gSalidaAlm = [];
    $gEntradaAlm = [];
    $gSalidaFin = [];
    
    ksort($porDia);
    foreach ($porDia as $fecha => $d) {
        $labels[] = $fecha;
        $gEntrada[] = $d['entrada_manana'] ? (date("H", strtotime($d['entrada_manana'])) + date("i", strtotime($d['entrada_manana']))/60) : null;
        $gSalidaAlm[] = $d['salida_almuerzo'] ? (date("H", strtotime($d['salida_almuerzo'])) + date("i", strtotime($d['salida_almuerzo']))/60) : null;
        $gEntradaAlm[] = $d['entrada_almuerzo'] ? (date("H", strtotime($d['entrada_almuerzo'])) + date("i", strtotime($d['entrada_almuerzo']))/60) : null;
        $gSalidaFin[] = $d['salida_final'] ? (date("H", strtotime($d['salida_final'])) + date("i", strtotime($d['salida_final']))/60) : null;
    }
    
    return [
        'labels' => $labels,
        'gEntrada' => $gEntrada,
        'gSalidaAlm' => $gSalidaAlm,
        'gEntradaAlm' => $gEntradaAlm,
        'gSalidaFin' => $gSalidaFin
    ];
}

function obtenerDatosEmpleado($id_empleado, $conexion, $fecha_inicio, $fecha_fin, $mostrar) {
    $sqlEmp = "
    SELECT e.*, h.nombre AS horario
    FROM empleados e
    LEFT JOIN horarios h ON e.id_horario = h.id_horario
    WHERE e.id_empleado = ?
    ";
    $stmt = $conexion->prepare($sqlEmp);
    $stmt->bind_param("i", $id_empleado);
    $stmt->execute();
    $res = $stmt->get_result();
    $emp = $res->fetch_assoc();
    
    if (!$emp) return null;
    
    $totalMarcajes = 0;
    $porDia = [];
    $diasTrabajados = 0;
    $formatoTarde = "00:00:00";
    $labels = [];
    $gEntrada = $gSalidaAlm = $gEntradaAlm = $gSalidaFin = [];
    $situaciones = [];
    $marcajesEditados = [];
    $marcajesEditadosPorId = [];
    
    if($mostrar){
        $regs = procesarRegistrosEmpleado($id_empleado, $conexion, $fecha_inicio, $fecha_fin);
        $clasificados = clasificarMarcajes($regs);
        
        $porDia = $clasificados['porDia'];
        $totalMarcajes = $clasificados['totalMarcajes'];
        $diasTrabajados = count($clasificados['dias']);
        
        // Verificar qué días tienen marcajes editados
        foreach (array_keys($porDia) as $fecha) {
            $marcajesEditados[$fecha] = tieneMarcajesModificados($id_empleado, $fecha, $conexion);
        }
        
        // Verificar qué marcajes específicos fueron editados
        foreach ($porDia as $fecha => $datos) {
            if ($datos['id_registro_entrada']) {
                $marcajesEditadosPorId[$datos['id_registro_entrada']] = marcajeFueEditado($datos['id_registro_entrada'], $conexion);
            }
            if ($datos['id_registro_salida_alm']) {
                $marcajesEditadosPorId[$datos['id_registro_salida_alm']] = marcajeFueEditado($datos['id_registro_salida_alm'], $conexion);
            }
            if ($datos['id_registro_entrada_alm']) {
                $marcajesEditadosPorId[$datos['id_registro_entrada_alm']] = marcajeFueEditado($datos['id_registro_entrada_alm'], $conexion);
            }
            if ($datos['id_registro_salida_fin']) {
                $marcajesEditadosPorId[$datos['id_registro_salida_fin']] = marcajeFueEditado($datos['id_registro_salida_fin'], $conexion);
            }
        }
        
        $situaciones = obtenerSituacionesEmpleado($id_empleado, $conexion, $fecha_inicio, $fecha_fin);
        
        if ($totalMarcajes > 0) {
            $formatoTarde = calcularTiempoTardanza($porDia);
            $datosGraficas = prepararDatosGraficas($porDia);
            $labels = $datosGraficas['labels'];
            $gEntrada = $datosGraficas['gEntrada'];
            $gSalidaAlm = $datosGraficas['gSalidaAlm'];
            $gEntradaAlm = $datosGraficas['gEntradaAlm'];
            $gSalidaFin = $datosGraficas['gSalidaFin'];
        }
    }
    
    return [
        'empleado' => $emp,
        'totalMarcajes' => $totalMarcajes,
        'porDia' => $porDia,
        'diasTrabajados' => $diasTrabajados,
        'formatoTarde' => $formatoTarde,
        'labels' => $labels,
        'gEntrada' => $gEntrada,
        'gSalidaAlm' => $gSalidaAlm,
        'gEntradaAlm' => $gEntradaAlm,
        'gSalidaFin' => $gSalidaFin,
        'situaciones' => $situaciones,
        'marcajesEditados' => $marcajesEditados,
        'marcajesEditadosPorId' => $marcajesEditadosPorId
    ];
}

function tendencia($arr){
    $n = count($arr);
    $x = []; $y = [];
    foreach ($arr as $i=>$v){
        if($v!==null){
            $x[]=$i;
            $y[]=floatval($v);
        }
    }
    $n=count($x);
    if($n<2) return array_fill(0,count($arr),null);
    $sx = array_sum($x);
    $sy = array_sum($y);
    $sxy = $sx2 = 0;
    for($i=0;$i<$n;$i++){
        $sxy += $x[$i]*$y[$i];
        $sx2 += $x[$i]*$x[$i];
    }
    $m = ($n*$sxy - $sx*$sy) / ($n*$sx2 - $sx*$sx);
    $b = ($sy - $m*$sx) / $n;
    $res = [];
    for($i=0;$i<count($arr);$i++){
        $res[] = $m*$i + $b;
    }
    return $res;
}

// FUNCIÓN CORREGIDA: Ahora maneja correctamente los marcajes antes de las 7 AM
function determinarClaseMarcaje($tipo, $valor) {
    if (empty($valor)) return "faltante";
    
    $hora = strtotime($valor);
    
    if ($tipo == 'entrada_manana') {
        if ($hora < strtotime("07:00:00")) return "naranja"; // Llegó antes de las 7
        if ($hora >= strtotime("09:00:00")) return "tarde9"; // Llegó después de las 9
        if ($hora > strtotime("08:00:00")) return "tarde"; // Llegó después de las 8 pero antes de las 9
        return ""; // Llegó en horario normal (7:00 - 8:00)
    }
    
    if ($tipo == 'entrada_almuerzo' && $hora > strtotime("14:00:00")) return "tarde";
    
    if ($tipo == 'salida_final') {
        if ($hora > strtotime("18:00:00")) return "verde";
        if ($hora < strtotime("16:00:00")) return "morado";
        if ($hora < strtotime("17:00:00")) return "morado"; // Salió antes de las 5
    }
    
    return "";
}

// FUNCIÓN MODIFICADA: Ahora incluye segundos en el formato de hora
function formatearHora12($hora) {
    if (empty($hora)) return '';
    return date("h:i:s A", strtotime($hora)); 
}

function mostrarSituacion($situacion, $personalizaciones) {
    if (empty($situacion)) return '';
    if (isset($personalizaciones[$situacion]['texto_personalizado']) && !empty($personalizaciones[$situacion]['texto_personalizado'])) {
        return $personalizaciones[$situacion]['texto_personalizado'];
    }
    return $situacion;
}

function claseSituacion($situacion, $personalizaciones) {
    if (empty($situacion)) return '';
    return 'situacion-' . strtolower(str_replace(' ', '-', $situacion));
}

// Procesar datos según el modo
if (!$modo_multiple) {
    $datos_empleado = obtenerDatosEmpleado($ids_array[0], $conexion, $fecha_inicio, $fecha_fin, $mostrar);
    if (!$datos_empleado) die("Empleado no encontrado");
    
    $emp = $datos_empleado['empleado'];
    $totalMarcajes = $datos_empleado['totalMarcajes'];
    $porDia = $datos_empleado['porDia'];
    $diasTrabajados = $datos_empleado['diasTrabajados'];
    $formatoTarde = $datos_empleado['formatoTarde'];
    $labels = $datos_empleado['labels'];
    $gEntrada = $datos_empleado['gEntrada'];
    $gSalidaAlm = $datos_empleado['gSalidaAlm'];
    $gEntradaAlm = $datos_empleado['gEntradaAlm'];
    $gSalidaFin = $datos_empleado['gSalidaFin'];
    $situaciones = $datos_empleado['situaciones'];
    $marcajesEditados = $datos_empleado['marcajesEditados'];
    $marcajesEditadosPorId = $datos_empleado['marcajesEditadosPorId'] ?? [];
    
    if($mostrar && $totalMarcajes > 0){
        $tEntrada = tendencia($gEntrada);
        $tSalidaAlm = tendencia($gSalidaAlm);
        $tEntradaAlm = tendencia($gEntradaAlm);
        $tSalidaFin = tendencia($gSalidaFin);
        
        $promEntradaFormato = formato_12h(calcular_promedio($gEntrada));
        $promSalidaAlmFormato = formato_12h(calcular_promedio($gSalidaAlm));
        $promEntradaAlmFormato = formato_12h(calcular_promedio($gEntradaAlm));
        $promSalidaFinFormato = formato_12h(calcular_promedio($gSalidaFin));
    }
} else {
    $datos_empleados = [];
    $nombres_empleados = [];
    
    foreach ($ids_array as $id) {
        $datos = obtenerDatosEmpleado($id, $conexion, $fecha_inicio, $fecha_fin, $mostrar);
        if ($datos) {
            $datos_empleados[] = $datos;
            $nombres_empleados[] = $datos['empleado']['nombre'] . " " . $datos['empleado']['apellido'];
        }
    }
    
    $datos_con_datos = array_filter($datos_empleados, function($d) {
        return $d['totalMarcajes'] > 0;
    });
    
    $hay_datos_multiple = !empty($datos_con_datos);
}

$dias_laborables_info = calcular_dias_laborables($fecha_inicio, $fecha_fin);
$dias_laborables_periodo = $dias_laborables_info['total'];
$fechas_laborables = $dias_laborables_info['fechas'];

// PARA EL ENCABEZADO: Usamos formato corto (con abreviatura)
$fecha_inicio_corta = formatearFechaCorto($fecha_inicio);
$fecha_fin_corta = formatearFechaCorto($fecha_fin);
$periodoTexto = $fecha_inicio_corta . " al " . $fecha_fin_corta;

// PARA LA TABLA: Usamos formato largo (con mes numérico)
// Esto se aplica directamente en el bucle
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?= $modo_multiple ? 'Reporte Múltiple - Viña' : 'Reporte Viña' ?></title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    /* COLORES DE MARCAJES */
    .tarde { background: #ed8e6e !important; color: #000000 !important; font-weight: bold; }
    .tarde9 { background: #a3d0eb !important; color: #000000 !important; font-weight: bold; }
    .faltante { background: #f5f178 !important; color: #000000 !important; font-weight: bold; }
    .verde { background: #9ce79c !important; color: #000000 !important; font-weight: bold; }
    .naranja { background: #ebc094 !important; color: #000000 !important; font-weight: bold; }
    .morado { background: #ccb8ef !important; color: #000000 !important; font-weight: bold; }
    .dia-descanso { background-color: #e0e0e0 !important; color: #000000 !important; font-style: italic; }
    
    /* Estilo para marcajes editados manualmente */
    .editado-manual {
        position: relative;
        cursor: help;
        padding-right: 20px !important;
    }
    .editado-manual::after {
        content: "✏️";
        font-size: 12px;
        position: absolute;
        top: 2px;
        right: 2px;
        opacity: 0.9;
        z-index: 5;
    }
    
    /* Estilo para situaciones editadas */
    .situacion-editada {
        position: relative;
        cursor: help;
    }
    .situacion-editada::after {
        content: "✏️";
        font-size: 12px;
        position: absolute;
        top: 2px;
        right: 2px;
        opacity: 0.9;
        z-index: 5;
    }
    
    /* Estilo para el texto en celdas faltantes */
    .faltante-texto {
        font-style: italic;
        color: #000000;
        font-weight: bold;
    }
    
    /* COLORES DE SITUACIONES */
    .situacion-permiso,
    .situacion-permiso td,
    td.situacion-permiso,
    tr.situacion-permiso td {
        background-color: #57df77 !important;
        color: #000000 !important;
        font-weight: bold;
    }
    
    .situacion-vacacion,
    .situacion-vacacion td,
    td.situacion-vacacion,
    tr.situacion-vacacion td {
        background-color: #cec12c !important;  
        color: #000000 !important;
        font-weight: bold;
    }
    
    .situacion-enfermedad,
    .situacion-enfermedad td,
    td.situacion-enfermedad,
    tr.situacion-enfermedad td {
        background-color: #cb1052 !important;
        color: #000000 !important;
        font-weight: bold;
    }
    
    .situacion-incapacidad,
    .situacion-incapacidad td,
    td.situacion-incapacidad,
    tr.situacion-incapacidad td {
        background-color: #b727ab !important;
        color: #000000 !important;
        font-weight: bold;
    }
    
    .situacion-dia-personal,
    .situacion-dia-personal td,
    td.situacion-dia-personal,
    tr.situacion-dia-personal td {
        background-color: #12beb8 !important;
        color: #000000 !important;
        font-weight: bold;
    }
    
    .situacion-no-se-presento,
    .situacion-no-se-presento td,
    td.situacion-no-se-presento,
    tr.situacion-no-se-presento td {
        background-color: #ec7b7b !important;
        color: #000000 !important;
        font-weight: bold;
    }
    
    /* Estilos para situaciones personalizadas desde la BD */
    <?php foreach ($personalizaciones as $sit => $pers): 
        $clase = 'situacion-' . strtolower(str_replace(' ', '-', $sit));
    ?>
    .<?= $clase ?>,
    .<?= $clase ?> td,
    td.<?= $clase ?> {
        background-color: <?= $pers['color_fondo'] ?> !important;
        color: <?= $pers['color_texto'] ?? '#000000' ?> !important;
        font-weight: bold;
    }
    <?php endforeach; ?>
    
    /* Estilos para asegurar que los colores personalizados se apliquen */
    <?php foreach ($situaciones_predefinidas as $sit): 
        $color = obtenerColorSituacion($sit, $personalizaciones);
    ?>
    .situacion-<?= strtolower(str_replace(' ', '-', $sit)) ?>,
    .situacion-<?= strtolower(str_replace(' ', '-', $sit)) ?> td,
    td.situacion-<?= strtolower(str_replace(' ', '-', $sit)) ?> {
        background-color: <?= $color ?> !important;
        color: #000000 !important;
        font-weight: bold;
    }
    <?php endforeach; ?>
    
    .no-presentado {
        background-color: #ec7b7b !important;
        color: #000000 !important;
        font-weight: bold;
        text-align: center;
    }
    .no-presentado td {
        background-color: #ec7b7b;
        color: #000000;
        font-weight: bold;
        text-align: center;
    }
    .mensaje-no-presentado {
        font-style: italic;
        text-align: center;
        color: #000000;
    }
    .dia-descanso td {
        background-color: #e0e0e0;
        color: #000000;
    }
    
    .graficas-container {
        margin: 40px 0;
        padding: 20px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 6px 18px rgba(0,0,0,.12);
    }
    .graficas-titulo {
        text-align: center;
        font-weight: bold;
        padding: 15px;
        background: #eef0f6;
        border-bottom: 4px solid #5b2a82;
        font-size: 19px;
        margin-bottom: 20px;
    }
    .graficas-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }
    .grafica-item {
        text-align: center;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
    }
    .promedio-badge {
        font-size: 18px;
        font-weight: bold;
        color: #5b2a82;
        margin-bottom: 15px;
        background: #eef0f6;
        padding: 8px 16px;
        border-radius: 30px;
        display: inline-block;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .empleado-sticky-header {
        position: sticky;
        top: 0;
        z-index: 100;
        background: #f5f5f5;
        margin-bottom: 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border-radius: 10px;
        overflow: hidden;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        border: 1px solid #ddd;
    }
    
    .empleado-info {
        flex: 2;
        min-width: 300px;
        padding: 15px 20px;
        background: #f5f5f5;
    }
    
    .empleado-nombre {
        margin: 0;
        font-size: 24px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
        color: #222;
    }
    
    .empleado-detalles {
        margin: 5px 0 0 0;
        font-size: 14px;
        color: #555;
    }
    
    .empleado-detalles strong {
        color: #333;
    }
    
    .resumen-sticky-grid {
        flex: 3;
        display: flex;
        background: #e9e9e9;
        border-left: 3px solid #5b2a82;
        min-width: 400px;
    }
    
    .resumen-sticky-item {
        flex: 1;
        padding: 12px 8px;
        text-align: center;
        border-right: 1px solid rgba(0,0,0,0.1);
    }
    
    .resumen-sticky-item:last-child {
        border-right: none;
    }
    
    .resumen-sticky-titulo {
        font-weight: bold;
        font-size: 12px;
        color: #5b2a82;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        white-space: nowrap;
    }
    
    .resumen-sticky-valor {
        font-size: 16px;
        font-weight: bold;
        color: #333;
        white-space: nowrap;
    }
    
    .periodo-sticky-valor {
        font-size: 13px;
        white-space: normal;
    }
    
    th {
        position: sticky;
        top: 100px;
        z-index: 90;
        background: #5b2a82;
        color: white;
        padding: 12px;
        text-align: center;
        font-size: 14px;
        box-shadow: 0 2px 2px -1px rgba(0,0,0,0.1);
    }
    
    td {
        border: 1px solid #ddd;
        padding: 10px;
        text-align: center;
        font-size: 13px;
    }
    
    .btn-volver, .btn-volver-fijo {
        background: #5b2a82;
        color: white;
        padding: 12px 0;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        border: none;
        cursor: pointer;
        font-size: 14px;
    }
    
    .btn-volver:hover, .btn-volver-fijo:hover {
        background: #6b3a92;
    }
    
    .btn-imprimir, .btn-imprimir-fijo {
        background: #2c7be5;
        color: white;
        padding: 12px 0;
        border: none;
        border-radius: 8px;
        font-weight: bold;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        font-size: 14px;
    }
    
    .btn-imprimir:hover, .btn-imprimir-fijo:hover {
        background: #3c8bf5;
    }
    
    .btn-editar-fijo {
        background: #41c7ce;
        color: white;
        padding: 12px 0;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        border: none;
        cursor: pointer;
        font-size: 14px;
    }
    
    .btn-editar-fijo:hover {
        background: #83d4cd;
    }
    
    .botones-fijos {
        position: fixed;
        right: 20px;
        top: 20px;
        z-index: 1000;
    }
    
    .botones-container {
        display: flex;
        flex-direction: column;
        gap: 12px;
        align-items: flex-end;
    }
    
    .boton-fijo {
        display: flex;
        justify-content: flex-end;
        width: 140px;
    }
    
    body {
        font-family: 'Segoe UI', Arial, sans-serif;
        background: linear-gradient(135deg, #526513 0%, #06a388 100%);
        min-height: 100vh;
        padding: 20px;
        margin: 0;
    }
    
    .reporte {
        background: white;
        max-width: 1000px;
        margin: auto;
        padding: 40px;
        border-radius: 10px;
        box-shadow: 0 10px 25px rgba(0,0,0,.15);
        margin-top: 20px;
    }
    
    header {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        margin-bottom: 20px;
    }
    
    header img {
        max-width: 100%;
        height: auto;
        display: block;
        margin: 0 auto;
    }
    
    /* Estilos para el membrete de texto como respaldo */
    .membrete-texto {
        display: none;
    }
    
    hr {
        margin: 25px 0;
        height: 3px;
        border: none;
        background: #5b2a82;
    }
    
    .filtro-flex {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .rango-box {
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
        margin-left: auto;
    }
    
    .fecha-group {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .msg-bienvenida {
        background: #9fecf0;
        color: #2d3748;
        padding: 18px 20px;
        border-radius: 10px;
        margin: 25px 0;
        text-align: center;
        font-size: 15px;
        box-shadow: 0 4px 12px rgba(91,42,130,0.12);
        max-width: 90%;
        margin-left: auto;
        margin-right: auto;
    }
    
    .msg-vacio {
        background: #bef9f9;
        color: #000000;
        padding: 14px;
        border-radius: 8px;
        margin: 25px 0;
        font-weight: bold;
        text-align: center;
    }
    
    .zona-imprimir {
        margin-top: 50px;
        text-align: center;
    }
    
    .zona-volver {
        margin-top: 80px;
        text-align: center;
    }
    
    input[type="date"] {
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
    }
    
    .rango-box button {
        background: #5b2a82;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: bold;
        cursor: pointer;
    }
    
    .rango-box button:hover {
        background: #6b3a92;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
    }
    

    .leyenda-centrada {
        display: flex;
        flex-wrap: nowrap;
        gap: 8px;
        align-items: center;
        justify-content: flex-start;
        margin: 10px 0;
        padding: 8px 12px;
        background: #f8f9fa;
        border-radius: 8px;
        font-size: 11px;
        overflow-x: auto;
        white-space: nowrap;
        max-width: 100%;
        border: 1px solid #e0e0e0;
    }
    
    .leyenda-centrada > div {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        flex-shrink: 0;
    }
    
    .leyenda-centrada .separador {
        width: 1px;
        height: 20px;
        background: #ccc;
        margin: 0 5px;
        display: inline-block;
        flex-shrink: 0;
    }
    
    /* Ajuste para los nombres de días completos */
    td:nth-child(2), th:nth-child(2) {
        min-width: 90px;
        text-align: center;
    }
    

    .page-break {
        page-break-before: always;
    }
    
    .empleado-seccion {
        page-break-inside: avoid;
    }
    
    table {
        page-break-inside: auto;
    }
    
    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }
    
    thead {
        display: table-header-group;
    }
    
    tfoot {
        display: table-footer-group;
    }
    
    @media (max-width: 768px) {
        .botones-fijos {
            position: static;
            margin-bottom: 20px;
        }
        .botones-container {
            flex-direction: row;
            justify-content: center;
        }
        .filtro-flex {
            flex-direction: column;
        }
        .rango-box {
            width: 100%;
            margin-left: 0;
            justify-content: center;
        }
        .empleado-sticky-header {
            flex-direction: column;
        }
        .resumen-sticky-grid {
            width: 100%;
            border-left: none;
            border-top: 3px solid #5b2a82;
        }
        th {
            top: 200px;
        }
        .graficas-grid {
            grid-template-columns: 1fr;
        }
        td:nth-child(2), th:nth-child(2) {
            min-width: 80px;
        }
        .leyenda-centrada {
            font-size: 10px;
            gap: 5px;
            padding: 6px 8px;
        }
    }
    
    @media print {
        .no-print { 
            display: none !important; 
        }
        .botones-fijos { 
            display: none !important; 
        }
        .leyenda-centrada {
            display: none !important;
        }
        body { 
            background: white; 
            padding: 0.5in;
            margin: 0;
        }
        .reporte { 
            box-shadow: none; 
            max-width: 100%; 
            padding: 0; 
            margin-top: 0; 
        }

        header {
            display: flex !important;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        header img {
            max-width: 80%;
            height: auto;
            display: block;
            margin: 0 auto;
        }
        hr {
            display: none !important;
        }
        th { 
            position: static; 
            background: #5b2a82 !important;
            color: white !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .situacion-permiso, .situacion-vacacion, .situacion-enfermedad,
        .situacion-incapacidad, .situacion-dia-personal, .situacion-no-se-presento,
        .tarde, .tarde9, .faltante, .verde, .naranja, .morado {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .empleado-sticky-header {
            position: static;
            box-shadow: none;
            border: 1px solid #ddd;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .graficas-container {
            page-break-inside: avoid;
        }
        .page-break {
            page-break-before: always;
        }
        .empleado-seccion {
            page-break-before: always;
            page-break-inside: avoid;
        }
        .empleado-seccion:first-of-type {
            page-break-before: auto;
        }
        /* Ajuste para que las gráficas se vean bien */
        .graficas-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .grafica-item {
            padding: 10px;
        }
        canvas {
            max-width: 100%;
            height: auto;
        }
    }
</style>
</head>
<body>

<div class="botones-fijos no-print">
    <div class="botones-container">
        <?php if($mostrar && (($modo_multiple && $hay_datos_multiple) || (!$modo_multiple && $totalMarcajes>0))): ?>
        <div class="boton-fijo">
            <button type="button" class="btn-imprimir-fijo" onclick="window.print()">🖨 Imprimir</button>
        </div>
        <?php endif; ?>
        <div class="boton-fijo">
            <a href="empleados.php" class="btn-volver-fijo">⬅ Volver</a>
        </div>
        <?php if($mostrar): ?>
        <div class="boton-fijo">
            <?php $ids_param = implode(',', $ids_array); ?>
            <a href="editar_marcajes.php?ids=<?= $ids_param ?>&fecha_inicio=<?= urlencode($fecha_inicio) ?>&fecha_fin=<?= urlencode($fecha_fin) ?>" class="btn-editar-fijo">✏️ Editar</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="reporte">

<!-- NUEVO MEMBRETE CENTRADO -->
<header>
    <img src="../img/deditos1.png" alt="VIÑA - Asociación">
    <!-- Texto de respaldo si la imagen no carga -->
    <div class="membrete-texto">
        <h1>VIÑA</h1>
        <p>AUDIO · VIDEO · MÚSICA · EDUCACIÓN · DRAMA</p>
        <p>9a Av. 'B' 7-19 Zona 2, 07001 Sololá, Guatemala</p>
        <p>Teléfonos: 7762-3231 · 7762-4244 · info@vinyastudios.org</p>
    </div>
</header>

<hr class="no-print">

<form method="GET" class="filtro filtro-flex no-print">

<!-- LEYENDA MODIFICADA  -->
<div class="leyenda-centrada">
    <div>
        <span style="width: 14px; height: 14px; background: #ebc094; border-radius: 3px; border: 1px solid #aaa; display: inline-block;"></span>
        <span>Llegó antes (antes 7:00)</span>
    </div>
    
    <div>
        <span style="width: 14px; height: 14px; background: #ed8e6e; border-radius: 3px; border: 1px solid #aaa; display: inline-block;"></span>
        <span>Después 08:00</span>
    </div>
    
    <div>
        <span style="width: 14px; height: 14px; background: #a3d0eb; border-radius: 3px; border: 1px solid #aaa; display: inline-block;"></span>
        <span>Después 09:00</span>
    </div>
    
    <div>
        <span style="width: 14px; height: 14px; background: #f5f178; border-radius: 3px; border: 1px solid #aaa; display: inline-block;"></span>
        <span>No marcó</span>
    </div>
    
    <div>
        <span style="width: 14px; height: 14px; background: #9ce79c; border-radius: 3px; border: 1px solid #aaa; display: inline-block;"></span>
        <span>Salió después (después 18:00)</span>
    </div>
    
    <div>
        <span style="width: 14px; height: 14px; background: #ccb8ef; border-radius: 3px; border: 1px solid #aaa; display: inline-block;"></span>
        <span>Salió antes (antes 16:00)</span>
    </div>
    
    <div>
        <span style="width: 14px; height: 14px; background: #e0e0e0; border-radius: 3px; border: 1px solid #aaa; display: inline-block;"></span>
        <span>Fin de semana</span>
    </div>
    
    <div>
        <span style="font-size: 12px;">✏️</span>
        <span>Editado</span>
    </div>
    
    <span class="separador"></span>
    
    <?php 
    foreach ($situaciones_predefinidas as $sit): 
        $color = obtenerColorSituacion($sit, $personalizaciones);
        $texto = $sit;
        if ($texto == 'No se presentó') $texto = 'No presentó';
    ?>
    <div>
        <span style="width: 14px; height: 14px; background: <?= $color ?>; border-radius: 3px; border: 1px solid #aaa; display: inline-block;"></span>
        <span><?= $texto ?></span>
    </div>
    <?php endforeach; ?>
</div>

<div class="rango-box no-print">
    <?php if($modo_multiple): ?>
        <?php foreach($ids_array as $id_val): ?>
            <input type="hidden" name="id[]" value="<?= $id_val ?>">
        <?php endforeach; ?>
    <?php else: ?>
        <input type="hidden" name="id" value="<?= $ids_array[0] ?>">
    <?php endif; ?>
    
    <div class="fecha-group">
        <label><b>Desde:</b></label>
        <input type="date" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>" style="padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
    </div>
    
    <div class="fecha-group">
        <label><b>Hasta:</b></label>
        <input type="date" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>" style="padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
    </div>

    <button class="btn-ver" style="background: #5b2a82; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer;">Ver</button>
</div>

</form>

<?php if(!$mostrar): ?>
    <div class="msg-bienvenida no-print">
        <strong>¡Bienvenido al sistema de reportes!</strong><br><br>
        Por favor, selecciona un rango de fechas y presiona el botón <strong>"Ver"</strong>.
    </div>
<?php elseif($modo_multiple && !$hay_datos_multiple): ?>
    <div class="msg-vacio no-print">No hay datos registrados en el rango seleccionado.</div>
<?php else: ?>

    <?php if($modo_multiple): ?>

        <!-- Encabezado del reporte múltiple (solo visible en pantalla, no en impresión) -->
        <div class="no-print" style="background: #0e838e; color: white; padding: 12px 18px; border-radius: 6px; margin-bottom: 25px;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <div><strong>REPORTE MÚLTIPLE</strong> <span><?= $fecha_inicio_corta ?> - <?= $fecha_fin_corta ?></span></div>
                <div><span style="background: rgba(0,0,0,0.2); padding: 5px 12px; border-radius: 20px;"><?= $total_empleados ?> empleado(s)</span></div>
            </div>
            <div style="margin-top: 8px; background: rgba(255,255,255,0.1); padding: 8px 12px; border-radius: 4px;">
                <strong>Empleados:</strong> <?= htmlspecialchars(implode(' · ', $nombres_empleados)) ?>
            </div>
        </div>
        
        <?php foreach($datos_con_datos as $idx => $emp_data): ?>
            <?php 
            $emp = $emp_data['empleado'];
            $emp_id_unico = $emp['id_empleado'];
            $emp_nombre_completo = $emp['nombre']." ".$emp['apellido'];
            $situaciones_emp = $emp_data['situaciones'];
            $marcajesEditados_emp = $emp_data['marcajesEditados'];
            $marcajesEditadosPorId_emp = $emp_data['marcajesEditadosPorId'] ?? [];
            
            $tEntrada_ind = tendencia($emp_data['gEntrada']);
            $tSalidaAlm_ind = tendencia($emp_data['gSalidaAlm']);
            $tEntradaAlm_ind = tendencia($emp_data['gEntradaAlm']);
            $tSalidaFin_ind = tendencia($emp_data['gSalidaFin']);
            
            $promEntradaFormato_ind = formato_12h(calcular_promedio($emp_data['gEntrada']));
            $promSalidaAlmFormato_ind = formato_12h(calcular_promedio($emp_data['gSalidaAlm']));
            $promEntradaAlmFormato_ind = formato_12h(calcular_promedio($emp_data['gEntradaAlm']));
            $promSalidaFinFormato_ind = formato_12h(calcular_promedio($emp_data['gSalidaFin']));
            ?>
            
            <?php if($idx > 0): ?>
                <!-- Para impresión: salto de página entre empleados -->
                <div class="page-break"></div>
            <?php endif; ?>
            
            <!-- Contenedor de cada empleado con clase para manejo de impresión -->
            <div class="empleado-seccion <?= $idx > 0 ? 'page-break' : '' ?>">
                
                <div class="empleado-sticky-header">
                    <div class="empleado-info">
                        <h2 class="empleado-nombre"><span>👤</span> <?= htmlspecialchars($emp_nombre_completo) ?></h2>
                        <p class="empleado-detalles">
                            <strong>Código:</strong> <?= htmlspecialchars($emp['codigo_biometrico'] ?? '') ?> | 
                            <strong>Puesto:</strong> <?= htmlspecialchars($emp['puesto'] ?? '') ?> | 
                            <strong>Horario:</strong> <?= htmlspecialchars($emp['horario'] ?? '-') ?>
                        </p>
                    </div>
                    <div class="resumen-sticky-grid">
                        <div class="resumen-sticky-item">
                            <div class="resumen-sticky-titulo">Periodo</div>
                            <div class="resumen-sticky-valor"><?= $fecha_inicio_corta ?> - <?= $fecha_fin_corta ?></div>
                        </div>
                        <div class="resumen-sticky-item">
                            <div class="resumen-sticky-titulo">Tardanza</div>
                            <div class="resumen-sticky-valor"><?= $emp_data['formatoTarde'] ?></div>
                        </div>
                        <div class="resumen-sticky-item">
                            <div class="resumen-sticky-titulo">Días</div>
                            <div class="resumen-sticky-valor"><?= $emp_data['diasTrabajados'] ?>/<?= $dias_laborables_periodo ?></div>
                        </div>
                        <div class="resumen-sticky-item">
                            <div class="resumen-sticky-titulo">Marcajes</div>
                            <div class="resumen-sticky-valor"><?= $emp_data['totalMarcajes'] ?></div>
                        </div>
                    </div>
                </div>
                
                <table width="100%" class="multiple-empleado">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Día</th>
                            <th>Fecha</th>
                            <th>Entrada</th>
                            <th>Salida Alm</th>
                            <th>Entrada Alm</th>
                            <th>Salida</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $i=1; foreach($fechas_laborables as $fecha): ?>
                        <?php
                        $tiene_marcajes = isset($emp_data['porDia'][$fecha]);
                        $situacion = $situaciones_emp[$fecha] ?? '';
                        $clase_situacion = claseSituacion($situacion, $personalizaciones);
                        $situacion_formateada = mostrarSituacion($situacion, $personalizaciones);
                        // Usar la nueva función para días completos
                        $dia_completo = obtenerDiaCompleto($fecha);
                        // EN LA TABLA: Usamos formato largo (con mes numérico)
                        $fecha_formateada_larga = formatearFechaLarga($fecha);
                        $editado = $marcajesEditados_emp[$fecha] ?? false;
                        $clase_editado = $editado ? 'editado-manual' : '';
                        ?>
                        
                        <?php if($tiene_marcajes): 
                            $dia = $emp_data['porDia'][$fecha];
                            $entrada_vacia = empty($dia['entrada_manana']);
                            $salida_alm_vacia = empty($dia['salida_almuerzo']);
                            $entrada_alm_vacia = empty($dia['entrada_almuerzo']);
                            $salida_fin_vacia = empty($dia['salida_final']);
                            
                            // Verificar si cada marcaje específico fue editado
                            $entrada_editada = !$entrada_vacia && ($marcajesEditadosPorId_emp[$dia['id_registro_entrada']] ?? false);
                            $salida_alm_editada = !$salida_alm_vacia && ($marcajesEditadosPorId_emp[$dia['id_registro_salida_alm']] ?? false);
                            $entrada_alm_editada = !$entrada_alm_vacia && ($marcajesEditadosPorId_emp[$dia['id_registro_entrada_alm']] ?? false);
                            $salida_fin_editada = !$salida_fin_vacia && ($marcajesEditadosPorId_emp[$dia['id_registro_salida_fin']] ?? false);
                        ?>
                            <tr class="<?= $clase_situacion ?>">
                                <td><?= $i++ ?></td>
                                <td><strong><?= $dia_completo ?></strong></td>
                                <td><?= $fecha_formateada_larga ?></td>
                                <td class="<?= $entrada_vacia && $situacion ? $clase_situacion : determinarClaseMarcaje('entrada_manana', $dia['entrada_manana'] ?? '') . ' ' . ($entrada_editada ? 'editado-manual' : '') ?>">
                                    <?php 
                                    if ($entrada_vacia && $situacion) {
                                        echo $situacion_formateada;
                                    } elseif ($entrada_vacia) {
                                        echo '<span class="faltante-texto">No marcó</span>';
                                    } else {
                                        echo formatearHora12($dia['entrada_manana']);
                                    }
                                    ?>
                                </td>
                                <td class="<?= $salida_alm_vacia && $situacion ? $clase_situacion : determinarClaseMarcaje('salida_almuerzo', $dia['salida_almuerzo'] ?? '') . ' ' . ($salida_alm_editada ? 'editado-manual' : '') ?>">
                                    <?php 
                                    if ($salida_alm_vacia && $situacion) {
                                        echo $situacion_formateada;
                                    } elseif ($salida_alm_vacia) {
                                        echo '<span class="faltante-texto">No marcó</span>';
                                    } else {
                                        echo formatearHora12($dia['salida_almuerzo']);
                                    }
                                    ?>
                                </td>
                                <td class="<?= $entrada_alm_vacia && $situacion ? $clase_situacion : determinarClaseMarcaje('entrada_almuerzo', $dia['entrada_almuerzo'] ?? '') . ' ' . ($entrada_alm_editada ? 'editado-manual' : '') ?>">
                                    <?php 
                                    if ($entrada_alm_vacia && $situacion) {
                                        echo $situacion_formateada;
                                    } elseif ($entrada_alm_vacia) {
                                        echo '<span class="faltante-texto">No marcó</span>';
                                    } else {
                                        echo formatearHora12($dia['entrada_almuerzo']);
                                    }
                                    ?>
                                </td>
                                <td class="<?= $salida_fin_vacia && $situacion ? $clase_situacion : determinarClaseMarcaje('salida_final', $dia['salida_final'] ?? '') . ' ' . ($salida_fin_editada ? 'editado-manual' : '') ?>">
                                    <?php 
                                    if ($salida_fin_vacia && $situacion) {
                                        echo $situacion_formateada;
                                    } elseif ($salida_fin_vacia) {
                                        echo '<span class="faltante-texto">No marcó</span>';
                                    } else {
                                        echo formatearHora12($dia['salida_final']);
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr class="<?= $clase_situacion ?: 'faltante' ?>">
                                <td><?= $i++ ?></td>
                                <td><strong><?= $dia_completo ?></strong></td>
                                <td><?= $fecha_formateada_larga ?></td>
                                <td colspan="4" style="text-align: center;">
                                    <?= $situacion_formateada ?: '<span class="faltante-texto">No marcó</span>' ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if($emp_data['totalMarcajes']>0 && count($emp_data['labels'])>0): ?>
                <div class="graficas-container">
                    <div class="graficas-titulo">📊 Comportamiento - <?= htmlspecialchars(explode(' ', $emp_nombre_completo)[0]) ?></div>
                    <div class="graficas-grid">
                        <div class="grafica-item">
                            <h4>Entrada 8AM</h4>
                            <div class="promedio-badge">⏱ Promedio: <?= $promEntradaFormato_ind ?></div>
                            <canvas id="grafEntrada_<?= $emp_id_unico ?>" width="360" height="200"></canvas>
                        </div>
                        <div class="grafica-item">
                            <h4>Salida 1PM</h4>
                            <div class="promedio-badge">⏱ Promedio: <?= $promSalidaAlmFormato_ind ?></div>
                            <canvas id="grafSalidaAlm_<?= $emp_id_unico ?>" width="360" height="200"></canvas>
                        </div>
                        <div class="grafica-item">
                            <h4>Entrada 2PM</h4>
                            <div class="promedio-badge">⏱ Promedio: <?= $promEntradaAlmFormato_ind ?></div>
                            <canvas id="grafEntradaAlm_<?= $emp_id_unico ?>" width="360" height="200"></canvas>
                        </div>
                        <div class="grafica-item">
                            <h4>Salida 5PM</h4>
                            <div class="promedio-badge">⏱ Promedio: <?= $promSalidaFinFormato_ind ?></div>
                            <canvas id="grafSalidaFin_<?= $emp_id_unico ?>" width="360" height="200"></canvas>
                        </div>
                    </div>
                </div>
                
                <script>
                (function() {
                    const labels_<?= $emp_id_unico ?> = <?= json_encode($emp_data['labels']) ?>;
                    function crearGraf_<?= $emp_id_unico ?>(id, datos, tendencia, color) {
                        const canvas = document.getElementById(id);
                        if (!canvas) return;
                        new Chart(canvas, {
                            type: 'line',
                            data: {
                                labels: labels_<?= $emp_id_unico ?>,
                                datasets: [
                                    { label: 'Horario', data: datos, borderColor: color, backgroundColor: color.replace('1)', '.15)'), tension: 0.35, pointRadius: 4, borderWidth: 2, fill: true },
                                    { label: 'Tendencia', data: tendencia, borderColor: 'rgba(241,196,15,1)', borderDash: [6,6], tension: 0, pointRadius: 0, borderWidth: 3, fill: false }
                                ]
                            },
                            options: { responsive: false, plugins: { legend: { display: true } }, scales: { y: { ticks: { callback: function(v) {
                                let h = Math.floor(v), m = Math.round((v - h) * 60), ampm = h >= 12 ? 'PM' : 'AM', h12 = h % 12 || 12;
                                return h12 + ":" + String(m).padStart(2,'0') + " " + ampm;
                            } } } } }
                        });
                    }
                    crearGraf_<?= $emp_id_unico ?>("grafEntrada_<?= $emp_id_unico ?>", <?= json_encode($emp_data['gEntrada']) ?>, <?= json_encode($tEntrada_ind) ?>, "rgba(52,152,219,1)");
                    crearGraf_<?= $emp_id_unico ?>("grafEntradaAlm_<?= $emp_id_unico ?>", <?= json_encode($emp_data['gEntradaAlm']) ?>, <?= json_encode($tEntradaAlm_ind) ?>, "rgba(52,152,219,1)");
                    crearGraf_<?= $emp_id_unico ?>("grafSalidaAlm_<?= $emp_id_unico ?>", <?= json_encode($emp_data['gSalidaAlm']) ?>, <?= json_encode($tSalidaAlm_ind) ?>, "rgba(231,76,60,1)");
                    crearGraf_<?= $emp_id_unico ?>("grafSalidaFin_<?= $emp_id_unico ?>", <?= json_encode($emp_data['gSalidaFin']) ?>, <?= json_encode($tSalidaFin_ind) ?>, "rgba(231,76,60,1)");
                })();
                </script>
                <?php endif; ?>
                
            </div> <!-- Cierra empleado-seccion -->
        <?php endforeach; ?>
        
    <?php else: ?>
        <!-- Modo individual -->
        <?php if($totalMarcajes>0): ?>
        
        <div class="empleado-seccion">
            <div class="empleado-sticky-header">
                <div class="empleado-info">
                    <h2 class="empleado-nombre"><span>👤</span> <?= htmlspecialchars($emp['nombre']." ".$emp['apellido']) ?></h2>
                    <p class="empleado-detalles">
                        <strong>Código:</strong> <?= htmlspecialchars($emp['codigo_biometrico'] ?? '') ?> | 
                        <strong>Puesto:</strong> <?= htmlspecialchars($emp['puesto'] ?? '') ?> | 
                        <strong>Horario:</strong> <?= htmlspecialchars($emp['horario'] ?? 'Horario General') ?>
                    </p>
                </div>
                <div class="resumen-sticky-grid">
                    <div class="resumen-sticky-item"><div class="resumen-sticky-titulo">Periodo</div><div class="resumen-sticky-valor"><?= $fecha_inicio_corta ?> - <?= $fecha_fin_corta ?></div></div>
                    <div class="resumen-sticky-item"><div class="resumen-sticky-titulo">Tardanza</div><div class="resumen-sticky-valor"><?= $formatoTarde ?></div></div>
                    <div class="resumen-sticky-item"><div class="resumen-sticky-titulo">Días</div><div class="resumen-sticky-valor"><?= $diasTrabajados ?>/<?= $dias_laborables_periodo ?></div></div>
                    <div class="resumen-sticky-item"><div class="resumen-sticky-titulo">Marcajes</div><div class="resumen-sticky-valor"><?= $totalMarcajes ?></div></div>
                </div>
            </div>
            
            <table width="100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Día</th>
                        <th>Fecha</th>
                        <th>Entrada</th>
                        <th>Salida Alm</th>
                        <th>Entrada Alm</th>
                        <th>Salida</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i=1; foreach($fechas_laborables as $fecha): ?>
                    <?php
                    $tiene_marcajes = isset($porDia[$fecha]);
                    $situacion = $situaciones[$fecha] ?? '';
                    $clase_situacion = claseSituacion($situacion, $personalizaciones);
                    $situacion_formateada = mostrarSituacion($situacion, $personalizaciones);
                    // Usar la nueva función para días completos
                    $dia_completo = obtenerDiaCompleto($fecha);
                    // EN LA TABLA: Usamos formato largo (con mes numérico)
                    $fecha_formateada_larga = formatearFechaLarga($fecha);
                    $editado = $marcajesEditados[$fecha] ?? false;
                    $clase_editado = $editado ? 'editado-manual' : '';
                    
                    // Verificar si cada marcaje específico fue editado
                    $entrada_editada = false;
                    $salida_alm_editada = false;
                    $entrada_alm_editada = false;
                    $salida_fin_editada = false;
                    
                    if ($tiene_marcajes) {
                        $dia = $porDia[$fecha];
                        $entrada_editada = !empty($dia['id_registro_entrada']) && ($marcajesEditadosPorId[$dia['id_registro_entrada']] ?? false);
                        $salida_alm_editada = !empty($dia['id_registro_salida_alm']) && ($marcajesEditadosPorId[$dia['id_registro_salida_alm']] ?? false);
                        $entrada_alm_editada = !empty($dia['id_registro_entrada_alm']) && ($marcajesEditadosPorId[$dia['id_registro_entrada_alm']] ?? false);
                        $salida_fin_editada = !empty($dia['id_registro_salida_fin']) && ($marcajesEditadosPorId[$dia['id_registro_salida_fin']] ?? false);
                    }
                    ?>
                    
                    <?php if($tiene_marcajes): 
                        $dia = $porDia[$fecha];
                        $entrada_vacia = empty($dia['entrada_manana']);
                        $salida_alm_vacia = empty($dia['salida_almuerzo']);
                        $entrada_alm_vacia = empty($dia['entrada_almuerzo']);
                        $salida_fin_vacia = empty($dia['salida_final']);
                    ?>
                        <tr class="<?= $clase_situacion ?>">
                            <td><?= $i++ ?></td>
                            <td><strong><?= $dia_completo ?></strong></td>
                            <td><?= $fecha_formateada_larga ?></td>
                            <td class="<?= $entrada_vacia && $situacion ? $clase_situacion : determinarClaseMarcaje('entrada_manana', $dia['entrada_manana'] ?? '') . ' ' . ($entrada_editada ? 'editado-manual' : '') ?>">
                                <?php 
                                if ($entrada_vacia && $situacion) {
                                    echo $situacion_formateada;
                                } elseif ($entrada_vacia) {
                                    echo '<span class="faltante-texto">No marcó</span>';
                                } else {
                                    echo formatearHora12($dia['entrada_manana']);
                                }
                                ?>
                            </td>
                            <td class="<?= $salida_alm_vacia && $situacion ? $clase_situacion : determinarClaseMarcaje('salida_almuerzo', $dia['salida_almuerzo'] ?? '') . ' ' . ($salida_alm_editada ? 'editado-manual' : '') ?>">
                                <?php 
                                if ($salida_alm_vacia && $situacion) {
                                    echo $situacion_formateada;
                                } elseif ($salida_alm_vacia) {
                                    echo '<span class="faltante-texto">No marcó</span>';
                                } else {
                                    echo formatearHora12($dia['salida_almuerzo']);
                                }
                                ?>
                            </td>
                            <td class="<?= $entrada_alm_vacia && $situacion ? $clase_situacion : determinarClaseMarcaje('entrada_almuerzo', $dia['entrada_almuerzo'] ?? '') . ' ' . ($entrada_alm_editada ? 'editado-manual' : '') ?>">
                                <?php 
                                if ($entrada_alm_vacia && $situacion) {
                                    echo $situacion_formateada;
                                } elseif ($entrada_alm_vacia) {
                                    echo '<span class="faltante-texto">No marcó</span>';
                                } else {
                                    echo formatearHora12($dia['entrada_almuerzo']);
                                }
                                ?>
                            </td>
                            <td class="<?= $salida_fin_vacia && $situacion ? $clase_situacion : determinarClaseMarcaje('salida_final', $dia['salida_final'] ?? '') . ' ' . ($salida_fin_editada ? 'editado-manual' : '') ?>">
                                <?php 
                                if ($salida_fin_vacia && $situacion) {
                                    echo $situacion_formateada;
                                } elseif ($salida_fin_vacia) {
                                    echo '<span class="faltante-texto">No marcó</span>';
                                } else {
                                    echo formatearHora12($dia['salida_final']);
                                }
                                ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr class="<?= $clase_situacion ?: 'faltante' ?>">
                            <td><?= $i++ ?></td>
                            <td><strong><?= $dia_completo ?></strong></td>
                            <td><?= $fecha_formateada_larga ?></td>
                            <td colspan="4" style="text-align: center;">
                                <?= $situacion_formateada ?: '<span class="faltante-texto">No marcó</span>' ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if($totalMarcajes>0 && count($labels)>0): ?>
            <div class="graficas-container">
                <div class="graficas-titulo">📊 Comportamiento - <?= htmlspecialchars(explode(' ', $emp['nombre'])[0]) ?></div>
                <div class="graficas-grid">
                    <div class="grafica-item"><h4>Entrada 8AM</h4><div class="promedio-badge">⏱ Promedio: <?= $promEntradaFormato ?></div><canvas id="grafEntrada" width="360" height="200"></canvas></div>
                    <div class="grafica-item"><h4>Salida 1PM</h4><div class="promedio-badge">⏱ Promedio: <?= $promSalidaAlmFormato ?></div><canvas id="grafSalidaAlmuerzo" width="360" height="200"></canvas></div>
                    <div class="grafica-item"><h4>Entrada 2PM</h4><div class="promedio-badge">⏱ Promedio: <?= $promEntradaAlmFormato ?></div><canvas id="grafEntradaAlmuerzo" width="360" height="200"></canvas></div>
                    <div class="grafica-item"><h4>Salida 5PM</h4><div class="promedio-badge">⏱ Promedio: <?= $promSalidaFinFormato ?></div><canvas id="grafSalidaFinal" width="360" height="200"></canvas></div>
                </div>
            </div>
            
            <script>
            const labels = <?= json_encode($labels) ?>;
            function crearGrafIndividual(id, datos, tendencia, color) {
                const canvas = document.getElementById(id);
                if (!canvas) return;
                new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            { label: 'Horario', data: datos, borderColor: color, backgroundColor: color.replace('1)', '.15)'), tension: 0.35, pointRadius: 4, borderWidth: 2, fill: true },
                            { label: 'Tendencia', data: tendencia, borderColor: 'rgba(241,196,15,1)', borderDash: [6,6], tension: 0, pointRadius: 0, borderWidth: 3, fill: false }
                        ]
                    },
                    options: { responsive: false, plugins: { legend: { display: true } }, scales: { y: { ticks: { callback: function(v) {
                        let h = Math.floor(v), m = Math.round((v - h) * 60), ampm = h >= 12 ? 'PM' : 'AM', h12 = h % 12 || 12;
                        return h12 + ":" + String(m).padStart(2,'0') + " " + ampm;
                    } } } } }
                });
            }
            document.addEventListener('DOMContentLoaded', function() {
                crearGrafIndividual("grafEntrada", <?= json_encode($gEntrada) ?>, <?= json_encode($tEntrada) ?>, "rgba(52,152,219,1)");
                crearGrafIndividual("grafEntradaAlmuerzo", <?= json_encode($gEntradaAlm) ?>, <?= json_encode($tEntradaAlm) ?>, "rgba(52,152,219,1)");
                crearGrafIndividual("grafSalidaAlmuerzo", <?= json_encode($gSalidaAlm) ?>, <?= json_encode($tSalidaAlm) ?>, "rgba(231,76,60,1)");
                crearGrafIndividual("grafSalidaFinal", <?= json_encode($gSalidaFin) ?>, <?= json_encode($tSalidaFin) ?>, "rgba(231,76,60,1)");
            });
            </script>
            <?php endif; ?>
        </div> <!-- Cierra empleado-seccion -->
        
        <?php else: ?>
        <div class="msg-vacio no-print"> No hay datos registrados en el rango seleccionado. </div>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<?php if($mostrar && (($modo_multiple && $hay_datos_multiple) || (!$modo_multiple && $totalMarcajes>0))): ?>
<div class="zona-imprimir no-print">
    <button type="button" class="btn-imprimir" onclick="window.print()">🖨 Imprimir reporte</button>
</div>
<?php endif; ?>

<div class="zona-volver no-print">
    <a href="empleados.php" class="btn-volver">⬅ Volver al panel de empleados</a>
</div>

</div>

</body>

</html>