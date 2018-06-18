<?php

/**
 *
 * @link              mailto:shahrwp.com@gmail.com
 * @since             1.0.0
 * @package           Zarinpal_Wp_Events_Manager
 *
 * @wordpress-plugin
 * Plugin Name:       درگاه زرین پال افزونه WP Events Manager
 * Plugin URI:        https://shahrwp.com
 * Description:       درگاه پرداخت زرین پال برای پلاگین مدیریت رویداد وردپرس.
 * Version:           1.1.0
 * Author:            شهر وردپرس
 * Author URI:        mailto:shahrwp.com@gmail.com
 * License:           mailto:shahrwp.com@gmail.com
 * License URI:       mailto:shahrwp.com@gmail.com
 * Text Domain:       zarinpal-wp-events-manager
 */


if (!defined('WPINC')) {
    die;
}

// so lets go start coding

if (class_exists('WPEMS_Abstract_Payment_Gateway')) {

    if (!class_exists('WPEMS_Payment_Gateway_Zarinpal')) {
        include plugin_dir_path(__FILE__) . 'class-wpems-payment-gateway-zarinpal.php';
    }

    // watch payment query
    add_action('init', 'zpwpems_get_request_payment', 99);
    function zpwpems_get_request_payment()
    {
        (new WPEMS_Payment_Gateway_Zarinpal())->payment_validation();
    }

    add_filter('wpems_currencies', function ($currencies) {
        $currencies['IRR'] = __('ایران (ریال)');
        $currencies['IRT'] = __('ایران (تومان)');
        return $currencies;
    });

    add_filter('tp_event_currency_symbol', function ($currency_symbol, $currency) {
        switch ($currency) {
            case 'IRR' :
                $currency_symbol = __('ریال');
                break;
            case 'IRT' :
                $currency_symbol = __('تومان');
                break;
        }
        return $currency_symbol;
    }, 11, 2);

    // add zarinpal payment geteway to setting
    add_filter('wpems_payment_gateways', 'add_zarinpal_checkout_section');
    function add_zarinpal_checkout_section($gateways)
    {
        $gateways['zarinpal'] = new WPEMS_Payment_Gateway_Zarinpal();
        return $gateways;
    }
} else {
    add_action('admin_notices', 'zpwpems_admin_notice_active_base_plugin');
    function zpwpems_admin_notice_active_base_plugin()
    {
        ?>
        <div class="notice notice-warning">
            <p><b>توجه</b>: برای استفاده از درگاه زرین پال باید پلاگین اصلی (WP Events Manager) را نصب و فعال کنید.</p>
        </div>
        <?php
    }
}