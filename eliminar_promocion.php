<?php
/**
 * eliminar_promocion.php
 * Procesa la eliminación de una promoción.
 * - Solo es accesible por administradores.
 * - Utilizo consultas preparadas para mayor seguridad.
 */
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'loginbd.php';

// 1. Control de acceso: solo administradores.
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

// 2. Validación de entrada: se requiere un ID numérico.
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin_dashboard.php?error=id_invalido');
    exit();
}

$promocion_id = (int)$_GET['id'];

// 3. Conexión a la base de datos.
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}

// 4. Hago la eliminación segura con una consulta preparada.
$sql = "DELETE FROM promociones WHERE id = ?";
$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "i", $promocion_id);

if (mysqli_stmt_execute($stmt)) {
    header('Location: admin_dashboard.php?msg=delete_ok');
} else {
    header('Location: admin_dashboard.php?error=delete_fail');
}

mysqli_stmt_close($stmt);
mysqli_close($conexion);
exit();