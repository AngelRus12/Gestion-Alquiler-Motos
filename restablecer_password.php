<?php
/**
 * restablecer_password.php
 * Página para que el usuario introduzca su nueva contraseña.
 * - Valida el token de la URL.
 * - Muestra el formulario de nueva contraseña.
 */
require_once 'funciones.php';
start_secure_session();
generar_csrf_token();

if (!isset($_GET['token'])) {
    die("Token no proporcionado.");
}

$token = $_GET['token'];
$token_hash = hash('sha256', $token);

require_once 'loginbd.php';
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

// Buscar el token en la base de datos y comprobar que no ha expirado
$sql = "SELECT id FROM usuarios WHERE reset_token = ? AND reset_token_expires > NOW()";
$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "s", $token_hash);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$usuario = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);

if (!$usuario) {
    die("El enlace de recuperación no es válido o ha expirado. Por favor, solicita uno nuevo.");
}

$is_mobile = preg_match("/(android|avantgo|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino)/i", $_SERVER["HTTP_USER_AGENT"]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="logo.png" type="image/png">
    <title>Restablecer Contraseña - ARUSLAT</title>
    <?php if ($is_mobile): ?>
        <link rel="stylesheet" href="estilos_mobile.css">
    <?php else: ?>
        <link rel="stylesheet" href="estilos.css">
    <?php endif; ?>
</head>
<body class="login-body">

    <div class="login-container">
        <h1 class="titulo-formulario">Establecer Nueva Contraseña</h1>

        <?php
        if (isset($_GET['error'])) {
            $error_msg = 'Ha ocurrido un error.';
            if ($_GET['error'] == 'password_mismatch') {
                $error_msg = 'Las contraseñas no coinciden.';
            } elseif ($_GET['error'] == 'password_format') {
                $error_msg = 'La contraseña debe tener al menos 8 caracteres, una letra y un número.';
            }
            echo '<div class="alerta alerta-error">' . $error_msg . '</div>';
        }
        ?>

        <form method="POST" action="procesar_restablecimiento.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div class="form-group">
                <label for="new_password">Nueva Contraseña:</label>
                <input type="password" id="new_password" name="new_password" required minlength="8" pattern="(?=.*\d)(?=.*[a-zA-Z]).{8,}" title="Mínimo 8 caracteres, con al menos una letra y un número.">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirmar Nueva Contraseña:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="btn btn-block">Guardar Nueva Contraseña</button>
        </form>
    </div>

</body>
</html>