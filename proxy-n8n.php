<?php
/**
 * PROXY DE SEGURIDAD - ReMASA
 * Envía datos del formulario a n8n con validación por Token
 */

// 1. CONFIGURACIÓN
// Usamos la URL de TEST que me pasaste para que puedas ver los datos en el lienzo de n8n
$n8n_webhook_url = 'https://n8n.srv1392353.hstgr.cloud/webhook-test/rendicion-de-gastos';
$secret_token = 'Hs_Inspeccion_2026_Secure_Alpha_99';

// 2. VALIDACIÓN DE MÉTODO
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Solo se permite POST');
}

// 3. PROCESAMIENTO DE DATOS Y ARCHIVOS
$post_data = $_POST;

if (!empty($_FILES)) {
    foreach ($_FILES as $key => $file) {
        if ($file['error'] === UPLOAD_ERR_OK) {
            // CURLFile usa el mime_type del archivo subido
            $post_data[$key] = new CURLFile(
                $file['tmp_name'], 
                $file['type'], 
                $file['name']
            );
        }
    }
}

// 4. ENVÍO A N8N MEDIANTE CURL
$ch = curl_init($n8n_webhook_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

// Header de seguridad que espera el nodo IF de tu n8n
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "X-N8N-SECRET: $secret_token"
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    curl_close($ch);
    header('HTTP/1.1 500 Internal Server Error');
    exit("Error de conexión con n8n: " . $error_msg);
}

curl_close($ch);

// 5. RESPUESTA AL FORMULARIO (Frontend)
header('Content-Type: application/json');
http_response_code($http_code);
echo $response;