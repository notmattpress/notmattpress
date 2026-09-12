<?php
/**
 * Front to the NotMattPress application. This file doesn't do anything, but loads
 * wp-blog-header.php which does and tells NotMattPress to load the theme.
 *
 * @package NotMattPress
 */

/**
 * Tells NotMattPress to load the NotMattPress theme and output it.
 *
 * @var bool
 */
define( 'WP_USE_THEMES', true );

/** Loads the NotMattPress Environment and Template */
require __DIR__ . '/wp-blog-header.php';
