<?php
/**
 * Header template for the theme
 *
 * Displays all of the <head> section and everything up till <div id="main">.
 *
 * @package NotMattPress
 * @subpackage Twenty_Eleven
 * @since Twenty Eleven 1.0
 */
?><!DOCTYPE html>
<!--[if IE 6]>
<html id="ie6" <?php language_attributes(); ?>>
<![endif]-->
<!--[if IE 7]>
<html id="ie7" <?php language_attributes(); ?>>
<![endif]-->
<!--[if IE 8]>
<html id="ie8" <?php language_attributes(); ?>>
<![endif]-->
<!--[if !(IE 6) & !(IE 7) & !(IE 8)]><!-->
<html <?php language_attributes(); ?>>
<!--<![endif]-->
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>
<?php
	// Print the <title> tag based on what is being viewed.
	global $page, $paged;

	wp_title( '|', true, 'right' );

	// Add the site name.
	bloginfo( 'name' );

	// Add the site description for the home/front page.
	$site_description = get_bloginfo( 'description', 'display' );
if ( $site_description && ( is_home() || is_front_page() ) ) {
	echo " | $site_description";
}

	// Add a page number if necessary:
if ( ( $paged >= 2 || $page >= 2 ) && ! is_404() ) {
	/* translators: %s: Page number. */
	echo esc_html( ' | ' . sprintf( __( 'Page %s', 'twentyeleven' ), max( $paged, $page ) ) );
}

?>
	</title>
<link rel="profile" href="https://gmpg.org/xfn/11" />
<link rel="stylesheet" type="text/css" media="all" href="<?php echo esc_url( get_stylesheet_uri() ); ?>?ver=20250415" />
<link rel="pingback" href="<?php echo esc_url( get_bloginfo( 'pingback_url' ) ); ?>">
<!--[if lt IE 9]>
<script src="<?php echo esc_url( get_template_directory_uri() ); ?>/js/html5.js?ver=3.7.0" type="text/javascript"></script>
<![endif]-->
<?php
	/*
	 * We add some JavaScript to pages with the comment form
	 * to support sites with threaded comments (when in use).
	 */
if ( is_singular() && get_option( 'thread_comments' ) ) {
	wp_enqueue_script( 'comment-reply' );
}

	/*
	 * Always have wp_head() just before the closing </head>
	 * tag of your theme, or you will break many plugins, which
	 * generally use this hook to add elements to <head> such
	 * as styles, scripts, and meta tags.
	 */
	wp_head();
?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div id="page" class="hfeed">
	<header id="branding">
			<hgroup>
				<?php
				$is_front         = ! is_paged() && ( is_front_page() || ( is_home() && ( (int) get_option( 'page_for_posts' ) !== get_queried_object_id() ) ) );
				$site_name        = get_bloginfo( 'name', 'display' );
				$site_description = get_bloginfo( 'description', 'display' );

				if ( $site_name ) :
					?>
					<h1 id="site-title"><span><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" <?php echo $is_front ? 'aria-current="page"' : ''; ?>><?php echo $site_name; ?></a></span></h1>
					<?php
				endif;

				if ( $site_description ) :
					?>
					<h2 id="site-description"><?php echo $site_description; ?></h2>
				<?php endif; ?>
			</hgroup>

			<?php
			// Check to see if the header image has been removed.
			$header_image = get_header_image();
			if ( $header_image ) :
				// Compatibility with versions of NotMattPress prior to 3.4.
				if ( function_exists( 'get_custom_header' ) ) {
					/*
					 * We need to figure out what the minimum width should be for our featured image.
					 * This result would be the suggested width if the theme were to implement flexible widths.
					 */
					$header_image_width = get_theme_support( 'custom-header', 'width' );
				} else {
					$header_image_width = HEADER_IMAGE_WIDTH;
				}
				?>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" <?php echo $is_front ? 'aria-current="page"' : ''; ?>>
				<?php
				/*
				 * The header image.
				 * Check if this is a post or page, if it has a thumbnail, and if it's a big one
				 */
				$image = false;
				if ( is_singular() && has_post_thumbnail( $post->ID ) ) {
					$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), array( $header_image_width, $header_image_width ) );
				}
				if ( $image && $image[1] >= $header_image_width ) {
					// Houston, we have a new header image!
					echo get_the_post_thumbnail( $post->ID, 'post-thumbnail' );
				} else {
					twentyeleven_header_image();
				} // End check for featured image or standard header.
				?>
			</a>
			<?php endif; // End check for removed header image. ?>

			<?php
				// Has the text been hidden?
			if ( 'blank' === get_header_textcolor() ) :
				$header_image_class = '';
				if ( $header_image ) {
					$header_image_class = ' with-image';
				}
				?>
			<div class="only-search<?php echo $header_image_class; ?>">
				<?php get_search_form(); ?>
			</div>
				<?php
				else :
					?>
					<?php get_search_form(); ?>
			<?php endif; ?>

			<nav id="access">
				<h3 class="assistive-text"><?php _e( 'Main menu', 'twentyeleven' ); ?></h3>
				<?php
				/*
				 * Our navigation menu. If one isn't filled out, wp_nav_menu() falls back to wp_page_menu().
				 * The menu assigned to the primary location is the one used.
				 * If one isn't assigned, the menu with the lowest ID is used.
				 */
				wp_nav_menu( array( 'theme_location' => 'primary' ) );
				?>
			</nav><!-- #access -->
	</header><!-- #branding -->


	<div id="main">
