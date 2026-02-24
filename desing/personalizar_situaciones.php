
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
    
    $mensaje = " Personalización guardada correctamente";
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Personalizar Situaciones</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #526513 0%, #06a388 100%);
            min-height: 100vh;
            padding: 20px;
            margin: 0;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,.15);
        }
        
        h1 {
            color: #5b2a82;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            background: #d4edda;
            color: #155724;
            border-left: 5px solid #28a745;
        }
        
        .situacion-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 5px solid #5b2a82;
        }
        
        .situacion-titulo {
            font-weight: bold;
            margin-bottom: 15px;
            color: #5b2a82;
            font-size: 18px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input[type="text"],
        .form-group input[type="color"] {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group input[type="color"] {
            height: 40px;
            cursor: pointer;
        }
        
        .preview {
            margin-top: 10px;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }
        
        .btn-guardar {
            background: #5b2a82;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        
        .btn-guardar:hover {
            background: #6b3a92;
        }
        
        .btn-volver {
            display: inline-block;
            background: #6c757d;
            color: white;
            padding: 12px 30px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .btn-volver:hover {
            background: #5a6268;
        }
        
        .botones {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px;
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
        
        <?php if (isset($mensaje)): ?>
            <div class="alert"><?= $mensaje ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <?php foreach ($situaciones_predefinidas as $situacion): 
                $personalizado = $personalizaciones[$situacion] ?? null;
                
                $colores_default = [
                    'Permiso' => ['fondo' => '#3adc60', 'texto' => '#070707'],      
                    'Vacación' => ['fondo' => '#cec12c', 'texto' => '#000000'],      
                    'Enfermedad' => ['fondo' => '#df165c', 'texto' => '#ffffff'],    
                    'Incapacidad' => ['fondo' => '#b727ab', 'texto' => '#ffffff'],   
                    'Día personal' => ['fondo' => '#12beb8', 'texto' => '#ffffff'],  
                    'No se presentó' => ['fondo' => '#ec7b7b', 'texto' => '#000000'] 
                ];
                
                $texto = $personalizado['texto_personalizado'] ?? $situacion;
                $color_fondo = $personalizado['color_fondo'] ?? $colores_default[$situacion]['fondo'];
                $color_texto = $personalizado['color_texto'] ?? $colores_default[$situacion]['texto'];
            ?>
                <div class="situacion-item">
                    <div class="situacion-titulo"><?= $situacion ?></div>
                    
                    <div class="form-group">
                        <label>Texto a mostrar:</label>
                        <input type="text" 
                               name="personalizacion[<?= $situacion ?>][texto]" 
                               value="<?= htmlspecialchars($texto) ?>"
                               placeholder="<?= $situacion ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Color de fondo:</label>
                        <input type="color" 
                               name="personalizacion[<?= $situacion ?>][color_fondo]" 
                               value="<?= $color_fondo ?>"
                               onchange="actualizarPreview(this, '<?= $situacion ?>')">
                    </div>
                    
                    <div class="form-group">
                        <label>Color de texto:</label>
                        <input type="color" 
                               name="personalizacion[<?= $situacion ?>][color_texto]" 
                               value="<?= $color_texto ?>"
                               onchange="actualizarPreview(this, '<?= $situacion ?>')">
                    </div>
                    
                    <div class="preview" id="preview-<?= str_replace(' ', '_', $situacion) ?>" 
                         style="background-color: <?= $color_fondo ?>; color: <?= $color_texto ?>">
                        <?= htmlspecialchars($texto) ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="botones">
                <a href="empleados.php" class="btn-volver">← Volver</a>
                <button type="submit" name="guardar_personalizacion" class="btn-guardar">Guardar Personalización</button>
            </div>
        </form>
    </div>
    
    <script>
        function actualizarPreview(input, situacion) {
            const item = input.closest('.situacion-item');
            const texto = item.querySelector('input[type="text"]').value;
            const colorFondo = item.querySelector('input[type="color"]:first-of-type').value;
            const colorTexto = item.querySelector('input[type="color"]:last-of-type').value;
            
            const preview = item.querySelector('.preview');
            preview.style.backgroundColor = colorFondo;
            preview.style.color = colorTexto;
            preview.textContent = texto;
        }
    </script>
</body>
</html>