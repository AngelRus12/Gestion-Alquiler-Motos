<?php
/**
 * SIMULADOR BANCARIO - Portal de Pago
 * 
 * Este archivo simula el portal del banco o pasarela de pago.
 * Aquí el usuario (desarrollador) puede elegir qué tipo de respuesta quiere probar.
 * 
 * Simula las interfaces de:
 * - Webpay Plus (Transbank)
 * - Mercado Pago
 * - PayPal
 * - Transferencia Bancaria
 */

session_start();

// Capturar datos que vienen desde checkout.php
$paymentMethod = $_POST['payment_method'] ?? 'unknown';
$amount = $_POST['amount'] ?? 0;
$returnUrl = $_POST['return_url'] ?? '';

// Parámetros específicos según método de pago
$token = $_POST['TBK_TOKEN'] ?? $_POST['token'] ?? $_POST['preference_id'] ?? $_POST['reference'] ?? '';
$orderId = $_POST['TBK_ORDEN_COMPRA'] ?? $_POST['external_reference'] ?? $_POST['order_id'] ?? '';

// Guardar en sesión para usar en callback
$_SESSION['simulator_data'] = [
    'payment_method' => $paymentMethod,
    'amount' => $amount,
    'token' => $token,
    'order_id' => $orderId,
    'return_url' => $returnUrl,
    'timestamp' => time()
];

// Configuración de marca según método de pago
$paymentConfig = [
    'webpay' => [
        'name' => 'Webpay Plus',
        'logo' => 'Transbank',
        'color' => 'primary',
        'icon' => 'credit-card'
    ],
    'mercadopago' => [
        'name' => 'Mercado Pago',
        'logo' => 'Mercado Pago',
        'color' => 'info',
        'icon' => 'wallet2'
    ],
    'paypal' => [
        'name' => 'PayPal',
        'logo' => 'PayPal',
        'color' => 'primary',
        'icon' => 'paypal'
    ],
    'bank_transfer' => [
        'name' => 'Transferencia Bancaria',
        'logo' => 'Banco',
        'color' => 'secondary',
        'icon' => 'bank'
    ]
];

$config = $paymentConfig[$paymentMethod] ?? $paymentConfig['webpay'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $config['name']; ?> - Portal de Pago Seguro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/bank-style.css">
    <style>
        .payment-simulator-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
        }
        .response-option {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .response-option:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .badge-simulator {
            font-size: 0.7rem;
            padding: 0.3rem 0.6rem;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Header del Banco Simulador -->
    <div class="payment-simulator-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h4 class="mb-0">
                        <i class="bi bi-<?php echo $config['icon']; ?>"></i>
                        <?php echo $config['logo']; ?>
                    </h4>
                    <small>Portal de Pago Seguro - Modo Simulación</small>
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="badge bg-warning text-dark">
                        <i class="bi bi-gear-fill"></i> ENTORNO DE PRUEBAS
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Información de la Transacción -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h6 class="mb-2">Alquiler de Motos ARUSLAT</h6>
                                <p class="mb-1">
                                    <strong>Comercio:</strong>TFG Ángel Rus Latorre<br>
                                    <strong>Orden:</strong> <code><?php echo htmlspecialchars($orderId); ?></code><br>
                                    <strong>Token:</strong> <code class="small"><?php echo substr($token, 0, 20); ?>...</code>
                                </p>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Alerta de Simulador -->
                <div class="alert alert-warning" role="alert">
                    <h6 class="alert-heading">
                        <i class="bi bi-info-circle-fill"></i> Modo Desarrollador Activo
                    </h6>
                    <p class="mb-0 small">
                        Selecciona el tipo de respuesta que deseas probar. En producción, aquí el usuario ingresaría
                        sus datos de tarjeta o completaría el flujo de pago real.
                    </p>
                </div>

                <!-- Opciones de Respuesta -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="bi bi-list-check"></i> Selecciona el Escenario a Probar
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <!-- APROBADO / ÉXITO -->
                            <div class="col-md-6">
                                <form action="callback.php" method="POST">
                                    <input type="hidden" name="response_type" value="approved">
                                    <div class="card response-option border-success h-100">
                                        <div class="card-body text-center">
                                            <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                                            <h5 class="mt-3">Pago Aprobado</h5>
                                            <p class="text-muted small">
                                                Simula una transacción exitosa.<br>
                                                Estado: <strong class="text-success">APPROVED</strong>
                                            </p>
                                            <button type="submit" class="btn btn-success w-100">
                                                <i class="bi bi-check-lg"></i> Aprobar Pago
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- RECHAZADO -->
                            <div class="col-md-6">
                                <form action="callback.php" method="POST">
                                    <input type="hidden" name="response_type" value="rejected">
                                    <div class="card response-option border-danger h-100">
                                        <div class="card-body text-center">
                                            <i class="bi bi-x-circle-fill text-danger" style="font-size: 3rem;"></i>
                                            <h5 class="mt-3">Pago Rechazado</h5>
                                            <p class="text-muted small">
                                                Simula fondos insuficientes o tarjeta rechazada.<br>
                                                Estado: <strong class="text-danger">REJECTED</strong>
                                            </p>
                                            <button type="submit" class="btn btn-danger w-100">
                                                <i class="bi bi-x-lg"></i> Rechazar Pago
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- PENDIENTE -->
                            <div class="col-md-6">
                                <form action="callback.php" method="POST">
                                    <input type="hidden" name="response_type" value="pending">
                                    <div class="card response-option border-warning h-100">
                                        <div class="card-body text-center">
                                            <i class="bi bi-clock-fill text-warning" style="font-size: 3rem;"></i>
                                            <h5 class="mt-3">Pago Pendiente</h5>
                                            <p class="text-muted small">
                                                Simula pago en revisión o procesamiento.<br>
                                                Estado: <strong class="text-warning">PENDING</strong>
                                            </p>
                                            <button type="submit" class="btn btn-warning w-100">
                                                <i class="bi bi-hourglass-split"></i> Dejar Pendiente
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- CANCELADO -->
                            <div class="col-md-6">
                                <form action="callback.php" method="POST">
                                    <input type="hidden" name="response_type" value="cancelled">
                                    <div class="card response-option border-secondary h-100">
                                        <div class="card-body text-center">
                                            <i class="bi bi-arrow-left-circle-fill text-secondary" style="font-size: 3rem;"></i>
                                            <h5 class="mt-3">Usuario Canceló</h5>
                                            <p class="text-muted small">
                                                Simula que el usuario abandonó el pago.<br>
                                                Estado: <strong class="text-secondary">CANCELLED</strong>
                                            </p>
                                            <button type="submit" class="btn btn-secondary w-100">
                                                <i class="bi bi-arrow-left"></i> Cancelar
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- ERROR DE RED -->
                            <div class="col-md-6">
                                <form action="callback.php" method="POST">
                                    <input type="hidden" name="response_type" value="error">
                                    <div class="card response-option border-dark h-100">
                                        <div class="card-body text-center">
                                            <i class="bi bi-exclamation-triangle-fill text-dark" style="font-size: 3rem;"></i>
                                            <h5 class="mt-3">Error del Sistema</h5>
                                            <p class="text-muted small">
                                                Simula error técnico o de conexión.<br>
                                                Estado: <strong>ERROR</strong>
                                            </p>
                                            <button type="submit" class="btn btn-dark w-100">
                                                <i class="bi bi-exclamation-triangle"></i> Error
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- TIMEOUT -->
                            <div class="col-md-6">
                                <form action="callback.php" method="POST">
                                    <input type="hidden" name="response_type" value="timeout">
                                    <div class="card response-option border-info h-100">
                                        <div class="card-body text-center">
                                            <i class="bi bi-alarm-fill text-info" style="font-size: 3rem;"></i>
                                            <h5 class="mt-3">Timeout</h5>
                                            <p class="text-muted small">
                                                Simula tiempo de espera agotado.<br>
                                                Estado: <strong class="text-info">TIMEOUT</strong>
                                            </p>
                                            <button type="submit" class="btn btn-info w-100">
                                                <i class="bi bi-alarm"></i> Timeout
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información Técnica -->
                <div class="card shadow-sm border-0">
                    <div class="card-body bg-light">
                        <h6 class="text-muted mb-3">
                            <i class="bi bi-code-square"></i> Información para Desarrolladores
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <strong>Método de Pago:</strong><br>
                                    <code><?php echo strtoupper($paymentMethod); ?></code>
                                </small>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <strong>URL de Retorno:</strong><br>
                                    <code class="small"><?php echo htmlspecialchars($returnUrl); ?></code>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-3 mt-5">
        <div class="container text-center">
            <small>
                <i class="bi bi-shield-lock-fill"></i> Conexión Segura SSL 256-bit |
                <i class="bi bi-code-slash"></i> Simulador de Pagos v1.0 |
                <i class="bi bi-gear"></i> Modo Desarrollo
            </small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>