<?php
/**
 * login.php
 * Esta es mi página de acceso para los usuarios.
 * - Si un usuario ya está autenticado, lo redirijo a la página principal para que no vea el login otra vez.
 * - He añadido una función para detectar si el cliente usa un móvil y así cargar la hoja de estilo correcta.
 * - También muestro mensajes de error si el login falla o un mensaje de éxito si viene de la página de registro.
 */
// Configuro el tiempo de vida de la sesión (ej. 30 minutos de inactividad).
require_once 'funciones.php';
ini_set('session.gc_maxlifetime', 1800);
start_secure_session();

generar_csrf_token();

// Función para detectar dispositivos móviles
function isMobile() {
    return preg_match("/(android|avantgo|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino)/i", $_SERVER["HTTP_USER_AGENT"]);
}

$is_mobile = isMobile();

if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="logo.png" type="image/png">
    <link rel="apple-touch-icon" href="logo.png">
    <title>Login - ARUSLAT</title>
    <?php if ($is_mobile): ?>
        <link rel="stylesheet" href="estilos_mobile.css">
    <?php else: ?>
        <link rel="stylesheet" href="estilos.css">
    <?php endif; ?>
</head>
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
<body class="login-body">
    
    <div class="login-container">
        <h1 class="titulo-formulario">Iniciar Sesión</h1>
        
        <?php
        if (isset($_GET['error'])) {
            echo '<div class="alerta alerta-error">';
            if 
            ($_GET['error'] == 'credenciales') {
                echo 'Email o contraseña incorrectos';
            } 
            elseif ($_GET['error'] == 'vacio') {
                echo 'Por favor, complete todos los campos';
            } 
            elseif ($_GET['error'] == 'acceso_denegado') {
                echo 'Debe iniciar sesión para acceder';
            }
            echo '</div>';
        }
        
        if (isset($_GET['registro']) && $_GET['registro'] == 'exitoso') {
            echo '<div class="alerta alerta-exito">Registro exitoso. Ya puede iniciar sesión</div>';
        }
        ?>
        
        <form method="POST" action="procesar_login.php">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" required>
                    <span class="toggle-password" onclick="togglePasswordVisibility('password')">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z"/><path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8zm8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/></svg>
                    </span>
                </div>
            </div>
            
            <button type="submit" class="btn btn-block">Acceder</button>
        </form>
        
        <div class="links">
            <p>¿No tienes cuenta? <a href="registro.php">Regístrate aquí</a></p>
            <br>
            <p><a href="index.php">Volver al inicio</a></p>
            <br>
            <p><a href="solicitar_recuperacion.php" class="enlace-discreto">¿Olvidaste tu contraseña?</a></p>
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
