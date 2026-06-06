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
    if (!$conexion) {
        header('Location: admin_dashboard.php?error=db');
        exit();
    }
    
    $id_a_eliminar = (int)$_GET['id'];
    $id_sesion_actual = $_SESSION['usuario_id'];

    // 2. Impido que un administrador se elimine a sí mismo.
    if ($id_a_eliminar == $id_sesion_actual) {
        header('Location: admin_dashboard.php?error=autodelecion');
        exit();
    }

    // 3. Borrado Lógico: En lugar de un DELETE, actualizo el estado a 'inactivo'.
    // Así preservo la integridad de los datos y el historial de alquileres del usuario.
    $sql = "UPDATE usuarios SET estado = 'inactivo' WHERE id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_a_eliminar);
    
    if (mysqli_stmt_execute($stmt)) {
        header('Location: admin_dashboard.php?msg=eliminado');
    } else {
        header('Location: admin_dashboard.php?error=sql');
    }
    
    mysqli_stmt_close($stmt);
    mysqli_close($conexion);
} else {
    header('Location: admin_dashboard.php');
}
exit();