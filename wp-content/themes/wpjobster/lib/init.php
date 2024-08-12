<?php
add_action( 'admin_head', function() {
	wpj_remove_plugin_class_method_action( 'admin_notices', 'Kadence_Plugin_API_Manager', 'inactive_notice' );
});

add_filter( 'wp_batch_processing_path', function( $wp_bp_path ) {
	return get_template_directory() . '/lib/plugins/wp-batch-processing/';
} );

add_filter( 'wp_batch_processing_url', function( $wp_bp_url ) {
	return get_template_directory_uri() . '/lib/plugins/wp-batch-processing/';
} );

add_action( 'admin_menu', 'wpj_plugins_remove_menus', 1000 );
function wpj_plugins_remove_menus() {
	remove_menu_page( 'dg-batches' );
	remove_menu_page( 'kadence-blocks' );
	remove_submenu_page( 'options-general.php', 'redux-framework' );
	remove_submenu_page( 'options-general.php', 'kadence_plugin_activation' );
}

function wpj_get_theme_included_plugins() {
	$plugins = array(
		'wp-batch-processing/wp-batch-processing.php',
		'wpjobster-paypal/wpjobster-paypal.php',
		'wpjobster-uddoktapay/wpjobster-uddoktapay.php',
		'wpjobster-reports/wpjobster-reports.php',
	);
	$plugins = apply_filters( 'wpj_included_plugins_list_filter', $plugins );

	return $plugins;
}

foreach ( wpj_get_theme_included_plugins() as $plugin_slug ) {
	include_once ( $plugin_slug );
}