<?php
/**
 * Standalone QR Code Image Generator with GD and API Fallbacks.
 */
function generateQRCodeImage($qr_content, $filepath) {
    $dir = dirname($filepath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    // 1. Try PHP GD extension with phpqrcode if available
    if (extension_loaded('gd') && function_exists('imagecreate')) {
        $qrlib = __DIR__ . '/phpqrcode/qrlib.php';
        if (file_exists($qrlib)) {
            try {
                require_once $qrlib;
                @QRcode::png($qr_content, $filepath, QR_ECLEVEL_L, 10);
                if (file_exists($filepath) && filesize($filepath) > 0) {
                    return true;
                }
            } catch (Throwable $e) {
                // Fallthrough to API fallback
            }
        }
    }

    // 2. High-reliability QRServer API fallback
    $api_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qr_content);
    $data = @file_get_contents($api_url);
    if ($data === false && function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $data = curl_exec($ch);
        curl_close($ch);
    }

    if ($data && strlen($data) > 50) {
        return @file_put_contents($filepath, $data) !== false;
    }

    return false;
}
?>
