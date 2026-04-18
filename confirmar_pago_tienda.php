<?php
session_start();
require_once 'loginbd.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

if (isset($_GET['id'])) {
    $conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
    
    $id_alquiler = (int)$_GET['id'];

    // Iniciar transacción para asegurar la consistencia de los datos
    mysqli_begin_transaction($conexion);

    // Paso 1: Actualizar el estado del alquiler a 'confirmado'
    $sql_update_alquiler = "UPDATE alquileres SET estado = 'confirmado' WHERE id = ?";
    $stmt_alquiler = mysqli_prepare($conexion, $sql_update_alquiler);
    mysqli_stmt_bind_param($stmt_alquiler, "i", $id_alquiler);
    $exito_alquiler = mysqli_stmt_execute($stmt_alquiler);
    mysqli_stmt_close($stmt_alquiler);

    // Paso 2: Obtener el ID de la moto para marcarla como no disponible
    $sql_get_moto = "SELECT moto_id FROM alquileres WHERE id = ?";
    $stmt_get_moto = mysqli_prepare($conexion, $sql_get_moto);
    mysqli_stmt_bind_param($stmt_get_moto, "i", $id_alquiler);
    mysqli_stmt_execute($stmt_get_moto);
    $resultado_moto = mysqli_stmt_get_result($stmt_get_moto);
    $moto_id = mysqli_fetch_assoc($resultado_moto)['moto_id'];
    mysqli_stmt_close($stmt_get_moto);

    // Paso 3: Actualizar el estado de la moto a no disponible (disponible = 0)
    $sql_update_moto = "UPDATE motos SET disponible = 0 WHERE id = ?";
    $stmt_moto = mysqli_prepare($conexion, $sql_update_moto);
    mysqli_stmt_bind_param($stmt_moto, "i", $moto_id);
    $exito_moto = mysqli_stmt_execute($stmt_moto);
    mysqli_stmt_close($stmt_moto);
    
    if ($exito_alquiler && $exito_moto) {
        mysqli_commit($conexion); // Confirmar cambios si todo fue bien
        header('Location: admin_dashboard.php?msg=actualizado');
    } else {
        mysqli_rollback($conexion); // Revertir cambios si algo falló
        header('Location: admin_dashboard.php?error=sql');
    }
    
    mysqli_close($conexion);
} else {
    header('Location: admin_dashboard.php');
}
exit();
?>