<?php
session_start();
require_once 'loginbd.php';

// Verificar que el usuario ha iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php?error=acceso_denegado'); // Redirigir al login si no ha iniciado sesión
    exit();
}

// Verificar que se ha proporcionado un ID de alquiler
if (!isset($_GET['id'])) {
    header('Location: perfil_usuario.php?error=no_id');
    exit();
}

$id_alquiler_a_cancelar = $_GET['id'];
$id_usuario_actual = $_SESSION['usuario_id'];

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

if (mysqli_connect_errno()) {
    // Manejar error de conexión
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// Sentencia preparada para actualizar el estado a 'cancelado'
// Se añade la condición del usuario_id para asegurar que un usuario no pueda cancelar la reserva de otro
$sql = "UPDATE alquileres SET estado = 'cancelado' WHERE id = ? AND usuario_id = ?";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "ii", $id_alquiler_a_cancelar, $id_usuario_actual);

if (mysqli_stmt_execute($stmt)) {
    header('Location: perfil_usuario.php?cancelacion=exitosa');
} else {
    header('Location: perfil_usuario.php?error=cancelacion_fallida');
}

mysqli_stmt_close($stmt);
mysqli_close($conexion);
?>