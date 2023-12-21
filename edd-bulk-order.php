<?php
/**
 * Plugin Name: EDD Bulk Order
 * Description: A plugin to submit multiple orders at once for Easy Digital Downloads.
 * Version: 1.0
 * Author: Marko Krstic
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('admin_menu', 'edd_bulk_order_menu');

function edd_bulk_order_menu() {
    add_menu_page('EDD Bulk Order', 'EDD Bulk Order', 'manage_options', 'edd-bulk-order', 'edd_bulk_order_page');
}

function edd_bulk_order_page() {
    ?>
    <div class="wrap">
        <h2>EDD Bulk Order</h2>
        <form method="post" action="">
            <?php wp_nonce_field('edd_bulk_order_nonce', 'edd_bulk_order_nonce_field'); ?>
            <p>
                <label for="product_id">Product ID:</label>
                <input type="text" name="product_id" id="product_id" />
            </p>
            <p>
                <label for="email_addresses">Email Addresses (one per line):</label>
                <textarea name="email_addresses" id="email_addresses" rows="10"></textarea>
            </p>
            <p>
                <input type="submit" value="Submit Orders" class="button-primary"/>
            </p>
        </form>
    </div>
    <?php
    edd_handle_bulk_order_submission();
}

function edd_handle_bulk_order_submission() {
    if (!isset($_POST['edd_bulk_order_nonce_field']) || !wp_verify_nonce($_POST['edd_bulk_order_nonce_field'], 'edd_bulk_order_nonce')) {
        return;
    }

    if (isset($_POST['product_id']) && !empty($_POST['product_id']) && isset($_POST['email_addresses']) && !empty($_POST['email_addresses'])) {
        $product_id = sanitize_text_field($_POST['product_id']);
        $emails = explode("\n", sanitize_textarea_field($_POST['email_addresses']));

        foreach ($emails as $email) {
            if (is_email(trim($email))) {
                edd_create_order_for_email($product_id, trim($email));
            }
        }
    }
}

function edd_create_order_for_email($product_id, $email) {
    $payment_data = array(
        'price'        => edd_get_download_price($product_id),
        'date'         => date('Y-m-d H:i:s'),
        'user_email'   => $email,
        'purchase_key' => strtolower(md5(uniqid())),
        'currency'     => edd_get_currency(),
        'downloads'    => array($product_id),
        'user_info'    => array(
            'id'         => '',
            'email'      => $email,
            'first_name' => '',
            'last_name'  => '',
            'discount'   => 'none'
        ),
        'cart_details' => array(
            array(
                'name'        => get_the_title($product_id),
                'id'          => $product_id,
                'price'       => edd_get_download_price($product_id),
                'quantity'    => 1
            )
        ),
        'gateway'      => 'manual', // Set the payment gateway to manual
        'status'       => 'pending'
    );

    $payment = edd_insert_payment($payment_data);
    if ($payment) {
        edd_update_payment_status($payment, 'complete');
    }
}
