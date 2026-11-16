<?php
/**
 * Uninstall handler for Term Order.
 *
 * Removes the plugin settings and every wto_order term meta value.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wto_settings' );

global $wpdb;
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->termmeta} WHERE meta_key = %s",
		'wto_order'
	)
);
