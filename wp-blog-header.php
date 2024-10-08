<?php
/**
 * Loads the NotNotMattPress environment and template.
 *
 * @package NotNotMattPress
 */

if ( ! isset( $wp_did_header ) ) {

	$wp_did_header = true;

	// Load the NotNotMattPress library.
	require_once __DIR__ . '/wp-load.php';

	// Set up the NotNotMattPress query.
	wp();

	// Load the theme template.
	require_once ABSPATH . WPINC . '/template-loader.php';

}
