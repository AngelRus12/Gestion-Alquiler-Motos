<?php
/**
 * registro.php
 * Esta es mi página de registro para nuevos usuarios.
 * - Para mejorar la experiencia, si hay un error, guardo temporalmente los datos en la sesión para volver a mostrarlos en el formulario y que el usuario no tenga que reescribir todo.
 * - Uso validación HTML y mensajes de error claros para que sea más fácil de usar.
 */
require_once 'funciones.php';
start_secure_session();
generar_csrf_token();

$old_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

// Función para detectar dispositivos móviles
function isMobile() {
    return preg_match("/(android|avantgo|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino)/i", $_SERVER["HTTP_USER_AGENT"]);
}

$is_mobile = isMobile();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="logo.png" type="image/png">
    <link rel="apple-touch-icon" href="logo.png">
    <title>Registro - ARUSLAT</title>
    <?php if ($is_mobile): ?>
        <link rel="stylesheet" href="estilos_mobile.css">
    <?php else: ?>
        <link rel="stylesheet" href="estilos.css">
    <?php endif; ?>
    <!-- Los estilos <style> se han movido a estilos.css y estilos_mobile.css -->
    <style>
        .password-container {
            position: relative;
        }
        .password-container input {
            width: 100%;
            padding-right: 45px; /* Espacio para el icono SVG */
        }
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            width: 20px;
            height: 20px;
        }
        .toggle-password svg {
            width: 100%;
            height: 100%;
            fill: white;
        }
    </style>
</head>
<body class="registro-body" >
        
    <div class="registro">
        <h1 class="titulo-formulario">CREAR CUENTA</h1>
        
        <?php
        if (isset($_GET['error'])) {
            echo '<div class="alerta alerta-error">';
            if ($_GET['error'] == 'email_existe') {
                echo 'El email ya está registrado';
            } else if ($_GET['error'] == 'dni_existe') {
                echo 'El DNI ya está registrado';
            } else if ($_GET['error'] == 'vacio') {
                echo 'Todos los campos obligatorios deben completarse';
            } else if ($_GET['error'] == 'password') {
                echo 'Las contraseñas no coinciden';
            } else if ($_GET['error'] == 'password_formato') {
                echo 'La contraseña debe tener al menos 8 caracteres, una letra y un número.';
            } else if ($_GET['error'] == 'dni_invalido') {
                echo 'El formato del DNI es incorrecto o la letra no es válida.';
            }
            echo '</div>';
        }
        ?>
        
        <form action="procesar_registro.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="form-grid-2-col">
                
                <div class="form-group">
                    <label for="nombre">Nombre: *</label>
                    <input type="text" id="nombre" name="nombre" required value="<?php echo htmlspecialchars($old_data['nombre'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="apellidos">Apellidos: *</label>
                    <input type="text" id="apellidos" name="apellidos" required value="<?php echo htmlspecialchars($old_data['apellidos'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="dni">DNI: *</label>
                    <input type="text" id="dni" name="dni" placeholder="12345678A" required pattern="\d{8}[A-Za-z]" title="El DNI debe contener 8 números y una letra." value="<?php echo htmlspecialchars($old_data['dni'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="telefono">Teléfono: *</label>
                    <input type="text" id="telefono" name="telefono" placeholder="666666666" required value="<?php echo htmlspecialchars($old_data['telefono'] ?? ''); ?>">
                </div>

                <div class="form-group full-width">
                    <label for="email">Email: *</label>
                    <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($old_data['email'] ?? ''); ?>">
                </div>
                
                <div class="form-group full-width">
                    <label for="direccion">Dirección:</label>
                    <textarea id="direccion" name="direccion" rows="2"><?php echo htmlspecialchars($old_data['direccion'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña: *</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" required minlength="8" pattern="(?=.*\d)(?=.*[a-zA-Z]).{8,}" title="La contraseña debe tener al menos 8 caracteres y contener como mínimo una letra y un número.">
                        <span class="toggle-password" onclick="togglePasswordVisibility('password')">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8zm8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/></svg>
                        </span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password2">Repetir Contraseña: *</label>
                    <div class="password-container">
                        <input type="password" id="password2" name="password2" required>
                        <span class="toggle-password" onclick="togglePasswordVisibility('password2')">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8zm8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/></svg>
                        </span>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-block">Registrarse</button>
        </form>
        
        <div class="links">
            <p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a></p>
            <br>
            <p><a href="index.php">Volver al inicio</a></p>
        </div>
    </div>
    <script>
        function togglePasswordVisibility(id) {
            const input = document.getElementById(id);
            const iconContainer = input.nextElementSibling;
            const isPassword = input.type === 'password';
            const eyeIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8zm8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/></svg>';
            const eyeSlashIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7.028 7.028 0 0 0-2.79.588l.77.771A5.944 5.944 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.134 13.134 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755-.165.165-.337.328-.517.486l.708.709z"/><path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829l.822.822zm-2.943 1.288.822.822.073.073a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829zm-3.174.734a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829l.822.822a3.5 3.5 0 0 1-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.829 2.829z"/><path d="M12.5 1b3.5 3.5 0 0 1 0 7H.5a.5.5 0 0 1 0-1H12a2.5 2.5 0 0 0 0-5H.5a.5.5 0 0 1 0-1H12a2.5 2.5 0 0 0 0-5zM.5 1a.5.5 0 0 0 0 1h12a2.5 2.5 0 0 1 0 5H.5a.5.5 0 0 0 0 1h12a3.5 3.5 0 0 0 0-7H.5z"/></svg>';

            input.type = isPassword ? 'text' : 'password';
            iconContainer.innerHTML = isPassword ? eyeSlashIcon : eyeIcon;
        }
    </script>
</body>
</html>
