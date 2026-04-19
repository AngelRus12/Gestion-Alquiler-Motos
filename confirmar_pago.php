<?php
session_start();
require_once 'loginbd.php';
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

if (isset($_SESSION['id_pago_pendiente'])) {
    $id_alquiler = $_SESSION['id_pago_pendiente'];

    // Usar sentencias preparadas para prevenir inyección SQL
    $sql = "UPDATE alquileres SET estado = 'confirmado' WHERE id = ?";
    
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_alquiler);
    
    if (mysqli_stmt_execute($stmt)) {

        unset($_SESSION['id_pago_pendiente']);
        unset($_SESSION['monto_pago']);
        header('Location: perfil_usuario.php?pago=confirmado');
        exit(); // Es una buena práctica llamar a exit() después de una redirección
    } else {
        echo "Error al confirmar pago: " . mysqli_error($conexion);
    }
    mysqli_stmt_close($stmt);
} else {
    header('Location: catalogo.php');
}
mysqli_close($conexion);
?>