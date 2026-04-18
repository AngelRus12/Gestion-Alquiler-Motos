<?php
/**
 * SIMULADOR BANCARIO - Callback / Respuesta
 * 
 * Este archivo recibe la respuesta del "banco" y la procesa.
 * Simula exactamente las respuestas que recibirías de las APIs reales.
 * 
 * En tu aplicación real:
 * - Aquí recibirías la notificación del banco
 * - Validarías la firma/token de seguridad
 * - Actualizarías el estado del pedido en tu base de datos
 * - Mostrarías confirmación al usuario
 */

session_start();

// Recuperar datos de la sesión
$simulatorData = $_SESSION['simulator_data'] ?? [];
$pendingTransaction = $_SESSION['pending_transaction'] ?? [];

$paymentMethod = $simulatorData['payment_method'] ?? 'unknown';
$amount = $simulatorData['amount'] ?? 0;
$orderId = $simulatorData['order_id'] ?? '';
$responseType = $_POST['response_type'] ?? 'approved';

// Generar respuesta simulada según el tipo seleccionado
$responses = [
    'approved' => [
        'status' => 'approved',
        'status_detail' => 'accredited',
        'title' => 'Pago Aprobado',
        'message' => '¡Tu pago fue procesado exitosamente!',
        'icon' => 'check-circle-fill',
        'color' => 'success',
        'code' => '00',
        'description' => 'Transacción aprobada sin problemas.'
    ],
    'rejected' => [
        'status' => 'rejected',
        'status_detail' => 'cc_rejected_insufficient_amount',
        'title' => 'Pago Rechazado',
        'message' => 'Tu pago fue rechazado por el banco emisor.',
        'icon' => 'x-circle-fill',
        'color' => 'danger',
        'code' => '51',
        'description' => 'Fondos insuficientes o tarjeta rechazada.'
    ],
    'pending' => [
        'status' => 'pending',
        'status_detail' => 'pending_contingency',
        'title' => 'Pago Pendiente',
        'message' => 'Tu pago está siendo procesado.',
        'icon' => 'clock-fill',
        'color' => 'warning',
        'code' => '02',
        'description' => 'El pago requiere verificación o está en revisión.'
    ],
    'cancelled' => [
        'status' => 'cancelled',
        'status_detail' => 'by_user',
        'title' => 'Pago Cancelado',
        'message' => 'Has cancelado la operación de pago.',
        'icon' => 'arrow-left-circle-fill',
        'color' => 'secondary',
        'code' => 'USR_CANCEL',
        'description' => 'El usuario abandonó el proceso de pago.'
    ],
    'error' => [
        'status' => 'error',
        'status_detail' => 'internal_error',
        'title' => 'Error del Sistema',
        'message' => 'Ocurrió un error al procesar tu pago.',
        'icon' => 'exclamation-triangle-fill',
        'color' => 'dark',
        'code' => 'ERR-500',
        'description' => 'Error técnico en la plataforma de pagos.'
    ],
    'timeout' => [
        'status' => 'timeout',
        'status_detail' => 'timeout_expired',
        'title' => 'Tiempo Agotado',
        'message' => 'El tiempo para completar el pago ha expirado.',
        'icon' => 'alarm-fill',
        'color' => 'info',
        'code' => 'TIMEOUT',
        'description' => 'La sesión de pago expiró por inactividad.'
    ],
];

$response = $responses[$responseType] ?? $responses['approved'];
$returnUrl = $pendingTransaction['return_url'] ?? 'index.php';
$autoRedirectOnApproved = (bool)($pendingTransaction['auto_redirect_on_approved'] ?? true);
$redirectDelayMs = (int)($pendingTransaction['redirect_delay_ms'] ?? 2000);
$redirectDelayMs = max(0, min($redirectDelayMs, 15000));

// Generar datos de transacción simulados (como los que devuelven las APIs reales)
$transactionData = [
    'transaction_id' => strtoupper(uniqid('TXN-')),
    'authorization_code' => ($responseType === 'approved') ? rand(100000, 999999) : null,
    'payment_id' => rand(1000000000, 9999999999),
    'timestamp' => date('Y-m-d H:i:s'),
    'payment_method' => $paymentMethod,
    'amount' => $amount,
    'currency' => 'EUR',
    'order_id' => $orderId,
    'status' => $response['status'],
    'status_detail' => $response['status_detail'],
    'response_code' => $response['code'],
];

// Simular respuesta específica por método de pago
switch ($paymentMethod) {
    case 'webpay':
        // Respuesta tipo Webpay (Transbank)
        $transactionData['response_data'] = [
            'vci' => 'TSY', // Transaction Security Indicator
            'amount' => $amount,
            'status' => ($responseType === 'approved') ? 'AUTHORIZED' : 'FAILED',
            'buy_order' => $orderId,
            'session_id' => session_id(),
            'card_detail' => [
                'card_number' => '****' . rand(1000, 9999),
            ],
            'accounting_date' => date('md'),
            'transaction_date' => date('Y-m-d H:i:s'),
            'authorization_code' => $transactionData['authorization_code'],
            'payment_type_code' => 'VN', // Venta Normal
            'response_code' => ($responseType === 'approved') ? 0 : -1,
            'installments_number' => 0,
        ];
        break;
    
    case 'mercadopago':
        // Respuesta tipo Mercado Pago
        $transactionData['response_data'] = [
            'id' => $transactionData['payment_id'],
            'status' => $response['status'],
            'status_detail' => $response['status_detail'],
            'payment_method_id' => 'visa',
            'payment_type_id' => 'credit_card',
            'transaction_amount' => $amount,
            'currency_id' => 'EUR',
            'date_created' => date('c'),
            'date_approved' => ($responseType === 'approved') ? date('c') : null,
            'authorization_code' => $transactionData['authorization_code'],
            'external_reference' => $orderId,
            'merchant_order_id' => rand(1000000, 9999999),
            'payer' => [
                'id' => rand(100000, 999999),
                'email' => 'test_user@test.com',
                'identification' => [
                    'type' => 'RUT',
                    'number' => '11111111-1'
                ]
            ],
        ];
        break;
    
    case 'paypal':
        // Respuesta tipo PayPal
        $transactionData['response_data'] = [
            'id' => 'PAY-' . strtoupper(substr(md5(time()), 0, 17)),
            'intent' => 'sale',
            'state' => ($responseType === 'approved') ? 'approved' : 'failed',
            'cart' => $orderId,
            'create_time' => date('c'),
            'update_time' => date('c'),
            'payer' => [
                'payment_method' => 'paypal',
                'status' => 'VERIFIED',
                'payer_info' => [
                    'email' => 'test@example.com',
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'payer_id' => 'PAYERID' . rand(100000, 999999),
                    'country_code' => 'CL'
                ]
            ],
            'transactions' => [[
                'amount' => [
                    'total' => number_format($amount / 1000, 2, '.', ''), // Convertir a dólares aprox
                    'currency' => 'USD',
                ],
                'description' => 'Payment description',
                'invoice_number' => $orderId,
            ]],
        ];
        break;
    
    case 'bank_transfer':
        // Respuesta de transferencia bancaria
        $transactionData['response_data'] = [
            'reference' => $transactionData['transaction_id'],
            'status' => $response['status'],
            'bank_name' => 'Banco de Chile',
            'account_number' => '****5678',
            'amount' => $amount,
            'currency' => 'EUR',
            'date' => date('Y-m-d H:i:s'),
        ];
        break;
}

// Guardar resultado en sesión (en producción esto iría a base de datos)
$_SESSION['last_transaction'] = $transactionData;

$jsonResponse = json_encode($transactionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultado del Pago - <?php echo $response['title']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/bank-style.css">
    <style>
        .result-header {
            padding: 3rem 0;
            text-align: center;
        }
        .result-icon {
            font-size: 5rem;
            display: block;
            margin-bottom: 1rem;
        }
        .json-viewer {
            background-color: #f8f9fa;
            border-left: 4px solid #0d6efd;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            overflow-x: auto;
            max-height: 400px;
        }
        .print-hidden {
            display: none;
        }
        @media print {
            .no-print {
                display: none;
            }
            .print-hidden {
                display: block;
            }
        }
    </style>
</head>
<body class="bg-light">
    <!-- Header de Resultado -->
    <div class="result-header bg-<?php echo $response['color']; ?> text-white">
        <div class="container">
            <i class="bi bi-<?php echo $response['icon']; ?> result-icon"></i>
            <h1><?php echo $response['title']; ?></h1>
            <p class="lead"><?php echo $response['message']; ?></p>
        </div>
    </div>

    <div class="container mt-4 mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Detalle de la Transacción -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="bi bi-receipt"></i> Detalle de la Transacción
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-6">
                                <strong>ID de Transacción:</strong><br>
                                <code><?php echo $transactionData['transaction_id']; ?></code>
                            </div>
                            <div class="col-6 text-end">
                                <strong>Estado:</strong><br>
                                <span class="badge bg-<?php echo $response['color']; ?> fs-6">
                                    <?php echo strtoupper($response['status']); ?>
                                </span>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <small class="text-muted">Orden de Compra</small><br>
                                <strong><?php echo $orderId; ?></strong>
                            </div>
                            <div class="col-md-6 mb-3">
                                <small class="text-muted">Método de Pago</small><br>
                                <strong><?php echo ucfirst($paymentMethod); ?></strong>
                            </div>
                            <div class="col-md-6 mb-3">
                                <small class="text-muted">Monto</small><br>
                                <strong class="text-primary fs-5">€<?php echo number_format($amount, 2, ',', '.'); ?> EUR</strong>
                            </div>
                            <div class="col-md-6 mb-3">
                                <small class="text-muted">Fecha y Hora</small><br>
                                <strong><?php echo $transactionData['timestamp']; ?></strong>
                            </div>
                            <?php if ($transactionData['authorization_code']): ?>
                            <div class="col-md-6 mb-3">
                                <small class="text-muted">Código de Autorización</small><br>
                                <strong><?php echo $transactionData['authorization_code']; ?></strong>
                            </div>
                            <?php endif; ?>
                            <div class="col-md-6 mb-3">
                                <small class="text-muted">Código de Respuesta</small><br>
                                <strong><?php echo $response['code']; ?></strong>
                            </div>
                        </div>

                        <div class="alert alert-<?php echo $response['color']; ?> mt-3">
                            <i class="bi bi-info-circle"></i> <strong>Descripción:</strong> <?php echo $response['description']; ?>
                        </div>
                    </div>
                </div>

                <!-- Respuesta JSON (Para desarrolladores) -->
                <div class="card shadow-sm mb-4 no-print">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="bi bi-code-square"></i> Respuesta JSON (Para desarrollo)
                        </h6>
                        <button type="button" class="btn btn-sm btn-outline-light" id="copyJsonButton" onclick="copyJsonResponse()">
                            <i class="bi bi-clipboard"></i> Copiar código
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="json-viewer">
                            <pre class="mb-0" id="jsonResponseContent"><?php echo $jsonResponse; ?></pre>
                        </div>
                    </div>
                    <div class="card-footer bg-light">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> Esta es la respuesta que recibirías en tu webhook o callback en producción.
                            Copia estos datos para implementar tu lógica de negocio.
                        </small>
                    </div>
                </div>

                <!-- Acciones -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body no-print">
                        <h6 class="mb-3"><i class="bi bi-ui-checks"></i> ¿Qué hacer ahora?</h6>
                        <div class="d-grid gap-2">
                            <a href="<?php echo htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary btn-lg">
                                <i class="bi bi-arrow-left-circle"></i> Volver a la Tienda
                            </a>
                            <button onclick="window.print()" class="btn btn-outline-secondary no-print">
                                <i class="bi bi-printer"></i> Imprimir Comprobante
                            </button>
                            <button class="btn btn-outline-info no-print" data-bs-toggle="collapse" data-bs-target="#webhookInfo">
                                <i class="bi bi-webhook"></i> Ver Info de Webhook
                            </button>
                        </div>

                        <!-- Información de Webhook (colapsable) -->
                        <div class="collapse mt-3" id="webhookInfo">
                            <div class="alert alert-info">
                                <h6 class="alert-heading">
                                    <i class="bi bi-webhook"></i> Integración con Webhook
                                </h6>
                                <p class="small mb-0">
                                    En producción, la plataforma de pago enviaría una notificación POST a tu URL de webhook
                                    con datos similares a los mostrados arriba en JSON. Deberías:
                                </p>
                                <ol class="small mb-0 mt-2">
                                    <li>Validar la firma/token de seguridad</li>
                                    <li>Actualizar el estado del pedido en tu base de datos</li>
                                    <li>Enviar email de confirmación al cliente</li>
                                    <li>Responder con HTTP 200 OK</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información del Simulador -->
                <div class="alert alert-warning no-print" role="alert">
                    <h6 class="alert-heading">
                        <i class="bi bi-gear-fill"></i> Modo Simulación Activo
                    </h6>
                    <p class="mb-0 small">
                        Esta es una respuesta simulada. En producción, aquí recibirías la confirmación real del banco
                        y deberías actualizar tu base de datos. Usa estos datos para probar tu implementación.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-3 mt-5">
        <div class="container text-center">
            <small>
                <i class="bi bi-code-slash"></i> Simulador de Pagos |
                <i class="bi bi-shield-check"></i> Entorno de Desarrollo |
                <i class="bi bi-github"></i> Open Source
            </small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyJsonResponse() {
            const copyButton = document.getElementById('copyJsonButton');
            const originalButtonHtml = copyButton.innerHTML;
            const jsonText = <?php echo json_encode($jsonResponse); ?>;

            function showSuccess() {
                copyButton.innerHTML = '<i class="bi bi-check2"></i> Copiado';
                copyButton.classList.remove('btn-outline-light');
                copyButton.classList.add('btn-success');

                setTimeout(() => {
                    copyButton.innerHTML = originalButtonHtml;
                    copyButton.classList.remove('btn-success');
                    copyButton.classList.add('btn-outline-light');
                }, 1800);
            }

            function showError() {
                copyButton.innerHTML = '<i class="bi bi-exclamation-triangle"></i> No se pudo copiar';

                setTimeout(() => {
                    copyButton.innerHTML = originalButtonHtml;
                }, 1800);
            }

            function copyWithFallback() {
                const tempTextArea = document.createElement('textarea');
                tempTextArea.value = jsonText;
                tempTextArea.setAttribute('readonly', '');
                tempTextArea.style.position = 'fixed';
                tempTextArea.style.left = '-9999px';
                document.body.appendChild(tempTextArea);
                tempTextArea.select();
                tempTextArea.setSelectionRange(0, tempTextArea.value.length);

                try {
                    const copied = document.execCommand('copy');
                    document.body.removeChild(tempTextArea);
                    if (copied) {
                        showSuccess();
                    } else {
                        showError();
                    }
                } catch (error) {
                    document.body.removeChild(tempTextArea);
                    showError();
                }
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(jsonText)
                    .then(showSuccess)
                    .catch(copyWithFallback);
            } else {
                copyWithFallback();
            }
        }

    </script>

    <?php
    // Si el pago es aprobado y la redirección automática está activa,
    // inyectamos un script adicional para la redirección.
    if ($responseType === 'approved' && $autoRedirectOnApproved) {
        echo '<script>';
        echo 'setTimeout(function() { window.location.href = ' . json_encode($returnUrl) . '; }, ' . $redirectDelayMs . ');';
        echo '</script>';
    }
    ?>

</body>
</html>
