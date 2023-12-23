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

function edd_bulk_order_menu()
{
    // Check if the current user has the 'administrator' role.
    if (!current_user_can('administrator')) {
        return; // Exit the function if the user is not an admin.
    }

    add_menu_page('EDD Bulk Order', 'EDD Bulk Order', 'manage_options', 'edd-bulk-order', 'edd_bulk_order_page');
}

function edd_bulk_order_page()
{
    // Fetch all downloads using WP_Query
    $args = array(
        'post_type'      => 'download',
        'posts_per_page' => -1  // Retrieve all downloads
    );
    $download_query = new WP_Query($args);

?>
    <style>
        #email_addresses, select {
            min-width: 500px;
        }
    </style>

    <div class="wrap">
        <h2>EDD Bulk Order</h2>
        <form method="post" action="">
            <?php wp_nonce_field('edd_bulk_order_nonce', 'edd_bulk_order_nonce_field'); ?>
            <p>
                <label for="product_id">Select Download:</label>
            </p>
            <p>
                <select name="product_id" id="product_id">
                    <option value="">-- Select a Download --</option>
                    <?php
                    if ($download_query->have_posts()) :
                        while ($download_query->have_posts()) : $download_query->the_post();
                    ?>
                            <option value="<?php echo get_the_ID(); ?>"><?php the_title(); ?></option>
                    <?php
                        endwhile;
                    endif;
                    wp_reset_postdata();
                    ?>
                </select>
            </p>
            <br>
            <p>
                <label for="email_addresses">Email Addresses (one per line):</label>
            </p>
            <p>
                <textarea name="email_addresses" id="email_addresses" rows="10"></textarea>
            </p>
            <br>
            <p>
                <input type="submit" value="Submit Orders" class="button-primary" />
            </p>
        </form>
    </div>
<?php
    edd_handle_bulk_order_submission();
}




function edd_handle_bulk_order_submission()
{
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

function edd_create_order_for_email($product_id, $email)
{
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
