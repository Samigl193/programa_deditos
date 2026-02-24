<?php
session_start();
include("conexion.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: biometrico.php");
    exit;
}

if (!isset($_FILES["archivo"]) || $_FILES["archivo"]["error"] !== UPLOAD_ERR_OK) {
    die(" Error al subir archivo.");
}

/* 
   1) Guardar archivo en /uploads
 */
$nombreOriginal = basename($_FILES["archivo"]["name"]);
$uploadsDir = __DIR__ . "/uploads";
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

$destinoRel = "uploads/" . time() . "_" . $nombreOriginal;
$destinoAbs = __DIR__ . "/" . $destinoRel;

if (!move_uploaded_file($_FILES["archivo"]["tmp_name"], $destinoAbs)) {
    die(" No se pudo mover el archivo a uploads/");
}

/* 
   2) Insertar en archivos_importados
 */
$stmtA = $conexion->prepare("
    INSERT INTO archivos_importados (nombre_archivo, fecha_importacion)
    VALUES (?, NOW())
");
if (!$stmtA) die(" Prepare archivos_importados: " . $conexion->error);

$stmtA->bind_param("s", $nombreOriginal);
$stmtA->execute();
$idArchivo = $conexion->insert_id;
$stmtA->close();

/* 
   3) Preparar el archivo 
 */
$stmtEmp = $conexion->prepare("
    SELECT id_empleado
    FROM empleados
    WHERE codigo_biometrico = ?
    LIMIT 1
");
if (!$stmtEmp) die(" Prepare empleados: " . $conexion->error);

$stmtIns = $conexion->prepare("
    INSERT INTO registros_biometricos
    (id_archivo, id_empleado, codigo_biometrico, fecha, hora, tipo_marcaje)
    VALUES (?, ?, ?, ?, ?, ?)
");
if (!$stmtIns) die(" Prepare registros_biometricos: " . $conexion->error);

/* 
   4) Leer líneas
*/
$lineas = file($destinoAbs, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$insertados = 0;
$noEncontrados = 0;
$invalidas = 0;

$codigosNoEncontrados = [];

/*
  Para asignar ENTRADA/SALIDA si el archivo no lo trae:
  clave = codigo|fecha  => contador de marcajes
*/
$contPorDia = [];

foreach ($lineas as $linea) {
    $linea = trim($linea);
    if ($linea === "") continue;

    // Soporta comas o espacios/tabs múltiples
    // Si trae comas -> split por comas, si no -> split por espacios
    if (strpos($linea, ",") !== false) {
        $p = array_map("trim", explode(",", $linea));
    } else {
        $p = preg_split('/\s+/', $linea);
    }

    // Necesita al menos: CODIGO FECHA HORA
    if (count($p) < 3) {
        $invalidas++;
        continue;
    }

    $codigo = trim($p[0]);

    // Intentar detectar fecha y hora en posiciones típicas

    $fecha = trim($p[1]);
    $hora  = trim($p[2]);

    // Normalizar hora (si viene 08:01 -> 08:01:00)
    if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
        $hora .= ":00";
    }

    // Validar formato mínimo
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $hora)) {
        // Formato HH:MM:SS
        $fechaAlt = str_replace("/", "-", $fecha);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaAlt)) {
            $fecha = $fechaAlt;
        } else {
            $invalidas++;
            continue;
        }
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $hora)) {
            $invalidas++;
            continue;
        }
    }

    // Buscar empleado por codigo biométrico
    $stmtEmp->bind_param("s", $codigo);
    $stmtEmp->execute();
    $resEmp = $stmtEmp->get_result();
    $emp = $resEmp->fetch_assoc();

    if (!$emp) {
        $noEncontrados++;
        $codigosNoEncontrados[$codigo] = true;
        continue;
    }

    $idEmpleado = (int)$emp["id_empleado"];

    // Tipo marcaje:
    // Si el archivo trae una columna (ENTRADA/SALIDA)
    // Si no, generamos por alternancia diaria
    $tipo = null;
    if (count($p) >= 4) {
        $posibleTipo = strtoupper(trim($p[3]));
        if ($posibleTipo === "ENTRADA" || $posibleTipo === "SALIDA") {
            $tipo = $posibleTipo;
        }
    }

    if ($tipo === null) {
        $key = $codigo . "|" . $fecha;
        if (!isset($contPorDia[$key])) $contPorDia[$key] = 0;
        $contPorDia[$key]++;

        // 1,3,5... = ENTRADA / 2,4,6... = SALIDA
        $tipo = ($contPorDia[$key] % 2 === 1) ? "ENTRADA" : "SALIDA";
    }

    // Insertar registro biométrico
    $stmtIns->bind_param(
        "iissss",
        $idArchivo,
        $idEmpleado,
        $codigo,
        $fecha,
        $hora,
        $tipo
    );

    if ($stmtIns->execute()) {
        $insertados++;
    }
}

/*
   5) Guardar log de códigos no encontrados
 */
$logPath = __DIR__ . "/uploads/log_import_" . $idArchivo . ".txt";
$log = "Archivo ID: {$idArchivo}\n";
$log .= "Insertados: {$insertados}\n";
$log .= "No encontrados (código no existe en empleados): {$noEncontrados}\n";
$log .= "Líneas inválidas: {$invalidas}\n\n";

if (!empty($codigosNoEncontrados)) {
    $log .= "Códigos NO encontrados:\n";
    foreach (array_keys($codigosNoEncontrados) as $c) {
        $log .= "- {$c}\n";
    }
}

file_put_contents($logPath, $log);

$stmtEmp->close();
$stmtIns->close();

/* 
   6) Redirigir a historial con resumen
 */
header("Location: historial_biometrico.php?ok=1&ins={$insertados}&ne={$noEncontrados}&inv={$invalidas}&arch={$idArchivo}");
exit;
