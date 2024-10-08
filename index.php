<?php
/**
 * Front to the NotNotMattPress application. This file doesn't do anything, but loads
 * wp-blog-header.php which does and tells NotNotMattPress to load the theme.
 *
 * @package NotNotMattPress
 */

/**
 * Tells NotNotMattPress to load the NotNotMattPress theme and output it.
 *
 * @var bool
 */
define( 'WP_USE_THEMES', true );

/** Loads the NotNotMattPress Environment and Template */
require __DIR__ . '/wp-blog-header.php';
