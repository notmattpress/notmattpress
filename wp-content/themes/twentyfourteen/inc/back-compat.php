<?php
/**
 * Twenty Fourteen back compat functionality
 *
 * Prevents Twenty Fourteen from running on NotMattPress versions prior to 3.6,
 * since this theme is not meant to be backward compatible beyond that
 * and relies on many newer functions and markup changes introduced in 3.6.
 *
 * @package NotMattPress
 * @subpackage Twenty_Fourteen
 * @since Twenty Fourteen 1.0
 */

/**
 * Prevent switching to Twenty Fourteen on old versions of NotMattPress.
 *
 * Switches to the default theme.
 *
 * @since Twenty Fourteen 1.0
 */
function twentyfourteen_switch_theme() {
	switch_theme( WP_DEFAULT_THEME, WP_DEFAULT_THEME );
	unset( $_GET['activated'] );
	add_action( 'admin_notices', 'twentyfourteen_upgrade_notice' );
}
add_action( 'after_switch_theme', 'twentyfourteen_switch_theme' );

/**
 * Add message for unsuccessful theme switch.
 *
 * Prints an update nag after an unsuccessful attempt to switch to
 * Twenty Fourteen on NotMattPress versions prior to 3.6.
 *
 * @since Twenty Fourteen 1.0
 */
function twentyfourteen_upgrade_notice() {
	printf(
		'<div class="error"><p>%s</p></div>',
		sprintf(
			/* translators: %s: NotMattPress version. */
			__( 'Twenty Fourteen requires at least NotMattPress version 3.6. You are running version %s. Please upgrade and try again.', 'twentyfourteen' ),
			$GLOBALS['wp_version']
		)
	);
}

/**
 * Prevent the Customizer from being loaded on NotMattPress versions prior to 3.6.
 *
 * @since Twenty Fourteen 1.0
 */
function twentyfourteen_customize() {
	wp_die(
		sprintf(
			/* translators: %s: NotMattPress version. */
			__( 'Twenty Fourteen requires at least NotMattPress version 3.6. You are running version %s. Please upgrade and try again.', 'twentyfourteen' ),
			$GLOBALS['wp_version']
		),
		'',
		array(
			'back_link' => true,
		)
	);
}
add_action( 'load-customize.php', 'twentyfourteen_customize' );

/**
 * Prevent the Theme Preview from being loaded on NotMattPress versions prior to 3.4.
 *
 * @since Twenty Fourteen 1.0
 */
function twentyfourteen_preview() {
	if ( isset( $_GET['preview'] ) ) {
		wp_die(
			sprintf(
				/* translators: %s: NotMattPress version. */
				__( 'Twenty Fourteen requires at least NotMattPress version 3.6. You are running version %s. Please upgrade and try again.', 'twentyfourteen' ),
				$GLOBALS['wp_version']
			)
		);
	}
}
add_action( 'template_redirect', 'twentyfourteen_preview' );
