<?php
/**
 * =================================================================
 * SIMULADOR BANCARIO - Funciones de Sesión y Utilidades
 * =================================================================
 * 
 * Este archivo contiene funciones auxiliares para el simulador.
 * En tu aplicación real, aquí tendrías funciones para:
 * - Conectar con base de datos
 * - Validar tokens de seguridad
 * - Generar firmas criptográficas
 * - Logs de transacciones
 */

/**
 * Inicia una sesión de PHP de forma segura, si no está ya iniciada.
 */
function iniciarSesionSegura() {
    if (session_status() === PHP_SESSION_NONE) {
        // Configuración de seguridad para las cookies de sesión.
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', 0); // En un entorno de producción con HTTPS, esto debería ser 1.
        
        session_start();
    }
}

/**
 * Genera un token único y seguro
 * Usa `random_bytes` para una alta entropía.
 * @param string $prefix Prefijo para el token
 * @return string Token generado
 */
function generarToken($prefix = 'TKN') {
    return strtoupper($prefix . '-' . bin2hex(random_bytes(16)));
}

/**
 * Genera un ID de transacción único
 * `uniqid` con el segundo parámetro a `true` añade más entropía.
 * @return string ID de transacción
 */
function generarIdTransaccion() {
    return strtoupper(uniqid('TXN-', true));
}

/**
 * Valida el formato de un monto
 * Útil para validar montos de pago.
 * @param mixed $monto Monto a validar
 * @return bool True si es válido, false si no
 */
function validarMonto($monto) {
    return is_numeric($monto) && $monto > 0;
}

/**
 * Formatea un monto numérico como una cadena de moneda (ej. €1.234,56).
 * 
 * @param float $monto Monto a formatear
 * @param string $moneda Código de moneda (CLP, USD, etc.)
 * @return string Monto formateado
 */
function formatearMonto($monto, $moneda = 'EUR') {
    $simbolos = [
        'CLP' => '$',
        'USD' => 'US$',
        'EUR' => '€',
    ];
    
    $simbolo = $simbolos[$moneda] ?? '$';
    return $simbolo . number_format($monto, 0, ',', '.');
}

/**
 * Simula el registro de una transacción. En este simulador, se guarda en la sesión.
 * En una aplicación real, esto insertaría un registro en una tabla de la base de datos.
 * 
 * @param array $datos Datos de la transacción
 * @return bool True si se guardó correctamente
 */
function registrarTransaccion($datos) {
    if (!isset($_SESSION['transacciones'])) {
        $_SESSION['transacciones'] = [];
    }
    
    $datos['fecha_registro'] = date('Y-m-d H:i:s');
    $_SESSION['transacciones'][] = $datos;
    
    return true;
}

/**
 * Obtiene el historial de transacciones guardado en la sesión.
 * 
 * @return array Array de transacciones
 */
function obtenerHistorialTransacciones() {
    return $_SESSION['transacciones'] ?? [];
}

/**
 * Limpia las variables de sesión temporales relacionadas con una transacción.
 */
function limpiarDatosTemporales() {
    unset($_SESSION['pending_transaction']);
    unset($_SESSION['simulator_data']);
    unset($_SESSION['transaction_token']);
}

/**
 * Valida que un array de datos de transacción contenga todos los campos obligatorios.
 * 
 * @param array $datos Datos de la transacción
 * @return array ['valido' => bool, 'errores' => array]
 */
function validarDatosTransaccion($datos) {
    $errores = [];
    $camposRequeridos = ['payment_method', 'amount', 'order_id'];
    
    foreach ($camposRequeridos as $campo) {
        if (!isset($datos[$campo]) || empty($datos[$campo])) {
            $errores[] = "El campo '$campo' es requerido";
        }
    }
    
    if (isset($datos['amount']) && !validarMonto($datos['amount'])) {
        $errores[] = "El monto debe ser un número mayor a 0";
    }
    
    return [
        'valido' => empty($errores),
        'errores' => $errores
    ];
}

/**
 * Genera una firma para validar la autenticidad de los datos
 * Esto simula cómo las pasarelas de pago firman sus notificaciones (webhooks)
 * para que el comercio pueda verificar que provienen de una fuente legítima.
 * 
 * @param array $datos Datos a firmar
 * @param string $secretKey Clave secreta
 * @return string Firma generada
 */
function generarFirma($datos, $secretKey = 'tu_clave_secreta_aqui') {
    ksort($datos);
    $cadena = http_build_query($datos);
    return hash_hmac('sha256', $cadena, $secretKey);
}

/**
 * Valida una firma comparándola con una firma calculada localmente.
 * 
 * @param array $datos Datos originales
 * @param string $firma Firma a validar
 * @param string $secretKey Clave secreta
 * @return bool True si la firma es válida
 */
function validarFirma($datos, $firma, $secretKey = 'tu_clave_secreta_aqui') {
    $firmaCalculada = generarFirma($datos, $secretKey);
    return hash_equals($firmaCalculada, $firma);
}

/**
 * Sanitiza los datos de entrada para prevenir ataques XSS (Cross-Site Scripting).
 * Elimina etiquetas HTML y convierte caracteres especiales.
 * 
 * @param mixed $data Datos a sanitizar
 * @return mixed Datos sanitizados
 */
function sanitizarDatos($data) {
    if (is_array($data)) {
        return array_map('sanitizarDatos', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Genera una respuesta en formato JSON y la imprime, finalizando el script.
 * 
 * @param array $data Datos a devolver
 * @param int $statusCode Código de estado HTTP
 */
function respuestaJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Registra un mensaje de log en un archivo.
 * Muy útil para depurar problemas en producción sin mostrar errores en pantalla.
 * 
 * @param string $mensaje Mensaje del log
 * @param array $datos Datos adicionales
 */
function registrarLog($mensaje, $datos = []) {
    $logFile = __DIR__ . '/../logs/transacciones.log';
    $timestamp = date('Y-m-d H:i:s');
    $datosJson = json_encode($datos, JSON_UNESCAPED_UNICODE);
    $linea = "[$timestamp] $mensaje | Datos: $datosJson\n";
    
    // Crear directorio de logs si no existe
    $logDir = dirname($logFile);
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, $linea, FILE_APPEND | LOCK_EX);
}

/**
 * Devuelve un array con la configuración simulada para un método de pago específico.
 * 
 * @param string $metodo Método de pago
 * @return array Configuración del método
 */
function obtenerConfigMetodoPago($metodo) {
    $configuraciones = [
        'webpay' => [
            'nombre' => 'Webpay Plus',
            'proveedor' => 'Transbank',
            'pais' => 'Chile',
            'moneda' => 'EUR',
            'modo_prueba' => true,
            'docs_url' => 'https://www.transbankdevelopers.cl/documentacion/webpay-plus'
        ],
        'mercadopago' => [
            'nombre' => 'Mercado Pago',
            'proveedor' => 'Mercado Libre',
            'pais' => 'Latinoamérica',
            'moneda' => 'EUR',
            'modo_prueba' => true,
            'docs_url' => 'https://www.mercadopago.cl/developers/'
        ],
        'paypal' => [
            'nombre' => 'PayPal',
            'proveedor' => 'PayPal',
            'pais' => 'Global',
            'moneda' => 'USD',
            'modo_prueba' => true,
            'docs_url' => 'https://developer.paypal.com/'
        ],
        'bank_transfer' => [
            'nombre' => 'Transferencia Bancaria',
            'proveedor' => 'Genérico',
            'pais' => 'Chile',
            'moneda' => 'EUR',
            'modo_prueba' => true,
            'docs_url' => null
        ]
    ];
    
    return $configuraciones[$metodo] ?? null;
}

/**
 * Simula la conversión de un monto entre diferentes monedas.
 * En una aplicación real, se usaría una API de tasas de cambio actualizada.
 * 
 * @param float $monto Monto a convertir
 * @param string $origen Moneda de origen
 * @param string $destino Moneda de destino
 * @return float Monto convertido
 */
function convertirMoneda($monto, $origen = 'EUR', $destino = 'USD') {
    // Tasas simuladas (en producción usarías una API real)
    $tasas = [
        'CLP_USD' => 0.0011,
        'USD_CLP' => 900,
        'CLP_EUR' => 0.0010,
        'EUR_CLP' => 1000,
    ];
    
    $clave = "{$origen}_{$destino}";
    $tasa = $tasas[$clave] ?? 1;
    
    return round($monto * $tasa, 2);
}

/**
 * Verifica si el simulador está en modo de depuración (debug).
 * 
 * @return bool True si está en modo debug
 */
function esModoDebug() {
    return defined('DEBUG_MODE') && DEBUG_MODE === true;
}

/**
 * Obtiene la URL base del proyecto dinámicamente.
 * 
 * @return string URL base
 */
function obtenerUrlBase() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    
    return $protocol . '://' . $host . $path;
}
