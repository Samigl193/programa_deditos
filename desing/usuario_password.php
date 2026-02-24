<?php
session_start();
include("conexion.php");

/* Editar o cambiar la contraseña */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Obtener información del usuario para mostrar en el formulario
$nombre_usuario = "";
if ($id > 0) {
    $sql = "SELECT usuario FROM usuarios WHERE id_usuario = ?";
    $stmt = $conexion->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $nombre_usuario = htmlspecialchars($row['usuario']);
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #526513, #06a388);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 450px;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .form-card {
            background-color: rgba(255, 255, 255, 0.98);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }

        .form-header {
            background: linear-gradient(to right, #243a5e, #2c5282);
            color: white;
            padding: 25px;
            text-align: center;
        }

        .form-header h1 {
            font-size: 1.6rem;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .form-header p {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .user-info {
            background-color: #f8fafc;
            padding: 15px 25px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-info i {
            color: #4a5568;
        }

        .user-info span {
            font-weight: 600;
            color: #2d3748;
        }

        .form-body {
            padding: 30px 25px 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
            font-size: 0.95rem;
        }

        .input-container {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 14px 50px 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1.1rem;
            transition: all 0.3s;
            background-color: white;
            letter-spacing: 1px;
        }

        .form-input:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.2);
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #718096;
            font-size: 1.3rem;
            cursor: pointer;
            transition: color 0.3s;
            padding: 5px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .toggle-password:hover {
            background-color: #f7fafc;
            color: #4a5568;
        }

        .password-hint {
            font-size: 0.85rem;
            color: #718096;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .btn {
            flex: 1;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(to right, #4CAF50, #45a049);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }

        .btn-secondary {
            background-color: #718096;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #4a5568;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(113, 128, 150, 0.3);
        }

        .form-footer {
            padding: 15px 25px 20px;
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            text-align: center;
        }

        .form-footer a {
            color: #4299e1;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .requirements {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            margin-top: 15px;
            font-size: 0.85rem;
            color: #4a5568;
        }

        .requirements h4 {
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .requirements ul {
            padding-left: 20px;
        }

        .requirements li {
            margin-bottom: 3px;
        }

        @media (max-width: 600px) {
            .container {
                max-width: 95%;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-body {
                padding: 20px 15px 15px;
            }
            
            .form-header {
                padding: 20px 15px;
            }
            
            .user-info {
                padding: 12px 15px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="form-card">
        <div class="form-header">
            <h1><i class="fas fa-key"></i> Cambiar Contraseña</h1>
            <p>Actualiza la contraseña de acceso del usuario</p>
        </div>
        
        <?php if($nombre_usuario): ?>
        <div class="user-info">
            <i class="fas fa-user"></i>
            <span>Usuario: <?php echo $nombre_usuario; ?> (ID: <?php echo $id; ?>)</span>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="usuario_guardar.php" id="passwordForm">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            
            <div class="form-body">
                <div class="form-group">
                    <label class="form-label" for="password">
                        <i class="fas fa-lock"></i> Nueva Contraseña
                    </label>
                    <div class="input-container">
                        <input type="password" 
                               name="password" 
                               id="password" 
                               class="form-input" 
                               placeholder="Ingrese la nueva contraseña" 
                               required
                               autocomplete="new-password"
                               minlength="6">
                        <button type="button" class="toggle-password" id="togglePassword" title="Mostrar/ocultar contraseña">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    
                    <div class="password-hint">
                        <i class="fas fa-info-circle"></i>
                        <span>Mínimo 6 caracteres</span>
                    </div>
                </div>
                
                <div class="requirements">
                    <h4><i class="fas fa-shield-alt"></i> Recomendaciones de seguridad</h4>
                    <ul>
                        <li>Usa al menos 8 caracteres</li>
                        <li>Combina letras, números y símbolos</li>
                        <li>Evita contraseñas comunes</li>
                    </ul>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='usuarios.php'">
                        <i class="fas fa-arrow-left"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </div>
        </form>
        
        <div class="form-footer">
            <a href="usuarios.php">
                <i class="fas fa-users"></i> Volver a la lista de usuarios
            </a>
        </div>
    </div>
</div>

<script>
// Mostrar/ocultar contraseña
const togglePassword = document.getElementById('togglePassword');
const passwordInput = document.getElementById('password');

togglePassword.addEventListener('click', function() {
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    
    // Cambiar icono
    const icon = this.querySelector('i');
    if (type === 'text') {
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
        this.setAttribute('title', 'Ocultar contraseña');
    } else {
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
        this.setAttribute('title', 'Mostrar contraseña');
    }
});

// Validación del formulario antes de enviar
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const password = passwordInput.value;
    
    // Validar longitud mínima
    if (password.length < 6) {
        e.preventDefault();
        alert('La contraseña debe tener al menos 6 caracteres.');
        passwordInput.focus();
        return false;
    }
    
    // Confirmación adicional para mayor seguridad
    if (!confirm('¿Estás seguro de que deseas cambiar la contraseña de este usuario?')) {
        e.preventDefault();
        return false;
    }
    
    // Mostrar mensaje de procesamiento
    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    submitBtn.disabled = true;
    
    // El formulario se enviará normalmente después de esto
    return true;
});

// Enfocar el campo de contraseña al cargar la página
window.addEventListener('load', function() {
    passwordInput.focus();
});

// Permitir ver contraseña manteniendo presionada una tecla
document.addEventListener('keydown', function(e) {
    if (e.key === 'Alt' && document.activeElement === passwordInput) {
        passwordInput.setAttribute('type', 'text');
        const icon = togglePassword.querySelector('i');
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
        togglePassword.setAttribute('title', 'Ocultar contraseña');
    }
});

document.addEventListener('keyup', function(e) {
    if (e.key === 'Alt' && passwordInput.getAttribute('type') === 'text') {
        passwordInput.setAttribute('type', 'password');
        const icon = togglePassword.querySelector('i');
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
        togglePassword.setAttribute('title', 'Mostrar contraseña');
    }
});
</script>

</body>
</html>