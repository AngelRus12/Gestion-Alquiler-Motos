<?php
session_start();
require_once 'loginbd.php';

// 1. Seguridad: Verifico que el usuario sea administrador.
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
mysqli_set_charset($conexion, "utf8");

// 2. Valido que se haya proporcionado un ID de moto.
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin_dashboard.php?error=id_invalido');
    exit();
}
$id = (int)$_GET['id'];

// Función para detectar dispositivos móviles
function isMobile() { // Mi función para detectar si es un móvil.
    return preg_match("/(android|avantgo|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino)/i", $_SERVER["HTTP_USER_AGENT"]);
}
$is_mobile = isMobile();

// 3. Procesar el formulario si se envía por POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Recoger y limpiar datos
    $marca = trim($_POST['marca']); // Recojo y limpio los datos del formulario.
    $modelo = trim($_POST['modelo']);
    $precio = (float)$_POST['precio_dia'];
    $disponible = (int)$_POST['disponible'];

    // Uso consultas preparadas para la actualización por seguridad.
    $sql = "UPDATE motos SET marca = ?, modelo = ?, precio_dia = ?, disponible = ? WHERE id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ssdii", $marca, $modelo, $precio, $disponible, $id);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        mysqli_close($conexion);
        header('Location: admin_dashboard.php?msg=actualizado');
        exit();
    } else {
        // En caso de error, redirijo con un mensaje.
        header('Location: editar_moto.php?id=' . $id . '&error=sql');
        exit();
    }
}

// 4. Obtengo los datos de la moto para mostrarlos en el formulario.
$sql_moto = "SELECT * FROM motos WHERE id = ?";
$stmt_moto = mysqli_prepare($conexion, $sql_moto);
mysqli_stmt_bind_param($stmt_moto, "i", $id);
mysqli_stmt_execute($stmt_moto);
$resultado = mysqli_stmt_get_result($stmt_moto);
$moto = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt_moto);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Moto - ARUSLAT</title>
    <?php if ($is_mobile): ?>
        <link rel="stylesheet" href="estilos_mobile.css">
    <?php else: ?>
        <link rel="stylesheet" href="estilos.css">
    <?php endif; ?>
</head>
<body>
    <div class="navbar">
        <div class="container">
            <h1>Área Admin</h1>
            <nav><a href="admin_dashboard">Volver atrás</a></nav>
        </div>
    </div>

    <div class="container">
        <h2 class="titulo-admin">Modificar Datos de la Moto</h2>
        
        <div class="formulario-admin">
            <?php 
            if (isset($_GET['error'])) {
                echo "<div class='alerta alerta-error'>Hubo un error al actualizar. Inténtalo de nuevo.</div>";
            }
            ?>
            <form action="editar_moto.php?id=<?php echo $id; ?>" method="POST">
                <div class="form-group">
                    <label>Marca</label>
                    <input type="text" name="marca" value="<?php echo htmlspecialchars($moto['marca']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Modelo</label>
                    <input type="text" name="modelo" value="<?php echo htmlspecialchars($moto['modelo']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Precio por Día (€)</label>
                    <input type="number" step="0.01" name="precio_dia" value="<?php echo htmlspecialchars($moto['precio_dia']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Estado de disponibilidad</label>
                    <select name="disponible">
                        <option value="1" <?php if($moto['disponible'] == 1) echo 'selected'; ?>>Disponible para alquilar</option>
                        <option value="0" <?php if($moto['disponible'] == 0) echo 'selected'; ?>>No disponible / Reservada</option>
                    </select>
                </div>

                <div class="acciones-form">
                    <button type="submit" class="btn">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <footer class="pie-pagina">
        <p>&copy; 2026 ARUSLAT - Alquiler de Motos</p>
    </footer>
</body>
</html>
<?php mysqli_close($conexion); ?>