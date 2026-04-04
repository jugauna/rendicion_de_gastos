<?php
/**
 * Rendición de gastos — proxy (app versión 1.0, ver archivo VERSION en el repo).
 *
 * PROXY DE SEGURIDAD - DEBUG VERSION
 *
 * Multimodal: reenvía por POST multipart todos los campos del formulario, múltiples ticket_foto_N
 * (imágenes JPG) y el audio opcional explicacion_audio. Mantiene validación en n8n vía header
 * X-N8N-SECRET (debe coincidir con Variables — Config / env del workflow).
 *
 * Ubicación: subí este archivo al public_html del MISMO sitio que el formulario WordPress
 * (ej. .../proxy-n8n.php). NO uses el alojamiento srv848-files.hstgr.io: suele responder 403
 * OpenResty y no ejecuta PHP.
 *
 * CORS: mismo dominio que el formulario = normalmente no hace falta; se mantiene por si
 * servís el HTML desde otro origen. Ajustá $cors_allowed_origins si cambiás el dominio.
 */

$cors_allowed_origins = [
    'https://lavenderblush-squirrel-497123.hostingersite.com',
    'http://lavenderblush-squirrel-497123.hostingersite.com',
];
$request_origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($request_origin !== '' && in_array($request_origin, $cors_allowed_origins, true)) {
    header('Access-Control-Allow-Origin: ' . $request_origin);
} else {
    // Pruebas locales (file://) u orígenes no listados
    header('Access-Control-Allow-Origin: *');
}
header('Vary: Origin');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-N8N-SECRET');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Habilitar reporte de errores para ver qué pasa en Hostinger
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. CONFIGURACIÓN
$n8n_webhook_url = 'https://n8n.srv1392353.hstgr.cloud/webhook-test/rendicion-de-gastos';
$secret_token = 'Hs_Inspeccion_2026_Secure_Alpha_99';

// 2. Método: GET/HEAD = comprobar que el proxy vive (navegador abre la URL con GET → es normal)
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    header('Content-Type: text/plain; charset=utf-8');
    if ($method === 'GET' || $method === 'HEAD') {
        http_response_code(200);
        if ($method !== 'HEAD') {
            echo "OK — Proxy de rendición de gastos activo (PHP corre bien).\n\n";
            echo "Al abrir esta URL en el navegador siempre llega GET; por eso no \"envía\" el formulario.\n";
            echo "El envío real lo hace el formulario con POST + multipart.\n";
        }
    } else {
        http_response_code(405);
        header('Allow: POST, OPTIONS, GET, HEAD');
        echo 'Método no permitido para datos: ' . $method . '. Usá POST desde el formulario.';
    }
    exit;
}

$post_data = $_POST;

// Manejo de archivos (imágenes ticket_foto_*, audio explicacion_audio, etc.)
if (!empty($_FILES)) {
    foreach ($_FILES as $key => $file) {
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($file['error'] === UPLOAD_ERR_OK) {
            $mime = (string) $file['type'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $uploadName = $file['name'];

            $keepAsIs = (strpos($mime, 'audio/') === 0) || (strpos($mime, 'video/') === 0);
            if (!$keepAsIs && ($mime === '' || $mime === 'application/octet-stream')) {
                $mimeMap = [
                    'webm' => 'video/webm',
                    'mp3' => 'audio/mpeg',
                    'm4a' => 'audio/mp4',
                    'mp4' => 'video/mp4',
                    'ogg' => 'audio/ogg',
                    'wav' => 'audio/wav',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                ];
                if ($key !== 'explicacion_audio') {
                    $mimeMap['webm'] = 'audio/webm';
                    $mimeMap['mp4'] = 'audio/mp4';
                }
                if (isset($mimeMap[$ext])) {
                    $mime = $mimeMap[$ext];
                }
            }

            // iPhone: nota de voz / cámara suele mandar video/quicktime; n8n lo marca como ext. "qt" y Whisper rechaza.
            // OpenAPI solo acepta: flac, m4a, mp3, mp4, mpeg, mpga, oga, ogg, wav, webm — no quicktime/mov/qt.
            if ($key === 'explicacion_audio') {
                if ($mime === 'video/quicktime' || in_array($ext, ['mov', 'qt'], true)) {
                    $mime = 'video/mp4';
                    $base = preg_replace('/\.[^.]+$/', '', $uploadName);
                    $uploadName = ($base !== '' ? $base : 'explicacion') . '.mp4';
                }
            }

            $post_data[$key] = new CURLFile($file['tmp_name'], $mime, $uploadName);
        }
    }
}

// 3. ENVÍO MEDIANTE CURL
$ch = curl_init($n8n_webhook_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "X-N8N-SECRET: $secret_token"
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo 'Error de CURL: ' . curl_error($ch);
} else {
    header("HTTP/1.1 $http_code");
    echo $response;
}

curl_close($ch);