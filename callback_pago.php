<?php
/**
 * Callback para procesar respuesta del simulador de pago
 * 
 * Este archivo recibe la respuesta del simulador de pago y actualiza
 * el estado del alquiler en la base de datos según el resultado.
 */

// Configurar el tiempo de vida de la sesión (ej. 30 minutos de inactividad)
ini_set('session.gc_maxlifetime', 1800);
session_set_cookie_params(1800);
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

// 1. Seguridad: Verificar que tenemos datos de la transacción en la sesión.
if (!isset($_SESSION['last_transaction']) || empty($_SESSION['last_transaction'])) {
    error_log("No se encontraron datos de la última transacción en la sesión.");
    header('Location: catalogo.php?error=no_transaction_data');
    exit();
}

$transaction = $_SESSION['last_transaction'];
$status = $transaction['status'] ?? 'error'; // Default a 'error' si no hay status
$order_id = $transaction['order_id'] ?? '';

// 2. El 'order_id' que generamos tiene el formato "ALQ-ID-TIMESTAMP". Aquí extraemos el ID del alquiler.
$parts = explode('-', $order_id);
$id_alquiler = isset($parts[1]) ? (int)$parts[1] : 0;

// 3. Seguridad: Verificar que el usuario está logueado y que el ID del alquiler es válido.
// Esto previene que se procesen pagos sin un alquiler asociado o de usuarios no autenticados.
if (!isset($_SESSION['usuario_id']) || !$id_alquiler) {
    error_log("Acceso denegado o ID de alquiler inválido. Usuario ID: " . ($_SESSION['usuario_id'] ?? 'N/A') . ", Alquiler ID: " . $id_alquiler);
    header('Location: catalogo.php?error=invalid_access');
    exit();
}

$u_id = $_SESSION['usuario_id'];

// 4. Seguridad: Verificar que el alquiler pertenece al usuario que está en la sesión.
// También obtenemos el 'moto_id' que necesitaremos más adelante.
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
    // Si no se encuentra, el alquiler no existe o no pertenece a este usuario.
    mysqli_stmt_close($stmt_check);
    error_log("Alquiler ID " . $id_alquiler . " no encontrado o no pertenece al usuario " . $u_id);
    mysqli_close($conexion);
    header('Location: catalogo.php?error=alquiler_not_found');
    exit();
}
mysqli_stmt_close($stmt_check);

// 5. INICIAR TRANSACCIÓN DE BASE DE DATOS.
// Esto es CRÍTICO. Asegura que todas las operaciones (actualizar alquiler, actualizar moto)
// se completen con éxito. Si alguna falla, se revierten todos los cambios (rollback).
// Esto evita inconsistencias, como un alquiler confirmado pero con la moto aún disponible.
mysqli_begin_transaction($conexion);
$transaction_successful = true; // Bandera para controlar el commit/rollback
$mensaje = 'pago=error_procesamiento'; // Mensaje por defecto en caso de fallo

// 6. Procesar según el estado del pago recibido del simulador ('approved', 'rejected', etc.).
// Se determina el nuevo estado que tendrá el alquiler en la base de datos.
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

// 7. Paso 1 de la transacción: Actualizar el estado del alquiler.
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

// 8. Paso 2 de la transacción: Si el pago fue aprobado y el alquiler se actualizó bien,
//    procedemos a marcar la moto como "no disponible".
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

// 9. Finalizar la transacción.
if ($transaction_successful) {
    // Si todo fue exitoso, se confirman los cambios en la base de datos.
    mysqli_commit($conexion);
    // Limpiar las variables de sesión relacionadas con el pago para evitar reprocesamientos.
    unset($_SESSION['id_pago_pendiente']);
    unset($_SESSION['monto_pago']);
    unset($_SESSION['last_transaction']);
    // Redirigir al perfil del usuario con un mensaje de éxito.
    header('Location: perfil_usuario.php?' . $mensaje);
} else {
    // Si algo falló, se revierten todos los cambios hechos durante la transacción.
    mysqli_rollback($conexion);
    // Redirigir con un mensaje de error genérico.
    header('Location: perfil_usuario.php?pago=error_procesamiento');
}

mysqli_close($conexion);
exit();
?>