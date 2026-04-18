<?php
session_start();
require_once 'loginbd.php';

if (!isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] !== 'admin') {
    exit("Acceso denegado");
}

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

$marca       = mysqli_real_escape_string($conexion, $_POST['marca']);
$modelo      = mysqli_real_escape_string($conexion, $_POST['modelo']);
$año         = $_POST['año'];
$tipo        = $_POST['tipo'];
$cilindrada  = $_POST['cilindrada'];
$km          = $_POST['kilometraje'];
$precio      = $_POST['precio_dia'];
$descripcion = mysqli_real_escape_string($conexion, $_POST['descripcion']);
$imagen      = $_POST['ruta_imagen'];

$sql = "INSERT INTO motos (marca, modelo, año, tipo, cilindrada, kilometraje, precio_dia, descripcion, imagen, disponible) 
        VALUES ('$marca', '$modelo', '$año', '$tipo', '$cilindrada', '$km', '$precio', '$descripcion', '$imagen', 'si')";

if (mysqli_query($conexion, $sql)) {
    header('Location: catalogo.php?exito=1');
} else {
    echo "Error al guardar en la base de datos: " . mysqli_error($conexion);
}

mysqli_close($conexion);
?>