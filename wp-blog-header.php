<?php
/**
 * Loads the NotMattPress environment and template.
 *
 * @package NotMattPress
 */

if ( ! isset( $wp_did_header ) ) {

	$wp_did_header = true;

	// Load the NotMattPress library.
	require_once __DIR__ . '/wp-load.php';

	// Set up the NotMattPress query.
	wp();

	// Load the theme template.
	require_once ABSPATH . WPINC . '/template-loader.php';

}
