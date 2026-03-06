<?php
// config/supabase.php

// TODO: Ganti dengan kredensial Supabase Anda
define('SUPABASE_URL', 'https://YOUR_SUPABASE_PROJECT_REF.supabase.co');
define('SUPABASE_KEY', 'YOUR_SUPABASE_ANON_OR_SERVICE_ROLE_KEY');

/**
 * Helper function untuk melakukan request ke Supabase REST API
 * 
 * @param string $endpoint Path setelah URL (contoh: '/rest/v1/products')
 * @param string $method HTTP Method (GET, POST, PATCH, DELETE)
 * @param array|null $data Data payload untuk POST/PATCH
 * @return array
 */
function supabase_request($endpoint, $method = 'GET', $data = null) {
    $url = SUPABASE_URL . $endpoint;
    
    $headers = [
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation' // Untuk mengembalikan object yang di-insert/update
    ];

    $ch = curl_init($url);
    
    // Set up cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    // Disable SSL verification for local dev (Sebaiknya diset true di production)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    if ($data !== null && in_array($method, ['POST', 'PATCH'])) {
        $json_data = json_encode($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'error' => $error,
            'code' => $http_code
        ];
    }
    
    $decoded = json_decode($response, true);
    
    // Check for HTTP errors (Supabase usually returns 200, 201 for success)
    if ($http_code >= 200 && $http_code < 300) {
        return [
            'success' => true,
            'data' => $decoded,
            'code' => $http_code
        ];
    } else {
        return [
            'success' => false,
            'error' => isset($decoded['message']) ? $decoded['message'] : 'Terjadi kesalahan.',
            'details' => $decoded,
            'code' => $http_code
        ];
    }
}
?>
