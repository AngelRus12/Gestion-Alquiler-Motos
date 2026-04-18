<?php
/**
 * SIMULADOR BANCARIO - Configuración
 * 
 * Este archivo contiene la configuración general del simulador.
 * En producción, estas configuraciones deberían estar en variables de entorno.
 * 
 * IMPORTANTE: Este archivo es un EJEMPLO. No contiene credenciales reales.
 */

// ===== CONFIGURACIÓN GENERAL =====

// Modo Debug (true = muestra errores, false = oculta errores)
define('DEBUG_MODE', true);

// URL Base del proyecto (se detecta automáticamente, pero puedes forzarla)
define('BASE_URL', 'http://localhost/paymentsimulator');

// Tiempo de expiración de sesión (en segundos)
define('SESSION_TIMEOUT', 3600); // 1 hora

// Moneda por defecto
define('DEFAULT_CURRENCY', 'EUR');

// ===== CONFIGURACIÓN DE PAGOS =====

// ¿Usar simulador o APIs reales?
define('USE_SIMULATOR', true); // Cambiar a false en producción

// ===== WEBPAY (TRANSBANK) =====
// Documentación: https://www.transbankdevelopers.cl/

$WEBPAY_CONFIG = [
    // Modo: 'development' o 'production'
    'mode' => 'development',
    
    // Credenciales de integración (ambiente de pruebas)
    'commerce_code_dev' => '597055555532',
    'api_key_dev' => '579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C',
    
    // Credenciales de producción (obtener de Transbank)
    'commerce_code_prod' => 'TU_CODIGO_COMERCIO_AQUI',
    'api_key_prod' => 'TU_API_KEY_AQUI',
    
    // URLs
    'url_dev' => 'https://webpay3gint.transbank.cl',
    'url_prod' => 'https://webpay3g.transbank.cl',
];

// ===== MERCADO PAGO =====
// Documentación: https://www.mercadopago.cl/developers/

$MERCADOPAGO_CONFIG = [
    'mode' => 'development',
    
    // Credenciales de prueba
    'public_key_dev' => 'TEST-xxx-xxx-xxx',
    'access_token_dev' => 'TEST-xxx-xxx-xxx',
    
    // Credenciales de producción
    'public_key_prod' => 'TU_PUBLIC_KEY_AQUI',
    'access_token_prod' => 'TU_ACCESS_TOKEN_AQUI',
    
    // URLs
    'url_dev' => 'https://api.mercadopago.com',
    'url_prod' => 'https://api.mercadopago.com',
];

// ===== PAYPAL =====
// Documentación: https://developer.paypal.com/

$PAYPAL_CONFIG = [
    'mode' => 'sandbox',
    
    // Credenciales Sandbox
    'client_id_sandbox' => 'TU_CLIENT_ID_SANDBOX_AQUI',
    'client_secret_sandbox' => 'TU_CLIENT_SECRET_SANDBOX_AQUI',
    
    // Credenciales Live
    'client_id_live' => 'TU_CLIENT_ID_LIVE_AQUI',
    'client_secret_live' => 'TU_CLIENT_SECRET_LIVE_AQUI',
    
    // URLs
    'url_sandbox' => 'https://api.sandbox.paypal.com',
    'url_live' => 'https://api.paypal.com',
];

// ===== CONFIGURACIÓN DE URLS DE RETORNO =====

// URL de retorno después del pago (callback)
define('RETURN_URL', BASE_URL . '/callback.php');

// URL de notificación webhook
define('WEBHOOK_URL', BASE_URL . '/webhook.php');

// URL de cancelación
define('CANCEL_URL', BASE_URL . '/index.php');

// ===== CONFIGURACIÓN DE BASE DE DATOS (OPCIONAL) =====
// Si en el futuro decides usar base de datos

$DB_CONFIG = [
    'enabled' => false, // Cambiar a true para habilitar
    'host' => 'localhost',
    'database' => 'bank_simulator',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
];

// ===== CONFIGURACIÓN DE LOGS =====

$LOG_CONFIG = [
    'enabled' => true,
    'path' => __DIR__ . '/logs',
    'level' => 'debug', // debug, info, warning, error
    'max_files' => 30, // Mantener logs por 30 días
];

// ===== CONFIGURACIÓN DE EMAIL =====
// Para enviar notificaciones de pago

$EMAIL_CONFIG = [
    'enabled' => false, // Cambiar a true para habilitar
    'from' => 'noreply@tudominio.com',
    'from_name' => 'Mi Tienda',
    
    // Configuración SMTP
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_user' => 'tu_email@gmail.com',
    'smtp_pass' => 'tu_password_aqui',
    'smtp_secure' => 'tls', // tls o ssl
];

// ===== CONFIGURACIÓN DE SEGURIDAD =====

// Clave secreta para firmas (cambiar en producción)
define('SECRET_KEY', 'cambiar_esta_clave_en_produccion_' . md5(__DIR__));

// Lista de IPs permitidas para webhooks (dejar vacío para permitir todas)
$ALLOWED_IPS = [
    // Webpay
    '200.10.12.25',
    '200.10.12.29',
    
    // Mercado Pago
    '209.225.49.0/24',
    
    // PayPal
    '173.0.82.0/24',
    '173.0.88.0/24',
];

// ===== CONFIGURACIÓN DE MÉTODOS DE PAGO =====

// Habilitar/deshabilitar métodos de pago
$PAYMENT_METHODS_ENABLED = [
    'webpay' => true,
    'mercadopago' => true,
    'paypal' => true,
    'bank_transfer' => true,
];

// ===== FUNCIONES DE CONFIGURACIÓN =====

/**
 * Obtiene la configuración de un método de pago
 */
function getPaymentConfig($method) {
    global $WEBPAY_CONFIG, $MERCADOPAGO_CONFIG, $PAYPAL_CONFIG;
    
    switch ($method) {
        case 'webpay':
            return $WEBPAY_CONFIG;
        case 'mercadopago':
            return $MERCADOPAGO_CONFIG;
        case 'paypal':
            return $PAYPAL_CONFIG;
        default:
            return null;
    }
}

/**
 * Verifica si un método de pago está habilitado
 */
function isPaymentMethodEnabled($method) {
    global $PAYMENT_METHODS_ENABLED;
    return $PAYMENT_METHODS_ENABLED[$method] ?? false;
}

/**
 * Verifica si estamos en modo producción
 */
function isProduction() {
    return !USE_SIMULATOR;
}

// ===== INICIALIZACIÓN =====

// Configurar errores según modo
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Configurar zona horaria
date_default_timezone_set('America/Santiago');

// Configurar locale para formato de números
setlocale(LC_MONETARY, 'es_CL');
setlocale(LC_TIME, 'es_CL.UTF-8');
