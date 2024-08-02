<?php
namespace Anar;
use Anar\AWCA_Product;

class AWCA_Checkout {

    public function __construct() {

        add_action( 'woocommerce_review_order_before_shipping', [$this, 'display_anar_products_shipping'] , 99);
        add_filter( 'woocommerce_shipping_package_name', [$this, 'prefix_shipping_package_name_other_products'] );
        add_action( 'woocommerce_cart_calculate_fees', [$this, 'add_custom_fee'] );
        add_action( 'woocommerce_checkout_create_order', [$this, 'add_custom_fee_to_order'], 20, 1 );
        add_action( 'woocommerce_checkout_update_order_review', [$this, 'checkout_delivery_choice_to_session'] );
        add_action('woocommerce_checkout_process', [$this, 'my_custom_checkout_field_process']);
    }

    /**
     * @return void
     */
    public function display_anar_products_shipping () {

        // Check if the billing city is filled
        if (empty(WC()->customer->get_billing_city())) {
            return; // Exit the function if billing city is not filled
        }

        global $woocommerce;
        $items = $woocommerce->cart->get_cart();
        $ship = [];
        foreach($items as $item => $values) {
            $_product =  wc_get_product( $values['data']->get_id());
            $value = null;
            $product_parent_id = $_product->get_parent_id();

            if($product_parent_id == 0){
                $value = get_post_meta($_product->get_id(),'_anar_products');
            } else {
                $value = get_post_meta($product_parent_id,'_anar_products');
            }
            if ($value) {
                // anar product
//                $sku = awca_get_anar_sku($_product->get_id());
//                $productShipment = AWCA_Product::get_all_products_shipments();
//
//                $val = $productShipment[$sku];
                $val = AWCA_Product::get_anar_product_shipments($_product->get_id());
                if (isset($ship[$val['shipmentId']])) {
                    $ship[$val['shipmentId']]['names'][] = $_product->get_title();
                } else {
                    $ship[$val['shipmentId']] = [
                        'delivery' => $val['delivery'],
                        'names' => [],
                    ];
                    $ship[$val['shipmentId']]['names'][] = $_product->get_title();
                }
            }
        }


        foreach ($ship as $key => $v) {
            $tags = implode(', ', $v['names']);
            $names = [];
            foreach ($v['delivery'] as $vkey => $value) {
                if($value['price'] == 0) {
                    $value['price'] = 10000;
                }
                $names[$vkey] = $value['name'] . '(' . $value['estimatedTime'] . ') : قیمت ' . $value['price'];
            }

            $chosen = WC()->session->get( 'anar_delivery_option_'.$key );
            $chosen = empty( $chosen ) ? WC()->checkout->get_value( 'anar_delivery_option_'.$key ) : $chosen;
            $chosen = empty( $chosen ) ? '0' : $chosen;

            ?>
            <tr id="anar-shipments-checkout">
                <th>حمل و نقل (<?php echo $tags; ?>)</th>
                <td>
                    <?php
                    woocommerce_form_field( 'anar_delivery_option_'.$key,  array(
                        'type'      => 'radio',
                        'required'      => true,
                        'class'     => array( 'form-row-wide', 'update_totals_on_change' ),
                        'options'   => $names,
                    ), $chosen);
                    ?>
                </td>
            </tr>
            <?php
        }
    }


    /**
     * Add non Anar product name as a prefix to shipping package name in checkout review products section
     * The goal is user knows shipping of Anar products not same as other products
     *
     * @param $name
     * @return string
     */
    public function prefix_shipping_package_name_other_products( $name ) {

        $res = AWCA_Product::get_anar_products_in_cart();

        $anarProducts = $res[0];
        $otherProducts = $res[1];

        $tags = implode(', ', $otherProducts);

        return "حمل و نقل ($tags)";
    }



    public function add_custom_fee( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        $resShip = AWCA_Product::get_all_products_shipments_ref();
        $resPro = AWCA_Product::get_anar_products_in_cart();

        $anarProducts = $resPro[0];
        $otherProducts = $resPro[1];

        $tags = implode(', ', $anarProducts);

        $name = "حمل و نقل ($tags)";

        if ( isset( $_POST['post_data'] ) ) {
            parse_str( $_POST['post_data'], $post_data );
            $fee = 0;
            foreach ($resShip as $key => $value) {
                if ( isset( $post_data['anar_delivery_option_'.$key] ) ) {

                    $delivery = $value['delivery'];
                    if ( isset( $delivery[$post_data['anar_delivery_option_'.$key]] ) ) {
                        $value = $delivery[$post_data['anar_delivery_option_'.$key]];
                        if($value['price'] == 0) {
                            $value['price'] = 10000;
                        }
                        $fee = $fee + $value['price'];
                    }
                }
            }
            $fee = $fee + WC()->cart->get_shipping_total();
            $cart->add_fee("حمل و نقل کلی", $fee );
        }
    }


    public function add_custom_fee_to_order( $order) {
        $ship = [];
        $anar = false;

        foreach ($order->get_items() as $item_id => $item ) {
            $_product =  $item->get_product();
            $value = null;
            $product_parent_id = $_product->get_parent_id();

            if($product_parent_id == 0){
                $value = get_post_meta($_product->get_id(),'_anar_products');
            } else {
                $value = get_post_meta($product_parent_id,'_anar_products');
            }
            if ($value) {
                $anar = true;
                // anar product
                $sku = awca_get_anar_sku($_product->get_id());
                $productShipment = AWCA_Product::get_all_products_shipments();
                $val = $productShipment[$sku];
                if (isset($ship[$val['shipmentId']])) {
                    $ship[$val['shipmentId']]['names'][] = $_product->get_title();
                } else {
                    $ship[$val['shipmentId']] = [
                        'delivery' => $val['delivery'],
                        'names' => [],
                    ];
                    $ship[$val['shipmentId']]['names'][] = $_product->get_title();
                }
            }
        }

        if ($anar) {
            update_post_meta($order->get_id(), '_anar_should_pay', true);
            $order->save();
        }

        foreach ($ship as $key => $v) {
            $tags = implode(', ', $v['names']);
            $names = [];

            $chosen = WC()->session->get( 'anar_delivery_option_'.$key );
            $chosen = empty( $chosen ) ? WC()->checkout->get_value( 'anar_delivery_option_'.$key ) : $chosen;
            $chosen = empty( $chosen ) ? '0' : $chosen;

            $value = $chosen;

            if ( $value && isset( $value ) && $value != '0' ) {
                $p = 0;

                foreach ($v['delivery'] as $vkey => $vv) {
                    if ($vkey == $value) {
                        $p = $vv['price'];

                    }
                }

                $total_fee = 0;
                $name = "حمل و نقل ($tags)";
                $total_fee = $p;

                if($total_fee == 0) {
                    $total_fee = 10000;
                }
                $fee = new WC_Order_Item_Fee();
                $fee->set_name($name);
                $fee->set_total($total_fee);
                $order->add_item($fee);
                $order->calculate_totals();
                $order->save();
            }
        }
    }


    public function checkout_delivery_choice_to_session( $posted_data ) {
        parse_str( $posted_data, $output );
        $resShip = AWCA_Product::get_all_products_shipments_ref();
        foreach ($resShip as $key => $v) {
            if ( isset( $output['anar_delivery_option_'.$key] ) ){
                WC()->session->set( 'anar_delivery_option_'.$key, $output['anar_delivery_option_'.$key] );
            }
        }
    }


    public function my_custom_checkout_field_process() {
        if ( isset($_POST['anar_delivery_option']) && empty($_POST['anar_delivery_option']) ) {
            wc_add_notice( __( '"anar_delivery_option option field" is a required field.' ), 'error' );
        }
    }
}
