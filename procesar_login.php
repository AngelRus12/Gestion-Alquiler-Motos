<?php
session_start();
require_once 'loginbd.php';
require_once 'funciones.php';

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}

$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($email) || empty($password)) {
    header('Location: login.php?error=vacio');
    exit();
}

// Preparar la consulta
$sql = "SELECT * FROM usuarios WHERE email = ? AND estado = 'activo'";
$stmt = mysqli_prepare($conexion, $sql);

// Vincular el dato real
// "s" significa que el dato es un String
mysqli_stmt_bind_param($stmt, "s", $email);

// --- PASO 3: Ejecutar la consulta ---
mysqli_stmt_execute($stmt);

// Obtener el resultado
$resultado = mysqli_stmt_get_result($stmt);

if ($usuario = mysqli_fetch_assoc($resultado)) {
    if (password_verify($password, $usuario['password'])) {
        
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['rol'] = $usuario['rol']; 
        
        // Cancelar reservas antiguas
        cancelar_reservas_antiguas($conexion);
        
        // Actualizar estados del sistema
        actualizar_sistema_completo($conexion);
        
        if ($usuario['rol'] == 'admin') {
            header('Location: admin_dashboard.php');
        } else {
            header('Location: index.php');
        }
        exit();
    }
}

// Si llega aquí, es que los datos no son válidos
header('Location: login.php?error=credenciales');
mysqli_stmt_close($stmt);
mysqli_close($conexion);
?>
