<?php

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
    // Redirigir inmediatamente al usuario para mejorar la experiencia y evitar timeouts.
    header('Location: login.php?registro=success');
    
    // Asegurarse de que el script siga ejecutándose para enviar el correo en segundo plano.
    ignore_user_abort(true);
    set_time_limit(30); // Darle 30 segundos al script para enviar el correo.

    // --- INICIO: Enviar correo de bienvenida con PHPMailer ---
    require 'PHPMailer/src/Exception.php';
    require 'PHPMailer/src/PHPMailer.php';
    require 'PHPMailer/src/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Configuración del servidor SMTP de x10hosting
        $mail->isSMTP();
        $mail->Host       = 'mail.alquilermotos.x10.mx'; // Servidor SMTP
        $mail->SMTPAuth   = true;
        $mail->Username   = 'info@alquilermotos.x10.mx'; // Tu correo completo
        $mail->Password   = '12345678';                  // La contraseña de tu correo
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; // O 'ssl'
        $mail->Port       = 587;                         // Puerto para TLS (o 465 para SSL)

        // Remitente y destinatarios
        $mail->setFrom('info@alquilermotos.x10.mx', 'ARUSLAT');
        $mail->addAddress($email, $nombre_limpio); // Añadir destinatario

        // Contenido del correo
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = '¡Bienvenido a ARUSLAT!';
        $mail->Body    = "
        <html>
        <body style='font-family: Arial, sans-serif; color: #333;'>
            <h2>¡Hola " . htmlspecialchars($nombre_limpio) . "!</h2>
            <p>Tu cuenta en ARUSLAT ha sido creada exitosamente.</p>
            <p>Aquí tienes tus datos para iniciar sesión:</p>
            <p><strong>Usuario:</strong> " . htmlspecialchars($email) . "<br>
            <strong>Contraseña:</strong> " . htmlspecialchars($password) . "</p>
            <p>Puedes iniciar sesión en nuestro sitio web: <a href='http://" . $_SERVER['HTTP_HOST'] . "/login.php'>Iniciar Sesión</a></p>
            <p>Gracias por unirte a nosotros.</p>
        </body>
        </html>";
        $mail->AltBody = "¡Hola " . $nombre_limpio . "! Tu cuenta en ARUSLAT ha sido creada. Usuario: " . $email . " Contraseña: " . $password;

        $mail->send();
    } catch (Exception $e) {
        // No detenemos el registro si el email falla, pero podríamos guardarlo en un log
        // error_log("El mensaje no se pudo enviar. Mailer Error: {$mail->ErrorInfo}");
    }
    // --- FIN: Enviar correo de bienvenida con PHPMailer ---

} else {
    header('Location: registro.php?error=sql');
}
mysqli_stmt_close($stmt_ins);
mysqli_close($conexion);


mysqli_close($conexion);
?>