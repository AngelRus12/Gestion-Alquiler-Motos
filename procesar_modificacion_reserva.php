<?php
/**
 * procesar_modificacion_reserva.php
 * Procesa la modificación de las fechas de una reserva.
 * - Realizo todas las validaciones de seguridad y reglas de negocio en el servidor.
 * - Compruebo la disponibilidad de las nuevas fechas.
 * - Recalculo el precio y actualizo la reserva en la base de datos.
 */
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'loginbd.php';
require_once 'funciones.php';

// --- 1. CONTROL DE ACCESO Y VALIDACIÓN INICIAL ---
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: perfil_usuario.php');
    exit();
}

if (!isset($_POST['id_alquiler']) || !is_numeric($_POST['id_alquiler']) || !isset($_POST['nuevas_fechas'])) {
    header('Location: perfil_usuario.php?error=datos_invalidos');
    exit();
}

$id_alquiler = (int)$_POST['id_alquiler'];
$id_usuario = $_SESSION['usuario_id'];
$rango_fechas = trim($_POST['nuevas_fechas']);

// Parseo el rango de fechas que me llega del calendario.
$fechas = explode(" to ", $rango_fechas);
if (count($fechas) !== 2) {
    header('Location: modificar_reserva.php?id=' . $id_alquiler . '&error=fecha_invalida');
    exit();
}
$nueva_fecha_inicio = $fechas[0];
$nueva_fecha_fin = $fechas[1];

// --- 2. CONEXIÓN A LA BASE DE DATOS ---
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
if (!$conexion) {
    die("Error de conexión a la base de datos.");
}
mysqli_set_charset($conexion, "utf8");

// --- 3. VUELVO A VALIDAR LA PROPIEDAD Y LAS REGLAS DE NEGOCIO EN EL SERVIDOR ---
$sql_alquiler = "SELECT * FROM alquileres WHERE id = ? AND usuario_id = ?";
$stmt = mysqli_prepare($conexion, $sql_alquiler);
mysqli_stmt_bind_param($stmt, "ii", $id_alquiler, $id_usuario);
mysqli_stmt_execute($stmt);
$alquiler = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$alquiler) {
    header('Location: perfil_usuario.php?error=no_encontrado');
    exit();
}

if (!in_array($alquiler['estado'], ['pendiente', 'confirmado'])) {
    header('Location: modificar_reserva.php?id=' . $id_alquiler . '&error=no_modificable');
    exit();
}

if (strtotime($alquiler['fecha_inicio']) <= strtotime('+48 hours')) {
    header('Location: modificar_reserva.php?id=' . $id_alquiler . '&error=modificacion_fuera_plazo');
    exit();
}

// --- 4. COMPROBAR DISPONIBILIDAD DE NUEVAS FECHAS ---
// Uso una consulta que excluye el alquiler actual para comprobar si las nuevas fechas están libres.
$sql_solapamiento = "SELECT COUNT(*) as total FROM alquileres
                     WHERE moto_id = ?
                       AND id != ?
                       AND estado IN ('confirmado', 'en_curso')
                       AND NOT (fecha_fin < ? OR fecha_inicio > ?)";
$stmt_solapamiento = mysqli_prepare($conexion, $sql_solapamiento);
mysqli_stmt_bind_param($stmt_solapamiento, "iiss", $alquiler['moto_id'], $id_alquiler, $nueva_fecha_inicio, $nueva_fecha_fin);
mysqli_stmt_execute($stmt_solapamiento);
$resultado_solapamiento = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_solapamiento));
mysqli_stmt_close($stmt_solapamiento);

if ($resultado_solapamiento['total'] > 0) {
    header('Location: modificar_reserva.php?id=' . $id_alquiler . '&error=fechas_ocupadas');
    exit();
}

// --- 5. RECALCULAR PRECIO Y ACTUALIZAR RESERVA ---
// Obtengo el precio/día de la moto para recalcular el total.
$sql_moto = "SELECT precio_dia FROM motos WHERE id = ?";
$stmt_moto = mysqli_prepare($conexion, $sql_moto);
mysqli_stmt_bind_param($stmt_moto, "i", $alquiler['moto_id']);
mysqli_stmt_execute($stmt_moto);
$moto = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_moto));
mysqli_stmt_close($stmt_moto);

if (!$moto) {
    header('Location: modificar_reserva.php?id=' . $id_alquiler . '&error=moto_no_encontrada');
    exit();
}

// Recalculo los días y el precio total.
$nuevos_dias = (strtotime($nueva_fecha_fin) - strtotime($nueva_fecha_inicio)) / (60 * 60 * 24) + 1;
$nuevo_precio_total = $nuevos_dias * $moto['precio_dia'];

// Actualizo la base de datos con los nuevos datos.
$sql_update = "UPDATE alquileres SET fecha_inicio = ?, fecha_fin = ?, dias_alquiler = ?, precio_total = ? WHERE id = ? AND usuario_id = ?";
$stmt_update = mysqli_prepare($conexion, $sql_update);
mysqli_stmt_bind_param($stmt_update, "ssidis", $nueva_fecha_inicio, $nueva_fecha_fin, $nuevos_dias, $nuevo_precio_total, $id_alquiler, $id_usuario);

if (mysqli_stmt_execute($stmt_update)) {
    // Éxito
    header('Location: perfil_usuario.php?modificacion=exitosa');
} else {
    // Error
    header('Location: modificar_reserva.php?id=' . $id_alquiler . '&error=actualizacion_fallida');
}

mysqli_stmt_close($stmt_update);
mysqli_close($conexion);
exit();
?>