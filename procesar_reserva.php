<?php
session_start();
require_once 'loginbd.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

$u_id     = $_SESSION['usuario_id'];
$moto_id  = $_POST['id_moto'];
$f_inicio = $_POST['f_inicio'];
$f_fin    = $_POST['f_fin'];
$metodo   = $_POST['metodo_pago'];
$precio   = $_POST['precio_dia'];

$dias = (strtotime($f_fin) - strtotime($f_inicio)) / 86400 + 1;

// El estado inicial de una reserva siempre debe ser 'pendiente'
$estado = 'pendiente';

$total = $dias * $precio;

$sql = "INSERT INTO alquileres (usuario_id, moto_id, fecha_inicio, fecha_fin, dias_alquiler, precio_total, estado) 
        VALUES ($u_id, $moto_id, '$f_inicio', '$f_fin', $dias, $total, '$estado')";

if (mysqli_query($conexion, $sql)) {
    if ($metodo == 'web') {
        $_SESSION['id_pago_pendiente'] = mysqli_insert_id($conexion);
        $_SESSION['monto_pago'] = $total;
        header('Location: pago.php');
    } else {
        header('Location: perfil_usuario.php?reserva=ok');
    }
} else {
    echo "Error: " . mysqli_error($conexion);
}

mysqli_close($conexion);
?>