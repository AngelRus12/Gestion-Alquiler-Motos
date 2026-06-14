<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$usuario_rol = $_SESSION['rol'] ?? null;
if (isset($_SESSION['usuario_id']) && $usuario_rol === null) {
    // Si el rol no está en la sesión, pero el usuario sí, lo obtenemos
    // Esto es útil si el usuario acaba de iniciar sesión.
    // NOTA: Asegúrate de que $conexion está disponible o ajusta esta lógica.
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="logo.png" type="image/png">
    <link rel="apple-touch-icon" href="logo.png">
    <link rel="stylesheet" href="estilos.css">
    <?php if (function_exists('isMobile') && isMobile()): ?>
        <link rel="stylesheet" href="estilos_mobile.css">
    <?php endif; ?>
</head>
<body>
    <header class="navbar">
        <div class="container">
            <h1><a href="index.php">ARUSLAT</a></h1>
            <nav>
                <a href="index.php">Inicio</a>
                <a href="catalogo.php">Catálogo</a>
                <?php if (isset($_SESSION['usuario_id'])): ?>
                    <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                        <a href="admin_dashboard.php" class="nav-destacado">Administración</a>
                    <?php endif; ?>
                    <a href="perfil_usuario.php">Mi Perfil</a>
                    <a href="logout.php">Cerrar Sesión</a>
                <?php else: ?>
                    <a href="login.php">Login</a>
                    <a href="registro.php">Registro</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>