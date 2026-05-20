<?php
/**
 * SIMULADOR BANCARIO - Página Principal
 * 
 * Esta es la página de ejemplo que simula ser tu e-commerce o aplicación.
 * Aquí es desde donde iniciarías un pago en tu proyecto real.
 * 
 * Medios de pago soportados:
 * - Webpay Plus (Transbank Chile)
 * - Mercado Pago
 * - PayPal
 */
session_start();

// Procesar datos POST si vienen desde la aplicación (simulando checkout.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'])) {
    // Mostrar página de redirección automática a simulator.php
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Redirigiendo al Portal de Pago...</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="css/bank-style.css">
    </head>
    <body class="page-redirect-body" onload="document.getElementById('redirect_form').submit();">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card shadow-lg">
                        <div class="card-body text-center p-5">
                            <div class="loader mx-auto mb-4"></div>
                            <h4 class="mb-3">Redirigiendo al Portal de Pago Seguro</h4>
                            <p class="text-muted mb-4">
                                <i class="bi bi-shield-lock-fill text-success"></i>
                                Conexión segura establecida
                            </p>
                            <p class="small text-muted">
                                No cierres esta ventana. Serás redirigido automáticamente...
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulario automático de redirección -->
        <form id="redirect_form" action="simulator.php" method="POST" class="d-none">
            <?php foreach ($_POST as $key => $value): ?>
                <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
            <?php endforeach; ?>
        </form>

        <script>
            // Timeout de seguridad por si no se envía automáticamente
            setTimeout(function() {
                document.getElementById('redirect_form').submit();
            }, 2000);
        </script>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulador de Pagos - Demo E-commerce</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/bank-style.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-shop"></i> Mi Tienda Demo
            </a>
            <span class="navbar-text text-white">
                <i class="bi bi-cart3"></i> Simulador de Pagos
            </span>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Alerta informativa -->
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="bi bi-info-circle-fill"></i>
                    <strong>Modo Simulación:</strong> Este es un simulador para desarrollo. Puedes probar diferentes respuestas de pago sin contratar servicios reales.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>

                <!-- Resumen de Compra -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-cart-check"></i> Resumen de Compra</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h6>Producto de Ejemplo</h6>
                                <p class="text-muted mb-0">Curso de Desarrollo Web Completo</p>
                                <small class="text-muted">SKU: WEB-2024-001</small>
                            </div>
                            <div class="col-md-4 text-end">
                                <h4 class="text-primary mb-0">$49.990</h4>
                                <small class="text-muted">EUR</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Selección de Método de Pago -->
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-credit-card"></i> Selecciona tu Método de Pago</h5>
                    </div>
                    <div class="card-body">
                        <form action="checkout.php" method="POST" id="paymentForm">
                            <!-- Monto (en tu app real vendría del carrito) -->
                            <input type="hidden" name="amount" value="49990">
                            <input type="hidden" name="order_id" value="ORD-<?php echo time(); ?>">
                            <input type="hidden" name="description" value="Curso de Desarrollo Web Completo">
                            
                            <div class="payment-methods">
                                <!-- Webpay Plus (Transbank) -->
                                <div class="payment-option mb-3">
                                    <input type="radio" class="btn-check" name="payment_method" id="webpay" value="webpay" checked>
                                    <label class="btn btn-outline-primary w-100 text-start payment-label" for="webpay">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <i class="bi bi-credit-card-2-front fs-4"></i>
                                                <strong class="ms-2">Webpay Plus</strong>
                                                <br>
                                                <small class="text-muted ms-5">Transbank Chile - Tarjetas de crédito y débito</small>
                                            </div>
                                            <img src="/img/webpay.png" alt="Webpay" class="payment-logo-small">
                                        </div>
                                    </label>
                                </div>

                                <!-- Mercado Pago -->
                                <div class="payment-option mb-3">
                                    <input type="radio" class="btn-check" name="payment_method" id="mercadopago" value="mercadopago">
                                    <label class="btn btn-outline-primary w-100 text-start payment-label" for="mercadopago">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <i class="bi bi-wallet2 fs-4"></i>
                                                <strong class="ms-2">Mercado Pago</strong>
                                                <br>
                                                <small class="text-muted ms-5">Tarjetas, efectivo y más opciones</small>
                                            </div>
                                             <span class="badge bg-info">Popular</span>
                                            <img src="/img/mercado-pago.png" alt="Mercado Pago" class="payment-logo-large">
                                        </div>
                                    </label>
                                </div>

                                <!-- PayPal -->
                                <div class="payment-option mb-3">
                                    <input type="radio" class="btn-check" name="payment_method" id="paypal" value="paypal">
                                    <label class="btn btn-outline-primary w-100 text-start payment-label" for="paypal">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <i class="bi bi-paypal fs-4"></i>
                                                <strong class="ms-2">PayPal</strong>
                                                <br>
                                                <small class="text-muted ms-5">Paga con tu cuenta PayPal</small>
                                            </div>
                                            <img src="/img/paypal.png" alt="PayPal" class="payment-logo-small">
                                        </div>
                                    </label>
                                </div>

                                <!-- Transferencia Bancaria (Genérico) -->
                                <div class="payment-option mb-3">
                                    <input type="radio" class="btn-check" name="payment_method" id="bank_transfer" value="bank_transfer">
                                    <label class="btn btn-outline-primary w-100 text-start payment-label" for="bank_transfer">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <i class="bi bi-bank fs-4"></i>
                                                <strong class="ms-2">Transferencia Bancaria</strong>
                                                <br>
                                                <small class="text-muted ms-5">Transferencia manual o automática</small>
                                            </div>
                                            <img src="/img/transferencia.png" alt="Transferencia Bancaria" class="payment-logo-medium">
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- Botón de Pago -->
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-shield-check"></i> Proceder al Pago Seguro
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
                                    <i class="bi bi-arrow-left"></i> Volver
                                </button>
                            </div>

                            <!-- Información de Seguridad -->
                            <div class="text-center mt-4">
                                <small class="text-muted">
                                    <i class="bi bi-lock-fill"></i> Pago seguro y encriptado
                                    | <i class="bi bi-shield-check"></i> Protección de datos
                                </small>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Información del Simulador -->
                <div class="card shadow-sm mt-4 border-warning">
                    <div class="card-body">
                        <h6 class="text-warning"><i class="bi bi-lightbulb-fill"></i> Sobre este Simulador</h6>
                        <p class="mb-0 small">
                            Este simulador replica los flujos exactos de Webpay, Mercado Pago, PayPal y otros proveedores de pago.
                            Te permite probar diferentes escenarios (éxito, rechazo, pendiente, etc.) sin contratar los servicios reales.
                            Ideal para desarrollo y testing antes de ir a producción.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="mt-5 py-4 bg-light">
        <div class="container text-center text-muted">
            <small>
                <i class="bi bi-code-slash"></i> Simulador Bancario para Desarrollo | 
                <i class="bi bi-github"></i> Open Source | 
                <i class="bi bi-calendar"></i> <?php echo date('Y'); ?>
            </small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>