<?php
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
</head>
<style>
    .registro-body {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
    }
    .registro {
        width: 100%;
        max-width: 800px; /* Aumentamos el ancho para las dos columnas */
        padding: 40px;
    }
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr; /* Dos columnas de igual tamaño */
        gap: 20px; /* Espacio entre columnas y filas */
    }
    .form-group {
        margin-bottom: 0; /* Quitamos el margen inferior que tenía por defecto */
    }
    .full-width {
        grid-column: 1 / -1; /* Ocupa todo el ancho de la rejilla */
    }
    .btn {
        width: 100%;
        margin-top: 20px;
    }

    /* Para dispositivos móviles, volvemos a una columna */
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
<body class="registro-body" >
        
    <div class="registro">
        <h1 align="center">CREAR CUENTA</h1>
        
        <?php
        if (isset($_GET['error'])) {
            echo '<div class="error">';
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
            <div class="form-grid">
                <div class="form-group">
                    <label for="nombre">Nombre: *</label>
                    <input type="text" id="nombre" name="nombre" required>
                </div>
                
                <div class="form-group">
                    <label for="apellidos">Apellidos: *</label>
                    <input type="text" id="apellidos" name="apellidos" required>
                </div>
                
                <div class="form-group">
                    <label for="dni">DNI: *</label>
                    <input type="text" id="dni" name="dni" placeholder="12345678A" required pattern="\d{8}[A-Za-z]" title="El DNI debe contener 8 números y una letra.">
                </div>

                <div class="form-group">
                    <label for="telefono">Teléfono: *</label>
                    <input type="text" id="telefono" name="telefono" placeholder="666666666" required>
                </div>

                <div class="form-group full-width">
                    <label for="email">Email: *</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group full-width">
                    <label for="direccion">Dirección:</label>
                    <textarea id="direccion" name="direccion" rows="2"></textarea>
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
            <button type="submit" class="btn">Registrarse</button>
        </form>
        
        <div class="links">
            <p>¿Ya tienes cuenta? <a href="login">Inicia sesión aquí</a></p>
            <br>
            <p><a href="index">Volver al inicio</a></p>
        </div>
    </div>
</body>
</html>
