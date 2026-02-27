<?php
session_start();
include("conexion.php");

// Verificar si el usuario está logueado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener el rol del usuario
$id_rol = $_SESSION['id_rol'] ?? 0;
$es_master = ($id_rol == 1); // MASTER
$es_admin = ($id_rol == 2);  // ADMIN

// Consulta de empleados
$sql = "
SELECT e.*, h.nombre AS horario
FROM empleados e
LEFT JOIN horarios h ON e.id_horario = h.id_horario
ORDER BY e.id_empleado DESC
";

$res = $conexion->query($sql);

if (!$res) {
    die("❌ Error SQL: " . $conexion->error);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Administrar Empleados</title>
</head>

<body>

<div class="panel">

<h1>Administrar Empleados 
    <?php if($es_master): ?>
        <span style="font-size: 0.8em; background: #5b2a82; color: white; padding: 3px 10px; border-radius: 20px;">Master</span>
    <?php else: ?>
        <span style="font-size: 0.8em; background: #3498db; color: white; padding: 3px 10px; border-radius: 20px;">Admin</span>
    <?php endif; ?>
</h1>

<div class="actions-top">
<div class="dropdown">
    <button class="btn btn-select" onclick="toggleDropdown(event)">🔘 Seleccionar (Uno)</button>
    <div class="dropdown-content" id="dropdownMenu">
        <a href="#" onclick="seleccionarUno(); cerrarDropdown(); return false;">Uno</a>
        <a href="#" onclick="seleccionarVarios(); cerrarDropdown(); return false;">Varios</a>
        <a href="#" onclick="seleccionarTodos(); cerrarDropdown(); return false;">Todos</a>
        <a href="#" onclick="deseleccionarTodos(); cerrarDropdown(); return false;">Ninguno</a>
    </div>
</div>

<button class="btn btn-add" onclick="location.href='empleado_crear.php'">➕ Crear</button>
<button class="btn btn-edit" onclick="editar()">✏ Editar</button>

<!-- Botón Eliminar - Solo visible para MASTER (id_rol = 1) -->
<?php if($es_master): ?>
<button class="btn btn-del" onclick="eliminar()">🗑 Eliminar</button>
<?php endif; ?>

<button class="btn btn-rep" onclick="reporte()">📄 Reporte</button>
<button class="btn btn-subir" onclick="subirArchivo()">📤 Subir archivo</button>
<button class="btn btn-files" onclick="verArchivos()">📂 Ver archivos</button>
<button class="btn btn-panel" onclick="location.href='panel.php'">🏠 Panel General</button>
</div>

<!-- Mensaje informativo solo para ADMIN -->
<?php if($es_admin): ?>
<div style="background-color: #e8f5e8; color: #2e7d32; padding: 10px; border-radius: 4px; margin-bottom: 15px; text-align: center; border-left: 4px solid #2e7d32;">
    <strong>👤 Modo Administrador</strong> - No tienes permisos para eliminar empleados. 
    Si necesitas eliminar algún registro, contacta al Master.
</div>
<?php endif; ?>

<table>
<thead>
<tr>
<th></th>
<th>Código</th>
<th>Nombre</th>
<th>Puesto</th>
<th>Horario</th>
<th>Horas</th>
<th>Estado</th>
</tr>
</thead>

<tbody>
<?php if($res->num_rows>0): ?>
<?php while($row=$res->fetch_assoc()): ?>
<tr>
<td>
    <input type="radio" name="emp" value="<?= $row['id_empleado'] ?>" class="radio-sel">
    <input type="checkbox" name="emp_check[]" value="<?= $row['id_empleado'] ?>" class="checkbox-sel" style="display:none;">
</td>
<td><?= htmlspecialchars($row['codigo_biometrico']) ?></td>
<td><?= htmlspecialchars($row['nombre']." ".$row['apellido']) ?></td>
<td><?= htmlspecialchars($row['puesto']) ?></td>
<td><?= htmlspecialchars($row['horario'] ?? '-') ?></td>
<td><?= htmlspecialchars($row['horas_trabajo']) ?></td>
<td class="<?= $row['estado'] ? 'estado-activo' : 'estado-inactivo' ?>">
    <?= $row['estado'] ? 'Activo' : 'Inactivo' ?>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="7" class="empty">No hay empleados</td></tr>
<?php endif; ?>
</tbody>
</table>

</div>

<script>
let modoSeleccion = 'radio';
let esMaster = <?php echo $es_master ? 'true' : 'false'; ?>;
let esAdmin = <?php echo $es_admin ? 'true' : 'false'; ?>;

// Función para cerrar el dropdown
function cerrarDropdown() {
    const dropdown = document.getElementById('dropdownMenu');
    dropdown.style.display = 'none';
    
    // Restaurar el comportamiento hover después de 200ms
    setTimeout(function() {
        dropdown.style.display = '';
    }, 200);
}

// Función para toggle manual del dropdown
function toggleDropdown(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('dropdownMenu');
    if (dropdown.style.display === 'block') {
        dropdown.style.display = 'none';
    } else {
        dropdown.style.display = 'block';
    }
}

// Cerrar dropdown al hacer clic fuera
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('dropdownMenu');
    const btn = document.querySelector('.btn-select');
    
    if (!btn.contains(event.target) && !dropdown.contains(event.target)) {
        dropdown.style.display = 'none';
    }
});

function getSel() {
    if(modoSeleccion === 'radio') {
        const r = document.querySelector('input[name="emp"]:checked');
        return r ? r.value : null;
    } else {
        const checkboxes = document.querySelectorAll('input[name="emp_check[]"]:checked');
        if(checkboxes.length === 0) return null;
        
        const ids = [];
        checkboxes.forEach(cb => ids.push(cb.value));
        return ids.join(',');
    }
}

function seleccionarUno() {
    modoSeleccion = 'radio';
    
    document.querySelectorAll('.radio-sel').forEach(el => {
        el.style.display = 'inline-block';
        el.checked = false;
    });
    
    document.querySelectorAll('.checkbox-sel').forEach(el => {
        el.style.display = 'none';
        el.checked = false;
    });
    
    const firstRadio = document.querySelector('.radio-sel');
    if(firstRadio) {
        firstRadio.checked = true;
    }
    
    actualizarBotonSeleccionar('Uno');
}

function seleccionarVarios() {
    modoSeleccion = 'checkbox';
    
    document.querySelectorAll('.radio-sel').forEach(el => {
        el.style.display = 'none';
        el.checked = false;
    });
    
    document.querySelectorAll('.checkbox-sel').forEach(el => {
        el.style.display = 'inline-block';
        el.checked = false;
    });
    
    actualizarBotonSeleccionar('Varios');
}

function seleccionarTodos() {
    modoSeleccion = 'checkbox';
    
    document.querySelectorAll('.radio-sel').forEach(el => {
        el.style.display = 'none';
        el.checked = false;
    });
    
    document.querySelectorAll('.checkbox-sel').forEach(el => {
        el.style.display = 'inline-block';
        el.checked = true;
    });
    
    actualizarBotonSeleccionar('Todos');
}

function deseleccionarTodos() {
    document.querySelectorAll('.radio-sel').forEach(el => el.checked = false);
    document.querySelectorAll('.checkbox-sel').forEach(el => el.checked = false);
    actualizarBotonSeleccionar('Ninguno');
}

function actualizarBotonSeleccionar(modo) {
    const boton = document.querySelector('.btn-select');
    boton.innerHTML = `🔘 Seleccionar (${modo})`;
}

function editar() {
    const id = getSel();
    if(!id) return alert("Selecciona al menos un empleado");
    
    if(modoSeleccion === 'checkbox') {
        const ids = id.split(',');
        if(ids.length > 1) {
            return alert("❌ Para editar solo puedes seleccionar UN empleado a la vez");
        }
        location = "empleado_editar.php?id=" + ids[0];
    } else {
        location = "empleado_editar.php?id=" + id;
    }
}

function eliminar() {
    // Solo permitir eliminar si es MASTER
    if (!esMaster) {
        return alert("❌ No tienes permisos para eliminar empleados. Esta función es solo para MASTER.");
    }
    
    const id = getSel();
    if(!id) return alert("Selecciona al menos un empleado");
    
    if(modoSeleccion === 'checkbox') {
        const ids = id.split(',');
        if(confirm(`¿Estás seguro de eliminar ${ids.length} empleado(s)?`)) {
            location = "empleado_eliminar.php?id=" + id;
        }
    } else {
        if(confirm("¿Estás seguro de eliminar este empleado?")) {
            location = "empleado_eliminar.php?id=" + id;
        }
    }
}

function reporte() {
    const id = getSel();
    if(!id) return alert("Selecciona al menos un empleado");
    location = "reporte_empleado.php?id=" + id;
}

function subirArchivo() {
    location = "biometrico.php";
}

function verArchivos() {
    location = "historial_biometrico.php";
}

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    seleccionarUno();
    
    // Prevenir que el hover muestre el dropdown cuando está oculto manualmente
    const dropdownContainer = document.querySelector('.dropdown');
    dropdownContainer.addEventListener('mouseleave', function() {
        const dropdown = document.getElementById('dropdownMenu');
        if (dropdown.style.display === 'none') {
        }
    });
});
</script>

<style>
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: linear-gradient(135deg, #526513 0%, #06a388 100%);
    min-height: 100vh;
    padding: 20px;
}
.panel{
    max-width:1200px;
    margin:40px auto;
    background:white;
    padding:30px;
    border-radius:10px;
    box-shadow:0 6px 18px rgba(0,0,0,.12);
}
.actions-top{
    display:flex;
    gap:10px;
    margin-bottom:15px;
    flex-wrap:wrap;
    align-items:center;
}
.btn{
    padding:10px 18px;
    border-radius:6px;
    border:none;
    cursor:pointer;
    color:white;
    font-weight:bold;
    text-align:center;
}
.btn-add{background:#2ecc71}
.btn-edit{background:#3498db}
.btn-del{background:#e74c3c}
.btn-rep{background:#9b59b6}
.btn-subir{background:#f39c12}
.btn-files{background:#34495e}
.btn-panel{background:#2c7be5; margin-left:auto;}
.btn-select{background:#1abc9c; min-width: 150px;}

.dropdown {
    position: relative;
    display: inline-block;
}
.dropdown-content {
    display: none;
    position: absolute;
    background-color: white;
    min-width: 150px;
    box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
    z-index: 1000;
    border-radius: 6px;
}
.dropdown-content a {
    color: #333;
    padding: 12px 16px;
    text-decoration: none;
    display: block;
    border-bottom: 1px solid #eee;
    cursor: pointer;
}
.dropdown-content a:hover {
    background-color: #f1f1f1;
}
.dropdown:hover .dropdown-content {
    display: block;
}
.dropdown:hover .btn-select {
    background-color: #16a085;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:15px;
}
th{
    background:#5b2a82;
    color:white;
    padding:10px;
}
td{
    border:1px solid #ddd;
    padding:10px;
    text-align:center;
}
.empty{padding:25px;text-align:center;color:#777;}
.estado-activo{background:#d9f2d9; color:#1f5c1f; font-weight:bold;}
.estado-inactivo{background:#f6caca; color:#7a0000; font-weight:bold;}
</style>

</body>
</html>