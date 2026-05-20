<?php
session_start();
require_once 'loginbd.php';

// Preparamos la respuesta JSON.
header('Content-Type: application/json');

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

$id_alquiler_a_cancelar = (int)$_POST['id'];
$id_usuario_actual = $_SESSION['usuario_id'];

// --- 3. CONEXIÓN A LA BASE DE DATOS ---
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
if (mysqli_connect_errno()) {
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión a la base de datos.']);
    exit();
}

// --- 4. ACTUALIZACIÓN SEGURA EN LA BASE DE DATOS ---
// La consulta es la misma, asegurando que el usuario solo pueda cancelar sus propias reservas.
$sql = "UPDATE alquileres SET estado = 'cancelado' WHERE id = ? AND usuario_id = ? AND estado = 'pendiente'";
$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "ii", $id_alquiler_a_cancelar, $id_usuario_actual);

if (mysqli_stmt_execute($stmt)) {
    // Comprobamos si realmente se afectó una fila.
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Reserva cancelada correctamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo cancelar la reserva (quizás ya no estaba pendiente o no te pertenece).']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error al ejecutar la consulta.']);
}

mysqli_stmt_close($stmt);
mysqli_close($conexion);
?>