<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'registration_for_categories_slugs' );
delete_transient( 'rfc_wc_missing' );
