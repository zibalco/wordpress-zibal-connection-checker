<?php
if (!defined('ABSPATH')) exit;

function zibal_merchant_test($merchant){
    if(empty($merchant)) {
        return '<span class="zibal-error">مرچنت کد خالی است</span>';
    }
    
    if (!preg_match('/^[a-zA-Z0-9]+$/', $merchant)) {
        return '<span class="zibal-error">فرمت مرچنت کد نامعتبر است</span>';
    }

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
        return '<span class="zibal-error">خطای اتصال: ' . esc_html($error_message) . '</span>';
    }

    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('Zibal: JSON decode error - ' . json_last_error_msg());
        return '<span class="zibal-error">فرمت پاسخ نامعتبر است</span>';
    }

    if (!is_array($data) || !isset($data['result'])) {
        error_log('Zibal: Unexpected response structure - ' . $response_body);
        return '<span class="zibal-error">پاسخ غیرمنتظره دریافت شد</span>';
    }

    $result_code = $data['result'];
    
    if (!is_numeric($result_code)) {
        error_log('Zibal: Non-numeric result code - ' . $result_code);
        return '<span class="zibal-error">کد نتیجه نامعتبر است</span>';
    }

    if ($result_code == 100) {
        return '<span class="zibal-ok">مرچنت کد سالم است</span>';
    } else {
        $error_messages = [
            102 => 'مرچنت کد یافت نشد',
            103 => 'مرچنت کد غیرفعال است',
            104 => 'مرچنت کد نامعتبر است',
            201 => 'قبلاً تایید شده است',
            202 => 'تراکنش ناموفق یا لغو شده',
            203 => 'شناسه پیگیری یافت نشد'
        ];
        
        $error_msg = isset($error_messages[$result_code]) ? 
            $error_messages[$result_code] : 
            'کد خطا: ' . esc_html($result_code);
        
        error_log('Zibal Merchant Test Failed: ' . $error_msg . ' (Code: ' . $result_code . ')');
        return '<span class="zibal-error">' . esc_html($error_msg) . '</span>';
    }
}
