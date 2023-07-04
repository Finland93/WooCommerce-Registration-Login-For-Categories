<?php
/*
Plugin Name: Registration for Categories
Plugin URI: https://github.com/Finland93/WooCommerce-Registration-Login-For-Categories
Description: Requires user registration or login for specific product categories in WooCommerce and allows users to order other products without login & registration.
Version: 1.0
Author: Finland93
Author URI: https://github.com/Finland93
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// Exit if directly accessed
if (!defined('ABSPATH')) {
    exit; 
}

// Plugin activation hook
register_activation_hook( __FILE__, 'registration_for_categories_activate' );

function registration_for_categories_activate() {
    // Check if WooCommerce is installed
    if ( ! class_exists( 'WooCommerce' ) ) {
        // Display admin notice to install WooCommerce
        add_action( 'admin_notices', 'registration_for_categories_install_woocommerce_notice' );

        // Deactivate the plugin
        deactivate_plugins( plugin_basename( __FILE__ ) );
        return;
    }
}

// Admin notice to install WooCommerce
function registration_for_categories_install_woocommerce_notice() {
    echo '<div class="error"><p><strong>Registration for Categories</strong> requires <a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a> to be installed and activated.</p></div>';
}

// Plugin settings menu
add_action( 'admin_menu', 'registration_for_categories_menu' );

function registration_for_categories_menu() {
    add_menu_page(
        'Registration for Categories',
        'Registration for Categories',
        'manage_options',
        'registration-for-categories',
        'registration_for_categories_settings_page',
        'dashicons-admin-generic',
        99
    );
}

// Plugin settings page
function registration_for_categories_settings_page() {
    // Save settings if form is submitted
    if ( isset( $_POST['registration_for_categories_submit'] ) && check_admin_referer( 'registration_for_categories_settings', 'registration_for_categories_nonce' ) ) {
        $category_slugs = isset( $_POST['registration_for_categories_slugs'] ) ? array_map( 'sanitize_text_field', $_POST['registration_for_categories_slugs'] ) : array();
        update_option( 'registration_for_categories_slugs', implode( ',', $category_slugs ) );
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    // Get current settings
    $category_slugs = get_option( 'registration_for_categories_slugs', '' );
    
    // Retrieve product categories
    $categories = get_terms( array(
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    ) );

    $selected_slugs = explode( ',', $category_slugs );

    // Pagination variables
    $categories_per_page = 50;
    $total_categories = count( $categories );
    $total_pages = ceil( $total_categories / $categories_per_page );
    $current_page = isset( $_GET['registration_for_categories_page'] ) ? absint( $_GET['registration_for_categories_page'] ) : 1;
    $offset = ( $current_page - 1 ) * $categories_per_page;

    // Get categories for the current page
    $categories_slice = array_slice( $categories, $offset, $categories_per_page );

    ?>
    <style>
        .pagination-links {
            margin-top: 10px;
        }

        .pagination-links a {
            display: inline-block;
            padding: 5px 10px;
            margin-right: 5px;
            background-color: #f0f0f0;
            border: 1px solid #ccc;
            text-decoration: none;
            color: #333;
        }

        .pagination-links a.active {
            background-color: #0073aa;
            border-color: #0073aa;
            color: #fff;
        }
    </style>

    <div class="wrap">
        <h1>Registration for Categories</h1>
        <form method="post" action="">
            <?php wp_nonce_field( 'registration_for_categories_settings', 'registration_for_categories_nonce' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Product Categories:</th>
                    <td>
                        <?php
                        foreach ( $categories_slice as $category ) {
                            $checked = in_array( $category->slug, $selected_slugs, true ) ? 'checked' : '';
                            ?>
                            <label>
                                <input type="checkbox" name="registration_for_categories_slugs[]" value="<?php echo esc_attr( $category->slug ); ?>" <?php echo esc_attr( $checked ); ?>>
                                <?php echo esc_html( $category->name ); ?>
                            </label>
                            <br>
                            <?php
                        }
                        ?>
                        <p class="description">Select the product categories for which user registration or login is required during checkout.</p>
                    </td>
                </tr>
            </table>
            <input type="submit" name="registration_for_categories_submit" class="button-primary" value="Save Settings">
        </form>

        <div class="pagination-links">
            <?php
            for ( $page = 1; $page <= $total_pages; $page++ ) {
                $active = $current_page === $page ? 'active' : '';
                echo '<a href="?page=registration-for-categories&registration_for_categories_page=' . esc_attr( $page ) . '" class="' . esc_attr( $active ) . '">' . esc_html( $page ) . '</a>';
            }
            ?>
        </div>

        <h2>How to use this plugin</h2>
        <p>To make this plugin work correctly, follow these steps:</p>
        <ol>
            <li>Ensure that WooCommerce is installed and activated on your WordPress site.</li>
            <li>In the WordPress admin area, go to "WooCommerce" and then "Settings".</li>
            <li>Click on the "Accounts & Privacy" tab</li>
            <li>Be sure you have enabled "Allow customers to place orders without an account", "Allow customers to log into an existing account during checkout" and "Allow customers to create an account during checkout" </li>
            <li>Save the changes.</li>
            <li>Go back to the "Registration for Categories" plugin settings page.</li>
            <li>Select the categories for which you want to enforce registration or login during checkout.</li>
            <li>Save the settings.</li>
        </ol>
    </div>
    <?php
}

// WooCommerce checkout registration filter
add_filter( 'woocommerce_checkout_registration_required', 'registration_for_categories_filter_registration_required', 10, 1 );

function registration_for_categories_filter_registration_required( $bool_value ) {
    $category_slugs = get_option( 'registration_for_categories_slugs', '' );

    if ( is_user_logged_in() ) {
        $user_email = wp_get_current_user()->user_email;
        $require_registration = false; // Assume registration is not required by default

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $product_categories = get_the_terms( $cart_item['product_id'], 'product_cat' );

            foreach ( $product_categories as $category ) {
                if ( in_array( $category->slug, explode( ',', $category_slugs ), true ) ) {
                    $require_registration = true; // If a product category requires registration, set $require_registration to true
                    break 2;
                }
            }
        }

        if ( $require_registration ) {
            // Check if the user has an account registered with their email
            $user = get_user_by( 'email', $user_email );

            if ( $user && ! empty( $user->ID ) ) {
                // If the user has an account, no registration is required
                $bool_value = false;
            } else {
                // If the user doesn't have an account, registration is required
                $bool_value = true;
            }
        } else {
            // No registration is required if no product category requires it
            $bool_value = false;
        }
    } else {
        // Check if any product in the cart belongs to the "registration not needed" category
        $registration_not_needed = false;

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $product_categories = get_the_terms( $cart_item['product_id'], 'product_cat' );

            foreach ( $product_categories as $category ) {
                if ( ! in_array( $category->slug, explode( ',', $category_slugs ), true ) ) {
                    $registration_not_needed = true; // If any product doesn't belong to the registered categories, set $registration_not_needed to true
                    break 2;
                }
            }
        }

        if ( $registration_not_needed ) {
            // No registration is required if any product doesn't belong to the registered categories
            $bool_value = false;
        } else {
            $bool_value = true; // Require registration if user is not logged in and all products belong to the registered categories
        }
    }

    return $bool_value;
}

register_uninstall_hook( __FILE__, 'registration_for_categories_uninstall' );

function registration_for_categories_uninstall() {
    // Delete the plugin settings when the plugin is uninstalled
    delete_option( 'registration_for_categories_slugs' );
}
