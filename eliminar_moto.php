<?php
session_start();
require_once 'loginbd.php';

// 1. Seguridad: Verificar que el usuario es administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

if (isset($_GET['id'])) {
    $conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
    $id_moto = (int)$_GET['id'];

    // 2. Comprobar si la moto tiene alquileres asociados (activos o pasados)
    $sql_check = "SELECT COUNT(*) as total FROM alquileres WHERE moto_id = ?";
    $stmt_check = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "i", $id_moto);
    mysqli_stmt_execute($stmt_check);
    $resultado = mysqli_stmt_get_result($stmt_check);
    $conteo = mysqli_fetch_assoc($resultado)['total'];
    mysqli_stmt_close($stmt_check);

    if ($conteo > 0) {
        // Si tiene alquileres, no se puede borrar para mantener la integridad de los datos
        header('Location: admin_dashboard.php?error=moto_con_alquileres');
        exit();
    }

    // 3. Si no tiene alquileres, proceder con la eliminación
    $sql_delete = "DELETE FROM motos WHERE id = ?";
    $stmt_delete = mysqli_prepare($conexion, $sql_delete);
    mysqli_stmt_bind_param($stmt_delete, "i", $id_moto);
    
    if (mysqli_stmt_execute($stmt_delete)) {
        header('Location: admin_dashboard.php?msg=eliminado');
    } else {
        header('Location: admin_dashboard.php?error=sql');
    }
    
    mysqli_stmt_close($stmt_delete);
    mysqli_close($conexion);
} else {
    header('Location: admin_dashboard.php');
}
exit();
    mysqli_stmt_bind_param($stmt, "i", $id_moto);

    if (mysqli_stmt_execute($stmt)) {
        header('Location: admin_dashboard.php?msg=eliminado');
    } else {
        header('Location: admin_dashboard.php?error=error_al_eliminar');
    }
    
    mysqli_stmt_close($stmt);
    mysqli_close($conexion);
} else {
    header('Location: admin_dashboard.php');
}
exit();