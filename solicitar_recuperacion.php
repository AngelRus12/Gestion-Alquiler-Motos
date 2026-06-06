<?php
/**
 * solicitar_recuperacion.php
 * Formulario para que el usuario solicite un enlace de recuperación de contraseña.
 * Aquí le pido al usuario su email para iniciar el proceso.
 */
require_once 'funciones.php';
start_secure_session();
generar_csrf_token();

// Construyo la URL absoluta para el 'action' del formulario para evitar errores de CORS.
$host = $_SERVER['HTTP_HOST'];
$path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$action_url = "{$protocol}://{$host}{$path}/procesar_solicitud_recuperacion.php";

$is_mobile = preg_match("/(android|avantgo|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino)/i", $_SERVER["HTTP_USER_AGENT"]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="logo.png" type="image/png">
    <title>Recuperar Contraseña - ARUSLAT</title>
    <?php if ($is_mobile): ?>
        <link rel="stylesheet" href="estilos_mobile.css">
    <?php else: ?>
        <link rel="stylesheet" href="estilos.css">
    <?php endif; ?>
</head>
<body class="login-body">

    <div class="login-container">
        <h1 class="titulo-formulario">Recuperar Contraseña</h1>

        <?php
        if (isset($_GET['status']) && $_GET['status'] == 'sent') {
            echo '<div class="alerta alerta-exito">Si tu correo electrónico existe en nuestro sistema, recibirás un enlace para restablecer tu contraseña.</div>';
        }
        if (isset($_GET['error'])) {
            $error_msg = 'Ha ocurrido un error.';
            if ($_GET['error'] == 'invalid_email') {
                $error_msg = 'Por favor, introduce una dirección de correo electrónico válida.';
            }
            echo '<div class="alerta alerta-error">' . $error_msg . '</div>';
        }
        ?>

        <p style="text-align: center; margin-bottom: 20px;">
            Introduce tu correo electrónico y te enviaremos un enlace para que puedas restablecer tu contraseña.
        </p>

        <form method="POST" action="<?php echo htmlspecialchars($action_url); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div class="form-group">
                <label for="email">Correo Electrónico:</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <button type="submit" class="btn btn-block">Enviar Enlace de Recuperación</button>
        </form>
        
        <div class="links">
            <p><a href="login.php">Volver a Iniciar Sesión</a></p>
        </div>
    </div>

</body>
</html>