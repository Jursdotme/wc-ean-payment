<?php
/*
Plugin Name:  WooCommerce EAN Payment gateway
Plugin URI:   https://norsemedia.dk/plugins
Description:  Take payment from people using EAN (European Article Number).
Version:      1.0.0
Author:       Norse Media
Author URI:   https://norsemedia.dk
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  norse-ean
Domain Path:  /languages
*/

add_action('init', 'wpdocs_load_textdomain');

/**
 * Load plugin textdomain.
 */
function wpdocs_load_textdomain() {
    load_plugin_textdomain('norse-ean', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

defined('ABSPATH') or exit;
// Make sure WooCommerce is active
if (!function_exists('is_woocommerce_activated')) {
    function is_woocommerce_activated() {
        if (class_exists('woocommerce')) {
            return true;
        } else {
            return false;
        }
    }
}

/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + offline gateway
 */
function wc_offline_add_to_gateways($gateways) {
    $gateways[] = 'WC_Gateway_Offline';
    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'wc_offline_add_to_gateways');
/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_offline_gateway_plugin_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=offline_gateway') . '">' . __('Configure', 'norse-ean') . '</a>'
    );
    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wc_offline_gateway_plugin_links');
/**
 * Offline Payment Gateway
 *
 * Provides an Offline Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Gateway_Offline
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		SkyVerge
 */
add_action('plugins_loaded', 'wc_offline_gateway_init', 11);

// display the extra data in the order admin panel
function norse_display_ean_data_in_admin($order) {
    $meta = maybe_unserialize(get_post_meta($order->id, '_norse_ean_payment', true));
    if (!empty($meta)) {
?>
        <div class="clearfix"></div>
        <h3><?php _e('EAN Details'); ?></h3>
        <?php
        echo '<p><strong>' . __('EAN') . ':</strong> ' . $meta['ean_num'] . '</p>';
        echo '<p><strong>' . __('Reference name') . ':</strong> ' . $meta['ref_name'] . '</p>';
        echo '<p><strong>' . __('Requisition number') . ':</strong> ' . $meta['req_num'] . '</p>';
    }
}
add_action('woocommerce_admin_order_data_after_order_details', 'norse_display_ean_data_in_admin');

function wc_offline_gateway_init() {
    class WC_Gateway_Offline extends WC_Payment_Gateway {
        /**
         * Constructor for the gateway.
         */
        public function __construct() {
            $this->id                 = 'offline_gateway';
            $this->icon               = apply_filters('woocommerce_offline_icon', '');
            $this->has_fields         = true;
            $this->method_title       = __('EAN Payment', 'norse-ean');
            $this->method_description = __('Allows offline payments. Very handy if you use your cheque gateway for another payment method, and can help with testing. Orders are marked as "on-hold" when received.', 'norse-ean');

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title        = $this->get_option('title');
            $this->description  = $this->get_option('description');

            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_instructions'));

            // Customer Emails
            add_action('woocommerce_email_after_order_table', array($this, 'email_instructions'), 10, 3);
        }

        /**
         * Initialize Gateway Settings Form Fields
         */
        public function init_form_fields() {

            $this->form_fields = apply_filters('wc_offline_form_fields', array(

                'enabled' => array(
                    'title'   => __('Enable/Disable', 'norse-ean'),
                    'type'    => 'checkbox',
                    'label'   => __('Enable EAN Payment', 'norse-ean'),
                    'default' => 'yes'
                ),

                'title' => array(
                    'title'       => __('Title', 'norse-ean'),
                    'type'        => 'text',
                    'description' => __('This controls the title for the payment method the customer sees during checkout.', 'norse-ean'),
                    'default'     => __('EAN Payment', 'norse-ean'),
                    'desc_tip'    => true,
                ),

                'description' => array(
                    'title'       => __('Description', 'norse-ean'),
                    'type'        => 'textarea',
                    'description' => __('Payment method description that the customer will see on your checkout.', 'norse-ean'),
                    'default'     => __('Please remit payment to Store Name upon pickup or delivery.', 'norse-ean'),
                    'desc_tip'    => true,
                )

            ));
        }


        public function payment_fields() { ?>
            <p class="form-row form-row-wide">
                <label for="gc-wc-ean-num"><?php esc_html_e('EAN number', 'norse-ean'); ?> </label>
                <input type="text" id="gc-wc-ean-num" name="norse_ean_payment[ean_num]" class="input-text">
            </p>
            <p class="form-row form-row-wide">
                <label for="gc-wc-ref-name"><?php esc_html_e('Reference person', 'norse-ean'); ?> </label>
                <input type="text" id="gc-wc-ref-name" name="norse_ean_payment[ref_name]" class="input-text">
            </p>
            <p class="form-row form-row-wide">
                <label for="gc-wc-req-num"><?php esc_html_e('Requisition number', 'norse-ean'); ?> </label>
                <input type="text" id="gc-wc-req-num" name="norse_ean_payment[req_num]" class="input-text">
            </p>
<?php }

        public function validate_fields() {
            $valid = true;

            function getEan13CheckDigit($param) {
                $sum = 0;
                $odd = true;

                for ($i = 11; $i >= 0; $i--) {
                    $val  = intval(substr($param, $i, 1));
                    $val *= ($odd ? 3 : 1);
                    $odd = !$odd;

                    $sum += $val;
                }

                $check = 10 - ($sum % 10);

                return ($check == 10 ? 0 : $check);
            }

            function validateEan13($ean) {
                $valid = preg_match("/^\d{13}$/", $ean);
                $valid = $valid && (substr($ean, 12) == getEan13CheckDigit(substr($ean, 0, 12)));

                return $valid;
            }

            if (empty($_POST['norse_ean_payment'])) {
                wc_add_notice(__('Please add EAN details.', 'norse-ean'), 'error');
                $valid = false;
            } else {
                $data = $_POST['norse_ean_payment'];
                if (empty($data['ean_num'])) {
                    wc_add_notice(__('Please enter an EAN.', 'norse-ean'), 'error');
                    $valid = false;
                } else {
                    if (strlen($data['ean_num']) != 13) {
                        wc_add_notice(__('EAN must be 13 characters.', 'norse-ean'), 'error');
                        $valid = false;
                    } else {
                        if (!validateEan13($data['ean_num'])) {
                            wc_add_notice(__('EAN is not valid.', 'norse-ean'), 'error');
                            $valid = false;
                        }
                    }
                }

                // if ( empty( $data['ref_name'] ) ) {
                //     wc_add_notice( __( 'Please enter a reference name.', 'norse-ean' ), 'error' );
                //     $valid = false;
                // }
                // if ( empty( $data['req_num'] ) ) {
                //     wc_add_notice( __( 'Please enter a requsition number.', 'norse-ean' ), 'error' );
                //     $valid = false;
                // }
            }
            return $valid;
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page() {
            if ($this->instructions) {
                echo wpautop(wptexturize($this->instructions));
            }
        }

        /**
         * Add content to the WC emails.
         *
         * @access public
         * @param WC_Order $order
         * @param bool $sent_to_admin
         * @param bool $plain_text
         */
        public function email_instructions($order, $sent_to_admin, $plain_text = false) {

            //$order_id = get_the_ID();
            $meta = maybe_unserialize(get_post_meta($order->id, '_norse_ean_payment', true));
            if (!empty($meta)) {
                echo '<div style="margin-bottom:40px">';
                echo '<h2>' . __('EAN Information', 'norse-ean') . '</h2>';
                echo '<table class="td" cellspacing="0" cellpadding="6" style="width:100%;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif;color:#636363;border:1px solid #e5e5e5;vertical-align:middle" border="1">';
                echo '<tr class="' . esc_attr(apply_filters('woocommerce_order_item_class', 'order_item', $item, $order)) . '">';
                echo '<td class="td"><strong>' . __('EAN', 'norse-ean') . ':</strong></td><td class="td">' . $meta['ean_num'] . '</td>';
                echo '</tr>';
                echo '<tr class="' . esc_attr(apply_filters('woocommerce_order_item_class', 'order_item', $item, $order)) . '">';
                echo '<td class="td"><strong>' . __('Reference name', 'norse-ean') . ':</strong></td><td class="td">' . $meta['ref_name'] . '</td>';
                echo '</tr>';
                echo '<tr class="' . esc_attr(apply_filters('woocommerce_order_item_class', 'order_item', $item, $order)) . '">';
                echo '<td class="td"><strong>' . __('Requisition number', 'norse-ean') . ':</strong></td><td class="td">' . $meta['req_num'] . '</td>';
                echo '</tr>';
                echo '</table>';
                echo '</div>';
            }
        }


        /**
         * Process the payment and return the result
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment($order_id) {

            if (isset($_POST['norse_ean_payment']) && is_array($_POST['norse_ean_payment'])) {
                $meta = array();
                $fields = array("ean_num", "ref_name", "req_num");
                foreach ($fields as $field) {
                    if (!empty($_POST['norse_ean_payment'][$field])) {
                        $meta[$field] = sanitize_text_field($_POST['norse_ean_payment'][$field]);
                    }
                }
                update_post_meta($order_id, '_norse_ean_payment', $meta);
            }

            $order = wc_get_order($order_id);

            // Mark as on-hold (we're awaiting the payment)
            $order->update_status('on-hold', __('Awaiting EAN payment.', 'norse-ean'));

            // Reduce stock levels
            $order->reduce_order_stock();

            // Remove cart
            WC()->cart->empty_cart();

            // Return thankyou redirect
            return array(
                'result'     => 'success',
                'redirect'    => $this->get_return_url($order)
            );
        }
    } // end \WC_Gateway_Offline class
}
