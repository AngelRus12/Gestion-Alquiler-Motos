<?php
/**
 * logout.php
 * Cierra la sesión del usuario y redirige al inicio.
 * - Elimina todas las variables de sesión y termina la sesión.
 */
// Como buena práctica, inicio la sesión para asegurarme de que estoy destruyendo la sesión correcta.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

session_unset();

session_destroy();

header('Location: index.php');
exit();
?>
