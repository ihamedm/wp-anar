<?php






//add_action( 'woocommerce_checkout_before_order_review', 'action_woocommerce_cart_totals_before_shipping' );
function action_woocommerce_cart_totals_before_shipping() {
    global $woocommerce;
    $items = $woocommerce->cart->get_cart();
    $ship = [];
    $siteProduct = false;
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
            $sku = awca_get_anar_sku($_product->get_id());
            $productShipment = getProductShipment();
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
        } else {
            return null;
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
            <tr> 
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





