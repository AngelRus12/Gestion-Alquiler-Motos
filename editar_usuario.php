<?php
session_start();
require_once 'loginbd.php';

// Control de acceso (Verifico que sea admin).
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

// Mi función para detectar dispositivos móviles.
function isMobile() { 
    return preg_match("/(android|avantgo|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino)/i", $_SERVER["HTTP_USER_AGENT"]);
}

$is_mobile = isMobile();

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}

$mensaje = "";

if (!isset($_GET['id'])) {
    header('Location: admin_dashboard.php');
    exit();
}

$id = $_GET['id'];

// Obtengo los datos del usuario.
$sql = "SELECT * FROM usuarios WHERE id = ?";
$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);

if (!$user) {
    header('Location: admin_dashboard.php?error=notfound');
    exit();
}

// Proceso la actualización por POST.
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $apellidos = trim($_POST['apellidos']);
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    $rol = $_POST['rol'];
    $estado = $_POST['estado'];
    
    if (empty($nombre) || empty($apellidos) || empty($email)) {
        $mensaje = "<div class='alerta alerta-error'>Nombre, apellidos y email son obligatorios.</div>";
    } else {
        $update_sql = "UPDATE usuarios SET nombre = ?, apellidos = ?, email = ?, telefono = ?, rol = ?, estado = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conexion, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "ssssssi", $nombre, $apellidos, $email, $telefono, $rol, $estado, $id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            $mensaje = "<div class='alerta alerta-exito'>Usuario actualizado correctamente.</div>";
            // Vuelvo a obtener los datos para mostrar la información actualizada en el formulario.
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $resultado = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($resultado);
            mysqli_stmt_close($stmt);
        } else {
            $mensaje = "<div class='alerta alerta-error'>Error al actualizar el usuario.</div>";
        }
        mysqli_stmt_close($update_stmt);
    }
}

mysqli_close($conexion);
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario - ARUSLAT</title>
    <?php if ($is_mobile): ?>
        <link rel="stylesheet" href="estilos_mobile.css">
    <?php else: ?>
        <link rel="stylesheet" href="estilos.css">
    <?php endif; ?>
</head>
<body>
    <div class="navbar">
        <div class="container">
            <h1>Editar Usuario: <?php 
            echo $user['nombre']; ?></h1>
            <nav><a href="admin_dashboard">Volver al Panel</a></nav>
        </div>
    </div>

    <div class="container">
        <div class="formulario-admin">
            <?php echo $mensaje; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Nombre</label>
                    <input type="text" name="nombre" value="<?php echo htmlspecialchars($user['nombre']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Apellidos</label>
                    <input type="text" name="apellidos" value="<?php echo htmlspecialchars($user['apellidos']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="telefono" value="<?php echo htmlspecialchars($user['telefono'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Rol de Usuario</label>
                    <select name="rol">
                        <option value="cliente" <?php if($user['rol'] == 'cliente') echo 'selected'; ?>>Cliente</option>
                        <option value="admin" <?php if($user['rol'] == 'admin') echo 'selected'; ?>>Administrador</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Estado de Cuenta</label>
                    <select name="estado">
                        <option value="activo" <?php if($user['estado'] == 'activo') echo 'selected'; ?>>Activo</option>
                        <option value="inactivo" <?php if($user['estado'] == 'inactivo') echo 'selected'; ?>>Inactivo</option>
                    </select>
                </div>
                <button type="submit" class="btn">Guardar Cambios</button>
            </form>
        </div>
    </div>
</body>
</html>