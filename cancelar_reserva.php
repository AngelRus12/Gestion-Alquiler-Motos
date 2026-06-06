<?php
/**
 * cancelar_reserva.php
 * Script para cancelar una reserva hecha por el usuario autenticado.
 * - Verifico la sesión y el propietario del alquiler.
 * - Cambio el estado de la reserva a 'cancelado' de forma segura.
 */
// Inicio la sesión para acceder a las variables de sesión, como el ID del usuario.
session_start();
// Incluye el archivo con las credenciales de la base de datos.
require_once 'loginbd.php';

// --- 1. CONTROL DE ACCESO ---
// Verifico si el usuario ha iniciado sesión. Si no, lo redirijo a la página de login.
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

// --- 2. VALIDACIÓN DE ENTRADA ---
// Verifico si se ha pasado un parámetro 'id' en la URL. Si no, redirijo al perfil.
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: perfil_usuario.php?error=no_id');
    exit();
}

// --- 3. RECOLECCIÓN DE DATOS ---
// Se obtiene el ID del alquiler a cancelar desde la URL.
// Obtengo el ID del alquiler a cancelar desde la URL.
// Obtengo el ID del usuario actual desde la sesión.
$id_usuario_actual = $_SESSION['usuario_id'];

// --- 4. CONEXIÓN A LA BASE DE DATOS ---
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

// Si la conexión falla, detengo la ejecución y muestro un error.
if (mysqli_connect_errno()) { // Si la conexión falla, detengo la ejecución y muestro un error.
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// --- 5. ACTUALIZACIÓN SEGURA EN LA BASE DE DATOS ---
// Preparo una consulta para actualizar el estado del alquiler a 'cancelado'.
// ¡CRÍTICO! Añado la condición `AND usuario_id = ?`. Esto me asegura que un usuario
// solo pueda cancelar una reserva que le pertenece, evitando que un usuario malintencionado
// cancele la reserva de otro simplemente cambiando el ID en la URL.
$sql = "UPDATE alquileres SET estado = 'cancelado' WHERE id = ? AND usuario_id = ?";

// --- 5. VERIFICACIÓN DE PERMISOS Y REGLA DE 48 HORAS ---
// Primero, obtengo los datos del alquiler para verificar al propietario y la fecha.
$sql_check = "SELECT usuario_id, fecha_inicio, estado FROM alquileres WHERE id = ?";
$stmt_check = mysqli_prepare($conexion, $sql_check);
mysqli_stmt_bind_param($stmt_check, "i", $id_alquiler_a_cancelar);
mysqli_stmt_execute($stmt_check);
$resultado = mysqli_stmt_get_result($stmt_check);
$alquiler = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt_check);

if (!$alquiler || $alquiler['usuario_id'] != $id_usuario_actual) { // Si el alquiler no existe o no pertenece al usuario, lo redirijo con un error.
    header('Location: perfil_usuario.php?error=cancelacion_fallida');
    exit();
}

// Compruebo la regla de negocio de las 48 horas.
if (strtotime($alquiler['fecha_inicio']) <= strtotime('+48 hours')) {
    // Si faltan 48 horas o menos, no permito la cancelación.
    header('Location: perfil_usuario.php?error=cancelacion_fuera_plazo');
    exit();
}

// Si todo es correcto, procedo a actualizar.

$stmt = mysqli_prepare($conexion, $sql);
// Asocio las variables a los parámetros de la consulta. "ii" significa que ambos son enteros (integer).
mysqli_stmt_bind_param($stmt, "ii", $id_alquiler_a_cancelar, $id_usuario_actual);

// Ejecuto la consulta.
if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
    // Si la actualización es exitosa (y afectó a alguna fila), redirijo al perfil con un mensaje de éxito.
    header('Location: perfil_usuario.php?cancelacion=exitosa');
} else {
    // Si falla o no se encontró la reserva (porque no cumplía las condiciones del WHERE), redirijo con un mensaje de error.
    header('Location: perfil_usuario.php?error=cancelacion_fallida');
}

// Se cierran la consulta preparada y la conexión para liberar recursos.
mysqli_stmt_close($stmt);
mysqli_close($conexion);
?>