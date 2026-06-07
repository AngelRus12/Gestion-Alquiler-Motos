<?php
/**
 * logout.php
 * Este script cierra la sesión del usuario y lo redirige al inicio.
 * - Elimino todas las variables de sesión y destruyo la sesión por completo.
 */
session_start();

session_unset();

session_destroy();

header('Location: index.php');
exit();
?>
