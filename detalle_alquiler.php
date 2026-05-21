<?php
// Establece la codificación de caracteres a UTF-8 para soportar caracteres especiales.
header('Content-Type: text/html; charset=utf-8');
// Inicia la sesión para poder acceder a las variables de sesión.
session_start();
// Se incluye el archivo con las credenciales de la base de datos.
require_once 'loginbd.php';

// --- 1. CONTROL DE ACCESO ---
// Verifica si el usuario ha iniciado sesión. Si no, lo redirige a la página de login.
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

// --- 2. VALIDACIÓN DE ENTRADA ---
// Verifica que se haya proporcionado un ID de alquiler en la URL y que sea un número.
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: perfil_usuario.php?error=id_invalido');
    exit();
}

// --- 3. CONEXIÓN A LA BASE DE DATOS ---
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
// Si la conexión falla, se detiene la ejecución y se muestra un error.
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
// Establece el conjunto de caracteres a UTF-8 para la conexión.
mysqli_set_charset($conexion, "utf8");

// Se convierte el ID de la URL a entero para mayor seguridad.
$alquiler_id = (int)$_GET['id'];
$usuario_id = $_SESSION['usuario_id'];

// --- 4. CONSULTA SEGURA DE DATOS DEL ALQUILER ---
// La consulta se adapta según el rol del usuario para garantizar la seguridad y privacidad.
if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
    // Si el usuario es administrador, puede ver los detalles de cualquier alquiler.
    // La consulta solo filtra por el ID del alquiler.
    $sql_alquiler = "SELECT * FROM alquileres WHERE id = ?";
    $stmt_alquiler = mysqli_prepare($conexion, $sql_alquiler);
    mysqli_stmt_bind_param($stmt_alquiler, "i", $alquiler_id);
} else {
    // Si es un usuario normal, solo puede ver los alquileres que le pertenecen.
    // CRÍTICO: Se añade la condición 'AND usuario_id = ?' para asegurar que un usuario no pueda ver
    // los datos de otro simplemente cambiando el ID en la URL.
    $sql_alquiler = "SELECT * FROM alquileres WHERE id = ? AND usuario_id = ?";
    $stmt_alquiler = mysqli_prepare($conexion, $sql_alquiler);
    mysqli_stmt_bind_param($stmt_alquiler, "ii", $alquiler_id, $usuario_id);
}
// Se ejecuta la consulta y se obtiene el resultado.
mysqli_stmt_execute($stmt_alquiler);
$resultado_alquiler = mysqli_stmt_get_result($stmt_alquiler);
$alquiler = mysqli_fetch_assoc($resultado_alquiler);
mysqli_stmt_close($stmt_alquiler);

// --- 5. VERIFICACIÓN DE EXISTENCIA ---
// Si la consulta anterior no devolvió ningún alquiler, significa que no existe o no pertenece al usuario.
if (!$alquiler) {
    header('Location: perfil_usuario.php?error=no_encontrado');
    exit();
}

// --- 6. OBTENCIÓN DE DATOS RELACIONADOS (SIN USAR JOINs) ---
// En lugar de una consulta compleja con JOIN, se realizan consultas simples y separadas.
// Esto puede ser más fácil de leer y depurar en algunos casos.
$moto_data = [];
$usuario_data = [];

// Consulta para obtener los datos de la moto asociada al alquiler.
$sql_moto = "SELECT marca, modelo, tipo, precio_dia, imagen, descripcion as moto_descripcion FROM motos WHERE id = ?";
$stmt_moto = mysqli_prepare($conexion, $sql_moto);
mysqli_stmt_bind_param($stmt_moto, "i", $alquiler['moto_id']);
mysqli_stmt_execute($stmt_moto);
// Se usa mysqli_fetch_assoc porque esperamos solo una fila (una moto por alquiler).
$moto_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_moto));
mysqli_stmt_close($stmt_moto);

// Consulta para obtener los datos del usuario asociado al alquiler.
$sql_usuario = "SELECT nombre, apellidos, email FROM usuarios WHERE id = ?";
$stmt_usuario = mysqli_prepare($conexion, $sql_usuario);
mysqli_stmt_bind_param($stmt_usuario, "i", $alquiler['usuario_id']);
mysqli_stmt_execute($stmt_usuario);
$usuario_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_usuario));
mysqli_stmt_close($stmt_usuario);

// --- 7. COMBINACIÓN DE DATOS Y VERIFICACIÓN FINAL ---
// Se simula el comportamiento de un INNER JOIN. Un INNER JOIN solo devuelve resultados si hay coincidencias en todas las tablas.
// Un INNER JOIN solo devuelve resultados si hay coincidencias en todas las tablas.
// Aquí replicamos ese comportamiento: si la moto o el usuario del alquiler han sido eliminados
// de la base de datos, consideramos que el alquiler ya no es válido.
if (!$moto_data || !$usuario_data) {
    $alquiler = false; // Se marca el alquiler como falso para que la siguiente comprobación falle.
} else {
    // Si todos los datos existen, se combinan los tres arrays ($alquiler, $moto_data, $usuario_data)
    // en un único array $alquiler para usarlo fácilmente en el HTML.
    $alquiler = array_merge($alquiler, $moto_data, $usuario_data);
}

// Si después de las comprobaciones el alquiler se marcó como falso (porque la moto o el usuario no existen), se redirige.
if (!$alquiler) {
    header('Location: perfil_usuario.php?error=no_encontrado');
    exit();
}

// --- 8. DETECCIÓN DE DISPOSITIVO MÓVIL ---
// Función simple para cargar una hoja de estilos diferente en móviles.
function isMobile() {
    return preg_match("/(android|avantgo|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino)/i", $_SERVER["HTTP_USER_AGENT"]);
}

$is_mobile = isMobile();
?>
<!-- El resto del archivo es la estructura HTML que muestra los datos combinados del alquiler. -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="logo.png" type="image/png">
    <link rel="apple-touch-icon" href="logo.png">
    <title>Detalle del Alquiler - ARUSLAT</title>
    <?php if ($is_mobile): ?>
        <link rel="stylesheet" href="estilos_mobile.css">
    <?php else: ?>
        <link rel="stylesheet" href="estilos.css">
    <?php endif; ?>
</head>
<body>
    <header class="navbar">
        <div class="container">
            <h1><a href="index.php">ARUSLAT</a></h1>
            <nav>
                <a href="index">Inicio</a>
                <a href="catalogo">Catálogo</a>
                <a href="perfil_usuario">Mi Perfil</a> 
                <a href="logout">Cerrar Sesión</a>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <h2 class="titulo-pagina text-left">Detalle del Alquiler #<?php echo $alquiler['id']; ?></h2>
            <a href="perfil_usuario" class="enlace-discreto enlace-volver">&larr; Volver a Mi Perfil</a>

            <div class="detalle-alquiler-container">
                <div class="moto-detalle-card">
                    <?php 
                    $imagen_src = 'imgs/default.jpg';
                    if (!empty($alquiler['imagen'])) {
                        $imagen_src = 'data:image/jpeg;base64,' . base64_encode($alquiler['imagen']);
                    }
                    ?>
                    <img src="<?php echo $imagen_src; ?>" alt="Moto">
                    <h3><?php echo $alquiler['marca'] . " " . $alquiler['modelo']; ?></h3>
                    <p class="texto-gris-claro"><?php echo $alquiler['moto_descripcion']; ?></p>
                    <span class="etiqueta etiqueta-azul"><?php echo strtoupper($alquiler['tipo']); ?></span>
                </div>

                <div class="resumen-alquiler">
                    <div class="perfil-container">
                        <h3>Resumen de la Reserva</h3>
                        <p><strong>Estado:</strong> 
                            <span class="estado-alquiler <?php echo $alquiler['estado']; ?>">
                                <?php echo str_replace('_', ' ', strtoupper($alquiler['estado'])); ?>
                            </span>
                        </p>
                        <p><strong>Fecha de reserva:</strong> <?php echo date("d/m/Y H:i", strtotime($alquiler['fecha_reserva'])); ?></p>
                        <p><strong>Fecha de finalización:</strong> <?php echo date("d/m/Y H:i", strtotime($alquiler['fecha_fin'])); ?></p>
                        <p><strong>Cliente:</strong> <?php echo $alquiler['nombre'] . " " . $alquiler['apellidos']; ?></p>
                        <p><strong>Email:</strong> <?php echo $alquiler['email']; ?></p>
                    </div>

                    <div class="perfil-container">
                        <h3>Detalles del Precio</h3>
                        <p><strong>Precio por día:</strong> <?php echo number_format($alquiler['precio_dia'], 2); ?>€</p>
                        <p><strong>Días de alquiler:</strong> <?php echo $alquiler['dias_alquiler']; ?></p>
                        <hr class="divider-muted">
                        <p><strong>Precio Total:</strong> <strong class="precio precio-total-grande"><?php echo number_format($alquiler['precio_total'], 2); ?>€</strong></p>
                        
                        <?php // Solo mostrar el botón de descarga si el alquiler está confirmado o finalizado ?>
                        <?php if ($alquiler['estado'] === 'confirmado' || $alquiler['estado'] === 'finalizado' || $alquiler['estado'] === 'en_curso'): ?>
                            <a href="generar_factura.php?id=<?php echo $alquiler['id']; ?>" target="_blank" class="boton btn-fullwidth espaciado-arriba">
                                📄 Descargar Factura en PDF
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <p>© 2026 ARUSLAT - Alquiler de Motos</p>
        <p>Proyecto TFG - Ángel Rus Latorre - ASIR</p>
    </footer>

</body>
</html>
<?php mysqli_close($conexion); // Cierra la conexión a la base de datos. ?>