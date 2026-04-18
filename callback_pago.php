<?php
/**
 * Callback para procesar respuesta del simulador de pago
 * 
 * Este archivo recibe la respuesta del simulador de pago y actualiza
 * el estado del alquiler en la base de datos según el resultado.
 */

session_start();
require_once 'loginbd.php';

// Habilitar reporte de errores para depuración (solo en desarrollo)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

// Verificar conexión a la base de datos
if (!$conexion) {
    error_log("Error de conexión a la base de datos en callback_pago.php: " . mysqli_connect_error());
    header('Location: perfil_usuario.php?pago=error_db_conn'); // Redirigir a una página de error o perfil
    exit();
}

// Verificar que tenemos datos de transacción del simulador
if (!isset($_SESSION['last_transaction']) || empty($_SESSION['last_transaction'])) {
    error_log("No se encontraron datos de la última transacción en la sesión.");
    header('Location: catalogo.php?error=no_transaction_data');
    exit();
}

$transaction = $_SESSION['last_transaction'];
$status = $transaction['status'] ?? 'error'; // Default a 'error' si no hay status
$order_id = $transaction['order_id'] ?? '';

// Extraer el ID del alquiler del order_id (formato: ALQ-{id}-{timestamp})
$parts = explode('-', $order_id);
$id_alquiler = isset($parts[1]) ? (int)$parts[1] : 0;

// Verificar que el usuario está logueado y que se pudo extraer un ID de alquiler válido
if (!isset($_SESSION['usuario_id']) || !$id_alquiler) {
    error_log("Acceso denegado o ID de alquiler inválido. Usuario ID: " . ($_SESSION['usuario_id'] ?? 'N/A') . ", Alquiler ID: " . $id_alquiler);
    header('Location: catalogo.php?error=invalid_access');
    exit();
}

$u_id = $_SESSION['usuario_id'];

// Necesitamos el moto_id para poder actualizar su estado
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

if (!$alquiler) {
    // El alquiler no existe o no pertenece al usuario
    mysqli_stmt_close($stmt_check);
    error_log("Alquiler ID " . $id_alquiler . " no encontrado o no pertenece al usuario " . $u_id);
    mysqli_close($conexion);
    header('Location: catalogo.php?error=alquiler_not_found');
    exit();
}
mysqli_stmt_close($stmt_check);

// Iniciar transacción
mysqli_begin_transaction($conexion);
$transaction_successful = true; // Bandera para controlar el commit/rollback
$mensaje = 'pago=error_procesamiento'; // Mensaje por defecto en caso de fallo

// Procesar según el estado del pago recibido del simulador
switch ($status) {
    case 'approved':
        // Pago aprobado - confirmar alquiler
        $nuevo_estado = 'confirmado';
        $mensaje = 'pago=confirmado';
        break;
    
    case 'rejected':
        // Pago rechazado - se cancela el alquiler
        $nuevo_estado = 'cancelado';
        $mensaje = 'pago=rechazado';
        break;
    
    case 'pending':
        // Pago pendiente - mantener pendiente
        $nuevo_estado = 'pendiente';
        $mensaje = 'pago=pendiente';
        break;
    
    case 'cancelled':
        // Usuario canceló - cancelar alquiler
        $nuevo_estado = 'cancelado';
        $mensaje = 'pago=cancelado';
        break;
    
    default:
        // Error o estado desconocido
        $nuevo_estado = 'error';
        $mensaje = 'pago=error';
        break;
}

$sql_update = "UPDATE alquileres SET estado = ? WHERE id = ?";
$stmt_update = mysqli_prepare($conexion, $sql_update); // Mover la preparación aquí

// Paso 1: Actualizar el estado del alquiler
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

// Paso 2: Si el pago fue aprobado y la actualización del alquiler fue exitosa, actualizar la moto
if ($transaction_successful && $nuevo_estado === 'confirmado') {
    $moto_id = $alquiler['moto_id'];
    $sql_update_moto = "UPDATE motos SET disponible = 0 WHERE id = ?";
    $stmt_moto = mysqli_prepare($conexion, $sql_update_moto);

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

// Finalizar transacción
if ($transaction_successful) {
    mysqli_commit($conexion);
    // Limpiar variables de sesión de pago
    unset($_SESSION['id_pago_pendiente']);
    unset($_SESSION['monto_pago']);
    unset($_SESSION['last_transaction']);
    // Redirigir al perfil con el resultado
    header('Location: perfil_usuario.php?' . $mensaje);
} else {
    mysqli_rollback($conexion);
    // En caso de error, redirigir con un mensaje de error genérico
    header('Location: perfil_usuario.php?pago=error_procesamiento');
}

mysqli_close($conexion);
exit();
?>