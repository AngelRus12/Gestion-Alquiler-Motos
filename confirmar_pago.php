<?php
session_start();
require_once 'loginbd.php';
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

if (isset($_SESSION['id_pago_pendiente'])) {
    $id_alquiler = $_SESSION['id_pago_pendiente'];

    $sql = "UPDATE alquileres SET estado = 'confirmado' WHERE id = $id_alquiler";
    
    if (mysqli_query($conexion, $sql)) {

        unset($_SESSION['id_pago_pendiente']);
        unset($_SESSION['monto_pago']);
        header('Location: perfil_usuario.php?pago=confirmado');
    } else {
        echo "Error al confirmar pago: " . mysqli_error($conexion);
    }
} else {
    header('Location: catalogo.php');
}
mysqli_close($conexion);
?>