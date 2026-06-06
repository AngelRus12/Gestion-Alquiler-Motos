<?php
/**
 * logout.php
 * Cierra la sesión del usuario y redirige al inicio.
 * - Elimina todas las variables de sesión y termina la sesión.
 */
session_start();

session_unset();

session_destroy();

header('Location: index.php');
exit();
?>
