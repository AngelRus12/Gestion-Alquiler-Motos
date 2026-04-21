<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'loginbd.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

mysqli_set_charset($conexion, "utf8");

$u_id = $_SESSION['usuario_id'];

// Usar consultas preparadas para seguridad y eficiencia
$sql_usuario = "SELECT *, antiguedad_usuario(?) as dias_antiguedad, total_gastadoo(?) as total_invertido FROM usuarios WHERE id = ?";
$stmt_usuario = mysqli_prepare($conexion, $sql_usuario);
mysqli_stmt_bind_param($stmt_usuario, "iii", $u_id, $u_id, $u_id);
mysqli_stmt_execute($stmt_usuario);
$res_stats = mysqli_stmt_get_result($stmt_usuario);
$usuario = mysqli_fetch_assoc($res_stats);
mysqli_stmt_close($stmt_usuario);


$sql_alquileres = "SELECT * FROM alquileres WHERE usuario_id = ? ORDER BY fecha_reserva DESC";
$stmt_alquileres = mysqli_prepare($conexion, $sql_alquileres);
mysqli_stmt_bind_param($stmt_alquileres, "i", $u_id);
mysqli_stmt_execute($stmt_alquileres);
$alquileres = mysqli_stmt_get_result($stmt_alquileres);