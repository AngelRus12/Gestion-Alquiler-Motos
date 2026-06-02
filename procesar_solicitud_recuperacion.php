<?php
/**
 * procesar_solicitud_recuperacion.php
 * Procesa la solicitud de recuperación de contraseña.
 * - Valida el email.
 * - Genera un token seguro y una fecha de expiración.
 * - Guarda el token hasheado en la BD.
 * - Simula el envío de un email con el enlace de recuperación.
 */
// Incluir PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Creado por Composer

require_once 'funciones.php';
start_secure_session();
require_once 'loginbd.php';
validar_csrf_token();

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

if (!$email) {
    header('Location: solicitar_recuperacion.php?error=invalid_email');
    exit();
}

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
if (!$conexion) {
    die("Error de conexión a la base de datos.");
}

// Buscar al usuario por email
$sql_user = "SELECT id FROM usuarios WHERE email = ?";
$stmt_user = mysqli_prepare($conexion, $sql_user);
mysqli_stmt_bind_param($stmt_user, "s", $email);
mysqli_stmt_execute($stmt_user);
$resultado = mysqli_stmt_get_result($stmt_user);
$usuario = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt_user);

if ($usuario) {
    // Generar un token seguro
    $token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $token);

    // Establecer una fecha de expiración (ej. 1 hora)
    $expires = new DateTime('now', new DateTimeZone('Europe/Madrid'));
    $expires->add(new DateInterval('PT1H')); // 1 hora de validez
    $expires_str = $expires->format('Y-m-d H:i:s');

    // Guardar el token hasheado y la expiración en la base de datos
    $sql_update = "UPDATE usuarios SET reset_token = ?, reset_token_expires = ? WHERE id = ?";
    $stmt_update = mysqli_prepare($conexion, $sql_update);
    mysqli_stmt_bind_param($stmt_update, "ssi", $token_hash, $expires_str, $usuario['id']);
    mysqli_stmt_execute($stmt_update);
    mysqli_stmt_close($stmt_update);

    // Construir el enlace de recuperación
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    $reset_link = "{$protocol}://{$host}{$path}/restablecer_password.php?token=" . $token;

    // --- ENVÍO DE CORREO REAL CON PHPMailer ---
    $mail = new PHPMailer(true);

    /*
    // EJEMPLO DE CÓDIGO PARA ENVIAR EMAIL REAL (necesita configuración)
    $subject = 'Recuperación de contraseña - ARUSLAT';
    $message = 'Hola, has solicitado restablecer tu contraseña. Haz clic en el siguiente enlace: ' . $reset_link;
    $headers = 'From: no-reply@aruslat.com';
    mail($email, $subject, $message, $headers);
    */
    try {
        // Configuración del servidor SMTP para x10hosting (o cualquier cPanel)
        // ¡DEBES USAR LAS CREDENCIALES DEL CORREO QUE CREASTE EN CPANEL!
        $mail->isSMTP();
        // En la mayoría de los hostings compartidos como x10hosting, 'localhost' es la opción correcta.
        // Esto se debe a que el script se ejecuta en el mismo servidor que el servicio de correo.
        $mail->Host       = 'localhost';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'info@alquilermotos.x10.mx'; // Tu dirección de correo completa creada en cPanel
        $mail->Password   = '12345678'; // La contraseña de ESA cuenta de correo, no la de cPanel.
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Remitente y destinatario
        $mail->setFrom($mail->Username, 'ARUSLAT Motos'); // Es buena práctica que el remitente sea el mismo que el usuario de autenticación.
        $mail->addAddress($email); // El correo del usuario que solicitó la recuperación

        // Contenido del correo
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Recuperación de contraseña - ARUSLAT';
        $mail->Body    = "
            <h2>Hola,</h2>
            <p>Has solicitado restablecer tu contraseña para tu cuenta en ARUSLAT.</p>
            <p>Para continuar, haz clic en el siguiente enlace. Si no has solicitado esto, puedes ignorar este correo.</p>
            <p><a href='" . htmlspecialchars($reset_link) . "'>Restablecer mi contraseña</a></p>
            <p>El enlace expirará en 1 hora.</p>
            <br>
            <p>Gracias,<br>El equipo de ARUSLAT</p>
        ";
        $mail->AltBody = 'Para restablecer tu contraseña, copia y pega este enlace en tu navegador: ' . htmlspecialchars($reset_link);

        $mail->send();
    } catch (Exception $e) {
        // Si el correo falla, puedes registrar el error pero no debes informar al usuario
        // para no revelar si el email existe o no.
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
    }
}

// Siempre redirigir a la misma página para no revelar si un email existe o no.
header('Location: solicitar_recuperacion.php?status=sent');
mysqli_close($conexion);
exit();