<?php
// --- CONFIGURACIÓN DE LA SESIÓN ---
// Se establece un tiempo de vida de 30 minutos para la sesión.
ini_set('session.gc_maxlifetime', 1800);
session_set_cookie_params(1800);
// Se inicia la sesión para poder acceder a las variables de sesión.
session_start();

// Se incluyen los archivos de configuración de la BD y de funciones reutilizables.
require_once 'loginbd.php';
require_once 'funciones.php';

// --- 1. CONTROL DE ACCESO ---
// Verifica si el usuario ha iniciado sesión. Si no, lo redirige al login.
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

// --- 2. CONEXIÓN A LA BASE DE DATOS ---
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
// Establece la codificación de caracteres a UTF-8.
mysqli_set_charset($conexion, "utf8");

// --- 3. RECOLECCIÓN Y VALIDACIÓN DE DATOS DEL FORMULARIO ---
// Se recogen los datos del formulario de reserva.
$u_id     = $_SESSION['usuario_id'];
$moto_id  = (int)$_POST['id_moto'];
$f_inicio = trim($_POST['f_inicio']);
$f_fin    = trim($_POST['f_fin']);
$metodo   = trim($_POST['metodo_pago']);

// --- 4. CÁLCULO SEGURO DEL PRECIO EN EL SERVIDOR ---
// CRÍTICO: Es fundamental que los cálculos de días y, sobre todo, el precio total, se hagan aquí, en el backend.
// Si se confiara en un precio enviado desde el formulario del cliente (frontend), un usuario malintencionado
// podría manipularlo fácilmente para pagar menos. Aquí, se recalcula todo de forma segura.

// Se calculan los días de alquiler.
$dias = (strtotime($f_fin) - strtotime($f_inicio)) / 86400 + 1;
// Se llama a una función segura que obtiene el precio/día de la moto desde la BD y calcula el total.
$total = calcular_precio_total($conexion, $moto_id, $f_inicio, $f_fin);

// El estado inicial de cualquier reserva nueva es 'pendiente'.
// Este estado cambiará a 'confirmado' solo después de que el pago se complete con éxito.
$estado = 'pendiente'; 

// --- 5. INSERCIÓN EN LA BASE DE DATOS ---
// Se inserta la nueva reserva en la tabla `alquileres` usando una consulta preparada para máxima seguridad.
$sql = "INSERT INTO alquileres (usuario_id, moto_id, fecha_inicio, fecha_fin, dias_alquiler, precio_total, estado) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conexion, $sql);
// Se asocian las variables a los marcadores de posición. "iissids" especifica los tipos de datos:
// i (integer), s (string), d (double).
mysqli_stmt_bind_param($stmt, "iissids", $u_id, $moto_id, $f_inicio, $f_fin, $dias, $total, $estado);

// Se ejecuta la inserción.
if (mysqli_stmt_execute($stmt)) {
    // --- 6. REDIRECCIÓN SEGÚN EL MÉTODO DE PAGO ---
    // Si la reserva se guarda correctamente, se decide el siguiente paso según el método de pago elegido.
    if ($metodo == 'web') {
        // Si el pago es online ('web'), se guarda el ID del alquiler recién creado y el monto en la sesión.
        // Luego, se redirige al usuario a la página de la pasarela de pago (`pago.php`).
        $_SESSION['id_pago_pendiente'] = mysqli_insert_id($conexion);
        $_SESSION['monto_pago'] = $total;
        header('Location: pago.php');
    } else {
        // Si el método es otro (ej. 'tienda'), se redirige directamente al perfil del usuario.
        header('Location: perfil_usuario.php?reserva=ok');
    }
} else {
    // Si hay un error al guardar en la base de datos, se redirige a la página de detalle de la moto con un error.
    header('Location: detalle_moto.php?id=' . $moto_id . '&error=reserva');
}

// Se cierran la consulta preparada y la conexión.
mysqli_stmt_close($stmt);
mysqli_close($conexion);
?>