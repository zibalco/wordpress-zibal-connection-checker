<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_menu_page(
        'Zibal Check',
        'Zibal Check',
        'manage_options',
        'zibal-check',
        'zibal_check_page'
    );
});

add_action('wp_ajax_zibal_test_connection', 'zibal_ajax_test_connection');
add_action('wp_ajax_zibal_test_ip', 'zibal_ajax_test_ip');
add_action('wp_ajax_zibal_test_merchant', 'zibal_ajax_test_merchant');

function zibal_ajax_test_connection() {
    check_ajax_referer('zibal_check_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('دسترسی غیرمجاز');
    }

    $server = isset($_POST['server']) ? sanitize_text_field($_POST['server']) : '';
    
    if ($server === 'iran') {
        $result = zibal_connection_test('https://gateway.zibal.ir/v1/merchant');
    } elseif ($server === 'outside') {
        $result = zibal_connection_test('https://gateway.zibal.io/v1/merchant');
    } else {
        wp_send_json_error('سرور نامعتبر است');
    }
    
    wp_send_json_success($result);
}

function zibal_ajax_test_ip() {
    check_ajax_referer('zibal_check_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('دسترسی غیرمجاز');
    }
    
    $result = zibal_ip_test();
    wp_send_json_success($result);
}

function zibal_ajax_test_merchant() {
    check_ajax_referer('zibal_check_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('دسترسی غیرمجاز');
    }
    
    $transient_key = 'zibal_merchant_test_' . get_current_user_id();
    if (get_transient($transient_key)) {
        wp_send_json_error('لطفاً 10 ثانیه بین تست‌ها صبر کنید');
    }
    
    $merchant = isset($_POST['merchant']) ? sanitize_text_field($_POST['merchant']) : '';
    
    if (empty($merchant) || !preg_match('/^[a-zA-Z0-9]+$/', $merchant)) {
        wp_send_json_error('فرمت مرچنت کد نامعتبر است');
    }
    
    set_transient($transient_key, true, 10);
    
    $result = zibal_merchant_test($merchant);
    wp_send_json_success($result);
}

function zibal_check_page(){
    if(!current_user_can('manage_options')) return;

    $connection_iran = '';
    $connection_outside = '';
    $ip_status = '';
    $ip_address = '';
    $merchant_status = '';

    if(isset($_POST['run_test']) && $_SERVER['REQUEST_METHOD'] === 'POST'){
        if (!isset($_POST['zibal_check_nonce_field']) || !wp_verify_nonce($_POST['zibal_check_nonce_field'], 'zibal_check_nonce')) {
            wp_die('بررسی امنیتی ناموفق بود.');
        }

        $transient_key = 'zibal_full_test_' . get_current_user_id();
        if (get_transient($transient_key)) {
            wp_die('لطفاً 30 ثانیه بین تست‌های کامل صبر کنید.');
        }
        set_transient($transient_key, true, 30);

        $merchant = sanitize_text_field($_POST['merchant']);
        
        if (!empty($merchant) && !preg_match('/^[a-zA-Z0-9]+$/', $merchant)) {
            wp_die('فرمت مرچنت کد نامعتبر است. فقط حروف و اعداد انگلیسی مجاز است.');
        }

        $connection_outside = zibal_connection_test('https://gateway.zibal.io/v1/merchant');
        $connection_iran = zibal_connection_test('https://gateway.zibal.ir/v1/merchant');
        $ip_result = zibal_ip_test();
        $ip_status = is_array($ip_result) ? $ip_result['status'] : $ip_result;
        $ip_address = is_array($ip_result) ? $ip_result['ip'] : '';
        $merchant_status = zibal_merchant_test($merchant);
    }
    
    $nonce = wp_create_nonce('zibal_check_nonce');
    ?>
    <div class="wrap zibal-wrap">
        <h1>Zibal Advanced Diagnostics</h1>
        <div class="zibal-flex">
            <form method="post" class="zibal-form">
                    <?php wp_nonce_field('zibal_check_nonce', 'zibal_check_nonce_field'); ?>
                    <input type="text" name="merchant" id="merchant-input" placeholder="Merchant ID" pattern="[a-zA-Z0-9]*" title="Only letters and numbers allowed">
                    <input type="submit" name="run_test" class="button button-primary" value="Run Full Diagnostic">
            </form>

            <div class="zibal-grid">
                <div class="zibal-card">
                    <h2>Host Connection</h2>
                    <p>
                        Iran Server: 
                        <button class="zibal-test-btn" data-test="connection" data-server="iran">Test</button>
                        <span id="result-iran"><?php echo !empty($connection_iran) ? wp_kses_post($connection_iran) : ''; ?></span>
                    </p>
                    <p>
                        Outside Server: 
                        <button class="zibal-test-btn" data-test="connection" data-server="outside">Test</button>
                        <span id="result-outside"><?php echo !empty($connection_outside) ? wp_kses_post($connection_outside) : ''; ?></span>
                    </p>
                </div>

                <div class="zibal-card">
                    <h2>IP Status</h2>
                    <p>
                        Check IP: 
                        <button class="zibal-test-btn" data-test="ip">Test</button>
                        <span id="result-ip-status"><?php echo !empty($ip_status) ? wp_kses_post($ip_status) : ''; ?></span>
                    </p>
                    <p>
                        Outbound IP: 
                        <span id="result-ip"><?php echo !empty($ip_address) ? wp_kses_post($ip_address) : ''; ?></span>
                    </p>
                </div>

                <div class="zibal-card">
                    <h2>Merchant Check</h2>
                    <p>
                        Merchant: 
                        <button class="zibal-test-btn" data-test="merchant">Test</button>
                        <span id="result-merchant"><?php echo !empty($merchant_status) ? wp_kses_post($merchant_status) : ''; ?></span>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('.zibal-test-btn').on('click', function(e) {
            e.preventDefault();
            
            var btn = $(this);
            var testType = btn.data('test');
            var originalText = btn.text();
            
            btn.prop('disabled', true).text('Testing...');
            
            var ajaxData = {
                nonce: '<?php echo esc_js($nonce); ?>'
            };
            
            var resultElement;
            var ajaxAction;
            
            if (testType === 'connection') {
                var server = btn.data('server');
                ajaxData.server = server;
                ajaxAction = 'zibal_test_connection';
                resultElement = $('#result-' + server);
            } else if (testType === 'ip') {
                ajaxAction = 'zibal_test_ip';
                resultElement = null;
            } else if (testType === 'merchant') {
                var merchant = $('#merchant-input').val();
                if (!merchant || !/^[a-zA-Z0-9]+$/.test(merchant)) {
                    alert('لطفاً یک مرچنت کد معتبر وارد کنید');
                    btn.prop('disabled', false).text(originalText);
                    return;
                }
                ajaxData.merchant = merchant;
                ajaxAction = 'zibal_test_merchant';
                resultElement = $('#result-merchant');
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: ajaxAction,
                    ...ajaxData
                },
                success: function(response) {
                    if (response.success) {
                        if (testType === 'ip') {
                            $('#result-ip-status').html(response.data.status);
                            $('#result-ip').html(response.data.ip);
                        } else {
                            resultElement.html(response.data);
                        }
                    } else {
                        if (testType === 'ip') {
                            $('#result-ip-status').html('<span class="zibal-error">' + response.data + '</span>');
                        } else {
                            resultElement.html('<span class="zibal-error">' + response.data + '</span>');
                        }
                    }
                },
                error: function() {
                    if (testType === 'ip') {
                        $('#result-ip-status').html('<span class="zibal-error">خطای اتصال</span>');
                    } else {
                        resultElement.html('<span class="zibal-error">خطای اتصال</span>');
                    }
                },
                complete: function() {
                    btn.prop('disabled', false).text(originalText);
                }
            });
        });
    });
    </script>
    <?php
}
