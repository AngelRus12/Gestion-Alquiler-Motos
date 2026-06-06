<?php
session_start();
require_once 'loginbd.php';

// Primero, me aseguro de que solo un administrador como yo pueda ejecutar este script.
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Compruebo que me hayan pasado un ID de alquiler por la URL.
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin_dashboard.php?error=id_invalido');
    exit();
}
$id_alquiler = (int)$_GET['id'];

// Actualizo el estado del alquiler a 'cancelado'.
// Añado la condición "AND estado = 'pendiente'" por seguridad, para no cancelar por error un alquiler que ya esté confirmado.
$sql = "UPDATE alquileres SET estado = 'cancelado' WHERE id = ? AND estado = 'pendiente'";
$stmt = mysqli_prepare($conexion, $sql);

mysqli_stmt_bind_param($stmt, "i", $id_alquiler);

if (mysqli_stmt_execute($stmt)) {
    header('Location: admin_dashboard.php?msg=cancelado');
} else {
    header('Location: admin_dashboard.php?error=sql_execute');
}

mysqli_stmt_close($stmt);
mysqli_close($conexion);
exit();