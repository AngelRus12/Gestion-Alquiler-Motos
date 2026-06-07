<?php
/**
 * procesar_login.php
 * Este es mi script que procesa el formulario de inicio de sesión.
 * - Uso consultas preparadas para buscar al usuario de forma segura.
 * - Verifico la contraseña con `password_verify()` para compararla con el hash de la BD.
 * - Redirijo al usuario a su panel correspondiente según su rol (admin o cliente).
 */
require_once 'funciones.php';
start_secure_session();
require_once 'loginbd.php';
validar_csrf_token();

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

// Preparo la consulta para buscar al usuario por su email y asegurarme de que su cuenta esté activa.
$sql = "SELECT * FROM usuarios WHERE email = ? AND estado = 'activo'";
$stmt = mysqli_prepare($conexion, $sql);

// Vinculo el dato real (el email).
// "s" significa que el dato es un String
mysqli_stmt_bind_param($stmt, "s", $email);

// Ejecuto la consulta.
mysqli_stmt_execute($stmt);

// Obtener el resultado
$resultado = mysqli_stmt_get_result($stmt);

if ($usuario = mysqli_fetch_assoc($resultado)) {
    if (password_verify($password, $usuario['password'])) {
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['rol'] = $usuario['rol']; 
        
        if ($usuario['rol'] == 'admin') {
            header('Location: admin_dashboard.php');
        } else {
            header('Location: index.php');
        }
        exit();
    }
}

// Si el código llega hasta aquí, es que los datos no eran válidos.
header('Location: login.php?error=credenciales');
mysqli_stmt_close($stmt);
mysqli_close($conexion);
?>
