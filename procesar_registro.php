<?php
/**
 * procesar_registro.php
 * Script que procesa el formulario de registro de usuario.
 * - Valida contraseña, DNI, email y campos obligatorios.
 * - Comprueba unicidad de email y DNI en la base de datos.
 * - Hashea la contraseña antes de guardarla.
 */
require_once 'funciones.php';
start_secure_session();
require_once 'loginbd.php';
validar_csrf_token();

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}

$nombre_limpio    = trim($_POST['nombre'] ?? '');
$apellidos_limpios = trim($_POST['apellidos'] ?? '');
$email            = trim($_POST['email'] ?? '');
$dni              = trim($_POST['dni'] ?? '');
$telefono         = trim($_POST['telefono'] ?? '');
$direccion        = trim($_POST['direccion'] ?? '');
$password         = trim($_POST['password'] ?? '');
$password2        = trim($_POST['password2'] ?? '');

if (empty($nombre_limpio) || empty($apellidos_limpios) || empty($email) || empty($dni) || empty($telefono) || empty($password) || empty($password2)) {
    $_SESSION['form_data'] = $_POST;
    unset($_SESSION['form_data']['password'], $_SESSION['form_data']['password2']);
    header('Location: registro.php?error=vacio');
    exit();
}

if ($password !== $password2) {
    $_SESSION['form_data'] = $_POST;
    unset($_SESSION['form_data']['password'], $_SESSION['form_data']['password2']);
    header('Location: registro.php?error=password');
    exit();
}

// --- Validación de formato de contraseña ---
if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
    $_SESSION['form_data'] = $_POST;
    unset($_SESSION['form_data']['password'], $_SESSION['form_data']['password2']);
    header('Location: registro.php?error=password_formato');
    exit();
}

// --- Validación de DNI (formato y letra) ---
function es_dni_valido($dni) {
    $dni = strtoupper(trim($dni));
    // 1. Comprobar formato (8 números y 1 letra)
    if (!preg_match('/^[0-9]{8}[A-Z]$/', $dni)) {
        return false;
    }
    // 2. Comprobar que la letra es correcta
    $letra = substr($dni, -1);
    $numeros = substr($dni, 0, -1);
    return substr("TRWAGMYFPDXBNJZSQVHLCKE", $numeros % 23, 1) === $letra;
}
if (!es_dni_valido($dni)) {
    $_SESSION['form_data'] = $_POST;
    unset($_SESSION['form_data']['password'], $_SESSION['form_data']['password2']);
    header('Location: registro.php?error=dni_invalido');
    exit();
}

// Comprobar si el email ya existe usando Consultas Preparadas
$sql_email = "SELECT id FROM usuarios WHERE email = ?";
$stmt_email = mysqli_prepare($conexion, $sql_email);
mysqli_stmt_bind_param($stmt_email, "s", $email);
mysqli_stmt_execute($stmt_email);
mysqli_stmt_store_result($stmt_email);

if (mysqli_stmt_num_rows($stmt_email) > 0) {
    mysqli_stmt_close($stmt_email);
    $_SESSION['form_data'] = $_POST;
    unset($_SESSION['form_data']['password'], $_SESSION['form_data']['password2']);
    header('Location: registro.php?error=email_existe');
    exit();
}
mysqli_stmt_close($stmt_email);

// Comprobar si el DNI ya existe
$sql_dni = "SELECT id FROM usuarios WHERE dni = ?";
$stmt_dni = mysqli_prepare($conexion, $sql_dni);
mysqli_stmt_bind_param($stmt_dni, "s", $dni);
mysqli_stmt_execute($stmt_dni);
mysqli_stmt_store_result($stmt_dni);

if (mysqli_stmt_num_rows($stmt_dni) > 0) {
    mysqli_stmt_close($stmt_dni);
    $_SESSION['form_data'] = $_POST;
    unset($_SESSION['form_data']['password'], $_SESSION['form_data']['password2']);
    header('Location: registro.php?error=dni_existe');
    exit();
}
mysqli_stmt_close($stmt_dni);

$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Insertar usuario con consulta preparada
$insertar = "INSERT INTO usuarios (nombre, apellidos, email, password, telefono, dni, direccion, rol, estado) 
             VALUES (?, ?, ?, ?, ?, ?, ?, 'cliente', 'activo')";

$stmt_ins = mysqli_prepare($conexion, $insertar);
mysqli_stmt_bind_param($stmt_ins, "sssssss", $nombre_limpio, $apellidos_limpios, $email, $password_hash, $telefono, $dni, $direccion);
if (mysqli_stmt_execute($stmt_ins)) {
    unset($_SESSION['form_data']);
    // Redirigir al usuario a la página de login con un mensaje de éxito.
    header('Location: login.php?registro=exitoso');
    exit(); // Finalizar el script para asegurar la redirección.
} else {
    $_SESSION['form_data'] = $_POST;
    unset($_SESSION['form_data']['password'], $_SESSION['form_data']['password2']);
    header('Location: registro.php?error=sql');
}
mysqli_stmt_close($stmt_ins);
mysqli_close($conexion);