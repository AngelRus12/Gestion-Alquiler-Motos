<?php
// Inicio la sesión para poder acceder a las variables que guardé en el paso anterior.
session_start();

// --- 1. CONTROL DE ACCESO ---
// Si un usuario llega a esta página sin haber iniciado un proceso de reserva,
// lo que significa que no existe `id_pago_pendiente` en su sesión, lo redirijo al catálogo.
if (!isset($_SESSION['id_pago_pendiente'])) {
    header('Location: catalogo.php');
    exit();
}

// --- 2. RECOLECCIÓN DE DATOS DE LA SESIÓN ---
// Recupero el monto a pagar y el ID del alquiler desde las variables de sesión.
$monto = $_SESSION['monto_pago'];
$id_alquiler = $_SESSION['id_pago_pendiente'];

// --- 3. PREPARACIÓN DE DATOS PARA LA PASARELA DE PAGO ---
// Preparo los datos que voy a enviar a mi simulador de pasarela de pago.
$amount = $monto; // Monto en euros

// Creo un ID de orden único para esta transacción, combinando el ID del alquiler y una marca de tiempo.
// Esto me resulta útil para el seguimiento y para evitar procesar la misma orden dos veces.
$order_id = 'ALQ-' . $id_alquiler . '-' . time();
$description = 'Alquiler de motocicleta - ID: ' . $id_alquiler;

// Se construye la URL a la que la pasarela de pago debe redirigir al usuario después de completar el pago.
// En este caso, es el script `callback_pago.php` que procesará el resultado.
// Se construye la URL de retorno dinámicamente para mayor robustez.
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST']; // Construyo la URL de retorno dinámicamente para que funcione en cualquier servidor.
$return_url = $protocol . '://' . $host . '/perfil_usuario.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="logo.png" type="image/png">
    <link rel="apple-touch-icon" href="logo.png">
    <title>Pago Seguro - ARUSLAT</title>
    <link rel="stylesheet" href="estilos.css">
    <link rel="stylesheet" href="estilos_mobile.css">
</head>
<body class="login-body">

    <div class="pago-container">
        <div class="pago-header">
            <p>Estás a punto de pagar</p>
            <h1 class="monto-valor"><?php echo number_format($monto, 2); ?>€</h1>
            <p class="info-alquiler">Concepto: Alquiler de moto (ID: <?php echo htmlspecialchars($id_alquiler); ?>)</p>
        </div>
        <div class="pago-body">
            <h3 class="titulo-seccion-pago">Selecciona tu método de pago</h3>

            <!-- El formulario envía los datos al script `checkout.php` del simulador de pago. -->
            <form action="payment/checkout.php" method="POST" id="paymentForm">
                <input type="hidden" name="amount" value="<?php echo htmlspecialchars(number_format($amount, 2, '.', '')); ?>">
                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                <input type="hidden" name="description" value="<?php echo $description; ?>">
                <input type="hidden" name="return_url" value="<?php echo $return_url; ?>">
                <input type="hidden" name="auto_redirect_on_approved" value="true">
                <input type="hidden" name="redirect_delay_ms" value="3000">

                <div class="metodo-pago-grid">
                    <!-- Opciones de métodos de pago simulados. Se usan radio buttons. -->
                    <label class="metodo-option-label">
                        <input type="radio" name="payment_method" value="webpay" checked>
                        <div class="metodo-option-content"><span>💳</span> Webpay Plus</div>
                    </label>
                    <label class="metodo-option-label">
                        <input type="radio" name="payment_method" value="paypal">
                        <div class="metodo-option-content"><span>🅿️</span> Paypal</div>
                    </label>
                    <label class="metodo-option-label">
                        <input type="radio" name="payment_method" value="mercadopago">
                        <div class="metodo-option-content"><span>🛒</span> Mercado Pago</div>
                    </label>
                    <label class="metodo-option-label">
                        <input type="radio" name="payment_method" value="bank_transfer">
                        <div class="metodo-option-content"><span>🏦</span> Transferencia</div>
                    </label>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-block">Proceder al Pago Seguro</button>
                </div>
                <div class="form-group text-center btn-cancelar">
                    <a href="perfil_usuario.php?pago=cancelado" class="enlace-discreto">Cancelar y volver</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>