<?php
include("../desing/conexion.php");

// Crear tabla si no existe
$conexion->query("CREATE TABLE IF NOT EXISTS personalizacion_situaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    situacion_original VARCHAR(100) NOT NULL,
    texto_personalizado VARCHAR(100),
    color_fondo VARCHAR(20),
    color_texto VARCHAR(20),
    UNIQUE KEY (situacion_original)
)");

// Procesar guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_personalizacion'])) {
    foreach ($_POST['personalizacion'] as $situacion => $datos) {
        $texto = $conexion->real_escape_string($datos['texto']);
        $color_fondo = $conexion->real_escape_string($datos['color_fondo']);
        $color_texto = $conexion->real_escape_string($datos['color_texto']);
        
        // Verificar si ya existe
        $check = $conexion->query("SELECT id FROM personalizacion_situaciones WHERE situacion_original = '$situacion'");
        
        if ($check->num_rows > 0) {
            $conexion->query("UPDATE personalizacion_situaciones SET 
                texto_personalizado = '$texto',
                color_fondo = '$color_fondo',
                color_texto = '$color_texto'
                WHERE situacion_original = '$situacion'");
        } else {
            $conexion->query("INSERT INTO personalizacion_situaciones 
                (situacion_original, texto_personalizado, color_fondo, color_texto) 
                VALUES ('$situacion', '$texto', '$color_fondo', '$color_texto')");
        }
    }
    
    $mensaje = "✅ Personalización guardada correctamente";
}

// Obtener personalizaciones actuales
$personalizaciones = [];
$result = $conexion->query("SELECT * FROM personalizacion_situaciones");
while ($row = $result->fetch_assoc()) {
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

// Colores por defecto mejorados para mejor visibilidad
$colores_default = [
    'Permiso' => ['fondo' => '#57df77', 'texto' => '#000000'],
    'Vacación' => ['fondo' => '#cec12c', 'texto' => '#000000'],
    'Enfermedad' => ['fondo' => '#cb1052', 'texto' => '#ffffff'],
    'Incapacidad' => ['fondo' => '#b727ab', 'texto' => '#ffffff'],
    'Día personal' => ['fondo' => '#12beb8', 'texto' => '#000000'],
    'No se presentó' => ['fondo' => '#ec7b7b', 'texto' => '#000000']
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Personalizar Situaciones</title>
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
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        h1 {
            color: #5b2a82;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 28px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            background: #d4edda;
            color: #155724;
            border-left: 5px solid #28a745;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .situacion-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            border-left: 5px solid #5b2a82;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .situacion-titulo {
            font-weight: bold;
            margin-bottom: 20px;
            color: #5b2a82;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .form-group input[type="text"],
        .form-group input[type="color"] {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input[type="text"]:focus,
        .form-group input[type="color"]:focus {
            border-color: #5b2a82;
            outline: none;
        }
        
        .form-group input[type="color"] {
            height: 45px;
            cursor: pointer;
        }
        
        .preview {
            margin-top: 15px;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            border: 2px solid rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .preview:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .btn-guardar {
            background: #28a745;
            color: white;
            border: none;
            padding: 14px 35px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-guardar:hover {
            background: #34ce57;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        
        .btn-volver {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #6c757d;
            color: white;
            padding: 14px 35px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-volver:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }
        
        .botones {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: flex-end;
        }
        
        .info-ayuda {
            background: #e3f2fd;
            border-left: 5px solid #2196f3;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            color: #0d47a1;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            
            .botones {
                flex-direction: column;
            }
            
            .btn-volver, .btn-guardar {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <i class="fas fa-paint-brush"></i>
            Personalizar Situaciones
        </h1>
        
        <div class="info-ayuda">
            <i class="fas fa-info-circle"></i>
            Personaliza cómo se verán las situaciones en los reportes. Los cambios se aplicarán inmediatamente.
        </div>
        
        <?php if (isset($mensaje)): ?>
            <div class="alert">
                <i class="fas fa-check-circle"></i>
                <?= $mensaje ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <?php foreach ($situaciones_predefinidas as $situacion): 
                $personalizado = $personalizaciones[$situacion] ?? null;
                
                $texto = $personalizado['texto_personalizado'] ?? $situacion;
                $color_fondo = $personalizado['color_fondo'] ?? $colores_default[$situacion]['fondo'];
                $color_texto = $personalizado['color_texto'] ?? $colores_default[$situacion]['texto'];
            ?>
                <div class="situacion-item">
                    <div class="situacion-titulo">
                        <i class="fas fa-tag"></i>
                        <?= $situacion ?>
                    </div>
                    
                    <div class="form-group">
                        <label>Texto a mostrar:</label>
                        <input type="text" 
                               name="personalizacion[<?= $situacion ?>][texto]" 
                               value="<?= htmlspecialchars($texto) ?>"
                               placeholder="<?= $situacion ?>"
                               onkeyup="actualizarPreview(this, '<?= str_replace(' ', '_', $situacion) ?>')">
                    </div>
                    
                    <div class="form-group">
                        <label>Color de fondo:</label>
                        <input type="color" 
                               name="personalizacion[<?= $situacion ?>][color_fondo]" 
                               value="<?= $color_fondo ?>"
                               onchange="actualizarPreview(this, '<?= str_replace(' ', '_', $situacion) ?>')">
                    </div>
                    
                    <div class="form-group">
                        <label>Color de texto:</label>
                        <input type="color" 
                               name="personalizacion[<?= $situacion ?>][color_texto]" 
                               value="<?= $color_texto ?>"
                               onchange="actualizarPreview(this, '<?= str_replace(' ', '_', $situacion) ?>')">
                    </div>
                    
                    <div class="preview" id="preview-<?= str_replace(' ', '_', $situacion) ?>" 
                         style="background-color: <?= $color_fondo ?>; color: <?= $color_texto ?>">
                        <?= htmlspecialchars($texto) ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="botones">
                <a href="empleados.php" class="btn-volver">
                    <i class="fas fa-arrow-left"></i>
                    Volver
                </a>
                <button type="submit" name="guardar_personalizacion" class="btn-guardar">
                    <i class="fas fa-save"></i>
                    Guardar Personalización
                </button>
            </div>
        </form>
    </div>
    
    <script>
        function actualizarPreview(elemento, situacionId) {
            const item = elemento.closest('.situacion-item');
            const texto = item.querySelector('input[type="text"]').value;
            const colorFondo = item.querySelector('input[type="color"]:first-of-type').value;
            const colorTexto = item.querySelector('input[type="color"]:last-of-type').value;
            
            const preview = document.getElementById('preview-' + situacionId);
            if (preview) {
                preview.style.backgroundColor = colorFondo;
                preview.style.color = colorTexto;
                preview.textContent = texto || 'Vista previa';
            }
        }
    </script>
</body>
</html>