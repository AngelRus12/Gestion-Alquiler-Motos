<?php
/**
 * registro.php
 * Página de registro de nuevos usuarios.
 * - Guarda temporalmente los datos inválidos en sesión para reinsertarlos en el formulario.
 * - Utiliza validación HTML y mensajes de error claros para facilitar la experiencia.
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
                    <input type="password" id="password" name="password" required minlength="8" pattern="(?=.*\d)(?=.*[a-zA-Z]).{8,}" title="La contraseña debe tener al menos 8 caracteres y contener como mínimo una letra y un número.">
                </div>
                
                <div class="form-group">
                    <label for="password2">Repetir Contraseña: *</label>
                    <input type="password" id="password2" name="password2" required>
                </div>
            </div>
            <button type="submit" class="btn btn-block">Registrarse</button>
        </form>
        
        <div class="links">
            <p>¿Ya tienes cuenta? <a href="login">Inicia sesión aquí</a></p>
            <br>
            <p><a href="index">Volver al inicio</a></p>
        </div>
    </div>
</body>
</html>
