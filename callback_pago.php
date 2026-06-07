<?php
/**
 * =========================================================================================
 * MI CALLBACK PARA PROCESAR LA RESPUESTA DEL SIMULADOR DE PAGO
 * =========================================================================================
 * 
 * ¿Qué es un Callback?
 * --------------------
 * Un callback (o webhook) es una URL en mi servidor a la que la pasarela de pago (en este
 * caso, mi simulador) envía una notificación automática para informarme del resultado de
 * una transacción (aprobada, rechazada, etc.).
 * 
 * Flujo de trabajo de este archivo:
 * 1. Recibo la notificación del simulador.
 * 2. Hago varias comprobaciones de seguridad para validar la transacción.
 * 3. Inicio una transacción de base de datos para garantizar la integridad de los datos.
 * 4. Actualizo el estado del alquiler y la disponibilidad de la moto.
 * 5. Confirmo (commit) o revierto (rollback) los cambios en la base de datos.
 * 6. Redirijo al usuario a su perfil con un mensaje de estado.
 */

// --- CONFIGURACIÓN DE LA SESIÓN ---
// Establezco un tiempo de vida de 30 minutos para la sesión.
ini_set('session.gc_maxlifetime', 1800);
session_set_cookie_params(1800);
// Inicio la sesión para poder acceder a las variables de sesión (como los datos del usuario y la transacción).
session_start();

require_once 'loginbd.php';

// --- CONEXIÓN A LA BASE DE DATOS ---
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

if (!$conexion) {
    // Si no me puedo conectar a la BD, guardo el error para mí y redirijo al usuario a una página de error.
    error_log("Error de conexión a la base de datos en callback_pago.php: " . mysqli_connect_error());
    header('Location: perfil_usuario.php?pago=error_db_conn');
    exit();
}

// --- PASO 1: VERIFICACIÓN DE SEGURIDAD INICIAL ---
// Compruebo que en la sesión existan los datos del pago. Si alguien llega a esta URL directamente,
// esta variable no existirá, así que lo echo de aquí.
if (!isset($_SESSION['last_transaction']) || empty($_SESSION['last_transaction'])) {
    error_log("No se encontraron datos de la última transacción en la sesión.");
    header('Location: catalogo.php?error=no_transaction_data');
    exit();
}

// Recupero los datos de la transacción que guardé en la sesión justo antes de ir a la pasarela de pago.
$transaction = $_SESSION['last_transaction'];

// El simulador de pago me envía el resultado (approved, rejected, etc.) por POST.
$status = $_POST['response_type'] ?? 'error';

$order_id = $transaction['order_id'] ?? '';

// --- PASO 2: EXTRACCIÓN DEL ID DEL ALQUILER ---
// El número de orden que generé era "ALQ-ID-TIMESTAMP". Lo separo para quedarme solo con el ID del alquiler.
$parts = explode('-', $order_id);
$id_alquiler = isset($parts[1]) ? (int)$parts[1] : 0;

// --- PASO 3: VERIFICACIÓN DE AUTENTICACIÓN Y DATOS VÁLIDOS ---
// Me aseguro de que el usuario haya iniciado sesión y de que el ID del alquiler sea un número válido.
if (!isset($_SESSION['usuario_id']) || !$id_alquiler) {
    error_log("Acceso denegado o ID de alquiler inválido. Usuario ID: " . ($_SESSION['usuario_id'] ?? 'N/A') . ", Alquiler ID: " . $id_alquiler);
    header('Location: catalogo.php?error=invalid_access');
    exit();
}
$u_id = $_SESSION['usuario_id'];

// --- PASO 4: VERIFICACIÓN DE PROPIEDAD DEL ALQUILER ---
// ¡Esto es muy importante! Compruebo que el alquiler que se está pagando pertenece de verdad
// al usuario que está conectado. Así evito que un usuario malintencionado pague o modifique el alquiler de otra persona.
$sql_check = "SELECT id, moto_id FROM alquileres WHERE id = ? AND usuario_id = ?";
$stmt_check = mysqli_prepare($conexion, $sql_check);

if (!$stmt_check) {
    error_log("Error al preparar la consulta SQL_CHECK en callback_pago.php: " . mysqli_error($conexion));
    mysqli_close($conexion);
    header('Location: perfil_usuario.php?pago=error_sql_prep');
    exit();
}
mysqli_stmt_bind_param($stmt_check, "ii", $id_alquiler, $u_id);
mysqli_stmt_execute($stmt_check);
$result = mysqli_stmt_get_result($stmt_check);
$alquiler = mysqli_fetch_assoc($result);

// Si la consulta no devuelve ninguna fila, significa que el alquiler no existe o no pertenece a este usuario.
if (!$alquiler) {
    mysqli_stmt_close($stmt_check);
    error_log("Alquiler ID " . $id_alquiler . " no encontrado o no pertenece al usuario " . $u_id);
    mysqli_close($conexion);
    header('Location: catalogo.php?error=alquiler_not_found');
    exit();
}
mysqli_stmt_close($stmt_check);

// --- PASO 5: INICIO DE LA TRANSACCIÓN DE BASE DE DATOS ---
// Inicio una transacción. Esto sirve para que las dos actualizaciones que haré ahora
// (cambiar estado del alquiler y de la moto) se hagan a la vez. Si una de las dos falla, la otra se deshace (rollback).
// Así me aseguro de que la base de datos siempre quede en un estado consistente.
mysqli_begin_transaction($conexion);
$transaction_successful = true; // Bandera para controlar el éxito de la transacción.
$mensaje = 'pago=error_procesamiento'; // Mensaje de redirección por defecto en caso de fallo.

// --- PASO 6: DETERMINAR EL NUEVO ESTADO DEL ALQUILER ---
// Según lo que me ha dicho el simulador ('approved', 'rejected', etc.), decido qué estado
// le voy a poner al alquiler en la base de datos y qué mensaje le mostraré al usuario.
switch ($status) {
    case 'approved':
        $nuevo_estado = 'confirmado';
        $mensaje = 'pago=confirmado';
        break;
    
    case 'rejected':
        $nuevo_estado = 'cancelado';
        $mensaje = 'pago=rechazado';
        break;
    
    case 'pending':
        $nuevo_estado = 'pendiente';
        $mensaje = 'pago=pendiente';
        break;
    
    case 'cancelled':
        $nuevo_estado = 'cancelado';
        $mensaje = 'pago=cancelado';
        break;
    
    default:
        // Si el estado es desconocido o hay un error, lo marco como 'error'.
        $nuevo_estado = 'error';
        $mensaje = 'pago=error';
        break;
}

// --- PASO 7: ACTUALIZAR EL ESTADO DEL ALQUILER (1ª operación de la transacción) ---
$sql_update = "UPDATE alquileres SET estado = ? WHERE id = ?";
$stmt_update = mysqli_prepare($conexion, $sql_update);

if (!$stmt_update) {
    error_log("Error al preparar la consulta SQL_UPDATE en callback_pago.php: " . mysqli_error($conexion));
    $transaction_successful = false;
} else {
    mysqli_stmt_bind_param($stmt_update, "si", $nuevo_estado, $id_alquiler);
    if (!mysqli_stmt_execute($stmt_update)) {
        error_log("Error al ejecutar SQL_UPDATE en callback_pago.php: " . mysqli_stmt_error($stmt_update));
        $transaction_successful = false;
    }
    mysqli_stmt_close($stmt_update);
}

// --- PASO 8: ACTUALIZAR DISPONIBILIDAD DE LA MOTO (2ª operación de la transacción) ---
// Si el pago se ha aprobado y la actualización anterior ha ido bien,
// ahora marco la moto como "no disponible".
if ($transaction_successful && $nuevo_estado === 'confirmado') {
    $moto_id = $alquiler['moto_id'];
    $sql_update_moto = "UPDATE motos SET disponible = 0 WHERE id = ?";
    $stmt_moto = mysqli_prepare($conexion, $sql_update_moto);

    // Aplico la misma lógica de comprobación de errores que en el paso anterior.
    if (!$stmt_moto) {
        error_log("Error al preparar la consulta SQL_UPDATE_MOTO en callback_pago.php: " . mysqli_error($conexion));
        $transaction_successful = false;
    } else {
        mysqli_stmt_bind_param($stmt_moto, "i", $moto_id);
        if (!mysqli_stmt_execute($stmt_moto)) {
            error_log("Error al ejecutar SQL_UPDATE_MOTO en callback_pago.php: " . mysqli_stmt_error($stmt_moto));
            $transaction_successful = false;
        }
        mysqli_stmt_close($stmt_moto);
    }
}

// --- PASO 9: FINALIZAR LA TRANSACCIÓN (COMMIT O ROLLBACK) ---
if ($transaction_successful) {
    // Si todo ha ido bien, confirmo los cambios para que se guarden de forma permanente en la BD.
    mysqli_commit($conexion);
    
    // Limpio los datos del pago de la sesión para que no se pueda volver a procesar por error si se recarga la página.
    unset($_SESSION['id_pago_pendiente']);
    unset($_SESSION['monto_pago']);
    unset($_SESSION['last_transaction']);
    
    // Redirijo al usuario a su perfil con el mensaje correspondiente al resultado del pago.
    header('Location: perfil_usuario.php?' . $mensaje);
} else {
    // Si algo ha fallado, revierto todos los cambios. La base de datos se quedará como estaba al principio.
    mysqli_rollback($conexion);
    header('Location: perfil_usuario.php?pago=error_procesamiento');
}

// --- PASO 10: CIERRE DE CONEXIÓN ---
mysqli_close($conexion);
exit();
?>