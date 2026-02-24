<?php
session_start();
include("conexion.php");

// Si el formulario ha sido enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario'] ?? '';
    $password = $_POST['password'] ?? '';
    $id_rol = $_POST['id_rol'] ?? '';
    $estado = $_POST['estado'] ?? 1;
    $codigo_biometrico = $_POST['codigo_biometrico'] ?? '';
    
    // Verificar que todos los campos estén completos
    if (empty($usuario) || empty($password) || empty($id_rol) || empty($codigo_biometrico)) {
        $error = "Todos los campos son obligatorios";
    } else {
        try {
            // Verificar si el código biométrico ya existe
            $check_sql = "SELECT id_usuario FROM usuarios WHERE codigo_biometrico = ?";
            $check_stmt = $conexion->prepare($check_sql);
            $check_stmt->bind_param("s", $codigo_biometrico);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = "El código biométrico ya está registrado en el sistema";
            } else {
                // Insertar nuevo usuario con código biométrico
                $sql = "
                INSERT INTO usuarios (usuario, password_hash, id_rol, estado, codigo_biometrico) 
                VALUES (?, ?, ?, ?, ?)
                ";
                
                $stmt = $conexion->prepare($sql);
                
                if ($stmt) {
                    // Encriptar la contraseña
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt->bind_param("ssiss", $usuario, $password_hash, $id_rol, $estado, $codigo_biometrico);
                    
                    if ($stmt->execute()) {
                        $success = "Usuario creado exitosamente con código biométrico";
                        // Redirigir después de 2 segundos
                        header("refresh:2;url=usuarios.php");
                    } else {
                        $error = "Error al crear el usuario: " . $stmt->error;
                    }
                    
                    $stmt->close();
                } else {
                    $error = "Error en la consulta: " . $conexion->error;
                }
            }
            
            $check_stmt->close();
            
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Obtener los roles para el select
$sql_roles = "SELECT id_rol, nombre FROM roles ORDER BY nombre";
$result_roles = $conexion->query($sql_roles);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Nuevo Usuario</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
</head>
<body>

<div class="container">
    <div class="form-card">
        <div class="form-header">
            <h1><i class="fas fa-user-plus"></i> Crear Nuevo Usuario</h1>
            <p><i class="fas fa-fingerprint"></i> Sistema con verificación biométrica</p>
        </div>
        
        <?php if(isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="userForm">
            <div class="form-body">
                <div class="form-group">
                    <label class="form-label" for="usuario">
                        <i class="fas fa-user"></i> Nombre de usuario
                    </label>
                    <input type="text" 
                           name="usuario" 
                           id="usuario" 
                           class="form-input" 
                           placeholder="Ej: nombre, alias" 
                           required
                           maxlength="50">
                </div>
                
                <!-- Campo: Código Biométrico (solo para ingreso manual) -->
                <div class="form-group">
                    <label class="form-label" for="codigo_biometrico">
                        <i class="fas fa-fingerprint"></i> Código Biométrico
                    </label>
                    <div class="input-container">
                        <input type="text" 
                               name="codigo_biometrico" 
                               id="codigo_biometrico" 
                               class="form-input" 
                               placeholder="Ingrese el código biométrico del empleado" 
                               required
                               maxlength="50"
                               pattern="[A-Za-z0-9\-]+"
                               title="Solo letras, números y guiones">
                    </div>
                    <div class="biometric-hint">
                        <i class="fas fa-info-circle"></i>
                        <span>Ingrese el código biométrico asignado al empleado (solo letras, números y guiones)</span>
                    </div>
                    <div class="biometric-preview" id="biometricPreview">
                        <!-- Se actualizará vía JavaScript -->
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">
                        <i class="fas fa-lock"></i> Contraseña
                    </label>
                    <div class="input-container">
                        <input type="password" 
                               name="password" 
                               id="password" 
                               class="form-input" 
                               placeholder="Ingrese la contraseña" 
                               required
                               autocomplete="new-password"
                               minlength="6">
                        <button type="button" class="toggle-password" id="togglePassword" title="Mostrar/ocultar contraseña">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    
                    <!-- Medidor de fortaleza de contraseña simplificado -->
                    <div class="password-strength-container">
                        <div class="strength-meter">
                            <div class="strength-fill" id="strengthFill"></div>
                        </div>
                        <div class="strength-labels">
                            <span>Débil</span>
                            <span>Media</span>
                            <span>Fuerte</span>
                        </div>
                        <div class="strength-text" id="strengthText">Ingresa una contraseña</div>
                    </div>
                    
                    <div class="password-hint">
                        <i class="fas fa-info-circle"></i>
                        <span><strong>Mínimo 6 caracteres. Use mayúsculas, números y símbolos para mayor seguridad</strong></span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="id_rol">
                        <i class="fas fa-user-tag"></i> Rol del usuario
                    </label>
                    <select name="id_rol" id="id_rol" class="form-input" required>
                        <option value="">Seleccione un rol</option>
                        <?php while($rol = $result_roles->fetch_assoc()): ?>
                        <option value="<?php echo $rol['id_rol']; ?>">
                            <?php echo htmlspecialchars($rol['nombre']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-power-off"></i> Estado del usuario
                    </label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" name="estado" id="estado_activo" value="1" checked>
                            <label for="estado_activo">Activo</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" name="estado" id="estado_inactivo" value="0">
                            <label for="estado_inactivo">Inactivo</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='usuarios.php'">
                        <i class="fas fa-arrow-left"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Crear Usuario
                    </button>
                </div>
            </div>
        </form>
        
        <div class="form-footer">
            <a href="usuarios.php">
                <i class="fas fa-users"></i> Volver a panel de usuarios
            </a>
        </div>
    </div>
</div>

<script>
// Función simplificada para calcular la fortaleza de la contraseña (solo niveles)
function getPasswordStrength(password) {
    if (!password) {
        return { level: '', color: '#718096', width: 0 };
    }
    
    let strength = 0;
    
    // Verificar longitud
    if (password.length >= 8) strength += 1;
    if (password.length >= 12) strength += 1;
    
    // Verificar diversidad de caracteres
    if (/[a-z]/.test(password)) strength += 1;
    if (/[A-Z]/.test(password)) strength += 1;
    if (/[0-9]/.test(password)) strength += 1;
    if (/[^A-Za-z0-9]/.test(password)) strength += 2;
    
    // Determinar nivel
    let level = '';
    let color = '';
    let width = 0;
    
    if (password.length < 6) {
        level = 'Muy corta (mínimo 6 caracteres)';
        color = '#e53e3e';
        width = 20;
    } else if (strength <= 3) {
        level = 'Débil';
        color = '#e53e3e';
        width = 33;
    } else if (strength <= 5) {
        level = 'Media';
        color = '#ecc94b';
        width = 66;
    } else {
        level = 'Fuerte';
        color = '#38a169';
        width = 100;
    }
    
    return { level, color, width };
}

// Validar formato de código biométrico
function validateBiometricCode(code) {
    const pattern = /^[A-Za-z0-9\-]+$/;
    return pattern.test(code);
}

// Actualizar UI de la contraseña
function updatePasswordUI(password) {
    const strengthInfo = getPasswordStrength(password);
    
    // Actualizar medidor de fuerza
    const strengthFill = document.getElementById('strengthFill');
    const strengthText = document.getElementById('strengthText');
    
    strengthFill.style.width = strengthInfo.width + '%';
    strengthFill.style.backgroundColor = strengthInfo.color;
    strengthText.textContent = strengthInfo.level;
    strengthText.style.color = strengthInfo.color;
    
    // Habilitar/deshabilitar botón de enviar (solo requiere longitud mínima)
    const submitBtn = document.getElementById('submitBtn');
    if (password.length >= 6) {
        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
        submitBtn.style.cursor = 'pointer';
    } else {
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.6';
        submitBtn.style.cursor = 'not-allowed';
    }
}

// Validar código biométrico en tiempo real
document.getElementById('codigo_biometrico').addEventListener('input', function() {
    const code = this.value;
    const preview = document.getElementById('biometricPreview');
    
    if (code.length > 0) {
        if (validateBiometricCode(code)) {
            preview.innerHTML = `
                <div class="biometric-valid">
                    <i class="fas fa-check-circle"></i> Formato válido
                </div>
            `;
            this.style.borderColor = '#38a169';
        } else {
            preview.innerHTML = `
                <div class="biometric-invalid">
                    <i class="fas fa-exclamation-triangle"></i> Formato inválido (solo letras, números y guiones)
                </div>
            `;
            this.style.borderColor = '#e53e3e';
        }
    } else {
        preview.innerHTML = '';
        this.style.borderColor = '#e2e8f0';
    }
});

// Escuchar cambios en el campo de contraseña
document.getElementById('password').addEventListener('input', function() {
    updatePasswordUI(this.value);
});

// Mostrar/ocultar contraseña
const togglePassword = document.getElementById('togglePassword');
const passwordInput = document.getElementById('password');

togglePassword.addEventListener('click', function() {
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    
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
document.getElementById('userForm').addEventListener('submit', function(e) {
    const password = passwordInput.value;
    const strengthInfo = getPasswordStrength(password);
    const biometricCode = document.getElementById('codigo_biometrico').value;
    
    // Validar longitud mínima de contraseña
    if (password.length < 6) {
        e.preventDefault();
        alert('La contraseña debe tener al menos 6 caracteres.');
        passwordInput.focus();
        return false;
    }
    
    // Validar código biométrico
    if (!validateBiometricCode(biometricCode)) {
        e.preventDefault();
        alert('El código biométrico contiene caracteres no válidos. Use solo letras, números y guiones.');
        document.getElementById('codigo_biometrico').focus();
        return false;
    }
    
    // Advertencia opcional para contraseñas débiles
    if (strengthInfo.level === 'Débil') {
        if (!confirm('⚠️ La contraseña es débil. ¿Desea continuar de todas formas?')) {
            e.preventDefault();
            passwordInput.focus();
            return false;
        }
    }
    
    // Mostrar mensaje de procesamiento
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando usuario...';
    submitBtn.disabled = true;
    
    return true;
});

// Inicializar al cargar la página
window.addEventListener('load', function() {
    document.getElementById('usuario').focus();
    updatePasswordUI('');
    
    // Configurar tooltips
    togglePassword.setAttribute('title', 'Mostrar contraseña');
    
    // Agregar evento para el campo de código biométrico
    document.getElementById('codigo_biometrico').addEventListener('blur', function() {
        if (this.value.length > 0 && !validateBiometricCode(this.value)) {
            alert('⚠️ El código biométrico tiene formato incorrecto. Use solo letras, números y guiones.');
        }
    });
});

// Permitir ver contraseña temporalmente con Alt
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

<style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea, #764ba2);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 500px;
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
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
            background-color: white;
        }

        .form-input:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.2);
        }

        .toggle-password {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #718096;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s;
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
            color: #2d3748;
        }

        .biometric-hint {
            font-size: 0.8rem;
            color: #718096;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .biometric-preview {
            margin-top: 8px;
            font-size: 0.85rem;
        }

        .biometric-valid {
            color: #38a169;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            background-color: #f0fff4;
            border-radius: 6px;
            border: 1px solid #c6f6d5;
        }

        .biometric-invalid {
            color: #e53e3e;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            background-color: #fff5f5;
            border-radius: 6px;
            border: 1px solid #feb2b2;
        }

        .password-strength-container {
            margin-top: 15px;
        }

        .strength-meter {
            height: 8px;
            background-color: #e2e8f0;
            border-radius: 4px;
            margin-top: 8px;
            overflow: hidden;
            position: relative;
        }

        .strength-fill {
            height: 100%;
            width: 0;
            border-radius: 4px;
            transition: width 0.3s, background-color 0.3s;
        }

        .strength-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
            font-size: 0.75rem;
            color: #718096;
        }

        .strength-text {
            font-size: 0.85rem;
            margin-top: 5px;
            font-weight: 600;
        }

        .password-hint {
            font-size: 0.85rem;
            color: #718096;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
            line-height: 1.4;
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

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }

        .btn-primary:disabled {
            background: #a0aec0;
            cursor: not-allowed;
            opacity: 0.6;
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

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 0 25px 20px;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 5px;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .radio-option input {
            width: auto;
            cursor: pointer;
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
        }
    </style>
</body>
</html>