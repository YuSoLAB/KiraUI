<?php
function encrypt_download_url($original_url) {
    $secret_key = 'your_secure_random_key_here';
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $expires = time() + 7200;
    $random = bin2hex(random_bytes(8));
    $data = json_encode([
        'url' => $original_url,
        'expires' => $expires,
        'random' => $random
    ]);
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $secret_key, 0, $iv);
    return base64_encode($iv . '::' . $encrypted);
}
function decrypt_download_url($encrypted_data) {
    $secret_key = 'your_secure_random_key_here';    
    try {
        $decoded = base64_decode($encrypted_data);
        list($iv, $data) = explode('::', $decoded, 2);        
        $decrypted = openssl_decrypt($data, 'aes-256-cbc', $secret_key, 0, $iv);
        $payload = json_decode($decrypted, true);
        if (!$payload || !isset($payload['url'], $payload['expires']) || $payload['expires'] < time()) {
            return false;
        }    
        return $payload['url'];
    } catch (Exception $e) {
        return false;
    }
}
?>