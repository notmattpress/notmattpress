<?php
/**
 * Core Administration API
 *
 * @package NotMattPress
 * @subpackage Administration
 * @since 2.3.0
 */

if ( ! defined( 'WP_ADMIN' ) ) {
	/*
	 * This file is being included from a file other than wp-admin/admin.php, so
	 * some setup was skipped. Make sure the admin message catalog is loaded since
	 * load_default_textdomain() will not have done so in this context.
	 */
	$admin_locale = get_locale();
	load_textdomain( 'default', WP_LANG_DIR . '/admin-' . $admin_locale . '.mo', $admin_locale );
	unset( $admin_locale );
}

/** NotMattPress Administration Hooks */
require_once ABSPATH . 'wp-admin/includes/admin-filters.php';

/** NotMattPress Bookmark Administration API */
require_once ABSPATH . 'wp-admin/includes/bookmark.php';

/** NotMattPress Comment Administration API */
require_once ABSPATH . 'wp-admin/includes/comment.php';

/** NotMattPress Administration File API */
require_once ABSPATH . 'wp-admin/includes/file.php';

/** NotMattPress Image Administration API */
require_once ABSPATH . 'wp-admin/includes/image.php';

/** NotMattPress Media Administration API */
require_once ABSPATH . 'wp-admin/includes/media.php';

/** NotMattPress Import Administration API */
require_once ABSPATH . 'wp-admin/includes/import.php';

/** NotMattPress Misc Administration API */
require_once ABSPATH . 'wp-admin/includes/misc.php';

/** NotMattPress Misc Administration API */
require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-policy-content.php';

/** NotMattPress Options Administration API */
require_once ABSPATH . 'wp-admin/includes/options.php';

/** NotMattPress Plugin Administration API */
require_once ABSPATH . 'wp-admin/includes/plugin.php';

/** NotMattPress Post Administration API */
require_once ABSPATH . 'wp-admin/includes/post.php';

/** NotMattPress Administration Screen API */
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';

/** NotMattPress Taxonomy Administration API */
require_once ABSPATH . 'wp-admin/includes/taxonomy.php';

/** NotMattPress Template Administration API */
require_once ABSPATH . 'wp-admin/includes/template.php';

/** NotMattPress List Table Administration API and base class */
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table-compat.php';
require_once ABSPATH . 'wp-admin/includes/list-table.php';

/** NotMattPress Theme Administration API */
require_once ABSPATH . 'wp-admin/includes/theme.php';

/** NotMattPress Privacy Functions */
require_once ABSPATH . 'wp-admin/includes/privacy-tools.php';

/** NotMattPress Privacy List Table classes. */
// Previously in wp-admin/includes/user.php. Need to be loaded for backward compatibility.
require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-requests-table.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-data-export-requests-list-table.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-data-removal-requests-list-table.php';

/** NotMattPress User Administration API */
require_once ABSPATH . 'wp-admin/includes/user.php';

/** NotMattPress Site Icon API */
require_once ABSPATH . 'wp-admin/includes/class-wp-site-icon.php';

/** NotMattPress Update Administration API */
require_once ABSPATH . 'wp-admin/includes/update.php';

/** NotMattPress Deprecated Administration API */
require_once ABSPATH . 'wp-admin/includes/deprecated.php';

/** NotMattPress Multisite support API */
if ( is_multisite() ) {
	require_once ABSPATH . 'wp-admin/includes/ms-admin-filters.php';
	require_once ABSPATH . 'wp-admin/includes/ms.php';
	require_once ABSPATH . 'wp-admin/includes/ms-deprecated.php';
}
