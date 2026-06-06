<?php
<<<<<<< HEAD
// Inicio la sesión para poder acceder a las variables de sesión.
session_start();
// Incluye el archivo con las credenciales de la base de datos.
require_once 'loginbd.php';
// Incluyo mi archivo de funciones para poder usar la validación CSRF.
=======
// Inicio la sesión para acceder a las variables de sesión.
session_start();
// Incluye el archivo con las credenciales de la base de datos.
require_once 'loginbd.php';
// Incluyo mis funciones para poder usar la validación CSRF.
>>>>>>> 4f061c50123c453b05ee62e02056c332830aa6a5
require_once 'funciones.php';

// --- 1. CONTROL DE ACCESO ---
// Verifico si el usuario ha iniciado sesión. Si no, lo redirijo al login.
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

// Valido el token CSRF para asegurarme de que la petición viene de mi propio formulario.
validar_csrf_token();

// --- 2. CONEXIÓN A LA BASE DE DATOS ---
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Obtengo el ID del usuario de la sesión.
$usuario_id = $_SESSION['usuario_id'];

// --- 3. RECOLECCIÓN Y VALIDACIÓN DE DATOS DEL FORMULARIO ---
// Recojo las contraseñas que el usuario ha escrito en el formulario.
$current_password = trim($_POST['current_password'] ?? '');
$new_password = trim($_POST['new_password'] ?? '');
$confirm_new_password = trim($_POST['confirm_new_password'] ?? '');

<<<<<<< HEAD
// Primero, compruebo que el usuario haya rellenado todos los campos.
=======
// Primero, compruebo que el usuario ha rellenado todos los campos.
>>>>>>> 4f061c50123c453b05ee62e02056c332830aa6a5
if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
    header('Location: perfil_usuario.php?password_change_error=empty');
    exit();
}

// Segundo, compruebo que la nueva contraseña y su confirmación son iguales.
if ($new_password !== $confirm_new_password) {
    header('Location: perfil_usuario.php?password_change_error=new_mismatch');
    exit();
}

// Tercero, compruebo que la nueva contraseña cumple los requisitos de seguridad que definí
// (mínimo 8 caracteres, con al menos una letra y un número).
if (strlen($new_password) < 8 || !preg_match('/[A-Za-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
    header('Location: perfil_usuario.php?password_change_error=format');
    exit();
}

// --- 4. OBTENCIÓN DE LA CONTRASEÑA ACTUAL DE LA BD ---
// Preparo una consulta para obtener el hash de la contraseña actual del usuario desde la base de datos.
$sql_get_pass = "SELECT password FROM usuarios WHERE id = ?"; // Busco la contraseña (el hash) del usuario en la BD.
$stmt_get = mysqli_prepare($conexion, $sql_get_pass);
mysqli_stmt_bind_param($stmt_get, "i", $usuario_id);
mysqli_stmt_execute($stmt_get);
$resultado = mysqli_stmt_get_result($stmt_get);
$usuario = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt_get);

// Si por alguna razón no encuentro al usuario, lo mando fuera.
if (!$usuario) {
    header('Location: logout.php');
    exit();
}

// Guardo la contraseña hasheada de la BD en una variable para usarla después.
$current_password_hash_db = $usuario['password'];

// --- 5. VERIFICACIÓN DE LA CONTRASEÑA ACTUAL ---
// Uso password_verify() para comparar de forma segura la contraseña que ha escrito el usuario
// con la que tengo guardada (hasheada) en la base de datos.
if (!password_verify($current_password, $current_password_hash_db)) {
    header('Location: perfil_usuario.php?password_change_error=current_mismatch');
    exit();
}

// --- 6. ACTUALIZACIÓN DE LA NUEVA CONTRASEÑA ---
// Si todo lo anterior es correcto, creo un nuevo hash para la nueva contraseña.
$new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

// Y finalmente, actualizo la contraseña en la base de datos con este nuevo hash.
$sql_update_pass = "UPDATE usuarios SET password = ? WHERE id = ?";
$stmt_update = mysqli_prepare($conexion, $sql_update_pass);
mysqli_stmt_bind_param($stmt_update, "si", $new_password_hash, $usuario_id);

// Ejecuto la actualización.
if (mysqli_stmt_execute($stmt_update)) {
    // Si tiene éxito, redirijo al perfil con un mensaje de éxito.
    header('Location: perfil_usuario.php?password_change=success');
} else {
    // Si falla, redirijo con un mensaje de error.
    header('Location: perfil_usuario.php?password_change_error=sql');
}

// Cierro todo para liberar recursos.
mysqli_stmt_close($stmt_update);
mysqli_close($conexion);
?>