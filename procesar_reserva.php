<?php
// Configurar el tiempo de vida de la sesión (ej. 30 minutos de inactividad)
ini_set('session.gc_maxlifetime', 1800);
session_set_cookie_params(1800);
session_start();

require_once 'loginbd.php';
require_once 'funciones.php'; // Incluir el archivo de funciones

// 1. Seguridad: Verificar que el usuario ha iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
mysqli_set_charset($conexion, "utf8");

// 2. Recoger y validar los datos del formulario
$u_id     = $_SESSION['usuario_id'];
$moto_id  = (int)$_POST['id_moto'];
$f_inicio = trim($_POST['f_inicio']);
$f_fin    = trim($_POST['f_fin']);
$metodo   = trim($_POST['metodo_pago']);

// 3. CÁLCULO SEGURO EN EL SERVIDOR.
// Es fundamental que los cálculos de días y precio total se hagan aquí, en el backend.
// Si se confiara en los datos enviados desde el formulario del cliente, un usuario malintencionado
// podría manipularlos para pagar menos. Aquí, se recalcula todo usando los datos de la BD.
$dias = (strtotime($f_fin) - strtotime($f_inicio)) / 86400 + 1;
$total = calcular_precio_total($conexion, $moto_id, $f_inicio, $f_fin);

// 4. El estado inicial de cualquier reserva es 'pendiente'.
// Cambiará a 'confirmado' solo después de que el pago se complete con éxito.
$estado = 'pendiente'; 

// 5. Insertar la nueva reserva en la base de datos usando una consulta preparada para máxima seguridad.
$sql = "INSERT INTO alquileres (usuario_id, moto_id, fecha_inicio, fecha_fin, dias_alquiler, precio_total, estado) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conexion, $sql);
// Se especifican los tipos de datos: i (integer), s (string), d (double).
mysqli_stmt_bind_param($stmt, "iissids", $u_id, $moto_id, $f_inicio, $f_fin, $dias, $total, $estado);

if (mysqli_stmt_execute($stmt)) {
    // 6. Si la reserva se inserta correctamente, se decide el siguiente paso según el método de pago.
    if ($metodo == 'web') {
        // PAGO ONLINE: Se guarda el ID del alquiler recién creado y el monto total en la sesión del usuario.
        // Luego, se le redirige a la página de la pasarela de pago (pago.php).
        $_SESSION['id_pago_pendiente'] = mysqli_insert_id($conexion);
        $_SESSION['monto_pago'] = $total;
        header('Location: pago.php');
    } else {
        header('Location: perfil_usuario.php?reserva=ok');
    }
} else {
    // Si hay un error en la base de datos, redirigir a la página anterior con un mensaje.
    header('Location: detalle_moto.php?id=' . $moto_id . '&error=reserva');
}

mysqli_stmt_close($stmt);
mysqli_close($conexion);
?>