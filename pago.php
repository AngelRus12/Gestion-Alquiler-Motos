<?php
session_start();

// Seguridad: Si no hay un pago pendiente, redirigir al catálogo
if (!isset($_SESSION['id_pago_pendiente'])) {
    header('Location: catalogo.php');
    exit();
}

$monto = $_SESSION['monto_pago'];

// Función para detectar dispositivos móviles
function isMobile() {
    return preg_match("/(android|avantgo|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino)/i", $_SERVER["HTTP_USER_AGENT"]);
}

$is_mobile = isMobile();
$id_alquiler = $_SESSION['id_pago_pendiente'];

// Preparar datos para el simulador de pago
$amount = $monto; // Monto en euros
$order_id = 'ALQ-' . $id_alquiler . '-' . time();
$description = 'Alquiler de motocicleta - ID: ' . $id_alquiler;

// URL de retorno después del pago
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
                        <p class="mb-0 opacity-75">Alquiler #<?php echo $id_alquiler; ?></p>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <h4 class="mb-4 text-center">Selecciona tu método de pago</h4>

                        <form action="payment/checkout.php" method="POST" id="paymentForm">
                            <input type="hidden" name="amount" value="<?php echo htmlspecialchars($amount); ?>">
                            <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                            <input type="hidden" name="description" value="<?php echo $description; ?>">
                            <input type="hidden" name="return_url" value="<?php echo $return_url; ?>">
                            <input type="hidden" name="auto_redirect_on_approved" value="true">
                            <input type="hidden" name="redirect_delay_ms" value="3000">

                            <div class="row g-3 mb-4">
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

    <script>
        function selectPayment(method) {
            // La selección ahora es manejada por CSS con :checked
            // No se necesita JavaScript para el efecto visual
        }
    </script>

    <?php if (isset($_SESSION['usuario_id'])): ?>
    <script src="logout_session.js"></script>
    <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>