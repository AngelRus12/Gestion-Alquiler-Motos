<?php
session_start();
require_once 'loginbd.php';

// 1. Seguridad: Verificar que el usuario ha iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}

$usuario_id = $_SESSION['usuario_id'];

// 2. Recoger y validar los datos del formulario
$current_password = trim($_POST['current_password'] ?? '');
$new_password = trim($_POST['new_password'] ?? '');
$confirm_new_password = trim($_POST['confirm_new_password'] ?? '');

if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
    header('Location: perfil_usuario.php?password_change_error=empty');
    exit();
}

// 3. Verificar que la nueva contraseña y su confirmación coinciden
if ($new_password !== $confirm_new_password) {
    header('Location: perfil_usuario.php?password_change_error=new_mismatch');
    exit();
}

// 4. Validación de formato de la nueva contraseña (como en el registro)
if (strlen($new_password) < 8 || !preg_match('/[A-Za-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
    header('Location: perfil_usuario.php?password_change_error=format');
    exit();
}

// 5. Obtener la contraseña actual del usuario desde la BD
$sql_get_pass = "SELECT password FROM usuarios WHERE id = ?";
$stmt_get = mysqli_prepare($conexion, $sql_get_pass);
mysqli_stmt_bind_param($stmt_get, "i", $usuario_id);
mysqli_stmt_execute($stmt_get);
$resultado = mysqli_stmt_get_result($stmt_get);
$usuario = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt_get);

if (!$usuario) {
    // Error inesperado, el usuario no se encontró
    header('Location: logout.php');
    exit();
}

$current_password_hash_db = $usuario['password'];

// 6. Verificar que la contraseña actual proporcionada es correcta
if (!password_verify($current_password, $current_password_hash_db)) {
    header('Location: perfil_usuario.php?password_change_error=current_mismatch');
    exit();
}

// 7. Si todo es correcto, hashear y actualizar la nueva contraseña
$new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

$sql_update_pass = "UPDATE usuarios SET password = ? WHERE id = ?";
$stmt_update = mysqli_prepare($conexion, $sql_update_pass);
mysqli_stmt_bind_param($stmt_update, "si", $new_password_hash, $usuario_id);

if (mysqli_stmt_execute($stmt_update)) {
    header('Location: perfil_usuario.php?password_change=success');
} else {
    header('Location: perfil_usuario.php?password_change_error=sql');
}

mysqli_stmt_close($stmt_update);
mysqli_close($conexion);
?>