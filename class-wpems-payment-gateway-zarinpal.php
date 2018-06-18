<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPEMS_Payment_Gateway_Zarinpal extends WPEMS_Abstract_Payment_Gateway
{

    /**
     * id of payment
     * @var null
     */
    public $id = 'zarinpal';
    // title
    public $title = null;
    // email
    protected $zarinpal_merchant = null;
    // url
    protected $zarinpal_url = null;
    // rest url
    protected $rest_url = 'https://www.zarinpal.com/pg/rest/WebGate/';
    // enable
    protected static $enable = false;
    //zarin gate
    protected $zarinpal_zaringate;
    // sandbox(testing mode)
    protected $sandbox;

    public function __construct()
    {
        $this->title = __('زرین پال', 'wp-events-manager');
        $this->icon = plugin_dir_url(__FILE__) . 'zarinpal.png';
        parent::__construct();

        // production environment
        $this->zarinpal_merchant = wpems_get_option('zarinpal_merchant') ? wpems_get_option('zarinpal_merchant') : '';
        $this->zarinpal_zaringate = wpems_get_option('zarinpal_zaringate') ? wpems_get_option('zarinpal_zaringate') : false;
        if ($this->zarinpal_zaringate == 'no') {
            $this->zarinpal_url = 'https://www.zarinpal.com/pg/StartPay/%s/';
        } else {
            $this->zarinpal_url = 'https://www.zarinpal.com/pg/StartPay/%s/ZarinGate/';
        }
        $this->sandbox = wpems_get_option('zarinpal_sandbox_mode') ? wpems_get_option('zarinpal_sandbox_mode') : '';
        if ($this->sandbox) {
            $this->zarinpal_url = str_replace('www', 'sandbox', $this->zarinpal_url);
            $this->rest_url = str_replace('www', 'sandbox', $this->rest_url);
        }
    }


    /*
     * Check gateway available
     */
    public function is_available()
    {
        return true;
    }

    /*
     * Check gateway enable
     */
    public function is_enable()
    {
        self::$enable = !empty($this->zarinpal_merchant) && wpems_get_option('zarinpal_enable') === 'yes';
        return apply_filters('tp_event_enable_zarinpal_payment', self::$enable);
    }


    // callback
    public function payment_validation()
    {
        // check validate query
        if (!isset($_GET['event-book']) || !$_GET['event-book']) {
            return;
        }

        $booking_id = absint($_GET['event-book']);

        if (!isset($_GET['tp-event-zarinpal-nonce']) || !wp_verify_nonce($_GET['tp-event-zarinpal-nonce'], 'tp-event-zarinpal-nonce' . $booking_id)) {
            return;
        }

        $book = new WPEMS_Booking($booking_id);
        if (is_null($book)) {
            return;
        }

        // check validate payment
        $Authority = isset($_GET['Authority']) ? $_GET['Authority'] : '';
        $amount = absint($book->price);
        if ($book->currency == 'IRR') {
            $amount /= 10;
        }

        $data = array(
            'MerchantID' => $this->zarinpal_merchant,
            'Authority' => $Authority,
            'Amount' => $amount,
        );
        $result = $this->SendRequestToZarinPal('PaymentVerification', json_encode($data));

        if (isset($_GET['Status']) && $result['Status']) {
            if (sanitize_text_field($_GET['Status']) === 'OK' && $result['Status'] == 100) {
                $status = 'ea-completed';
                $book->update_status($status);
                wpems_add_notice('success', sprintf(__('تراکنش با شماره ' . $result['RefID'] . ' پرداخت شد.')));
            } elseif (sanitize_text_field($_GET['Status']) === 'NOK' && $result['Status'] == -21) {
                $status = 'ea-cancelled';
                $book->update_status($status);
                wpems_add_notice('error', sprintf(__('تراکنش توسط کاربر لغو شد.')));
            } elseif (sanitize_text_field($_GET['Status']) === 'NOK' && $result['Status'] != 100) {
                wpems_add_notice('error', sprintf(__('تراکنش ناموفق! کد خطا: ' . $result['Status'])));
            }
        }
    }

    /**
     * fields settings
     * @return array
     */
    public function admin_fields()
    {
        $prefix = 'thimpress_events_';
        return apply_filters('tp_event_zarinpal_admin_fields', array(
            array(
                'type' => 'section_start',
                'id' => 'zarinpal_settings',
                'title' => __('تنظیمات زرین پال', 'wp-events-manager'),
                'desc' => esc_html__('ساخت درگاه زرین پال', 'wp-events-manager')
            ),
            array(
                'type' => 'yes_no',
                'title' => __('فعال کردن', 'wp-events-manager'),
                'id' => $prefix . 'zarinpal_enable',
                'default' => 'no',
                'desc' => apply_filters('tp_event_filter_enable_zarinpal_gateway', '')
            ),
            array(
                'type' => 'text',
                'title' => __('کد درگاه', 'wp-events-manager'),
                'id' => $prefix . 'zarinpal_merchant',
                'default' => '',
            ),
            array(
                'type' => 'yes_no',
                'title' => __('استفاده از زرین گیت', 'wp-events-manager'),
                'id' => $prefix . 'zarinpal_zaringate',
                'default' => 'no',
                'desc' => apply_filters('tp_event_filter_enable_zarinpal_gateway', '')
            ),
            array(
                'type' => 'checkbox',
                'title' => __('حالت تست (sandbox)', 'wp-events-manager'),
                'id' => $prefix . 'zarinpal_sandbox_mode',
                'default' => false,
            ),
            array(
                'type' => 'section_end',
                'id' => 'zarinpal_settings'
            )
        ));
    }

    /**
     * get_item_name
     * @return string
     */
    public function get_item_name($booking_id = null)
    {
        if (!$booking_id)
            return;

        // book
        $book = WPEMS_Booking::instance($booking_id);
        $description = sprintf('%s(%s)', $book->post->post_title, wpems_format_price($book->price, $book->currency));

        return $description;
    }

    /**
     * checkout url
     * @return url string
     */
    public function result($booking_id = false)
    {
        if (!$booking_id) {
            wp_send_json(array(
                'status' => false,
                'message' => __('Booking ID is not exists!', 'wp-events-manager')
            ));
            die();
        }
        // book
        $book = wpems_get_booking($booking_id);
        // process amount
        $amount = absint($book->price);
        if ($book->currency != 'IRR' && $book->currency != 'IRT') {
            wp_send_json(array(
                'status' => false,
                'message' => __('برای استفاده از درگاه مبلغ باید به ریال یا تومان باشد.')
            ));
            die();
        }
        if ($book->currency == 'IRR') {
            $amount /= 10;
        }

        // create nonce
        $nonce = wp_create_nonce('tp-event-zarinpal-nonce' . $booking_id);

        $user = get_userdata($book->user_id);
        $email = $user->user_email;
        $callback_url = add_query_arg(array('tp-event-zarinpal-nonce' => $nonce, 'event-book' => $booking_id), wpems_account_url());
        $description = 'شماره رزرو: ' . $booking_id . ' | خریدار: ' . $user->display_name;

        // query post
        $data = array(
            'MerchantID' => $this->zarinpal_merchant,
            'Amount' => $amount,
            'CallbackURL' => $callback_url,
            'Description' => $description,
            'Email' => $email,
        );

        $result = $this->SendRequestToZarinPal('PaymentRequest', json_encode($data));

        return $result;
    }

    public function process($booking_id = false)
    {
        if (!$this->is_available()) {
            return array(
                'status' => false,
                'message' => __('مرچنت کد را بررسی کنید.', 'wp-events-manager')
            );
        }

        $result = $this->result($booking_id);

        if ($result === false) {
            return array(
                'status' => false,
                'message' => __('cURL Error', 'wp-events-manager')
            );
        } else {
            if ($result['Status'] == 100) {
                return array(
                    'status' => true,
                    'url' => sprintf($this->zarinpal_url, $result['Authority']),
                );
            } else {
                return array(
                    'status' => false,
                    'message' => ' تراکنش ناموفق، کد خطا : ' . $result["Status"],
                );
            }
        }

    }

    /**
     * @param $action (PaymentRequest, )
     * @param $params string
     *
     * @return mixed
     */
    public function SendRequestToZarinPal($action, $params)
    {
        try {
            $ch = curl_init($this->rest_url . $action . '.json');
            curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($params)
            ));
            $result = curl_exec($ch);
            return json_decode($result, true);
        } catch (Exception $ex) {
            return false;
        }
    }

}
