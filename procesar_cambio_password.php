<?php
// Inicia la sesión para acceder a las variables de sesión.
session_start();
// Incluye el archivo con las credenciales de la base de datos.
require_once 'loginbd.php';
// Incluimos las funciones para poder usar la validación CSRF.
require_once 'funciones.php';

// --- 1. CONTROL DE ACCESO ---
// Verifica si el usuario ha iniciado sesión. Si no, lo redirige al login.
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

// --- ¡SEGURIDAD EXTRA! ---
// Validamos el token CSRF. Si no es válido, el script se detiene.
validar_csrf_token();

// --- 2. CONEXIÓN A LA BASE DE DATOS ---
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Se obtiene el ID del usuario de la sesión.
$usuario_id = $_SESSION['usuario_id'];

// --- 3. RECOLECCIÓN Y VALIDACIÓN DE DATOS DEL FORMULARIO ---
// Se recogen las contraseñas del formulario, eliminando espacios en blanco.
$current_password = trim($_POST['current_password'] ?? '');
$new_password = trim($_POST['new_password'] ?? '');
$confirm_new_password = trim($_POST['confirm_new_password'] ?? '');

// Se comprueba que ninguno de los campos esté vacío.
if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
    header('Location: perfil_usuario.php?password_change_error=empty');
    exit();
}

// Se verifica que la nueva contraseña y su confirmación coincidan.
if ($new_password !== $confirm_new_password) {
    header('Location: perfil_usuario.php?password_change_error=new_mismatch');
    exit();
}

// Se valida el formato de la nueva contraseña (mínimo 8 caracteres, con letras y números).
// Es la misma validación que se usa en el formulario de registro para mantener la consistencia.
if (strlen($new_password) < 8 || !preg_match('/[A-Za-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
    header('Location: perfil_usuario.php?password_change_error=format');
    exit();
}

// --- 4. OBTENCIÓN DE LA CONTRASEÑA ACTUAL DE LA BD ---
// Se prepara una consulta para obtener el hash de la contraseña actual del usuario desde la base de datos.
$sql_get_pass = "SELECT password FROM usuarios WHERE id = ?";
$stmt_get = mysqli_prepare($conexion, $sql_get_pass);
mysqli_stmt_bind_param($stmt_get, "i", $usuario_id);
mysqli_stmt_execute($stmt_get);
$resultado = mysqli_stmt_get_result($stmt_get);
$usuario = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt_get);

// Si no se encuentra el usuario (algo muy improbable si ha iniciado sesión), se le desloguea por seguridad.
if (!$usuario) {
    header('Location: logout.php');
    exit();
}

// Se guarda el hash de la contraseña de la BD en una variable.
$current_password_hash_db = $usuario['password'];

// --- 5. VERIFICACIÓN DE LA CONTRASEÑA ACTUAL ---
// Se utiliza `password_verify()` para comparar de forma segura la contraseña en texto plano
// que el usuario ha introducido (`$current_password`) con el hash almacenado en la base de datos.
if (!password_verify($current_password, $current_password_hash_db)) {
    header('Location: perfil_usuario.php?password_change_error=current_mismatch');
    exit();
}

// --- 6. ACTUALIZACIÓN DE LA NUEVA CONTRASEÑA ---
// Si todas las comprobaciones son correctas, se crea un nuevo hash para la nueva contraseña.
$new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

// Se prepara una consulta para actualizar la contraseña en la base de datos con el nuevo hash.
$sql_update_pass = "UPDATE usuarios SET password = ? WHERE id = ?";
$stmt_update = mysqli_prepare($conexion, $sql_update_pass);
mysqli_stmt_bind_param($stmt_update, "si", $new_password_hash, $usuario_id);

// Se ejecuta la actualización.
if (mysqli_stmt_execute($stmt_update)) {
    // Si tiene éxito, se redirige al perfil con un mensaje de éxito.
    header('Location: perfil_usuario.php?password_change=success');
} else {
    // Si falla, se redirige con un mensaje de error.
    header('Location: perfil_usuario.php?password_change_error=sql');
}

// Se cierran la consulta preparada y la conexión.
mysqli_stmt_close($stmt_update);
mysqli_close($conexion);
?>