<?php
session_start();
if (!isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Función para detectar dispositivos móviles
function isMobile() {
    return preg_match("/(android|avantgo|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino)/i", $_SERVER["HTTP_USER_AGENT"]);
}

$is_mobile = isMobile();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Moto - ARUSLAT</title>
    <?php if ($is_mobile): ?>
        <link rel="stylesheet" href="estilos_mobile.css">
    <?php else: ?>
        <link rel="stylesheet" href="estilos.css">
    <?php endif; ?>
</head>
<body>
    <div class="navbar">
        <div class="container">
            <h1>Panel Admin</h1>
            <nav>
                <a href="admin_dashboard">Panel</a>
                <a href="catalogo">Ver Catálogo</a>
            </nav>
            <div class="clear"></div>
        </div>
    </div>

    <div class="container-formulario">
        <h2 class="titulo-admin">Añadir Nueva Moto</h2>
        
        <form action="guardar_moto.php" method="POST">
            
            <div class="form-fila">
                <input type="text" name="marca" placeholder="Marca (Ej: BMW)" required>
                <input type="text" name="modelo" placeholder="Modelo (Ej: S1000RR)" required>
            </div>

            <div class="form-fila">
                <input type="number" name="año" placeholder="Año" required>
                <input type="number" name="cilindrada" placeholder="CC" required>
                <input type="number" name="kilometraje" placeholder="Kilómetros" required>
            </div>

            <div class="form-group">
                <select name="tipo" required>
                    <option value="" disabled selected>Selecciona Tipo de Moto</option>
                    <option value="Deportiva">Deportiva</option>
                    <option value="Naked">Naked</option>
                    <option value="Custom">Custom</option>
                    <option value="Scooter">Scooter</option>
                    <option value="Trail">Trail</option>
                </select>
            </div>

            <div class="form-group">
                <input type="number" step="0.01" name="precio_dia" placeholder="Precio por día (€)" required>
            </div>
            
            <div class="form-group">
                <textarea name="descripcion" placeholder="Descripción breve de la moto..." required></textarea>
            </div>

            <div class="form-group">
                <label>Ruta de la imagen:</label>
                <input type="text" name="ruta_imagen" placeholder="Ej: img/s1000rr.jpg" required>
            </div>
            
            <button type="submit" class="btn btn-block">Registrar Moto</button>
        </form>

        <div class="link-volver">
            <a href="catalogo">← Volver al catálogo</a>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 ARUSLAT - Sistema de Gestión</p>
    </footer>
</body>
</html>