<?php

// apply filters before inclusion
add_filter( 'wp_batch_processing_path', function( $wp_bp_path ) {
	return get_template_directory() . '/plugins/wp-batch-processing/';
} );
add_filter( 'wp_batch_processing_url', function( $wp_bp_url ) {
	return get_template_directory_uri() . '/plugins/wp-batch-processing/';
} );


// Include theme's must use plugins

require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

foreach ( wpj_get_theme_included_plugins() as $plugin_slug ) {
	if ( is_plugin_active( $plugin_slug ) ) {
		deactivate_plugins( plugin_basename( $plugin_slug ) );
		header("Refresh:0");
	}
	include_once( $plugin_slug );
}

add_filter( 'acf/settings/dir', 'wpj_acf_settings_dir' );
function wpj_acf_settings_dir( $dir ) {
	$dir = get_template_directory_uri() . '/plugins/advanced-custom-fields-pro/';
	return $dir;
}

function wpj_get_theme_included_plugins() {
	$plugins = array(
		'user-bookmarks/user-bookmarks.php',
		'remove-dashboard-access-for-non-admins/remove-dashboard-access.php',
		'zm-ajax-login-register/plugin.php',
		'advanced-custom-fields-pro/acf.php',
		'kirki/kirki.php',
		'image-watermark/image-watermark.php',
		'wp-batch-processing/wp-batch-processing.php', // https://github.com/jobstertheme/wp-batch-processing
		'wpjobster-paypal/wpjobster-paypal.php',
		'wpjobster-uddoktapay/wpjobster-uddoktapay.php',
		'wpjobster-reports/wpjobster-reports.php',
		'wpjobster-seller-order-rejection/wpjobster-seller-order-rejection.php',
	);
	$plugins = apply_filters( 'wpj_included_plugins_list', $plugins );

	return $plugins;
}

function wpj_exists_theme_included_plugins() {
	$flag = false;
	foreach ( wpj_get_theme_included_plugins() as $plugin_slug ) {
		if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_slug ) ) {
			$flag = true;
		}
	}
	return $flag;
}

function wpj_delete_theme_included_plugins() {
	require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	delete_plugins( wpj_get_theme_included_plugins() );
}
