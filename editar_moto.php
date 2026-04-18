<?php
session_start();
require_once 'loginbd.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
$id = $_GET['id'];
$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $marca = $_POST['marca'];
    $modelo = $_POST['modelo'];
    $precio = $_POST['precio_dia'];
    $disponible = $_POST['disponible'];

    $sql = "UPDATE motos SET marca='$marca', modelo='$modelo', precio_dia='$precio', disponible='$disponible' WHERE id=$id";
    if (mysqli_query($conexion, $sql)) {
        $mensaje = "<div class='success'>¡Listo! La moto se ha actualizado.</div>";
    }
}

$res = mysqli_query($conexion, "SELECT * FROM motos WHERE id = $id");
$moto = mysqli_fetch_assoc($res);

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
        <h2 class="titulo-admin">Modificar datos de la Moto</h2>
        
        <div class="formulario-admin">
            <?php echo $mensaje; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Marca</label>
                    <input type="text" name="marca" value="<?php 
                    echo $moto['marca']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Modelo</label>
                    <input type="text" name="modelo" value="<?php 
                    echo $moto['modelo']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Precio por Día (€)</label>
                    <input type="number" step="0.01" name="precio_dia" value="<?php 
                    echo $moto['precio_dia']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Estado de disponibilidad</label>
                    <select name="disponible">
                        <option value="1" <?php if($moto['disponible']) 
                            echo 'selected'; ?>>Disponible para alquilar</option>
                        <option value="0" <?php if(!$moto['disponible']) 
                            echo 'selected'; ?>>No disponible / Reservada</option>
                    </select>
                </div>

                <div class="acciones-form">
                    <button type="submit" class="btn">Guardar cambios ahora</button>
                </div>
            </form>
        </div>
    </div>

    <footer class="footer-admin">
        <p>&copy; 2026 ARUSLAT - Alquiler de Motos</p>
    </footer>
</body>
</html>
<?php mysqli_close($conexion); ?>