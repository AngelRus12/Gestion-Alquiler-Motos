<?php
session_start();
require_once 'loginbd.php';

// 1. Seguridad: Verifico que el usuario sea administrador.
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

if (isset($_GET['id'])) {
    $conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
    $id_moto = (int)$_GET['id'];

    // 2. Compruebo si la moto tiene alquileres asociados (activos o pasados).
    $sql_check = "SELECT COUNT(*) as total FROM alquileres WHERE moto_id = ?";
    $stmt_check = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "i", $id_moto);
    mysqli_stmt_execute($stmt_check);
    $resultado = mysqli_stmt_get_result($stmt_check);
    $conteo = mysqli_fetch_assoc($resultado)['total'];
    mysqli_stmt_close($stmt_check);

    if ($conteo > 0) {
        // Si tiene alquileres, no permito el borrado para mantener la integridad de los datos.
        header('Location: admin_dashboard.php?error=moto_con_alquileres');
        exit();
    }

    // 3. Borrado Lógico: En lugar de un borrado físico (DELETE), se actualiza el estado de la moto a 'no disponible'.
    // Esto preserva la integridad de los datos históricos y me permite "retirar" una moto del catálogo sin perder su información.
    // Es la misma estrategia que uso para eliminar usuarios.
    $sql_update = "UPDATE motos SET disponible = 0 WHERE id = ?";
    $stmt_update = mysqli_prepare($conexion, $sql_update);
    mysqli_stmt_bind_param($stmt_update, "i", $id_moto);
    
    if (mysqli_stmt_execute($stmt_update)) {
        header('Location: admin_dashboard.php?msg=eliminado');
    } else {
        header('Location: admin_dashboard.php?error=sql');
    }
    
    mysqli_stmt_close($stmt_update);
    mysqli_close($conexion);
} else {
    header('Location: admin_dashboard.php');
}
exit();