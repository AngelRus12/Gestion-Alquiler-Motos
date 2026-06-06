<?php
/**
 * ajax_cancelar_reserva.php
 * Script para cancelar una reserva vía AJAX.
 * - Verifico la sesión, el propietario y la regla de las 48 horas.
 * - Devuelvo una respuesta JSON para que el frontend la procese sin recargar la página.
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once 'loginbd.php';

// --- 1. CONTROL DE ACCESO ---
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Acceso denegado. Debes iniciar sesión.']);
    exit();
}

// --- 2. VALIDACIÓN DE ENTRADA ---
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID de alquiler no válido.']);
    exit();
}

// --- 3. RECOLECCIÓN DE DATOS ---
$id_alquiler_a_cancelar = (int) $_POST['id'];
$id_usuario_actual = $_SESSION['usuario_id'];

// --- 4. CONEXIÓN A LA BASE DE DATOS ---
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
if (mysqli_connect_errno()) {
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión a la base de datos.']);
    exit();
}

// --- 5. VERIFICACIÓN DE PERMISOS Y REGLA DE 48 HORAS ---
// Primero, obtengo los datos del alquiler para verificar al propietario y la fecha.
$sql_check = "SELECT usuario_id, fecha_inicio, estado FROM alquileres WHERE id = ?";
$stmt_check = mysqli_prepare($conexion, $sql_check);
mysqli_stmt_bind_param($stmt_check, "i", $id_alquiler_a_cancelar);
mysqli_stmt_execute($stmt_check);
$resultado = mysqli_stmt_get_result($stmt_check);
$alquiler = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt_check);

if (!$alquiler) {
    echo json_encode(['status' => 'error', 'message' => 'La reserva no existe.']);
    exit();
}

if ($alquiler['usuario_id'] != $id_usuario_actual) {
    echo json_encode(['status' => 'error', 'message' => 'No tienes permiso para cancelar esta reserva.']);
    exit();
}

if (strtotime($alquiler['fecha_inicio']) <= strtotime('+48 hours')) {
    echo json_encode(['status' => 'error', 'message' => 'No se puede cancelar. Faltan menos de 48 horas para el inicio del alquiler.']);
    exit();
}

// --- 6. ACTUALIZACIÓN SEGURA EN LA BASE DE DATOS ---
$sql_update = "UPDATE alquileres SET estado = 'cancelado' WHERE id = ? AND usuario_id = ?";
$stmt_update = mysqli_prepare($conexion, $sql_update);
mysqli_stmt_bind_param($stmt_update, "ii", $id_alquiler_a_cancelar, $id_usuario_actual);

if (mysqli_stmt_execute($stmt_update) && mysqli_stmt_affected_rows($stmt_update) > 0) {
    echo json_encode(['status' => 'success', 'message' => 'Reserva cancelada correctamente.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo cancelar la reserva. Es posible que ya estuviera cancelada o que haya ocurrido un error.']);
}

mysqli_stmt_close($stmt_update);
mysqli_close($conexion);
?>