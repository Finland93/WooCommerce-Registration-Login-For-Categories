<?php
/*
Plugin Name: Registration for Categories
Plugin URI: https://github.com/Finland93/WooCommerce-Registration-Login-For-Categories
Description: Require registration or login at checkout when the cart contains products from selected WooCommerce categories; allow guest checkout for everything else.
Version: 2.0.0
Author: Finland93
Author URI: https://github.com/Finland93
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: registration-for-categories
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RFC_VERSION', '2.0.0' );
define( 'RFC_FILE', __FILE__ );

final class Registration_For_Categories {

	const OPTION = 'registration_for_categories_slugs';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( RFC_FILE, array( __CLASS__, 'activate' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public static function activate() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( plugin_basename( RFC_FILE ) );
			set_transient( 'rfc_wc_missing', 1, 60 );
		}
	}

	public function init() {
		load_plugin_textdomain( 'registration-for-categories', false, dirname( plugin_basename( RFC_FILE ) ) . '/languages' );

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'wc_missing_notice' ) );
			return;
		}

		add_filter( 'woocommerce_checkout_registration_required', array( $this, 'registration_required' ) );
	}

	public function wc_missing_notice() {
		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Registration for Categories', 'registration-for-categories' ) . '</strong> ' .
			esc_html__( 'requires WooCommerce to be installed and active.', 'registration-for-categories' ) . '</p></div>';
	}

	/* ---------------------------------------------------------------------
	 * Core logic
	 * ------------------------------------------------------------------- */

	/** Currently selected category slugs. */
	private function selected_slugs() {
		$raw = get_option( self::OPTION, '' );
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $raw ) ) ) );
	}

	/**
	 * True if the cart contains at least one product in any selected category.
	 *
	 * The 1.0 logic was inverted: it allowed guest checkout as soon as *any*
	 * cart item was outside the selected categories, so a restricted product
	 * slipped through whenever the cart also held an unrestricted one. This
	 * checks the correct direction — does any item belong to a restricted
	 * category — and guards get_the_terms() against false / WP_Error.
	 */
	private function cart_has_restricted_category() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		$selected = $this->selected_slugs();
		if ( empty( $selected ) ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id = isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0;
			if ( ! $product_id ) {
				continue;
			}

			$terms = get_the_terms( $product_id, 'product_cat' );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				if ( in_array( $term->slug, $selected, true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Force registration/login for guests when a restricted product is in the
	 * cart. Logged-in users already have an account, so leave them unchanged.
	 */
	public function registration_required( $required ) {
		if ( is_user_logged_in() ) {
			return $required;
		}
		if ( $this->cart_has_restricted_category() ) {
			return true;
		}
		return $required;
	}

	/* ---------------------------------------------------------------------
	 * Settings page
	 * ------------------------------------------------------------------- */

	public function admin_menu() {
		add_menu_page(
			__( 'Registration for Categories', 'registration-for-categories' ),
			__( 'Registration for Categories', 'registration-for-categories' ),
			'manage_options',
			'registration-for-categories',
			array( $this, 'render_settings_page' ),
			'dashicons-lock',
			99
		);
	}

	/** All product category terms (or empty array). */
	private function all_categories() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);
		return ( is_array( $terms ) ) ? $terms : array();
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$categories = $this->all_categories();
		$valid      = wp_list_pluck( $categories, 'slug' );

		// ---- Save (single form, all checkboxes submit together — no pagination
		// to lose selections from) ----
		if ( isset( $_POST['registration_for_categories_submit'] ) && check_admin_referer( 'registration_for_categories_settings', 'registration_for_categories_nonce' ) ) {
			$posted = isset( $_POST['registration_for_categories_slugs'] ) ? (array) $_POST['registration_for_categories_slugs'] : array();
			$posted = array_map( 'sanitize_title', array_map( 'wp_unslash', $posted ) );
			$clean  = array_values( array_intersect( $posted, $valid ) );
			update_option( self::OPTION, implode( ',', $clean ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'registration-for-categories' ) . '</p></div>';
		}

		$selected = $this->selected_slugs();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Registration for Categories', 'registration-for-categories' ); ?></h1>
			<p class="description" style="max-width:760px;">
				<?php esc_html_e( 'When a cart contains any product from the categories you select below, guests will be required to register or log in before they can place the order. Other carts can still be checked out as a guest.', 'registration-for-categories' ); ?>
			</p>

			<form method="post" action="">
				<?php wp_nonce_field( 'registration_for_categories_settings', 'registration_for_categories_nonce' ); ?>

				<h2><?php esc_html_e( 'Restricted product categories', 'registration-for-categories' ); ?></h2>

				<?php if ( empty( $categories ) ) : ?>
					<p><?php esc_html_e( 'No product categories found.', 'registration-for-categories' ); ?></p>
				<?php else : ?>
					<input type="search" id="rfc-filter" placeholder="<?php esc_attr_e( 'Filter categories…', 'registration-for-categories' ); ?>" class="regular-text" style="margin-bottom:8px;">
					<div id="rfc-cat-list" style="max-height:360px;overflow:auto;border:1px solid #dcdcde;background:#fff;padding:10px 14px;max-width:520px;">
						<?php foreach ( $categories as $category ) : ?>
							<label class="rfc-cat" style="display:block;margin:4px 0;">
								<input type="checkbox" name="registration_for_categories_slugs[]" value="<?php echo esc_attr( $category->slug ); ?>" <?php checked( in_array( $category->slug, $selected, true ) ); ?>>
								<span class="rfc-cat-name"><?php echo esc_html( $category->name ); ?></span>
								<span style="color:#787c82;">(<?php echo esc_html( $category->count ); ?>)</span>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php submit_button( __( 'Save Settings', 'registration-for-categories' ), 'primary', 'registration_for_categories_submit' ); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Required WooCommerce settings', 'registration-for-categories' ); ?></h2>
			<p><?php esc_html_e( 'Under WooCommerce → Settings → Accounts & Privacy, enable:', 'registration-for-categories' ); ?></p>
			<ol>
				<li><?php esc_html_e( 'Allow customers to place orders without an account', 'registration-for-categories' ); ?></li>
				<li><?php esc_html_e( 'Allow customers to log into an existing account during checkout', 'registration-for-categories' ); ?></li>
				<li><?php esc_html_e( 'Allow customers to create an account during checkout', 'registration-for-categories' ); ?></li>
			</ol>
		</div>

		<script>
		( function () {
			var input = document.getElementById( 'rfc-filter' );
			if ( ! input ) { return; }
			input.addEventListener( 'input', function () {
				var q = this.value.toLowerCase();
				var rows = document.querySelectorAll( '#rfc-cat-list .rfc-cat' );
				for ( var i = 0; i < rows.length; i++ ) {
					var name = rows[ i ].querySelector( '.rfc-cat-name' ).textContent.toLowerCase();
					rows[ i ].style.display = ( -1 !== name.indexOf( q ) ) ? '' : 'none';
				}
			} );
		} )();
		</script>
		<?php
	}
}

Registration_For_Categories::instance();
