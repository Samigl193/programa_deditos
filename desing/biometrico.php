<?php
session_start();
include("conexion.php");

// Mensaje de confirmacion de que el archivo se subio 
$mensaje = '';
if (isset($_SESSION['mensaje_archivo'])) {
    $mensaje = $_SESSION['mensaje_archivo'];
    unset($_SESSION['mensaje_archivo']); // aca se muestra el mensaje del archivo 
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Importar archivo biométrico</title>
<style>
body{
    font-family:Arial;
    background:#f2f2f2;
    padding:40px
}
.box{
    max-width:500px;
    margin:auto;
    background:white;
    padding:30px;
    border-radius:10px;
    box-shadow:0 8px 20px rgba(0,0,0,.15);
    text-align:center
}
input{margin:15px 0}
button{
    background:#5b2a82;
    color:white;
    padding:12px 25px;
    border:none;
    border-radius:6px;
    cursor:pointer;
}
.btn-volver{
    margin-top:20px;
    background:#3498db;
}

/* Estilos para drag and drop */
.drop-zone {
    border: 2px dashed #5b2a82;
    border-radius: 10px;
    padding: 30px;
    text-align: center;
    background: #f8f4ff;
    cursor: pointer;
    margin: 20px 0;
    transition: all 0.3s ease;
}

.drop-zone.dragover {
    background: #e1d5f0;
    border-color: #3498db;
}

/* Estilo cuando hay un archivo seleccionado */
.drop-zone.file-selected {
    border-color: #28a745;
    background: #d4edda;
}

.drop-zone.file-selected .icon {
    color: #28a745;
}

.drop-zone.file-selected p {
    color: #155724;
}

.drop-zone p {
    margin: 0;
    color: #666;
    font-size: 16px;
    transition: color 0.3s ease;
}

.drop-zone .icon {
    font-size: 40px;
    margin-bottom: 10px;
    color: #5b2a82;
    transition: color 0.3s ease;
}

.file-name {
    margin-top: 10px;
    font-weight: bold;
    color: #5b2a82;
}

.drop-zone.file-selected .file-name {
    color: #155724;
}

.file-input {
    display: none;
}

/* Estilos para mensajes aca se muestran los estilos para los mensajes que deben de aparecer en la pantalla del codigo 
asi que todo va a funcionar aca correctamente  */
.mensaje {
    padding: 15px;
    margin: 20px 0;
    border-radius: 5px;
    font-weight: bold;
    animation: fadeIn 0.5s;
}

.mensaje.exito {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.mensaje.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
</head>

<body>

<div class="box">

<h2>📥 Importar archivo biométrico para reporte</h2>

<?php if ($mensaje): ?>
    <div class="mensaje <?php echo $mensaje['tipo']; ?>">
        <?php echo $mensaje['texto']; ?>
    </div>
<?php endif; ?>

<form method="POST"
      enctype="multipart/form-data"
      action="biometrico_importar.php"
      id="uploadForm">

    <!-- Drop zone -->
    <div class="drop-zone" id="dropZone">
        <div class="icon">📄</div>
        <p>Arrastra o da click aqui y selecciona un archivo</p>
        <div class="file-name" id="fileName"></div> 
    </div>

    <!-- aca es donde funciona para subir el archivo  -->
    <input type="file" 
           name="archivo" 
           id="fileInput" 
           class="file-input" 
           required>

    <br>

    <button type="submit">Click aqui para importar </button> 

</form>

<!-- boton de volver  -->
<br>
<button class="btn-volver" onclick="location.href='empleados.php'">
⬅ Volver al panel de empleados
</button>

</div>

<script>
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const fileName = document.getElementById('fileName');

// Función para actualizar el estilo del drop zone
function updateDropZoneStyle() {
    if (fileInput.files.length > 0) {
        dropZone.classList.add('file-selected');
    } else {
        dropZone.classList.remove('file-selected');
    }
}

// funcion para el drag y el drop 
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, preventDefaults, false);
    document.body.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

// Resaltar drop zone cuando se arrastra un archivo
['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, unhighlight, false);
});

function highlight(e) {
    dropZone.classList.add('dragover');
}

function unhighlight(e) {
    dropZone.classList.remove('dragover');
}

// Manejar el drop de archivos
dropZone.addEventListener('drop', handleDrop, false);

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    
    if (files.length > 0) {
        fileInput.files = files;
        updateFileName(files[0].name);
        updateDropZoneStyle(); // Actualizar estilo cuando se arrastra un archivo
    }
}

// Abrir selector de archivos al hacer clic en el drop zone
dropZone.addEventListener('click', () => {
    fileInput.click();
});

// Actualizar nombre y estilo cuando se selecciona el archivo
fileInput.addEventListener('change', function() {
    if (this.files.length > 0) {
        updateFileName(this.files[0].name);
    } else {
        fileName.textContent = '';
    }
    updateDropZoneStyle(); // Actualiza el estado si hay un archivo cargado 
});

function updateFileName(name) {
    fileName.textContent = 'Archivo seleccionado: ' + name;
}

// Llamar a updateDropZoneStyle al cargar la página por si hay algún archivo preseleccionado
updateDropZoneStyle();
</script>

</body>
</html>






















<html>

<style>
     /* this section is for work ending, so 