<?php
/**
 * loginbd.php
 * Mi archivo de configuración para la conexión a la base de datos.
 * - Aquí mantengo centralizadas las credenciales para que el mantenimiento sea más fácil.
 * - Lo incluyo en todas las páginas que necesitan acceder a MySQL.
 */
// Aquí centralizo las credenciales de la base de datos.
// Doy preferencia a las variables de entorno cuando están disponibles, para no dejar las credenciales directamente en el código.
$db_hostname = getenv('DB_HOST') ?: 'localhost';
$db_database = getenv('DB_NAME') ?: 'nntpktdd_alquilermotos';
$db_username = getenv('DB_USER') ?: 'nntpktdd_alquilermotos';
$db_password = getenv('DB_PASS') ?: '12345678';
?>
