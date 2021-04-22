<?php

add_filter( 'woocommerce_gateway_description', 'my_pesa_description_fields', 20, 2 );

function my_pesa_description_fields( $description, $payment_id ) {

    if ( 'pesa_payment' !== $payment_id ) {
        return $description;
    }
    
    ob_start();

    echo '<div style="display: block; width:300px; height:auto;">';
    echo '<img src="' . plugins_url('../assets/img/mpesa_logo.png', __FILE__ ) . '">';
    

    woocommerce_form_field(
        'payment_number',
        array(
            'type' => 'text',
            'label' =>__( 'Payment Phone Number', 'pesa-pay-woo' ),
            'class' => array( 'form-row', 'form-row-wide' ),
            'required' => true,
        )
    );

    woocommerce_form_field(
        'paying_network',
        array(
            'type' => 'select',
            'label' => __( 'Payment Network : Safaricom', 'pesa-pay-woo' ),
            'class' => array( 'form-row', 'form-row-wide' ),
            'required' => true,
            'options' => array(
                'mpesa' => __( 'MPESA', 'pesa-pay-woo' ),
            ),
        )
    );

    echo '</div>';

    $description .= ob_get_clean();

    return $description;
}

add_action( 'woocommerce_checkout_process', 'my_pesa_description_fields_validation' );

function my_pesa_description_fields_validation() {
    if( 'pesa_payment' === $_POST['payment_method'] && ! isset( $_POST['payment_number'] )  || empty( $_POST['payment_number'] ) ) {
        wc_add_notice( 'Please enter a number that is to be billed', 'error' );
    }
}

add_action( 'woocommerce_checkout_update_order_meta', 'pesa_checkout_update_order_meta', 10, 1 );

function pesa_checkout_update_order_meta( $order_id ) {
    if( isset( $_POST['payment_number'] ) || ! empty( $_POST['payment_number'] ) ) {
       update_post_meta( $order_id, 'payment_number', $_POST['payment_number'] );
    }
}

add_action( 'woocommerce_admin_order_data_after_billing_address', 'pesa_order_data_after_billing_address', 10, 1 );

function pesa_order_data_after_billing_address( $order ) {
    echo '<p><strong>' . __( 'Payment Phone Number:', 'pesa-pay-woo' ) . '</strong><br>' . get_post_meta( $order->get_id(), 'payment_number', true ) . '</p>';
}

add_action( 'woocommerce_order_item_meta_end', 'pesa_order_item_meta_end', 10, 3 );

function pesa_order_item_meta_end( $item_id, $item, $order ) {
    echo '<p><strong>' . __( 'Payment Phone Number:', 'pesa-pay-woo' ) . '</strong><br>' . get_post_meta( $order->get_id(), 'payment_number', true ) . '</p>';
}
