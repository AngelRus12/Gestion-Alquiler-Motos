<?php
/**
 * index.php
 * Página de entrada del sitio.
 * - Muestra navegación, promociones y enlaces a catálogo, login y registro.
 * - Detecta si el usuario está autenticado para ajustar las opciones de menú.
 */
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'loginbd.php';
require_once 'funciones.php';
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

if (!$conexion) { 
    die("Error de conexión: " . mysqli_connect_error()); 
}

mysqli_set_charset($conexion, "utf8");

// Asegura que la tabla de promociones exista antes de intentar leerla.
crear_tabla_promociones_si_no_existe($conexion);
$promocion_activa = obtener_promocion_activa($conexion);

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
    <title>ARUSLAT - Inicio</title>
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
                        if ($_SESSION['rol'] === 'admin') { ?>
                            <a href="admin_dashboard" class="nav-destacado">Panel Admin</a>
                        <?php }
                    } ?>
                    <a href="perfil_usuario">Mi Perfil</a> 
                    <a href="logout">Cerrar Sesión</a>
                <?php } else { ?>
                    <a href="login">Login</a>
                    <a href="registro">Registro</a>
                <?php } ?>
            </nav>
        </div>
    </header>

    <?php if (!empty($promocion_activa)): ?>
    <section class="promo-banner">
        <div class="container">
            <div class="promo-content">
                <div class="promo-text">
                    <span class="promo-label">Oferta destacada</span>
                    <h2><?php echo htmlspecialchars($promocion_activa['titulo']); ?></h2>
                    <p><?php echo htmlspecialchars($promocion_activa['mensaje']); ?></p>
                </div>
                <?php if (!empty($promocion_activa['enlace'])): ?>
                    <a href="<?php echo htmlspecialchars($promocion_activa['enlace']); ?>" class="btn promo-btn">Ver oferta</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <main class="main-content">
        <div class="hero">
            <h1>Siente la libertad<br>en cada curva</h1>
            <p>Descubre la mejor selección de motocicletas de alta gama para tu próxima aventura. Alquiler fácil, rápido y seguro.</p>
            <a href="catalogo" class="btn">Ver Catálogo</a>
        </div>

        <div class="container">
            <h2 class="titulo-seccion">Motos Destacadas</h2>
            <div class="grid">
                <?php
                $consulta = "SELECT * FROM motos WHERE disponible = 1 OR disponible = 'si' ORDER BY precio_dia DESC LIMIT 4";
                $resultado = mysqli_query($conexion, $consulta);
                
                while ($fila = mysqli_fetch_assoc($resultado)) {
                    echo '<div class="card">';
                    
                    if (!empty($fila['imagen'])) {
                        echo '<img src="data:image/jpeg;base64,' . base64_encode($fila['imagen']) . '" alt="Moto">';
                    } else {
                        echo '<img src="imgs/default.jpg" alt="Moto">';
                    }
                    
                    echo '<div class="card-body">';
                    echo '<h3>' . $fila['marca'] . ' ' . $fila['modelo'] . '</h3>';
                    echo '<p class="descripcion">Compañera perfecta para viajes largos. Potencia y confort sin límites.</p>'; // Descripción genérica
                    echo '<p class="precio">' . $fila['precio_dia'] . ' €/día</p>';
                    echo '<a href="detalle_moto?id=' . $fila['id'] . '" class="btn">Ver Detalles</a>';
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
</body>
</html>