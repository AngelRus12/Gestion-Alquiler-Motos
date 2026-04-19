<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

require_once 'loginbd.php';

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
    header('Location: registro.php?error=vacio');
    exit();
}

if ($password !== $password2) {
    header('Location: registro.php?error=password');
    exit();
}

// --- Validación de formato de contraseña ---
if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
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
    // --- Envío de correo con PHPMailer (SMTP Autenticado) ---
    // --- Envío de correo con PHPMailer (SMTP Autenticado) ---
    $mail = new PHPMailer(true);

    try {
        // Desactivamos la depuración para producción. Cambia a 2 para ver logs detallados si vuelve a fallar.
        $mail->SMTPDebug = 0; 

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'angelruslatorre@gmail.com';
        $mail->Password   = 'mwjq bmlz uiiu tdqa'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        // Remitente y Destinatario
        // Es recomendable que el remitente sea la misma cuenta con la que te autenticas.
        $mail->setFrom('angelruslatorre@gmail.com', 'ARUSLAT Soporte');
        $mail->addAddress($email, $nombre_limpio);

        // Contenido del mensaje
        $mail->isHTML(false); 
        $mail->Subject = 'Bienvenido a ARUSLAT - Cuenta Creada';
        $mail->Body    = "Hola " . $nombre_limpio . ",\n\n" .
                         "¡Gracias por registrarte en ARUSLAT! Tu cuenta ha sido creada correctamente.\n\n" .
                         "Ya puedes acceder a nuestro catálogo y reservar la moto que prefieras para tu próxima aventura.\n\n" .
                         "Accede aquí: http://" . $_SERVER['HTTP_HOST'] . "/login.php\n\n" .
                         "Atentamente,\nEl equipo de ARUSLAT.";

        $mail->send();
        
        // Si el correo se envía bien, cerramos y redirigimos
        mysqli_stmt_close($stmt_ins);
        mysqli_close($conexion);
        header('Location: login.php?registro=exitoso');
        exit();

    } catch (Exception $e) {
        // Si el correo falla, el registro es exitoso igualmente, pero informamos al usuario.
        // Redirigimos a la página de login con una advertencia.
        mysqli_stmt_close($stmt_ins);
        mysqli_close($conexion);
        header('Location: login.php?registro=exitoso&email_error=1');
        exit();
    }

} else {
    echo "Error al registrar usuario: " . mysqli_error($conexion);
}

mysqli_close($conexion);
?>