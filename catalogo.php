<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'loginbd.php';
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

mysqli_set_charset($conexion, "utf8");

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
    <title>Catálogo - ARUSLAT</title>
    <?php if ($is_mobile): ?>
        <link rel="stylesheet" href="estilos_mobile.css">
    <?php else: ?>
        <link rel="stylesheet" href="estilos.css">
    <?php endif; ?>
</head>
<body>
    <header class="navbar">
        <div class="container">
            <h1>ARUSLAT</h1>
            <nav>
                <a href="index">Inicio</a>
                <a href="catalogo">Catálogo</a>
                <?php 
                if (isset($_SESSION['usuario_id'])) { 
                    if (isset($_SESSION['rol'])) {
                        if ($_SESSION['rol'] === 'admin') { 
                ?>
                            <a href="admin_dashboard" class="nav-destacado">Panel Admin</a>
                <?php 
                        }
                    } 
                ?>
                    <a href="perfil_usuario">Mi Perfil</a> 
                    <a href="logout">Cerrar Sesión</a>
                <?php 
                } else { 
                ?>
                    <a href="login">Login</a>
                <?php 
                } 
                ?>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <h2 class="titulo-seccion">Nuestro Catálogo de Motos</h2>
            <div class="grid">
                <?php
                $resultado = mysqli_query($conexion, "SELECT * FROM motos order by marca ");
                while ($fila = mysqli_fetch_assoc($resultado)) {
                    // Estructura de la tarjeta actualizada para coincidir con .card y .card-body
                    echo '<div class="card">';
                    if (!empty($fila['imagen'])) {
                        echo '<img src="data:image/jpeg;base64,' . base64_encode($fila['imagen']) . '" alt="Moto">';
                    } else {
                        echo '<img src="imgs/default.jpg" alt="Moto">';
                    }
                    echo '<div class="card-body">';
                    echo '<h3>' . $fila['marca'] . ' ' . $fila['modelo'] . '</h3>';
                    // Añadido .descripcion
                    echo '<p class="descripcion">' . $fila['descripcion'] . '</p>';
                    echo '<p class="precio">' . $fila['precio_dia'] . ' €/día</p>';
                    echo '<a href="detalle_moto?id=' . $fila['id'] . '" class="btn">Reservar Ahora</a>';
                    echo '</div></div>';
                }
                mysqli_close($conexion);
                ?>
            </div>
        </div>
    </main>
    <footer>
        <p>© 2026 ARUSLAT - Alquiler de Motos</p>
        <p>Proyecto TFG - Ángel Rus Latorre - ASIR</p>
    </footer>

    <?php if (isset($_SESSION['usuario_id'])): ?>
    <script src="logout_session.js"></script>
    <?php endif; ?>

</body>
</html>