<?php
/**
 * procesar_restablecimiento.php
 * Procesa el formulario de restablecimiento de contraseña.
 * - Valida el token y las nuevas contraseñas.
 * - Hashea la nueva contraseña y la actualiza en la BD.
 * - Invalida el token de recuperación.
 */
require_once 'funciones.php';
start_secure_session();
require_once 'loginbd.php';
validar_csrf_token();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['token'], $_POST['new_password'], $_POST['confirm_password'])) {
    die("Solicitud no válida.");
}

$token = $_POST['token'];
$new_password = $_POST['new_password'];
$confirm_password = $_POST['confirm_password'];

// Validar que las contraseñas coincidan
if ($new_password !== $confirm_password) {
    header('Location: restablecer_password.php?token=' . urlencode($token) . '&error=password_mismatch');
    exit();
}

// Validar formato de la contraseña
if (strlen($new_password) < 8 || !preg_match('/[A-Za-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
    header('Location: restablecer_password.php?token=' . urlencode($token) . '&error=password_format');
    exit();
}

$token_hash = hash('sha256', $token);

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

// Buscar el usuario por el token y verificar que no haya expirado
$sql_user = "SELECT id FROM usuarios WHERE reset_token = ? AND reset_token_expires > NOW()";
$stmt_user = mysqli_prepare($conexion, $sql_user);
mysqli_stmt_bind_param($stmt_user, "s", $token_hash);
mysqli_stmt_execute($stmt_user);
$resultado = mysqli_stmt_get_result($stmt_user);
$usuario = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt_user);

if (!$usuario) {
    die("El enlace de recuperación no es válido o ha expirado. Por favor, solicita uno nuevo.");
}

// Hashear la nueva contraseña
$password_hash = password_hash($new_password, PASSWORD_DEFAULT);

// Actualizar la contraseña y limpiar el token de recuperación
$sql_update = "UPDATE usuarios SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?";
$stmt_update = mysqli_prepare($conexion, $sql_update);
mysqli_stmt_bind_param($stmt_update, "si", $password_hash, $usuario['id']);

if (mysqli_stmt_execute($stmt_update)) {
    // Éxito: redirigir al login con un mensaje de éxito
    header('Location: login.php?reset=success');
} else {
    // Error
    echo "Error al actualizar la contraseña. Por favor, inténtalo de nuevo.";
}

mysqli_stmt_close($stmt_update);
mysqli_close($conexion);
exit();
?>