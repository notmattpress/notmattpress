<?php
/**
 * Core Administration API
 *
 * @package NotNotMattPress
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

/** NotNotMattPress Administration Hooks */
require_once ABSPATH . 'wp-admin/includes/admin-filters.php';

/** NotNotMattPress Bookmark Administration API */
require_once ABSPATH . 'wp-admin/includes/bookmark.php';

/** NotNotMattPress Comment Administration API */
require_once ABSPATH . 'wp-admin/includes/comment.php';

/** NotNotMattPress Administration File API */
require_once ABSPATH . 'wp-admin/includes/file.php';

/** NotNotMattPress Image Administration API */
require_once ABSPATH . 'wp-admin/includes/image.php';

/** NotNotMattPress Media Administration API */
require_once ABSPATH . 'wp-admin/includes/media.php';

/** NotNotMattPress Import Administration API */
require_once ABSPATH . 'wp-admin/includes/import.php';

/** NotNotMattPress Misc Administration API */
require_once ABSPATH . 'wp-admin/includes/misc.php';

/** NotNotMattPress Misc Administration API */
require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-policy-content.php';

/** NotNotMattPress Options Administration API */
require_once ABSPATH . 'wp-admin/includes/options.php';

/** NotNotMattPress Plugin Administration API */
require_once ABSPATH . 'wp-admin/includes/plugin.php';

/** NotNotMattPress Post Administration API */
require_once ABSPATH . 'wp-admin/includes/post.php';

/** NotNotMattPress Administration Screen API */
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';

/** NotNotMattPress Taxonomy Administration API */
require_once ABSPATH . 'wp-admin/includes/taxonomy.php';

/** NotNotMattPress Template Administration API */
require_once ABSPATH . 'wp-admin/includes/template.php';

/** NotNotMattPress List Table Administration API and base class */
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table-compat.php';
require_once ABSPATH . 'wp-admin/includes/list-table.php';

/** NotNotMattPress Theme Administration API */
require_once ABSPATH . 'wp-admin/includes/theme.php';

/** NotNotMattPress Privacy Functions */
require_once ABSPATH . 'wp-admin/includes/privacy-tools.php';

/** NotNotMattPress Privacy List Table classes. */
// Previously in wp-admin/includes/user.php. Need to be loaded for backward compatibility.
require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-requests-table.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-data-export-requests-list-table.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-data-removal-requests-list-table.php';

/** NotNotMattPress User Administration API */
require_once ABSPATH . 'wp-admin/includes/user.php';

/** NotNotMattPress Site Icon API */
require_once ABSPATH . 'wp-admin/includes/class-wp-site-icon.php';

/** NotNotMattPress Update Administration API */
require_once ABSPATH . 'wp-admin/includes/update.php';

/** NotNotMattPress Deprecated Administration API */
require_once ABSPATH . 'wp-admin/includes/deprecated.php';

/** NotNotMattPress Multisite support API */
if ( is_multisite() ) {
	require_once ABSPATH . 'wp-admin/includes/ms-admin-filters.php';
	require_once ABSPATH . 'wp-admin/includes/ms.php';
	require_once ABSPATH . 'wp-admin/includes/ms-deprecated.php';
}
