<?php
session_start();
require_once 'loginbd.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if (isset($_GET['id'])) {
    $conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

    $id_moto = $_GET['id'];

    $sql = "DELETE FROM motos WHERE id = ?";
    $stmt = mysqli_prepare($conexion, $sql);

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