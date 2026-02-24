<?php
include("conexion.php");
// Aca se ven las validaciones para eliminar 

// Primera validación: solo procesar si viene de confirmación doble
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'final') {
    // Mostrar primera confirmación
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        header("Location: empleados.php");
        exit;
    }
    
    $id = (int)$_GET['id'];
    
    // Obtener información del empleado para mostrar en el mensaje
    $sql_info = "SELECT nombre, apellido, puesto, codigo_biometrico FROM empleados WHERE id_empleado = ?";
    $stmt_info = $conexion->prepare($sql_info);
    
    if ($stmt_info) {
        $stmt_info->bind_param("i", $id);
        $stmt_info->execute();
        $result_info = $stmt_info->get_result();
        
        if ($result_info->num_rows > 0) {
            $empleado = $result_info->fetch_assoc();
            $nombre_completo = htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido']);
            $puesto = htmlspecialchars($empleado['puesto']);
            $codigo_biometrico = htmlspecialchars($empleado['codigo_biometrico']);
        } else {
            $nombre_completo = "empleado desconocido";
            $puesto = "puesto desconocido";
            $codigo_biometrico = "N/A";
        }
        $stmt_info->close();
    } else {
        $nombre_completo = "empleado";
        $puesto = "";
        $codigo_biometrico = "N/A";
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Eliminación</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
       
        
        body {
              box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #526513 0%, #06a388 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .confirm-box {
            background: white;
            border-radius: 15px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .warning-icon {
            font-size: 4rem;
            color: #ff6b6b;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.8rem;
        }
        
        .employee-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        
        .employee-info p {
            margin: 10px 0;
            color: #555;
        }
        
        .employee-info strong {
            color: #333;
        }
        
        .warning-text {
            color: #e74c3c;
            font-weight: bold;
            margin: 20px 0;
            font-size: 1.1rem;
            line-height: 1.5;
        }
        
        .buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: center;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-cancel {
            background: #95a5a6;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }
        
        .btn-confirm {
            background: #e74c3c;
            color: white;
        }
        
        .btn-confirm:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .step.active {
            background: #e74c3c;
            color: white;
        }
        
        .step.inactive {
            background: #ecf0f1;
            color: #7f8c8d;
        }
    </style>
    </head>
    <body>
    
    <div class="confirm-box">
        <div class="step-indicator">
            <div class="step active">1</div>
            <div class="step inactive">2</div>
        </div>
        
        <div class="warning-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        
        <h2>¿Deseas eliminar?</h2>
        
        <div class="employee-info">
            <p><strong>Empleado:</strong> <?php echo $nombre_completo; ?></p>
            <p><strong>Puesto:</strong> <?php echo $puesto; ?></p>
            <p><strong>Código Biométrico:</strong> <?php echo $codigo_biometrico; ?></p>
        </div>
        
        <div class="warning-text">
            ¿Estás seguro de que deseas eliminar a este empleado?
            Esta acción eliminará todos los datos relacionados con él.
        </div>
        
        <div class="buttons">
            <a href="empleados.php" class="btn btn-cancel">
                <i class="fas fa-times"></i> Cancelar
            </a>
            <a href="empleado_eliminar.php?id=<?php echo $id; ?>&confirm=final" 
               class="btn btn-confirm"
               onclick="return confirmSecondStep()">
                <i class="fas fa-check"></i> Sí, Eliminar
            </a>
        </div>
    </div>



  <style>
       
        
        body {
            background: linear-gradient(135deg, #526513 0%, #06a388 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .confirm-box {
            background: white;
            border-radius: 15px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
          
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .warning-icon {
            font-size: 4rem;
            color: #ef1111;
            margin-bottom: 20px;
        }
  
        
        h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.8rem;
        }
        
        .employee-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        
        .employee-info p {
            margin: 10px 0;
            color: #555;
        }
        
        .employee-info strong {
            color: #333;
        }
        
        .warning-text {
            color: #e74c3c;
            font-weight: bold;
            margin: 20px 0;
            font-size: 1.1rem;
            line-height: 1.5;
        }
        
        .buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: center;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-cancel {
            background: #95a5a6;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }
        
        .btn-confirm {
            background: #e74c3c;
            color: white;
        }
        
        .btn-confirm:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .step.active {
            background: #e74c3c;
            color: white;
        }
        
        .step.inactive {
            background: #ecf0f1;
            color: #7f8c8d;
        }
    </style>



    
    <script>
    function confirmSecondStep() {
        // Esta función mostrará el segundo mensaje cuando se haga clic en "Sí, Eliminar"
        return confirm("⚠️ ATENCIÓN ⚠️\n\n Esta acción es IRREVERSIBLE\n Se perderán todos los datos del empleado\n\n¿Continuar con la eliminación definitiva?");
    }
    </script>
    
    </body>
    </html>
    <?php
    exit;
}

// Si llega aquí, es la confirmación final
// Validar ID para la eliminación final
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: empleados.php");
    exit;
}

$id = (int)$_GET['id'];

// Eliminar
$stmt = $conexion->prepare(
    "DELETE FROM empleados WHERE id_empleado = ?"
);

if (!$stmt) {
    die("❌ Error prepare: " . $conexion->error);
}

$stmt->bind_param("i", $id);

if (!$stmt->execute()) {
    die("❌ Error al eliminar empleado: " . $stmt->error);
}

$stmt->close();

// Redirigir con mensaje de éxito
header("Location: empleados.php?del=1&msg=empleado_eliminado");
exit;























