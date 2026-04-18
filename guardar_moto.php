<?php
session_start();
require_once 'loginbd.php';

// 1. Seguridad: Verificar que el usuario es administrador
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
mysqli_set_charset($conexion, "utf8");

// 2. Recoger y validar datos del formulario
$marca       = trim($_POST['marca']);
$modelo      = trim($_POST['modelo']);
$año         = (int)$_POST['año'];
$tipo        = trim($_POST['tipo']);
$cilindrada  = (int)$_POST['cilindrada'];
$km          = (int)$_POST['kilometraje'];
$precio      = (float)$_POST['precio_dia'];
$descripcion = trim($_POST['descripcion']);
$imagen_contenido = null;

// 3. Procesar la imagen subida
if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == UPLOAD_ERR_OK) {
    // Leer el contenido binario del archivo temporal
    $imagen_contenido = file_get_contents($_FILES['imagen']['tmp_name']);
} else {
    // Manejar error si no se sube la imagen
    header('Location: nueva_moto.php?error=imagen');
    exit();
}

// 4. Usar consultas preparadas para insertar los datos de forma segura
$sql = "INSERT INTO motos (marca, modelo, año, tipo, cilindrada, kilometraje, precio_dia, descripcion, imagen, disponible) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";

$stmt = mysqli_prepare($conexion, $sql);

// "sssiidssb" -> s: string, i: integer, d: double, b: blob
mysqli_stmt_bind_param($stmt, "sssiidssb", $marca, $modelo, $año, $tipo, $cilindrada, $km, $precio, $descripcion, $imagen_contenido);

// El último parámetro para bind_param debe ser la variable que contiene los datos del blob
// Se necesita enviar los datos del blob con send_long_data
mysqli_stmt_send_long_data($stmt, 8, $imagen_contenido);

if (mysqli_stmt_execute($stmt)) {
    header('Location: admin_dashboard.php?msg=moto_creada');
} else {
    // Redirigir con un error específico
    header('Location: nueva_moto.php?error=sql');
}

mysqli_stmt_close($stmt);
mysqli_close($conexion);
?>