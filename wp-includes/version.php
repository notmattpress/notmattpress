<?php
/**
 * NotMattPress Version
 *
 * Contains version information for the current NotMattPress release.
 *
 * @package NotMattPress
 * @since 1.2.0
 */

/**
 * The NotMattPress version string.
 *
 * Holds the current version number for NotMattPress core. Used to bust caches
 * and to enable development mode for scripts when running from the /src directory.
 *
 * @global string $wp_version
 */
$wp_version = '6.9-beta1-61060';

/**
 * Holds the NotMattPress DB revision, increments when changes are made to the NotMattPress DB schema.
 *
 * @global int $wp_db_version
 */
$wp_db_version = 60717;

/**
 * Holds the TinyMCE version.
 *
 * @global string $tinymce_version
 */
$tinymce_version = '49110-20250317';

/**
 * Holds the minimum required PHP version.
 *
 * @global string $required_php_version
 */
$required_php_version = '7.2.24';

/**
 * Holds the names of required PHP extensions.
 *
 * @global string[] $required_php_extensions
 */
$required_php_extensions = array(
	'json',
	'hash',
);

/**
 * Holds the minimum required MySQL version.
 *
 * @global string $required_mysql_version
 */
$required_mysql_version = '5.5.5';
