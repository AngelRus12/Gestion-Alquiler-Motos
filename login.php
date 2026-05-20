<?php
/**
 * login.php
 * Página de acceso del usuario.
 * - Evita el acceso de usuarios ya autenticados redirigiéndolos a la página principal.
 * - Detecta si el cliente es móvil para cargar la hoja de estilo correcta.
 * - Muestra mensajes de error de login y resultados del registro.
 */
// Configurar el tiempo de vida de la sesión (ej. 30 minutos de inactividad)
ini_set('session.gc_maxlifetime', 1800);
session_set_cookie_params(1800);
session_start();

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
        
        <form method="POST" action="procesar_login">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn btn-block">Acceder</button>
        </form>
        
        <div class="links">
            <p>¿No tienes cuenta? <a href="registro">Regístrate aquí</a></p>
            <br>


            
            <p><a href="index">Volver al inicio</a></p>
            <br>
            <p>Si olvidaste tu contraseña, envie un correo a info@alquilermotos.com</a></p>
            <p>indicando tu DNI y correo electrónico.</a></p>
        </div>
    </div>
</body>
</html>
