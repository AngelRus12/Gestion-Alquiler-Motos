<?php
/**
 * Script para cerrar sesión automáticamente
 * Se llama desde JavaScript cuando se cierra la pestaña
 */

session_start();

// Solo procesar si viene de AJAX y es para cerrar por pestaña
if (isset($_POST['close_tab']) || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    // Limpiar todas las variables de sesión
    $_SESSION = array();

    // Destruir la sesión
    session_destroy();

    // Limpiar la cookie de sesión
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    // Responder con éxito
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'Sesión cerrada']);
    exit;
}

// Si no es una petición válida, redirigir
header('Location: index.php');
exit;
?>