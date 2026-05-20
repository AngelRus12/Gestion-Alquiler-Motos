<?php
/**
 * =========================================================================================
 * CALLBACK PARA PROCESAR LA RESPUESTA DEL SIMULADOR DE PAGO
 * =========================================================================================
 * 
 * ¿Qué es un Callback?
 * --------------------
 * En el contexto de un pago online, un callback (o webhook) es una URL en nuestro servidor
 * a la que la pasarela de pago (en este caso, nuestro simulador) envía una notificación
 * automática para informarnos del resultado de una transacción (aprobada, rechazada, etc.).
 * 
 * Flujo de trabajo de este archivo:
 * 1. Recibe la notificación del simulador.
 * 2. Realiza múltiples comprobaciones de seguridad para validar la transacción.
 * 3. Inicia una transacción de base de datos para garantizar la integridad de los datos.
 * 4. Actualiza el estado del alquiler y la disponibilidad de la moto.
 * 5. Confirma (commit) o revierte (rollback) los cambios en la base de datos.
 * 6. Redirige al usuario a su perfil con un mensaje de estado.
 */

// --- CONFIGURACIÓN DE LA SESIÓN ---
// Se establece un tiempo de vida de 30 minutos para la sesión.
ini_set('session.gc_maxlifetime', 1800);
session_set_cookie_params(1800);
// Se inicia la sesión para poder acceder a las variables de sesión (como los datos del usuario y la transacción).
session_start();

// Se incluye el archivo con las credenciales de la base de datos.
require_once 'loginbd.php';

// --- CONEXIÓN A LA BASE DE DATOS ---
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

// Se verifica si la conexión a la base de datos fue exitosa.
if (!$conexion) {
    // Es crucial registrar el error para poder depurarlo sin exponer detalles al usuario.
    error_log("Error de conexión a la base de datos en callback_pago.php: " . mysqli_connect_error());
    // Redirigimos al usuario a su perfil con un mensaje de error genérico.
    header('Location: perfil_usuario.php?pago=error_db_conn');
    exit();
}

// --- PASO 1: VERIFICACIÓN DE SEGURIDAD INICIAL ---
// Comprobamos que existan datos de una transacción en la sesión. Si un usuario llega aquí
// directamente sin haber pasado por la pasarela de pago, esta variable no existirá.
if (!isset($_SESSION['last_transaction']) || empty($_SESSION['last_transaction'])) {
    error_log("No se encontraron datos de la última transacción en la sesión.");
    header('Location: catalogo.php?error=no_transaction_data');
    exit();
}

// Recuperamos los datos de la transacción que guardamos en `pago.php` antes de redirigir.
$transaction = $_SESSION['last_transaction'];
// Se usa el operador de fusión de null (??) para asignar 'error' si el estado no está definido.
$status = $transaction['status'] ?? 'error'; // Estado del pago (ej: 'approved', 'rejected')
$order_id = $transaction['order_id'] ?? '';

// --- PASO 2: EXTRACCIÓN DEL ID DEL ALQUILER ---
// El 'order_id' que generamos en `pago.php` tiene el formato "ALQ-ID-TIMESTAMP".
// Aquí, descomponemos esa cadena para obtener el ID del alquiler que necesitamos actualizar.
$parts = explode('-', $order_id);
// Se convierte el ID a entero para mayor seguridad y consistencia.
$id_alquiler = isset($parts[1]) ? (int)$parts[1] : 0;

// --- PASO 3: VERIFICACIÓN DE AUTENTICACIÓN Y DATOS VÁLIDOS ---
// Prevenimos que se procesen pagos si el usuario no ha iniciado sesión o si el ID del alquiler no es válido (0).
if (!isset($_SESSION['usuario_id']) || !$id_alquiler) {
    error_log("Acceso denegado o ID de alquiler inválido. Usuario ID: " . ($_SESSION['usuario_id'] ?? 'N/A') . ", Alquiler ID: " . $id_alquiler);
    header('Location: catalogo.php?error=invalid_access');
    exit();
}
// Se guarda el ID del usuario de la sesión en una variable para facilitar su uso.
$u_id = $_SESSION['usuario_id'];

// --- PASO 4: VERIFICACIÓN DE PROPIEDAD DEL ALQUILER ---
// Esta es una comprobación de seguridad crítica. Nos aseguramos de que el alquiler que se
// intenta procesar realmente pertenece al usuario que ha iniciado sesión. Esto evita que
// un usuario malintencionado pueda manipular la URL para afectar al alquiler de otro.
// También obtenemos el 'moto_id' para usarlo más adelante si el pago se aprueba.
$sql_check = "SELECT id, moto_id FROM alquileres WHERE id = ? AND usuario_id = ?";
$stmt_check = mysqli_prepare($conexion, $sql_check);

// Se verifica si la preparación de la consulta falló.
if (!$stmt_check) {
    error_log("Error al preparar la consulta SQL_CHECK en callback_pago.php: " . mysqli_error($conexion));
    mysqli_close($conexion);
    header('Location: perfil_usuario.php?pago=error_sql_prep');
    exit();
}
// Se asocian las variables a los parámetros de la consulta. "ii" significa que ambos son enteros.
mysqli_stmt_bind_param($stmt_check, "ii", $id_alquiler, $u_id);
mysqli_stmt_execute($stmt_check);
$result = mysqli_stmt_get_result($stmt_check);
$alquiler = mysqli_fetch_assoc($result);

// Si la consulta no devuelve ninguna fila, el alquiler no existe o no pertenece a este usuario.
if (!$alquiler) {
    mysqli_stmt_close($stmt_check);
    error_log("Alquiler ID " . $id_alquiler . " no encontrado o no pertenece al usuario " . $u_id);
    mysqli_close($conexion);
    header('Location: catalogo.php?error=alquiler_not_found');
    exit();
}
// Se cierra la consulta preparada para liberar recursos.
mysqli_stmt_close($stmt_check);

// --- PASO 5: INICIO DE LA TRANSACCIÓN DE BASE DE DATOS ---
// Esto es CRÍTICO para la integridad de los datos. Una transacción asegura que un grupo de
// operaciones de base de datos (en nuestro caso, 2 `UPDATE`) se traten como una sola unidad.
// O TODAS se ejecutan con éxito, o NINGUNA lo hace.
// Esto evita inconsistencias, como tener un alquiler 'confirmado' pero que la moto siga
// apareciendo como 'disponible' porque la segunda consulta falló.
mysqli_begin_transaction($conexion);
$transaction_successful = true; // Bandera para controlar el éxito de la transacción.
$mensaje = 'pago=error_procesamiento'; // Mensaje de redirección por defecto en caso de fallo.

// --- PASO 6: DETERMINAR EL NUEVO ESTADO DEL ALQUILER ---
// Según el estado del pago que nos envió el simulador ('approved', 'rejected', etc.),
// decidimos qué estado debe tener el alquiler en nuestra base de datos y qué mensaje
// mostraremos al usuario.
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
        // Si el estado es desconocido o hay un error, se marca como 'error'.
        $nuevo_estado = 'error';
        $mensaje = 'pago=error';
        break;
}

// --- PASO 7: ACTUALIZAR EL ESTADO DEL ALQUILER (1ª operación de la transacción) ---
$sql_update = "UPDATE alquileres SET estado = ? WHERE id = ?";
$stmt_update = mysqli_prepare($conexion, $sql_update);

// Se comprueba si la preparación de la consulta falló.
if (!$stmt_update) {
    error_log("Error al preparar la consulta SQL_UPDATE en callback_pago.php: " . mysqli_error($conexion));
    // Si falla, se marca la transacción como no exitosa.
    $transaction_successful = false;
} else {
    // Se asocian los parámetros. "si" significa string e integer.
    mysqli_stmt_bind_param($stmt_update, "si", $nuevo_estado, $id_alquiler);
    // Se ejecuta la consulta y se comprueba si hubo un error.
    if (!mysqli_stmt_execute($stmt_update)) {
        error_log("Error al ejecutar SQL_UPDATE en callback_pago.php: " . mysqli_stmt_error($stmt_update));
        $transaction_successful = false;
    }
    // Se cierra la consulta preparada.
    mysqli_stmt_close($stmt_update);
}

// --- PASO 8: ACTUALIZAR DISPONIBILIDAD DE LA MOTO (2ª operación de la transacción) ---
// Esta operación solo se ejecuta si el pago fue aprobado (`$nuevo_estado === 'confirmado'`)
// y la operación anterior fue exitosa (`$transaction_successful` es true).
if ($transaction_successful && $nuevo_estado === 'confirmado') {
    $moto_id = $alquiler['moto_id'];
    $sql_update_moto = "UPDATE motos SET disponible = 0 WHERE id = ?";
    $stmt_moto = mysqli_prepare($conexion, $sql_update_moto);

    // Misma lógica de comprobación de errores que en el paso anterior.
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
    // Si todas las operaciones fueron exitosas, confirmamos los cambios permanentemente en la base de datos.
    mysqli_commit($conexion);
    
    // Se limpian las variables de sesión relacionadas con el pago para evitar que se pueda
    // reprocesar accidentalmente si el usuario recarga la página o vuelve atrás.
    unset($_SESSION['id_pago_pendiente']);
    unset($_SESSION['monto_pago']);
    unset($_SESSION['last_transaction']);
    
    // Se redirige al perfil del usuario con el mensaje de estado correspondiente ('pago=confirmado', 'pago=rechazado', etc.).
    header('Location: perfil_usuario.php?' . $mensaje);
} else {
    // Si alguna operación falló, se revierten TODOS los cambios hechos durante la transacción.
    // La base de datos vuelve al estado en que estaba antes de `mysqli_begin_transaction`.
    mysqli_rollback($conexion);
    // Se redirige al usuario con un mensaje de error genérico.
    header('Location: perfil_usuario.php?pago=error_procesamiento');
}

// --- PASO 10: CIERRE DE CONEXIÓN ---
// Se cierra la conexión a la base de datos para liberar recursos.
mysqli_close($conexion);
// Se finaliza la ejecución del script.
exit();
?>