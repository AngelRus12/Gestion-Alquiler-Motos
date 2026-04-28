<?php
session_start();
require_once 'loginbd.php';

// 1. Seguridad: Verificar que el usuario es administrador
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

// 2. Verificar que la solicitud sea por método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: nueva_moto.php');
    exit();
}

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
if (!$conexion) {
    // Redirigir con un error si la conexión a la BD falla
    header('Location: nueva_moto.php?error=db');
    exit();
}
mysqli_set_charset($conexion, "utf8");

// 3. Recoger y validar los datos del formulario
$marca = trim($_POST['marca'] ?? '');
$modelo = trim($_POST['modelo'] ?? '');
$año = (int)($_POST['año'] ?? 0);
$cilindrada = (int)($_POST['cilindrada'] ?? 0);
$kilometraje = (int)($_POST['kilometraje'] ?? 0);
$tipo = trim($_POST['tipo'] ?? '');
$matricula = trim($_POST['matricula'] ?? '');
$precio_dia = (float)($_POST['precio_dia'] ?? 0.0);
$descripcion = trim($_POST['descripcion'] ?? '');

// Validar que los campos obligatorios no estén vacíos
if (empty($marca) || empty($modelo) || $año <= 0 || $cilindrada <= 0 || empty($tipo) || empty($matricula) || $precio_dia <= 0) {
    header('Location: nueva_moto.php?error=campos_vacios');
    exit();
}

// 4. Procesar la imagen
$imagen_contenido = null;
if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == UPLOAD_ERR_OK) {
    // file_get_contents lee el archivo binario para guardarlo en el campo BLOB
    $imagen_contenido = file_get_contents($_FILES['imagen']['tmp_name']);
} else {
    // Si la imagen es obligatoria y falla la subida
    header('Location: nueva_moto.php?error=imagen');
    exit();
}

// 5. Insertar los datos en la base de datos usando una consulta preparada
$sql = "INSERT INTO motos (marca, modelo, año, cilindrada, kilometraje, tipo, matricula, precio_dia, descripcion, imagen, disponible) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";

$stmt = mysqli_prepare($conexion, $sql);

// "ssiiissdsb" - s: string, i: integer, d: double, b: blob
mysqli_stmt_bind_param($stmt, "ssiiissdsb", $marca, $modelo, $año, $cilindrada, $kilometraje, $tipo, $matricula, $precio_dia, $descripcion, $imagen_contenido);

if (mysqli_stmt_execute($stmt)) {
    // Si la inserción es exitosa, redirigir al panel de admin
    header('Location: admin_dashboard.php?msg=moto_creada');
} else {
    // Si hay un error en la consulta, redirigir con un mensaje de error
    header('Location: nueva_moto.php?error=sql');
}

mysqli_stmt_close($stmt);
mysqli_close($conexion);
exit();