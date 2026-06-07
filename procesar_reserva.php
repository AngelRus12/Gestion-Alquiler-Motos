<?php
/**
 * procesar_reserva.php
 * Este es el script que procesa una petición de reserva desde la página de detalle de la moto.
 * - Verifico la sesión del usuario, que las fechas sean válidas y que haya disponibilidad.
 * - Calculo el precio siempre en el servidor para evitar manipulaciones desde el cliente.
 * - Inserto la reserva con estado 'pendiente' y redirijo al usuario según el método de pago que haya elegido.
 */
// --- CONFIGURACIÓN DE LA SESIÓN ---
// Establezco un tiempo de vida de 30 minutos para la sesión.
ini_set('session.gc_maxlifetime', 1800);
session_set_cookie_params(1800);
// Inicio la sesión para poder acceder a las variables de sesión.
session_start();

// Incluyo los archivos de configuración de la BD y mis funciones reutilizables.
require_once 'loginbd.php';
require_once 'funciones.php';

// --- 1. CONTROL DE ACCESO ---
// Verifica si el usuario ha iniciado sesión. Si no, lo redirige al login.
// ¡MEJORA! Ahora, si no ha iniciado sesión, guardo los datos de la reserva
// en la sesión para que no se pierdan y le pido que inicie sesión.
if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['redirect_url'] = 'detalle_moto.php?id=' . (int)$_POST['id_moto'];
    $_SESSION['reserva_temporal'] = [
        'f_inicio' => $_POST['f_inicio'],
        'f_fin' => $_POST['f_fin']
    ];
    header('Location: login.php?error=acceso_denegado');
    exit();
}

// --- 2. CONEXIÓN A LA BASE DE DATOS ---
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
// Establece la codificación de caracteres a UTF-8.
mysqli_set_charset($conexion, "utf8"); // Pongo la codificación a UTF-8 para la conexión.

// --- 3. RECOLECCIÓN Y VALIDACIÓN DE DATOS DEL FORMULARIO ---
// Se recogen los datos del formulario de reserva.
$u_id     = $_SESSION['usuario_id'];
$moto_id  = (int)$_POST['id_moto'];
$f_inicio = trim($_POST['f_inicio']);
$f_fin    = trim($_POST['f_fin']);
$metodo   = trim($_POST['metodo_pago']);

// --- 4. VALIDACIÓN DE FECHAS Y DISPONIBILIDAD ---
$fecha_inicio_ts = strtotime($f_inicio);
$fecha_fin_ts = strtotime($f_fin);
$hoy_ts = strtotime(date('Y-m-d'));

if ($fecha_inicio_ts === false || $fecha_fin_ts === false || $fecha_inicio_ts > $fecha_fin_ts || $fecha_inicio_ts < $hoy_ts) {
    header('Location: detalle_moto.php?id=' . $moto_id . '&error=fecha_invalida');
    exit();
}

if (existe_solapamiento_reserva($conexion, $moto_id, $f_inicio, $f_fin)) {
    header('Location: detalle_moto.php?id=' . $moto_id . '&error=fechas_solapadas');
    exit();
}

// --- 5. CÁLCULO SEGURO DEL PRECIO EN EL SERVIDOR ---
// ¡CRÍTICO! Para mí es fundamental que los cálculos de días y, sobre todo, el precio total, se hagan aquí, en el backend.
// Si confiara en un precio enviado desde el formulario del cliente (frontend), un usuario malintencionado
// podría manipularlo fácilmente para pagar menos. Por eso, aquí recalculo todo de forma segura.

// Calculo los días de alquiler.
$dias = (strtotime($f_fin) - strtotime($f_inicio)) / 86400 + 1;
// Llamo a mi función segura que obtiene el precio/día de la moto desde la BD y calcula el total.
$total = calcular_precio_total($conexion, $moto_id, $f_inicio, $f_fin);

// El estado inicial de cualquier reserva nueva que creo es 'pendiente'.
// Este estado solo cambiará a 'confirmado' después de que el pago se complete con éxito.
$estado = 'pendiente'; 

// --- 5. INSERCIÓN EN LA BASE DE DATOS ---
// Inserto la nueva reserva en la tabla `alquileres` usando una consulta preparada para máxima seguridad.
$sql = "INSERT INTO alquileres (usuario_id, moto_id, fecha_inicio, fecha_fin, dias_alquiler, precio_total, estado) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conexion, $sql);
// Asocio las variables a los marcadores de posición. "iissids" especifica los tipos de datos:
// i (integer), s (string), d (double).
mysqli_stmt_bind_param($stmt, "iissids", $u_id, $moto_id, $f_inicio, $f_fin, $dias, $total, $estado);

// Se ejecuta la inserción.
if (mysqli_stmt_execute($stmt)) {
    // --- 6. REDIRECCIÓN SEGÚN EL MÉTODO DE PAGO ---
    // Si la reserva se guarda correctamente, se decide el siguiente paso según el método de pago elegido.
    if ($metodo == 'web') {
        // Si el pago es online ('web'), guardo el ID del alquiler recién creado y el monto en la sesión.
        // Luego, redirijo al usuario a mi página de la pasarela de pago (`pago.php`).
        $_SESSION['id_pago_pendiente'] = mysqli_insert_id($conexion);
        $_SESSION['monto_pago'] = $total;
        header('Location: pago.php');
    } else {
        // Si el método es otro (ej. 'tienda'), lo redirijo directamente a su perfil.
        header('Location: perfil_usuario.php?reserva=ok');
    }
} else {
    // Si hay un error al guardar en la base de datos, lo redirijo a la página de detalle de la moto con un mensaje de error.
    header('Location: detalle_moto.php?id=' . $moto_id . '&error=reserva');
}

// Cierro la consulta preparada y la conexión.
mysqli_stmt_close($stmt);
mysqli_close($conexion);
?>