<?php
session_start();
require_once 'loginbd.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin') {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

if (isset($_GET['id'])) {
    $conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
    
    $id_a_eliminar = mysqli_real_escape_string($conexion, $_GET['id']);
    $id_sesion_actual = $_SESSION['usuario_id'];

    if ($id_a_eliminar == $id_sesion_actual) {
        header('Location: admin_dashboard.php?error=autodelecion');
        exit();
    }

    $query = "DELETE FROM usuarios WHERE id = '$id_a_eliminar'";
    
    if (mysqli_query($conexion, $query)) {
        header('Location: admin_dashboard.php?msg=eliminado');
    } else {
        header('Location: admin_dashboard.php?error=sql');
    }
    
    mysqli_close($conexion);
} else {
    header('Location: admin_dashboard.php');
}
exit();