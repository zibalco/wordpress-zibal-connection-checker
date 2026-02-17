<?php
if (!defined('ABSPATH')) exit;

function zibal_connection_test($url){
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        error_log('Zibal: Invalid URL provided - ' . $url);
        return '<span class="zibal-error">آدرس نامعتبر است</span>';
    }

    $response = wp_remote_get($url, [
        'timeout' => 10,
        'sslverify' => true,
        'user-agent' => 'Zibal-Connection-Checker/1.0'
    ]);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log('Zibal Connection Error: ' . $error_message);
        return '<span class="zibal-error">خطای اتصال: ' . esc_html($error_message) . '</span>';
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        error_log('Zibal: Unexpected status code ' . $status_code . ' from ' . $url);
        return '<span class="zibal-error">کد خطای HTTP: ' . esc_html($status_code) . '</span>';
    }

    return '<span class="zibal-ok">اتصال برقرار است</span>';
}

function zibal_ip_test(){
    $response = wp_remote_get('https://api.ipify.org', [
        'timeout' => 8,
        'sslverify' => true
    ]);
    
    if(is_wp_error($response)){
        $error_message = $response->get_error_message();
        error_log('Zibal IP Detection Error: ' . $error_message);
        return [
            'status' => '<span class="zibal-error">خطا: ' . esc_html($error_message) . '</span>',
            'ip' => ''
        ];
    }
    
    $public_ip = trim(wp_remote_retrieve_body($response));
    
    if (!filter_var($public_ip, FILTER_VALIDATE_IP)) {
        error_log('Zibal: Invalid IP format received - ' . $public_ip);
        return [
            'status' => '<span class="zibal-error">فرمت IP نامعتبر است</span>',
            'ip' => ''
        ];
    }
    
    $public_ip = esc_html($public_ip);

    $errno = 0;
    $errstr = '';
    $fp = @fsockopen("gateway.zibal.ir", 443, $errno, $errstr, 5);
    
    if (!$fp) {
        error_log('Zibal Gateway Connection Failed: errno=' . $errno . ', errstr=' . $errstr);
        return [
            'status' => '<span class="zibal-error">اتصال مسدود است</span>',
            'ip' => '<span class="zibal-error">' . $public_ip . '</span>'
        ];
    }
    
    fclose($fp);

    return [
        'status' => '<span class="zibal-ok">IP سالم است</span>',
        'ip' => '<span class="zibal-ok">' . $public_ip . '</span>'
    ];
}
