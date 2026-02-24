<?php
include("conexion.php");

$horarios = $conexion->query("
    SELECT id_horario, nombre
    FROM horarios
    WHERE estado = 1
");

/* 
   Este archivo es para crear el empleado, contiene cada uno de los campos necesarios para hacer los reportes 
 */

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Crear Empleado</title>

</head>

<body>
 
<div class="form-box">
<h2>Nuevo Empleado</h2>

<form method="POST" action="empleado_guardar.php" id="empleadoForm">
    
    <div class="form-group">
        <label class="form-label">Nombre</label>
        <input name="nombre" class="form-input" id="nombre" required>
    </div>
    
    <div class="form-group">
        <label class="form-label">Apellido</label>
        <input name="apellido" class="form-input" id="apellido" required>
    </div>
    
    <div class="form-group">
        <label class="form-label">Código biométrico</label>
        <input name="codigo_biometrico" class="form-input" id="codigo_biometrico" required>
    </div>
    
    <div class="form-group">
        <label class="form-label">Puesto (Ej: Contador, Cajero)</label>
        <input name="puesto" class="form-input" id="puesto" required>
    </div>
    
    <div class="form-group">
        <label class="form-label">Horas por día</label>
        <input type="number" name="horas_trabajo" class="form-input" id="horas_trabajo" min="1" max="24" value="8" required>
    </div>
    
    <div class="form-group">
        <label class="form-label">Horario</label>
        <select name="id_horario" class="form-input" id="id_horario" required>
        <?php while($h=$horarios->fetch_assoc()): ?>
        <option value="<?= $h['id_horario'] ?>">
        <?= htmlspecialchars($h['nombre']) ?>
        </option>
        <?php endwhile; ?>
        </select>
    </div>
    
    <div class="form-group">
        <label class="form-label">Estado</label>
        <select name="estado" class="form-input" id="estado">
        <option value="1">Activo</option>
        <option value="0">Inactivo</option>
        </select>
    </div>

    <div class="form-buttons">
        <button type="button" class="btn-save" id="guardarBtn" onclick="guardarFormulario()">Guardar</button>
        <a href="empleados.php" class="btn-back">Volver</a>
    </div>

</form>
</div>

</body>
</html>

<style>
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: linear-gradient(135deg, #526513 0%, #06a388 100%);
    min-height: 100vh;
    padding: 20px;
    position: relative;
    overflow-x: hidden;
}


.form-box {
    max-width: 600px;
    width: 100%;
    margin: 20px auto;
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.form-box h2 {
    text-align: center;
     color: #5b2a82;
    margin-bottom: 25px;
    font-size: 24px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    color: #444;
    font-size: 14px;
}

.form-input {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 15px;
    transition: border-color 0.3s ease;
    box-sizing: border-box;
}

.form-input:focus {
    outline: none;
    border-color: #06a388;
    box-shadow: 0 0 0 2px rgba(6, 163, 136, 0.1);
}

.form-buttons {
    display: flex;
    gap: 15px;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.btn-save {
    background: #2ecc71;
    color: white;
    padding: 12px 25px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    flex: 1;
    transition: background-color 0.3s ease;
}

.btn-save:hover {
    background: #27ae60;
}

.btn-back {
    background: #777;
    color: white;
    padding: 12px 25px;
    text-decoration: none;
    border-radius: 6px;
    text-align: center;
    font-size: 16px;
    font-weight: 600;
    flex: 1;
    transition: background-color 0.3s ease;
}

.btn-back:hover {
    background: #666;
}

/* Responsive */
@media (max-width: 480px) {
    .form-box {
        padding: 20px;
        margin: 10px;
    }
    
    .form-buttons {
        flex-direction: column;
    }
}
</style>

<script>
// Array con el orden de los campos
const campos = [
    'nombre',
    'apellido', 
    'codigo_biometrico',
    'puesto',
    'horas_trabajo',
    'id_horario',
    'estado'
];

// Función para manejar la tecla Enter
function manejarEnter(event, campoActual) {
    if (event.key === 'Enter') {
        event.preventDefault(); // Prevenir comportamiento por defecto
        
        // Encontrar el índice del campo actual
        const indiceActual = campos.indexOf(campoActual);
        
        // Si no es el último campo, ir al siguiente
        if (indiceActual < campos.length - 1) {
            const siguienteCampo = document.getElementById(campos[indiceActual + 1]);
            if (siguienteCampo) {
                siguienteCampo.focus();
            }
        } else {
            // Si es el último campo (estado), ir al botón Guardar
            document.getElementById('guardarBtn').focus();
        }
    }
}

// Asignar eventos a todos los campos
document.addEventListener('DOMContentLoaded', function() {
    campos.forEach(function(campoId) {
        const campo = document.getElementById(campoId);
        if (campo) {
            campo.addEventListener('keydown', function(event) {
                manejarEnter(event, campoId);
            });
        }
    });
    
    // También asignar evento al botón Guardar para enviar el formulario con Enter
    document.getElementById('guardarBtn').addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            guardarFormulario();
        }
    });
});

// Función para guardar el formulario
function guardarFormulario() {
    // Validar formulario
    let formularioValido = true;
    
    campos.forEach(function(campoId) {
        const campo = document.getElementById(campoId);
        if (campo.hasAttribute('required') && !campo.value.trim()) {
            formularioValido = false;
            campo.style.borderColor = '#e4e73c';
        } else {
            campo.style.borderColor = '#ddd';
        }
    });
    
    if (formularioValido) {
        // Enviar formulario
        document.getElementById('empleadoForm').submit();
    } else {
        alert('Por favor, complete todos los campos requeridos.');
        // Enfocar el primer campo vacío
        for (let i = 0; i < campos.length; i++) {
            const campo = document.getElementById(campos[i]);
            if (campo.hasAttribute('required') && !campo.value.trim()) {
                campo.focus();
                break;
            }
        }
    }
}
</script>








