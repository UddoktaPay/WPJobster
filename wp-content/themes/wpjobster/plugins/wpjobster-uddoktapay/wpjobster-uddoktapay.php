<?php
/*
	Plugin Name: UddoktaPay Plugin
	Plugin URL: https://uddoktapay.com
	Description: UddoktaPay
	Version: 1.0.0
	Author: Md Rasel Islam
	Author URI: https://rtrasel.com
*/

if (!defined('ABSPATH')) {
    exit;
}


if (!class_exists("WPJobster_UddoktaPay_Loader")) {

    class WPJobster_UddoktaPay_Loader
    {

        private static $instance;

        public $_api_url, $_jb_action, $_oid, $_unique_id;

        var $enable_log;

        public static function get_instance()
        {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function __construct($gateway = 'uddoktapay')
        {

            $this->priority   = 200;
            $this->_unique_id = 'uddoktapay';

            add_action('after_setup_theme', array($this, 'init_gateways'), 0);
            add_action('wpjobster_taketo_' . $this->_unique_id . '_gateway', array($this, 'taketogateway_function'), 10, 2);
            add_action('wpjobster_processafter_' . $this->_unique_id . '_gateway', array($this, 'processgateway_function'), 10, 2);
            add_filter('wpjobster_take_allowed_currency_' . $this->_unique_id, array($this, 'get_gateway_currency'));


            if (isset($_POST['wpjobster_save_' . $this->_unique_id])) {
                add_action('wpjobster_payment_methods_action', array($this, 'save_gateway'), 11);
            }

            $this->site_url = get_bloginfo('url');

            $this->key = md5(date("Y-m-d:") . rand());

            // write IPN debug log in a text file
            $this->enable_log = false;
        }

        function get_gateway_currency($currency)
        {
            // if the gateway requires a specific currency you can declare it there
            // currency conversions are done automatically

            $currency = wpjobster_get_currency();

            $accepted_uddoktapay_currencies = array('BDT');

            // if currency not supported
            if (!in_array($currency, $accepted_uddoktapay_currencies)) {

                // fallback
                $currency = 'BDT';

                // try other site currencies
                global $wpjobster_currencies_array;
                foreach ($wpjobster_currencies_array as $wpjobster_currency) {
                    if (in_array($wpjobster_currency, $accepted_uddoktapay_currencies)) {
                        $currency = $wpjobster_currency;
                        break;
                    }
                }
            }

            return $currency;
        }

        public function init_gateways()
        {
            add_filter('wpjobster_payment_gateways', array($this, 'add_gateways'));
        }

        public function add_gateways($methods)
        {
            if (get_option('wpjobster_uddoktapay_api_key')) {
                $methods[$this->priority] = array(
                    'label'           => __('UddoktaPay', 'wpjobster'),
                    'unique_id'       => $this->_unique_id,
                    'action'          => 'wpjobster_taketo_' . $this->_unique_id . '_gateway', // action called when user request to send payment to gateway
                    'response_action' => 'wpjobster_processafter_' . $this->_unique_id . '_gateway', //action called when any response comes from gateway after payment
                );
            } else {
                $methods[$this->priority] = array(
                    'label'         => __('UddoktaPay', 'wpjobster'),
                    'unique_id'     => $this->_unique_id,
                    'no_pay_button' => true
                );
            }
            add_action('wpjobster_show_paymentgateway_forms', array($this, 'show_gateways'), $this->priority, 3);

            return $methods;
        }

        public function save_gateway()
        {
            if (isset($_POST['wpjobster_save_' . $this->_unique_id])) {
                update_option('wpjobster_uddoktapay_enable', trim($_POST['wpjobster_uddoktapay_enable']));
                update_option('wpjobster_uddoktapay_api_key', trim($_POST['wpjobster_uddoktapay_api_key']));
                update_option('wpjobster_uddoktapay_api_url', trim($_POST['wpjobster_uddoktapay_api_url']));
                update_option('wpjobster_uddoktapay_button_caption', trim($_POST['wpjobster_uddoktapay_button_caption']));

                if (wpj_get_payment_types()) {
                    foreach (wpj_get_payment_types() as $payment_type_enable_key => $payment_type_enable) {
                        if (isset($_POST['wpjobster_uddoktapay_enable_' . $payment_type_enable_key]))
                            update_option('wpjobster_uddoktapay_enable_' . $payment_type_enable_key, trim($_POST['wpjobster_uddoktapay_enable_' . $payment_type_enable_key]));
                    }
                }
                echo '<div class="updated fade"><p>' . __('Settings saved!', 'wpjobster') . '</p></div>';
            }
        }

        function show_gateways($wpjobster_payment_gateways, $arr, $arr_pages)
        {
            $tab_id = get_tab_id($wpjobster_payment_gateways); ?>

            <div id="tabs<?php echo $tab_id ?>">
                <form method="post" action="<?php bloginfo('url'); ?>/wp-admin/admin.php?page=payment-methods&active_tab=tabs<?php echo $tab_id; ?>">
                    <table width="100%" class="wpj-admin-table">
                        <tr>
                            <td valign="top" width="22"><?php wpjobster_theme_bullet(); ?></td>
                            <td width="200"><?php _e('Enable:', 'wpjobster'); ?></td>
                            <td><?php echo wpjobster_get_option_drop_down($arr, 'wpjobster_uddoktapay_enable', 'no'); ?></td>
                        </tr>


                        <?php foreach (wpj_get_payment_types() as $key => $payment_type) { ?>

                            <tr>
                                <td valign="top" width="22"><?php wpjobster_theme_bullet($payment_type['hint_label']); ?></td>
                                <td width="200"><?php echo $payment_type['enable_label']; ?></td>
                                <td><?php echo wpjobster_get_option_drop_down($arr, 'wpjobster_uddoktapay_enable_' . $key); ?></td>
                            </tr>

                        <?php } ?>


                        <tr>
                            <td valign="top" width="22"><?php wpjobster_theme_bullet(); ?></td>
                            <td><?php _e('API Key:', 'wpjobster'); ?></td>
                            <td><input type="text" size="45" name="wpjobster_uddoktapay_api_key" value="<?php echo apply_filters('wpj_sensitive_info_email', get_option('wpjobster_uddoktapay_api_key')); ?>" /></td>
                        </tr>

                        <tr>
                            <td valign="top" width="22"><?php wpjobster_theme_bullet(); ?></td>
                            <td><?php _e('API URL:', 'wpjobster'); ?></td>
                            <td><input type="text" size="45" name="wpjobster_uddoktapay_api_url" value="<?php echo apply_filters('wpj_sensitive_info_email', get_option('wpjobster_uddoktapay_api_url')); ?>" /></td>
                        </tr>

                        <tr>
                            <td valign="top" width="22"><?php wpjobster_theme_bullet("Put the UddoktaPay Button caption you want user to see on purchase page "); ?></td>
                            <td><?php _e('UddoktaPay Button caption:', 'wpjobster'); ?></td>
                            <td><input type="text" size="45" name="wpjobster_uddoktapay_button_caption" value="<?php echo get_option('wpjobster_uddoktapay_button_caption'); ?>" /></td>
                        </tr>

                        <tr>
                            <td valign="top" width="22"><?php wpjobster_theme_bullet("Please select a page to show when uddoktapay payment successful."); ?></td>
                            <td><?php _e('Transaction success page:', 'wpjobster'); ?></td>
                            <td><?php echo wpjobster_get_option_drop_down($arr_pages, 'wpjobster_uddoktapay_success_page', '', ' class="" '); ?></td>
                        </tr>

                        <tr>
                            <td valign="top" width="22"><?php wpjobster_theme_bullet(); ?></td>
                            <td><?php _e('Transaction failure page:', 'wpjobster'); ?></td>
                            <td><?php echo wpjobster_get_option_drop_down($arr_pages, 'wpjobster_uddoktapay_failure_page', '', ' class="" '); ?> </td>
                        </tr>

                        <tr>
                            <td></td>
                            <td></td>
                            <td><input type="submit" name="wpjobster_save_uddoktapay" value="<?php _e('Save Options', 'wpjobster'); ?>" /></td>
                        </tr>

                    </table>
                </form>
            </div>

<?php }

        public function taketogateway_function($payment_type, $common_details)
        {
            $this->api_key = get_option('wpjobster_uddoktapay_api_key');
            if (empty($this->api_key)) {
                echo __("ERROR: please input your uddoktapay api key address in backend", 'wpjobster');
                exit;
            }

            $this->init_api();
            $this->debug_log("Gateway start");

            $order_id = isset($common_details['order_id']) ? $common_details['order_id'] : '';

            // Get Order ID
            $payment = wpj_get_payment(array(
                'payment_type'    => $payment_type,
                'payment_type_id' => $order_id,
            ));

            // Normal Purchase Vars
            $currency_code      = $common_details['selected'];
            $amount             = $common_details['wpjobster_final_payable_amount'];
            $title              = isset($common_details['title']) ? $common_details['title'] : '-';
            $webhook_url        = get_bloginfo('url') . '/?payment_response=uddoktapay&oid=' . $order_id . '&wpj_payment_id=' . $payment->id . '&action=ipn';
            $success_page       = get_bloginfo('url') . '/?payment_response=uddoktapay&oid=' . $order_id . '&wpj_payment_id=' . $payment->id . '&action=success';
            $cancel_page        = get_bloginfo('url') . '/?payment_response=uddoktapay&payment_type=' . $payment_type . '&action=cancel&order_id=' . $order_id . '&jobid=' . $pid . '&wpj_payment_id=' . $payment->id;
            $full_name          = $common_details['current_user']->data->display_name;
            $email              = $common_details['current_user']->data->user_email;

            // Create a new charge.
            $metadata = array(
                'order_id'          => $order_id,
                'payment_id'         => $payment->id,
                'item_name'         => $title
            );

            $result = UddoktaPay_Gateway_API_Handler::create_payment(
                $amount,
                $currency_code,
                $full_name,
                $email,
                $metadata,
                $success_page,
                $cancel_page,
                $webhook_url
            );

            if (isset($result) && $result->status === FALSE) {
                echo __("ERROR: Something went wrong", 'wpjobster');
                exit;
            }

            wp_redirect($result->payment_url);
            exit();
        }

        /**
         * Init the API class and set the API key etc.
         */
        protected function init_api()
        {
            $this->debug_log("INIT API");
            include_once dirname(__FILE__) . '/lib/class-uddoktapay-api-handler.php';

            UddoktaPay_Gateway_API_Handler::$api_url = trim(get_option('wpjobster_uddoktapay_api_url'));
            UddoktaPay_Gateway_API_Handler::$api_key = trim(get_option('wpjobster_uddoktapay_api_key'));
        }

        public function processgateway_function($payment_type, $details)
        {
            $this->debug_log("IPN START");

            $this->init_api();

            if (isset($_GET['action']) && $_GET['action'] == 'cancel') {

                $payment = wpj_get_payment(array('id' => $_REQUEST['wpj_payment_id']));

                if (!$payment || $payment->payment_status != 'completed') {

                    if (wpj_get_buyer_by_payment_type($order_id, $payment_type) == get_current_user_id()) {
                        $payment_details = __('Buyer clicked cancel', 'wpjobster');
                        $this->cancel($payment_type, $order_id, $payment_details, $payment_response);
                    } else {
                        wp_redirect(get_bloginfo('url'));
                        exit;
                    }
                } else {

                    $wpjobster_failure_page_id = get_option("wpjobster_{$this->_unique_id}_failure_page");
                    if ($wpjobster_failure_page_id != '' && $wpjobster_failure_page_id != '0') {
                        wp_redirect(get_permalink($wpjobster_failure_page_id));
                    } else {
                        wp_redirect(get_bloginfo('url') . '/?jb_action=chat_box&oid=' . $order_id);
                    }
                }
            }

            if (isset($_GET['action']) && $_GET['action'] === 'ipn') {
                $payment_response = file_get_contents('php://input');
                if (!empty($payment_response) && $this->validate_webhook()) {
                    $response_decoded = json_decode($payment_response);
                    $this->up_process_payment($response_decoded);
                }
                $this->debug_log("IPN END.");
                echo 'ok';
                exit();
            }

            if (isset($_GET['action']) && $_GET['action'] === 'success') {
                $invoice_id = $_REQUEST['invoice_id'];
                $order_id = $_REQUEST['id'];
                $payment_id = $_REQUEST['wpj_payment_id'];
                $result = UddoktaPay_Gateway_API_Handler::verify_payment($invoice_id);
                if (!empty($result) && isset($result->status)) {
                    $this->up_process_payment($result);
                }
                $success_url = get_bloginfo('url') . '/?jb_action=loader_page&oid=' . $order_id . '&wpj_payment_id=' . $payment_id;
                wp_redirect($success_url);
                exit();
            }
        }

        /**
         * Process UddoktaPay Payment
         * @param  string $response_decoded
         */

        private function up_process_payment($response_decoded)
        {

            if (!empty($response_decoded->metadata->order_id)) {
                $order_id = isset($response_decoded->metadata->order_id) ? $response_decoded->metadata->order_id : "Unknown Order";
            }


            $transaction_id = isset($response_decoded->transaction_id) ? $response_decoded->transaction_id : "Transaction ID Not Found";
            $payment_status = isset($response_decoded->status) ? $response_decoded->status : "Status Not Found";

            $payment['order_id']         = $order_id;
            $payment['payment_type']     = $payment_type;
            $payment['transaction_id']   = $transaction_id;
            $payment['payment_response'] = $payment_response;
            $payment['payment_status']   = $payment_status;
            $payment['gateway']          = $this->_unique_id;

            do_action("wpjobster_store_payment_gateway_log", $payment);

            if (!empty($response_decoded->metadata->payment_id)) {
                $payment      = wpj_get_payment(array('id' => $response_decoded->metadata->payment_id));
                $payment_type = $payment->payment_type;
            }


            $amount = !empty($response_decoded->amount) ? $response_decoded->amount : '';
            $date   = time();

            // Webhooks
            if (isset($response_decoded->invoice_id)) {

                global $wpdb;
                $results = $wpdb->get_results("SELECT webhook_id FROM {$wpdb->prefix}job_webhooks WHERE webhook_id = '{$response_decoded->invoice_id}';");

                if (!$results) {

                    wpj_save_webhook(array(
                        'webhook_id'      => !empty($response_decoded->invoice_id) ? $response_decoded->invoice_id : '',
                        'payment_id'      => !empty($response_decoded->metadata->payment_id) ? $response_decoded->metadata->payment_id : '',
                        'status'          => $payment_status,
                        'type'            => !empty($response_decoded->payment_method) ? $response_decoded->payment_method : '',
                        'description'     => !empty($response_decoded->metadata->item_name) ? $response_decoded->metadata->item_name : '',
                        'amount'          => $amount,
                        'amount_currency' => 'BDT',
                        'fees'            => '',
                        'fees_currency'   => '',
                        'create_time'     => strtotime($date),
                        'response'        => $payment_response,
                        'payment_type'    => $payment_type,
                        'order_id'        => !empty($response_decoded->metadata->order_id) ? $response_decoded->metadata->order_id : ''
                    ));
                }
            }

            if (isset($response_decoded->metadata->payment_id) && isset($response_decoded->status) && isset($response_decoded->sender_number) && isset($response_decoded->transaction_id)) {
                $this->debug_log("Come to final option.");
                $payment        = wpj_get_payment(array('id' => $response_decoded->metadata->payment_id));
                $order_id       = $response_decoded->metadata->order_id;
                $payment_type   = $payment->payment_type;
                $payment_status = $response_decoded->status;

                if (strtolower($payment_status) == 'completed') {
                    $payment_details = sprintf(__('UddoktaPay Payment Method: %s , UddoktaPay Transaction ID: %s , UddoktaPay Phone Number: %s', 'wpjobster'), $payment_method, $transaction_id, $phone_number);
                    $this->debug_log('success');
                    $this->success($payment_type, $order_id, $payment_details, $payment_response);
                } elseif (strtolower($payment_status) == 'invalid') {
                    $payment_details = sprintf(__('Payment not cleared yet, may need manual action through UddoktaPay. Pending reason: %s', 'wpjobster'), 'Amount is not matching');
                    $this->debug_log('invalid');
                    $this->other($payment_type, $order_id, $payment_details, $payment_response, 'processing');
                } else {
                    $payment_details = sprintf(__('Payment not cleared yet, may need manual action through UddoktaPay. Pending reason: %s', 'wpjobster'), 'Transaction ID Not Found');
                    $this->debug_log('pending');
                    $this->other($payment_type, $order_id, $payment_details, $payment_response, 'pending');
                }
            }
        }


        /**
         * Check UddoktaPay Gateway webhook request is valid.
         * @param  string $data
         */
        public function validate_webhook()
        {
            $this->debug_log("Checking Webhook response is valid.");

            $key = 'HTTP_' . strtoupper(str_replace('-', '_', 'RT-UDDOKTAPAY-API-KEY'));
            if (!isset($_SERVER[$key])) {
                $this->debug_log("Key not found.");
                return false;
            }

            $api = trim($_SERVER[$key]);

            $api_key = trim(get_option('wpjobster_uddoktapay_api_key'));

            if ($api_key === $api) {
                return true;
            }

            return false;
        }



        public function success($payment_type, $order_id, $payment_details, $payment_response)
        {
            do_action("wpjobster_" . $payment_type . "_payment_success", $order_id, $this->_unique_id, $payment_details, $payment_response);
        }

        public function cancel($payment_type, $order_id, $payment_details, $payment_response)
        {
            do_action("wpjobster_" . $payment_type . "_payment_failed", $order_id, $this->_unique_id, $payment_details, $payment_response);
        }

        public function other($payment_type, $order_id, $payment_details, $payment_response, $payment_status = 'pending')
        {
            do_action("wpjobster_" . $payment_type . "_payment_other", $order_id, $this->_unique_id, $payment_details, $payment_response, $payment_status);
        }

        public function debug_log($text)
        {
            if (!$this->enable_log) return;
            $fp = fopen('.wpj_uddoktapay.log', 'a');
            fwrite($fp, $text . "\n");
            fclose($fp);
        }
    } // END CLASS

    $GLOBALS['WPJobster_UddoktaPay_Loader'] = WPJobster_UddoktaPay_Loader::get_instance();
} // END IF CLASS EXIST