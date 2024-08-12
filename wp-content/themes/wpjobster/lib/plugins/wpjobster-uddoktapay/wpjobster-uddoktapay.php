<?php

// Exit if the file is accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include plugin library
require_once plugin_dir_path(__FILE__) . 'lib/class-uddoktapay-api-handler.php';

if (!class_exists('WPJobster_UddoktaPay_Loader')) {

    class WPJobster_UddoktaPay_Loader extends \stdClass
    {

        private $uddoktaPay;

        public function __construct($gateway = 'uddoktapay')
        {
            // Define gateway slug
            $this->unique_id = 'uddoktapay';

            // Add gateway to payment methods list
            add_filter('wpj_payment_gateways_filter', function ($payment_gateways_list) {
                $payment_gateways_list[$this->unique_id] = __('UddoktaPay', 'wpjobster');
                return $payment_gateways_list;
            }, 10, 1);

            // Add gateway option to admin
            add_filter('wpj_admin_settings_items_filter', function ($menu) {
                $menu['payment-gateways']['childs'][$this->unique_id] = ['order' => '02a', 'path' => get_template_directory() . '/lib/plugins/wpjobster-uddoktapay/admin-fields.php'];
                return $menu;
            }, 10, 1);

            // Add gateway to payment process flow
            add_action('wpjobster_taketo_' . $this->unique_id . '_gateway', [$this, 'initializePayment'], 10, 2);
            add_action('wpjobster_processafter_' . $this->unique_id . '_gateway', [$this, 'processPayment'], 10, 2);

            // Set gateway currencies
            add_filter('wpjobster_take_allowed_currency_' . $this->unique_id, [$this, 'setGatewayCurrencies']);

            // Init payment library class
            $this->init_api();
        }

        /**
         * Init the API class and set the API key etc.
         */
        protected function init_api()
        {
            $this->uddoktaPay = new UddoktaPay(trim(wpj_get_option('wpjobster_uddoktapay_api_key')), trim(wpj_get_option('wpjobster_uddoktapay_api_url')));
        }

        public static function init()
        {$class = __CLASS__;new $class;}

        public function initializePayment($payment_type, $order_details)
        { // params from gateways/init.php

            $user = wp_get_current_user();

            // Payment ROW
            $payment_row = wpj_get_payment(['payment_type_id' => $order_details['id'], 'payment_type' => $payment_type]);

            // Callback URL
            $callback_url = get_bloginfo('url') . '/?payment_response=' . $this->unique_id . '&payment_id=' . $payment_row->id;

            // Normal Purchase Vars
            $requestData = [
                'full_name'    => $user->user_login,
                'email'        => $user->user_email,
                'amount'       => $payment_row->final_amount_exchanged,
                'metadata'     => [
                    'payment_id' => $payment_row->id,
                ],
                'redirect_url' => $callback_url . '&action=paid',
                'return_type'  => 'GET',
                'cancel_url'   => $callback_url . '&action=cancelled',
                'webhook_url'  => $callback_url . '&action=ipn',
            ];

            try {
                $paymentUrl = $this->uddoktaPay->initPayment($requestData);
                wp_redirect($paymentUrl);
                exit();
            } catch (\Exception $e) {
                die("Initialization Error: " . $e->getMessage());
            }
        }

        public function processPayment($payment_type, $payment_type_class)
        { // params from gateways/init.php
            if (isset($_REQUEST['payment_id'])) {
                $payment_id = $_REQUEST['payment_id'];
                $payment_row = wpj_get_payment(['id' => $payment_id]);

                $order_id = $payment_row->payment_type_id;
                $payment_type = $payment_row->payment_type;

                $order = wpj_get_order_by_payment_type($payment_type, $order_id);

                try {
                    if (isset($_REQUEST['invoice_id'])) {
                        $response = $this->uddoktaPay->verifyPayment($_REQUEST['invoice_id']);
                    } else {
                        $response = $this->uddoktaPay->executePayment();
                    }
                } catch (Exception $e) {
                    die("Verification Error: " . $e->getMessage());
                }

                $payment_response = json_encode($response);
                $response_decoded = wpj_json_decode($payment_response);
                $payment_status = isset($response_decoded->status) ? strtolower($response_decoded->status) : '';
                $payment_details = $response_decoded->transaction_id;

                // Save response
                $webhook = wpj_save_webhook([
                    'webhook_id'       => $response_decoded->invoice_id,
                    'payment_id'       => $response_decoded->metadata->payment_id,
                    'status'           => $payment_status,
                    'type'             => WPJ_Form::get('action') . ' ' . $response_decoded->transaction_id,
                    'description'      => $payment_type,
                    'amount'           => $response_decoded->amount,
                    'amount_currency'  => 'BDT',
                    'fees'             => $response_decoded->fee,
                    'fees_currency'    => 'BDT',
                    'create_time'      => isset($response_decoded->date) ? strtotime($response_decoded->date) : current_time('timestamp', 1),
                    'payment_response' => $payment_response,
                    'payment_type'     => $payment_type,
                    'order_id'         => $order_id,
                ]);

                // Apply response to order
                if (WPJ_Form::get('action') == 'cancelled' && $order->payment_status != 'cancelled') { // mark order as cancelled
                    do_action("wpjobster_" . $payment_type . "_payment_failed", $order_id, $this->unique_id, 'Buyer clicked cancel', $payment_response);

                }

                if (WPJ_Form::get('action') == 'paid' && $order->payment_status != 'completed' && $payment_status == 'completed') {
                    do_action("wpjobster_" . $payment_type . "_payment_success", $order_id, $this->unique_id, $payment_details, $payment_response);
                } elseif (WPJ_Form::get('action') == 'ipn' && $order->payment_status != 'completed' && $payment_status == 'completed') {
                    do_action("wpjobster_" . $payment_type . "_payment_success", $order_id, $this->unique_id, $payment_details, $payment_response);
                } else {
                    do_action("wpjobster_" . $payment_type . "_payment_other", $order_id, $this->unique_id, $payment_details, $payment_response, $payment_status);
                }
            }
        }

        public function setGatewayCurrencies($currency)
        {
            return 'BDT';
        }
    } // END CLASS

} // END IF CLASS EXIST

add_action('after_setup_theme', ['WPJobster_UddoktaPay_Loader', 'init']);