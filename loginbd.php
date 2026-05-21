<?php
/**
 * loginbd.php
 * Configuración de conexión a la base de datos.
 * - Mantiene centralizadas las credenciales para facilitar el mantenimiento.
 * - Se usa en todas las páginas que requieren acceso a MySQL.
 */
// Este archivo centraliza las credenciales de la base de datos.
// Se prefiere la variable de entorno cuando está disponible, para no dejar credenciales en el código.
$db_hostname = getenv('DB_HOST') ?: 'localhost';
$db_database = getenv('DB_NAME') ?: 'nntpktdd_alquilermotos';
$db_username = getenv('DB_USER') ?: 'nntpktdd_alquilermotos';
$db_password = getenv('DB_PASS') ?: '12345678';
?>

