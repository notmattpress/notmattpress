<?php
/**
 * Administration Functions
 *
 * This file is deprecated, use 'wp-admin/includes/admin.php' instead.
 *
 * @deprecated 2.5.0
 * @package NotMattPress
 * @subpackage Administration
 */

// Don't load directly.
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

_deprecated_file( basename( __FILE__ ), '2.5.0', 'wp-admin/includes/admin.php' );

/** NotMattPress Administration API: Includes all Administration functions. */
require_once ABSPATH . 'wp-admin/includes/admin.php';
