<?php
session_start();
require_once 'loginbd.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

// Función para detectar dispositivos móviles
function isMobile() {
    return preg_match("/(android|avantgo|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino)/i", $_SERVER["HTTP_USER_AGENT"]);
}

$is_mobile = isMobile();
$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = mysqli_real_escape_string($conexion, $_POST['nombre']);
    $apellidos = mysqli_real_escape_string($conexion, $_POST['apellidos']);
    $email = mysqli_real_escape_string($conexion, $_POST['email']);
    $telefono = mysqli_real_escape_string($conexion, $_POST['telefono']);
    $dni = mysqli_real_escape_string($conexion, $_POST['dni']);
    $direccion = mysqli_real_escape_string($conexion, $_POST['direccion']);
    $rol = $_POST['rol'];
    
    $password_plana = $_POST['password'];
    $password_hash = password_hash($password_plana, PASSWORD_DEFAULT);

    $check_email = mysqli_query($conexion, "SELECT id FROM usuarios WHERE email = '$email'");
    
    if (mysqli_num_rows($check_email) > 0) {
        $mensaje = "<div class='alerta alerta-error'>Ese correo ya existe en el sistema.</div>";
    } else {
        $sql = "INSERT INTO usuarios (nombre, apellidos, email, password, telefono, dni, direccion, rol, estado) 
                VALUES ('$nombre', '$apellidos', '$email', '$password_hash', '$telefono', '$dni', '$direccion', '$rol', 'activo')";

        if (mysqli_query($conexion, $sql)) {
            header('Location: admin_dashboard.php?msg=usuario_creado');
            exit();
        } else {
            $mensaje = "<div class='alerta alerta-error'>No se pudo crear el usuario.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Añadir Usuario - ARUSLAT</title>
    <?php if ($is_mobile): ?>
        <link rel="stylesheet" href="estilos_mobile.css">
    <?php else: ?>
        <link rel="stylesheet" href="estilos.css">
    <?php endif; ?>
</head>
<body class="registro-body">
    <div class="registro">
        <h1 class="titulo-formulario">Añadir Nuevo Usuario</h1>
            
            <?php 
            echo $mensaje; ?>

            <form method="POST">
                <div class="form-grid-2-col">
                    <div class="form-group">
                        <label for="nombre">Nombre</label>
                        <input type="text" id="nombre" name="nombre" required>
                    </div>

                    <div class="form-group">
                        <label for="apellidos">Apellidos</label>
                        <input type="text" id="apellidos" name="apellidos" required>
                    </div>

                    <div class="form-group">
                        <label for="dni">DNI / NIE</label>
                        <input type="text" id="dni" name="dni">
                    </div>

                    <div class="form-group">
                        <label for="telefono">Teléfono de contacto</label>
                        <input type="text" id="telefono" name="telefono">
                    </div>

                    <div class="form-group full-width">
                        <label for="email">Correo Electrónico</label>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group full-width">
                        <label for="direccion">Dirección completa</label>
                        <input type="text" id="direccion" name="direccion">
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña Temporal</label>
                        <input type="password" id="password" name="password" placeholder="Mínimo 8 caracteres, 1 letra y 1 número" required>
                    </div>

                    <div class="form-group">
                        <label for="rol">Tipo de Usuario (Rol)</label>
                        <select name="rol" id="rol">
                            <option value="cliente">Cliente</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-block espaciado-arriba-20">Registrar Usuario</button>
            </form>
            <div class="links">
                <p><a href="admin_dashboard">Volver al Panel</a></p>
            </div>
        </div>
    </div>
</body>
</html>
<?php mysqli_close($conexion); ?>