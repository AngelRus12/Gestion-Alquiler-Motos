<?php
// Pongo la codificación a UTF-8 para que no haya problemas con acentos o caracteres especiales.
header('Content-Type: text/html; charset=utf-8');
// Inicio la sesión para poder usar las variables de sesión.
session_start();
// Incluyo el archivo con las credenciales de la base de datos.
require_once 'loginbd.php';

// --- 1. CONTROL DE ACCESO ---
// Verifico si el usuario ha iniciado sesión. Si no, lo redirijo a la página de login.
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

// --- 2. VALIDACIÓN DE ENTRADA ---
// Verifico que se haya proporcionado un ID de alquiler en la URL y que sea un número.
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: perfil_usuario.php?error=id_invalido');
    exit();
}

// --- 3. CONEXIÓN A LA BASE DE DATOS ---
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
// Si la conexión falla, detengo la ejecución y muestro un error para saber qué ha pasado.
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
// Establece el conjunto de caracteres a UTF-8 para la conexión.
mysqli_set_charset($conexion, "utf8");

// Se convierte el ID de la URL a entero para mayor seguridad.
$alquiler_id = (int)$_GET['id']; // Lo convierto a entero por seguridad (evitar inyección SQL).
$usuario_id = $_SESSION['usuario_id'];

// --- 4. CONSULTA SEGURA DE DATOS DEL ALQUILER ---
// La consulta para obtener los datos del alquiler es diferente si soy admin o un usuario normal.
if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
    // Si soy admin, puedo ver cualquier alquiler solo con saber su ID.
    $sql_alquiler = "SELECT * FROM alquileres WHERE id = ?";
    $stmt_alquiler = mysqli_prepare($conexion, $sql_alquiler);
    mysqli_stmt_bind_param($stmt_alquiler, "i", $alquiler_id);
} else {
    // Si es un usuario normal, además del ID del alquiler, compruebo que el 'usuario_id' del alquiler
    // coincida con el de la sesión. Hago esto para que un usuario no pueda ver los alquileres de otro
    // simplemente cambiando el ID en la URL.
    $sql_alquiler = "SELECT * FROM alquileres WHERE id = ? AND usuario_id = ?";
    $stmt_alquiler = mysqli_prepare($conexion, $sql_alquiler);
    mysqli_stmt_bind_param($stmt_alquiler, "ii", $alquiler_id, $usuario_id);
}
// Ejecuto la consulta y obtengo el resultado.
mysqli_stmt_execute($stmt_alquiler);
$resultado_alquiler = mysqli_stmt_get_result($stmt_alquiler);
$alquiler = mysqli_fetch_assoc($resultado_alquiler);
mysqli_stmt_close($stmt_alquiler);

// --- 5. VERIFICACIÓN DE EXISTENCIA ---
// Si la consulta no devuelve nada, es que el alquiler no existe o el usuario no tiene permiso para verlo.
if (!$alquiler) {
    header('Location: perfil_usuario.php?error=no_encontrado');
    exit();
}

// --- 6. OBTENCIÓN DE DATOS RELACIONADOS (SIN USAR JOINs) ---
// Ahora que tengo los datos del alquiler, necesito los de la moto y el usuario asociados.
// Los busco por separado para mantener las consultas simples, en lugar de usar JOINs complejos.
$moto_data = [];
$usuario_data = [];

// Busco los datos de la moto usando el 'moto_id' que obtuve en la consulta del alquiler.
$sql_moto = "SELECT marca, modelo, tipo, precio_dia, imagen, descripcion as moto_descripcion FROM motos WHERE id = ?";
$stmt_moto = mysqli_prepare($conexion, $sql_moto);
mysqli_stmt_bind_param($stmt_moto, "i", $alquiler['moto_id']);
mysqli_stmt_execute($stmt_moto);
$moto_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_moto));
mysqli_stmt_close($stmt_moto);

// Hago lo mismo para el usuario, usando el 'usuario_id' que también saqué del alquiler.
$sql_usuario = "SELECT nombre, apellidos, email FROM usuarios WHERE id = ?";
$stmt_usuario = mysqli_prepare($conexion, $sql_usuario);
mysqli_stmt_bind_param($stmt_usuario, "i", $alquiler['usuario_id']);
mysqli_stmt_execute($stmt_usuario);
$usuario_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_usuario));
mysqli_stmt_close($stmt_usuario);

// --- 7. COMBINACIÓN DE DATOS ---
// Si la moto o el usuario han sido eliminados de la base de datos, las variables de antes estarán vacías.
// En ese caso, considero que el alquiler ya no es válido.
if (!$moto_data || !$usuario_data) {
    $alquiler = false; // Se marca el alquiler como falso para que la siguiente comprobación falle.
} else {
    // Si todos los datos existen, se combinan los tres arrays ($alquiler, $moto_data, $usuario_data)
    // en un único array `$alquiler` para usarlo fácilmente en el HTML.
    $alquiler = array_merge($alquiler, $moto_data, $usuario_data);
}

// Si el alquiler se marcó como falso, redirijo al usuario.
if (!$alquiler) {
    header('Location: perfil_usuario.php?error=no_encontrado');
    exit();
}

// --- 8. DETECCIÓN DE DISPOSITIVO MÓVIL ---
// He creado esta función simple para poder cargar una hoja de estilos diferente en móviles.
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
            <h2 class="titulo-pagina text-left">Detalle del Alquiler #<?php echo htmlspecialchars($alquiler['id']); ?></h2>
            <a href="perfil_usuario" class="enlace-discreto enlace-volver">&larr; Volver a Mi Perfil</a>

            <div class="detalle-alquiler-container">
                <div class="moto-detalle-card">
                    <?php 
                    $imagen_src = 'imgs/default.jpg';
                    if (!empty($alquiler['imagen'])) {
                        $imagen_src = 'data:image/jpeg;base64,' . base64_encode($alquiler['imagen']);
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($imagen_src); ?>" alt="Moto">
                    <h3><?php echo htmlspecialchars($alquiler['marca'] . " " . $alquiler['modelo']); ?></h3>
                    <p class="texto-gris-claro"><?php echo htmlspecialchars($alquiler['moto_descripcion']); ?></p>
                    <span class="etiqueta etiqueta-azul"><?php echo strtoupper(htmlspecialchars($alquiler['tipo'])); ?></span>
                </div>

                <div class="resumen-alquiler">
                    <div class="perfil-container">
                        <h3>Resumen de la Reserva</h3>
                        <p><strong>Estado:</strong> 
                            <span class="estado-alquiler <?php echo htmlspecialchars($alquiler['estado']); ?>">
                                <?php echo str_replace('_', ' ', strtoupper(htmlspecialchars($alquiler['estado']))); ?>
                            </span>
                        </p>
                        <p><strong>Fecha de reserva:</strong> <?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($alquiler['fecha_reserva']))); ?></p>
                        <p><strong>Fecha de finalización:</strong> <?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($alquiler['fecha_fin']))); ?></p>
                        <p><strong>Cliente:</strong> <?php echo htmlspecialchars($alquiler['nombre'] . " " . $alquiler['apellidos']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($alquiler['email']); ?></p>
                    </div>

                    <div class="perfil-container">
                        <h3>Detalles del Precio</h3>
                        <p><strong>Precio por día:</strong> <?php echo htmlspecialchars(number_format($alquiler['precio_dia'], 2)); ?>€</p>
                        <p><strong>Días de alquiler:</strong> <?php echo htmlspecialchars($alquiler['dias_alquiler']); ?></p>
                        <hr class="divider-muted">
                        <p><strong>Precio Total:</strong> <strong class="precio precio-total-grande"><?php echo htmlspecialchars(number_format($alquiler['precio_total'], 2)); ?>€</strong></p>
                        
                        <?php // Solo mostrar el botón de descarga si el alquiler está confirmado o finalizado ?>
                        <?php if ($alquiler['estado'] === 'confirmado' || $alquiler['estado'] === 'finalizado' || $alquiler['estado'] === 'en_curso'): ?>
                            <a href="generar_factura.php?id=<?php echo htmlspecialchars($alquiler['id']); ?>" target="_blank" class="boton btn-fullwidth espaciado-arriba">
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