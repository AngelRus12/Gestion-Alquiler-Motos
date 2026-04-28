<?php
session_start();
require_once 'loginbd.php';

// 1. Seguridad: Verificar que el usuario es administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}

// 2. Validar que se ha proporcionado un ID de alquiler
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin_dashboard.php?error=id_invalido');
    exit();
}
$id_alquiler = (int)$_GET['id'];

// 3. Actualizar el estado del alquiler a 'cancelado'
// Se actualiza solo si el estado actual es 'pendiente' para evitar cancelar alquileres ya confirmados por error.
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