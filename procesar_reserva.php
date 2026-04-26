<?php
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

// 3. Calcular días y precio total en el servidor para mayor seguridad
// Se usa la función para un cálculo seguro en el servidor
$dias = (strtotime($f_fin) - strtotime($f_inicio)) / 86400 + 1;
$total = calcular_precio_total($conexion, $moto_id, $f_inicio, $f_fin);

// El estado inicial de una reserva siempre es 'pendiente' hasta que se procesa el pago
$estado = 'pendiente'; 

// 4. Usar consultas preparadas para insertar la reserva de forma segura
$sql = "INSERT INTO alquileres (usuario_id, moto_id, fecha_inicio, fecha_fin, dias_alquiler, precio_total, estado) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conexion, $sql);
// i: integer, s: string, d: double
mysqli_stmt_bind_param($stmt, "iissids", $u_id, $moto_id, $f_inicio, $f_fin, $dias, $total, $estado);

if (mysqli_stmt_execute($stmt)) {
    // Si la reserva se inserta correctamente, procedemos según el método de pago
    if ($metodo == 'web') {
        // Si el pago es online, guardamos el ID del nuevo alquiler y el monto en sesión
        // y redirigimos a la pasarela de pago.
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