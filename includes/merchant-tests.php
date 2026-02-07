<?php
if (!defined('ABSPATH')) exit;

function zibal_merchant_test($merchant){
    // Validate merchant input
    if(empty($merchant)) {
        return '<span class="zibal-error">Merchant Empty</span>';
    }
    
    // Check if merchant is alphanumeric (حروف و اعداد)
    if (!preg_match('/^[a-zA-Z0-9]+$/', $merchant)) {
        return '<span class="zibal-error">Invalid Merchant Format</span>';
    }

    // Generate random amount
    $amount = rand(1000, 9999);
    
    $body = [
        'merchant' => $merchant,
        'amount' => $amount,
        'callbackUrl' => esc_url_raw(home_url() . '?zibal_test=' . time())
    ];

    $response = wp_remote_post('https://gateway.zibal.ir/v1/request', [
        'timeout' => 15,
        'sslverify' => true,
        'headers' => [
            'Content-Type' => 'application/json',
            'User-Agent' => 'Zibal-Connection-Checker/1.0'
        ],
        'body' => wp_json_encode($body)
    ]);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log('Zibal Merchant Test Error: ' . $error_message);
        return '<span class="zibal-error">Connection Failed: ' . esc_html($error_message) . '</span>';
    }

    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);

    // Check for JSON decode errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('Zibal: JSON decode error - ' . json_last_error_msg());
        return '<span class="zibal-error">Invalid Response Format</span>';
    }

    // Validate response structure
    if (!is_array($data) || !isset($data['result'])) {
        error_log('Zibal: Unexpected response structure - ' . $response_body);
        return '<span class="zibal-error">Unexpected Response</span>';
    }

    $result_code = $data['result'];
    
    // Validate result is numeric
    if (!is_numeric($result_code)) {
        error_log('Zibal: Non-numeric result code - ' . $result_code);
        return '<span class="zibal-error">Invalid Result Code</span>';
    }

    if ($result_code == 100) {
        return '<span class="zibal-ok">Merchant OK</span>';
    } else {
        // Map common error codes
        $error_messages = [
            102 => 'Merchant not found',
            103 => 'Merchant inactive',
            104 => 'Merchant invalid',
            201 => 'Already verified',
            202 => 'Transaction failed or canceled',
            203 => 'Track ID not found'
        ];
        
        $error_msg = isset($error_messages[$result_code]) ? 
            $error_messages[$result_code] : 
            'Error Code: ' . esc_html($result_code);
        
        error_log('Zibal Merchant Test Failed: ' . $error_msg . ' (Code: ' . $result_code . ')');
        return '<span class="zibal-error">' . esc_html($error_msg) . '</span>';
    }
}
