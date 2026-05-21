<?php
/**
 * cancelar_reserva.php
 * Script para cancelar una reserva hecha por el usuario autenticado.
 * - Verifica la sesión y el propietario del alquiler.
 * - Cambia el estado de la reserva a 'cancelado' de forma segura.
 */
// Inicia la sesión para acceder a las variables de sesión, como el ID del usuario.
session_start();
// Incluye el archivo con las credenciales de la base de datos.
require_once 'loginbd.php';

// --- 1. CONTROL DE ACCESO ---
// Verifica si el usuario ha iniciado sesión. Si no, lo redirige a la página de login.
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

// --- 2. VALIDACIÓN DE ENTRADA ---
// Verifica si se ha pasado un parámetro 'id' en la URL. Si no, redirige al perfil.
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: perfil_usuario.php?error=no_id');
    exit();
}

// --- 3. RECOLECCIÓN DE DATOS ---
// Se obtiene el ID del alquiler a cancelar desde la URL.
$id_alquiler_a_cancelar = (int) $_GET['id'];
// Se obtiene el ID del usuario actual desde la sesión.
$id_usuario_actual = $_SESSION['usuario_id'];

// --- 4. CONEXIÓN A LA BASE DE DATOS ---
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

// Si la conexión falla, se detiene la ejecución y se muestra un error.
if (mysqli_connect_errno()) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// --- 5. ACTUALIZACIÓN SEGURA EN LA BASE DE DATOS ---
// Se prepara una consulta para actualizar el estado del alquiler a 'cancelado'.
// CRÍTICO: Se añade la condición `AND usuario_id = ?`. Esto asegura que un usuario
// solo pueda cancelar una reserva que le pertenece, evitando que un usuario malintencionado
// cancele la reserva de otro cambiando el ID en la URL.
$sql = "UPDATE alquileres SET estado = 'cancelado' WHERE id = ? AND usuario_id = ?";

$stmt = mysqli_prepare($conexion, $sql);
// Se asocian las variables a los parámetros de la consulta. "ii" significa que ambos son enteros.
mysqli_stmt_bind_param($stmt, "ii", $id_alquiler_a_cancelar, $id_usuario_actual);

// Se ejecuta la consulta.
if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
    // Si la actualización es exitosa, se redirige al perfil con un mensaje de éxito.
    header('Location: perfil_usuario.php?cancelacion=exitosa');
} else {
    // Si falla o no se encontró la reserva, se redirige con un mensaje de error.
    header('Location: perfil_usuario.php?error=cancelacion_fallida');
}

// Se cierran la consulta preparada y la conexión para liberar recursos.
mysqli_stmt_close($stmt);
mysqli_close($conexion);
?>