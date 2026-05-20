<?php
// Inicia la sesión para acceder a las variables de sesión.
session_start();

// --- 1. CONTROL DE ACCESO ---
// Si un usuario llega a esta página sin haber iniciado un proceso de reserva
// (es decir, sin que exista `id_pago_pendiente` en su sesión), se le redirige al catálogo.
if (!isset($_SESSION['id_pago_pendiente'])) {
    header('Location: catalogo.php');
    exit();
}

// --- 2. RECOLECCIÓN DE DATOS DE LA SESIÓN ---
// Se recupera el monto a pagar y el ID del alquiler desde las variables de sesión.
$monto = $_SESSION['monto_pago'];
$id_alquiler = $_SESSION['id_pago_pendiente'];

// --- 3. PREPARACIÓN DE DATOS PARA LA PASARELA DE PAGO ---
// Se preparan los datos que se enviarán al simulador de la pasarela de pago.
$amount = $monto; // Monto en euros

// Se crea un ID de orden único para esta transacción, combinando el ID del alquiler y una marca de tiempo.
// Esto es útil para el seguimiento y para evitar procesar la misma orden dos veces.
$order_id = 'ALQ-' . $id_alquiler . '-' . time();
$description = 'Alquiler de motocicleta - ID: ' . $id_alquiler;

// Se construye la URL a la que la pasarela de pago debe redirigir al usuario después de completar el pago.
// En este caso, es el script `callback_pago.php` que procesará el resultado.
$return_url = 'http://' . $_SERVER['HTTP_HOST'] . '/perfil_usuario.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Seguro - ARUSLAT</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Estilos personalizados del simulador -->
    <link rel="stylesheet" href="payment/css/bank-style.css">

    <!-- INICIO: Bloque de estilos para adaptar los colores al tema de la aplicación -->
    <!-- Estos estilos "sobrescriben" los estilos por defecto del simulador de pago
         para que la interfaz de la pasarela coincida con el tema oscuro de la aplicación. -->
    <style>
        :root {
            --bg-oscuro: #120907;
            --bg-tarjeta: #1c110e;
            --naranja-principal: #e03e00;
            --texto-blanco: #ffffff;
            --texto-gris: #a0a0a0;
            --borde-tarjeta: #2d1f1b;
        }

        body {
            background-color: var(--bg-oscuro);
            color: var(--texto-blanco); /* Cambiado a blanco para mejor legibilidad */
        }

        .card {
            background-color: var(--bg-tarjeta);
            border: 1px solid var(--borde-tarjeta);
        }

        .card-header.bg-gradient-primary {
            background: var(--bg-oscuro) !important; /* Anula el gradiente */
            border-bottom: 1px solid var(--borde-tarjeta);
        }

        .card-header h1, .card-header .display-4 {
            color: var(--naranja-principal) !important;
        }

        .card-body, .card-body h4 {
            color: var(--texto-blanco);
        }

        .payment-label {
            background-color: transparent;
            border-color: var(--borde-tarjeta);
            color: var(--texto-blanco); /* Cambiado a blanco */
        }
        .payment-label h5 { color: var(--texto-blanco); }
        .payment-label:hover { background-color: #2a1e1a; border-color: #4d3b36; }

        .btn-check:checked + .payment-label {
            background-color: rgba(224, 62, 0, 0.1);
            border-color: var(--naranja-principal);
            box-shadow: 0 5px 20px rgba(224, 62, 0, 0.2);
        }
        /* El texto secundario ahora también es blanco */
        .payment-label .text-muted { color: var(--texto-blanco) !important; opacity: 0.7; }

        .btn-primary { background-color: var(--naranja-principal); border-color: var(--naranja-principal); }
        .btn-primary:hover { background-color: #ff4500; border-color: #ff4500; }
        .btn-outline-secondary { color: var(--texto-gris); border-color: var(--borde-tarjeta); }
        .btn-outline-secondary:hover { background-color: #3d2b26; border-color: #3d2b26; color: var(--texto-blanco); }
    </style>
    <!-- FIN: Bloque de estilos -->
</head>
<body>

    <main class="container mt-4 mt-md-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card shadow-soft border-rounded">
                    <div class="card-header bg-gradient-primary text-white text-center p-4">
                        <h2 class="mb-0">Pasarela de Pago</h2>
                        <p class="mb-0">Estás a punto de pagar</p>
                        <h1 class="display-4 fw-bold my-2"><?php echo number_format($monto, 2); ?>€</h1>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <h4 class="mb-4 text-center">Selecciona tu método de pago</h4>

                        <!-- El formulario envía los datos al script `checkout.php` del simulador de pago. -->
                        <form action="payment/checkout.php" method="POST" id="paymentForm">
                            <input type="hidden" name="amount" value="<?php echo htmlspecialchars(number_format($amount, 2, '.', '')); ?>">
                            <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                            <input type="hidden" name="description" value="<?php echo $description; ?>">
                            <input type="hidden" name="return_url" value="<?php echo $return_url; ?>">
                            <input type="hidden" name="auto_redirect_on_approved" value="true">
                            <input type="hidden" name="redirect_delay_ms" value="3000">

                            <div class="row g-3 mb-4">
                                <!-- Opciones de métodos de pago simulados. Se usan radio buttons. -->
                                <div class="col-6 payment-option">
                                    <input type="radio" class="btn-check" name="payment_method" id="webpay" value="webpay" checked>
                                    <label class="btn btn-outline-secondary w-100 payment-label" for="webpay">
                                        <h5 class="mb-1">Webpay Plus</h5>
                                        <small class="text-muted">Tarjetas de crédito y débito</small>
                                    </label>
                                </div>
                                <div class="col-6 payment-option">
                                    <input type="radio" class="btn-check" name="payment_method" id="mercadopago" value="mercadopago">
                                    <label class="btn btn-outline-secondary w-100 payment-label" for="mercadopago">
                                        <h5 class="mb-1">Mercado Pago</h5>
                                        <small class="text-muted">Varias opciones de pago</small>
                                    </label>
                                </div>
                                <div class="col-6 payment-option">
                                    <input type="radio" class="btn-check" name="payment_method" id="paypal" value="paypal">
                                    <label class="btn btn-outline-secondary w-100 payment-label" for="paypal">
                                        <h5 class="mb-1">PayPal</h5>
                                        <small class="text-muted">Paga con tu cuenta PayPal</small>
                                    </label>
                                </div>
                                <div class="col-6 payment-option">
                                    <input type="radio" class="btn-check" name="payment_method" id="bank_transfer" value="bank_transfer">
                                    <label class="btn btn-outline-secondary w-100 payment-label" for="bank_transfer">
                                        <h5 class="mb-1">Transferencia</h5>
                                        <small class="text-muted">Desde tu banco</small>
                                    </label>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg hover-lift">Proceder al Pago Seguro</button>
                                <a href="catalogo.php" class="btn btn-outline-secondary">Cancelar y volver</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>