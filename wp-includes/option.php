<?php
/**
 * Option API
 *
 * @package NotMattPress
 * @subpackage Option
 */

/**
 * Retrieves an option value based on an option name.
 *
 * If the option does not exist, and a default value is not provided,
 * boolean false is returned. This could be used to check whether you need
 * to initialize an option during installation of a plugin, however that
 * can be done better by using add_option() which will not overwrite
 * existing options.
 *
 * Not initializing an option and using boolean `false` as a return value
 * is a bad practice as it triggers an additional database query.
 *
 * The type of the returned value can be different from the type that was passed
 * when saving or updating the option. If the option value was serialized,
 * then it will be unserialized when it is returned. In this case the type will
 * be the same. For example, storing a non-scalar value like an array will
 * return the same array.
 *
 * In most cases non-string scalar and null values will be converted and returned
 * as string equivalents.
 *
 * Exceptions:
 *
 * 1. When the option has not been saved in the database, the `$default_value` value
 *    is returned if provided. If not, boolean `false` is returned.
 * 2. When one of the Options API filters is used: {@see 'pre_option_$option'},
 *    {@see 'default_option_$option'}, or {@see 'option_$option'}, the returned
 *    value may not match the expected type.
 * 3. When the option has just been saved in the database, and get_option()
 *    is used right after, non-string scalar and null values are not converted to
 *    string equivalents and the original type is returned.
 *
 * Examples:
 *
 * When adding options like this: `add_option( 'my_option_name', 'value' )`
 * and then retrieving them with `get_option( 'my_option_name' )`, the returned
 * values will be:
 *
 *   - `false` returns `string(0) ""`
 *   - `true`  returns `string(1) "1"`
 *   - `0`     returns `string(1) "0"`
 *   - `1`     returns `string(1) "1"`
 *   - `'0'`   returns `string(1) "0"`
 *   - `'1'`   returns `string(1) "1"`
 *   - `null`  returns `string(0) ""`
 *
 * When adding options with non-scalar values like
 * `add_option( 'my_array', array( false, 'str', null ) )`, the returned value
 * will be identical to the original as it is serialized before saving
 * it in the database:
 *
 *     array(3) {
 *         [0] => bool(false)
 *         [1] => string(3) "str"
 *         [2] => NULL
 *     }
 *
 * @since 1.5.0
 *
 * @global wpdb $wpdb NotMattPress database abstraction object.
 *
 * @param string $option        Name of the option to retrieve. Expected to not be SQL-escaped.
 * @param mixed  $default_value Optional. Default value to return if the option does not exist.
 * @return mixed Value of the option. A value of any type may be returned, including
 *               scalar (string, boolean, float, integer), null, array, object.
 *               Scalar and null values will be returned as strings as long as they originate
 *               from a database stored option value. If there is no option in the database,
 *               boolean `false` is returned.
 */
function get_option( $option, $default_value = false ) {
	global $wpdb;

	if ( is_scalar( $option ) ) {
		$option = trim( $option );
	}

	if ( empty( $option ) ) {
		return false;
	}

	/*
	 * Until a proper _deprecated_option() function can be introduced,
	 * redirect requests to deprecated keys to the new, correct ones.
	 */
	$deprecated_keys = array(
		'blacklist_keys'    => 'disallowed_keys',
		'comment_whitelist' => 'comment_previously_approved',
	);

	if ( isset( $deprecated_keys[ $option ] ) && ! wp_installing() ) {
		_deprecated_argument(
			__FUNCTION__,
			'5.5.0',
			sprintf(
				/* translators: 1: Deprecated option key, 2: New option key. */
				__( 'The "%1$s" option key has been renamed to "%2$s".' ),
				$option,
				$deprecated_keys[ $option ]
			)
		);
		return get_option( $deprecated_keys[ $option ], $default_value );
	}

	/**
	 * Filters the value of an existing option before it is retrieved.
	 *
	 * The dynamic portion of the hook name, `$option`, refers to the option name.
	 *
	 * Returning a value other than false from the filter will short-circuit retrieval
	 * and return that value instead.
	 *
	 * @since 1.5.0
	 * @since 4.4.0 The `$option` parameter was added.
	 * @since 4.9.0 The `$default_value` parameter was added.
	 *
	 * @param mixed  $pre_option    The value to return instead of the option value. This differs from
	 *                              `$default_value`, which is used as the fallback value in the event
	 *                              the option doesn't exist elsewhere in get_option().
	 *                              Default false (to skip past the short-circuit).
	 * @param string $option        Option name.
	 * @param mixed  $default_value The fallback value to return if the option does not exist.
	 *                              Default false.
	 */
	$pre = apply_filters( "pre_option_{$option}", false, $option, $default_value );

	/**
	 * Filters the value of all existing options before it is retrieved.
	 *
	 * Returning a truthy value from the filter will effectively short-circuit retrieval
	 * and return the passed value instead.
	 *
	 * @since 6.1.0
	 *
	 * @param mixed  $pre_option    The value to return instead of the option value. This differs from
	 *                              `$default_value`, which is used as the fallback value in the event
	 *                              the option doesn't exist elsewhere in get_option().
	 *                              Default false (to skip past the short-circuit).
	 * @param string $option        Name of the option.
	 * @param mixed  $default_value The fallback value to return if the option does not exist.
	 *                              Default false.
	 */
	$pre = apply_filters( 'pre_option', $pre, $option, $default_value );

	if ( false !== $pre ) {
		return $pre;
	}

	if ( defined( 'WP_SETUP_CONFIG' ) ) {
		return false;
	}

	// Distinguish between `false` as a default, and not passing one.
	$passed_default = func_num_args() > 1;

	if ( ! wp_installing() ) {
		$alloptions = wp_load_alloptions();
		/*
		 * When getting an option value, we check in the following order for performance:
		 *
		 * 1. Check the 'alloptions' cache first to prioritize existing loaded options.
		 * 2. Check the 'notoptions' cache before a cache lookup or DB hit.
		 * 3. Check the 'options' cache prior to a DB hit.
		 * 4. Check the DB for the option and cache it in either the 'options' or 'notoptions' cache.
		 */
		if ( isset( $alloptions[ $option ] ) ) {
			$value = $alloptions[ $option ];
		} else {
			// Check for non-existent options first to avoid unnecessary object cache lookups and DB hits.
			$notoptions = wp_cache_get( 'notoptions', 'options' );

			if ( ! is_array( $notoptions ) ) {
				$notoptions = array();
				wp_cache_set( 'notoptions', $notoptions, 'options' );
			}

			if ( isset( $notoptions[ $option ] ) ) {
				/**
				 * Filters the default value for an option.
				 *
				 * The dynamic portion of the hook name, `$option`, refers to the option name.
				 *
				 * @since 3.4.0
				 * @since 4.4.0 The `$option` parameter was added.
				 * @since 4.7.0 The `$passed_default` parameter was added to distinguish between a `false` value and the default parameter value.
				 *
				 * @param mixed  $default_value  The default value to return if the option does not exist
				 *                               in the database.
				 * @param string $option         Option name.
				 * @param bool   $passed_default Was `get_option()` passed a default value?
				 */
				return apply_filters( "default_option_{$option}", $default_value, $option, $passed_default );
			}

			$value = wp_cache_get( $option, 'options' );

			if ( false === $value ) {

				$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option ) );

				// Has to be get_row() instead of get_var() because of funkiness with 0, false, null values.
				if ( is_object( $row ) ) {
					$value = $row->option_value;
					wp_cache_add( $option, $value, 'options' );
				} else { // Option does not exist, so we must cache its non-existence.
					$notoptions[ $option ] = true;
					wp_cache_set( 'notoptions', $notoptions, 'options' );

					/** This filter is documented in wp-includes/option.php */
					return apply_filters( "default_option_{$option}", $default_value, $option, $passed_default );
				}
			}
		}
	} else {
		$suppress = $wpdb->suppress_errors();
		$row      = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option ) );
		$wpdb->suppress_errors( $suppress );

		if ( is_object( $row ) ) {
			$value = $row->option_value;
		} else {
			/** This filter is documented in wp-includes/option.php */
			return apply_filters( "default_option_{$option}", $default_value, $option, $passed_default );
		}
	}

	// If home is not set, use siteurl.
	if ( 'home' === $option && '' === $value ) {
		return get_option( 'siteurl' );
	}

	if ( in_array( $option, array( 'siteurl', 'home', 'category_base', 'tag_base' ), true ) ) {
		$value = untrailingslashit( $value );
	}

	/**
	 * Filters the value of an existing option.
	 *
	 * The dynamic portion of the hook name, `$option`, refers to the option name.
	 *
	 * @since 1.5.0 As 'option_' . $setting
	 * @since 3.0.0
	 * @since 4.4.0 The `$option` parameter was added.
	 *
	 * @param mixed  $value  Value of the option. If stored serialized, it will be
	 *                       unserialized prior to being returned.
	 * @param string $option Option name.
	 */
	return apply_filters( "option_{$option}", maybe_unserialize( $value ), $option );
}

/**
 * Primes specific options into the cache with a single database query.
 *
 * Only options that do not already exist in cache will be loaded.
 *
 * @since 6.4.0
 *
 * @global wpdb $wpdb NotMattPress database abstraction object.
 *
 * @param string[] $options An array of option names to be loaded.
 */
function wp_prime_option_caches( $options ) {
	global $wpdb;

	$alloptions     = wp_load_alloptions();
	$cached_options = wp_cache_get_multiple( $options, 'options' );
	$notoptions     = wp_cache_get( 'notoptions', 'options' );
	if ( ! is_array( $notoptions ) ) {
		$notoptions = array();
	}

	// Filter options that are not in the cache.
	$options_to_prime = array();
	foreach ( $options as $option ) {
		if (
			( ! isset( $cached_options[ $option ] ) || false === $cached_options[ $option ] )
			&& ! isset( $alloptions[ $option ] )
			&& ! isset( $notoptions[ $option ] )
		) {
			$options_to_prime[] = $option;
		}
	}

	// Bail early if there are no options to be loaded.
	if ( empty( $options_to_prime ) ) {
		return;
	}

	$results = $wpdb->get_results(
		$wpdb->prepare(
			sprintf(
				"SELECT option_name, option_value FROM $wpdb->options WHERE option_name IN (%s)",
				implode( ',', array_fill( 0, count( $options_to_prime ), '%s' ) )
			),
			$options_to_prime
		)
	);

	$options_found = array();
	foreach ( $results as $result ) {
		/*
		 * The cache is primed with the raw value (i.e. not maybe_unserialized).
		 *
		 * `get_option()` will handle unserializing the value as needed.
		 */
		$options_found[ $result->option_name ] = $result->option_value;
	}
	wp_cache_set_multiple( $options_found, 'options' );

	// If all options were found, no need to update `notoptions` cache.
	if ( count( $options_found ) === count( $options_to_prime ) ) {
		return;
	}

	$options_not_found = array_diff( $options_to_prime, array_keys( $options_found ) );

	// Add the options that were not found to the cache.
	$update_notoptions = false;
	foreach ( $options_not_found as $option_name ) {
		if ( ! isset( $notoptions[ $option_name ] ) ) {
			$notoptions[ $option_name ] = true;
			$update_notoptions          = true;
		}
	}

	// Only update the cache if it was modified.
	if ( $update_notoptions ) {
		wp_cache_set( 'notoptions', $notoptions, 'options' );
	}
}

/**
 * Primes the cache of all options registered with a specific option group.
 *
 * @since 6.4.0
 *
 * @global array $new_allowed_options
 *
 * @param string $option_group The option group to load options for.
 */
function wp_prime_option_caches_by_group( $option_group ) {
	global $new_allowed_options;

	if ( isset( $new_allowed_options[ $option_group ] ) ) {
		wp_prime_option_caches( $new_allowed_options[ $option_group ] );
	}
}

/**
 * Retrieves multiple options.
 *
 * Options are loaded as necessary first in order to use a single database query at most.
 *
 * @since 6.4.0
 *
 * @param string[] $options An array of option names to retrieve.
 * @return array An array of key-value pairs for the requested options.
 */
function get_options( $options ) {
	wp_prime_option_caches( $options );

	$result = array();
	foreach ( $options as $option ) {
		$result[ $option ] = get_option( $option );
	}

	return $result;
}

/**
 * Sets the autoload values for multiple options in the database.
 *
 * Autoloading too many options can lead to performance problems, especially if the options are not frequently used.
 * This function allows modifying the autoload value for multiple options without changing the actual option value.
 * This is for example recommended for plugin activation and deactivation hooks, to ensure any options exclusively used
 * by the plugin which are generally autoloaded can be set to not autoload when the plugin is inactive.
 *
 * @since 6.4.0
 * @since 6.7.0 The autoload values 'yes' and 'no' are deprecated.
 *
 * @global wpdb $wpdb NotMattPress database abstraction object.
 *
 * @param array $options Associative array of option names and their autoload values to set. The option names are
 *                       expected to not be SQL-escaped. The autoload values should be boolean values. For backward
 *                       compatibility 'yes' and 'no' are also accepted, though using these values is deprecated.
 * @return array Associative array of all provided $options as keys and boolean values for whether their autoload value
 *               was updated.
 */
function wp_set_option_autoload_values( array $options ) {
	global $wpdb;

	if ( ! $options ) {
		return array();
	}

	$grouped_options = array(
		'on'  => array(),
		'off' => array(),
	);
	$results         = array();
	foreach ( $options as $option => $autoload ) {
		wp_protect_special_option( $option ); // Ensure only valid options can be passed.

		/*
		 * Sanitize autoload value and categorize accordingly.
		 * The values 'yes', 'no', 'on', and 'off' are supported for backward compatibility.
		 */
		if ( 'off' === $autoload || 'no' === $autoload || false === $autoload ) {
			$grouped_options['off'][] = $option;
		} else {
			$grouped_options['on'][] = $option;
		}
		$results[ $option ] = false; // Initialize result value.
	}

	$where      = array();
	$where_args = array();
	foreach ( $grouped_options as $autoload => $options ) {
		if ( ! $options ) {
			continue;
		}
		$placeholders = implode( ',', array_fill( 0, count( $options ), '%s' ) );
		$where[]      = "autoload != '%s' AND option_name IN ($placeholders)";
		$where_args[] = $autoload;
		foreach ( $options as $option ) {
			$where_args[] = $option;
		}
	}
	$where = 'WHERE ' . implode( ' OR ', $where );

	/*
	 * Determine the relevant options that do not already use the given autoload value.
	 * If no options are returned, no need to update.
	 */
	// phpcs:ignore NotMattPress.DB.PreparedSQL.InterpolatedNotPrepared,NotMattPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	$options_to_update = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM $wpdb->options $where", $where_args ) );
	if ( ! $options_to_update ) {
		return $results;
	}

	// Run UPDATE queries as needed (maximum 2) to update the relevant options' autoload values to 'yes' or 'no'.
	foreach ( $grouped_options as $autoload => $options ) {
		if ( ! $options ) {
			continue;
		}
		$options                      = array_intersect( $options, $options_to_update );
		$grouped_options[ $autoload ] = $options;
		if ( ! $grouped_options[ $autoload ] ) {
			continue;
		}

		// Run query to update autoload value for all the options where it is needed.
		$success = $wpdb->query(
			$wpdb->prepare(
				"UPDATE $wpdb->options SET autoload = %s WHERE option_name IN (" . implode( ',', array_fill( 0, count( $grouped_options[ $autoload ] ), '%s' ) ) . ')',
				array_merge(
					array( $autoload ),
					$grouped_options[ $autoload ]
				)
			)
		);
		if ( ! $success ) {
			// Set option list to an empty array to indicate no options were updated.
			$grouped_options[ $autoload ] = array();
			continue;
		}

		// Assume that on success all options were updated, which should be the case given only new values are sent.
		foreach ( $grouped_options[ $autoload ] as $option ) {
			$results[ $option ] = true;
		}
	}

	/*
	 * If any options were changed to 'on', delete their individual caches, and delete 'alloptions' cache so that it
	 * is refreshed as needed.
	 * If no options were changed to 'on' but any options were changed to 'no', delete them from the 'alloptions'
	 * cache. This is not necessary when options were changed to 'on', since in that situation the entire cache is
	 * deleted anyway.
	 */
	if ( $grouped_options['on'] ) {
		wp_cache_delete_multiple( $grouped_options['on'], 'options' );
		wp_cache_delete( 'alloptions', 'options' );
	} elseif ( $grouped_options['off'] ) {
		$alloptions = wp_load_alloptions( true );

		foreach ( $grouped_options['off'] as $option ) {
			if ( isset( $alloptions[ $option ] ) ) {
				unset( $alloptions[ $option ] );
			}
		}

		wp_cache_set( 'alloptions', $alloptions, 'options' );
	}

	return $results;
}

/**
 * Sets the autoload value for multiple options in the database.
 *
 * This is a wrapper for {@see wp_set_option_autoload_values()}, which can be used to set different autoload values for
 * each option at once.
 *
 * @since 6.4.0
 * @since 6.7.0 The autoload values 'yes' and 'no' are deprecated.
 *
 * @see wp_set_option_autoload_values()
 *
 * @param string[] $options  List of option names. Expected to not be SQL-escaped.
 * @param bool     $autoload Autoload value to control whether to load the options when NotMattPress starts up.
 *                           For backward compatibility 'yes' and 'no' are also accepted, though using these values is
 *                           deprecated.
 * @return array Associative array of all provided $options as keys and boolean values for whether their autoload value
 *               was updated.
 */
function wp_set_options_autoload( array $options, $autoload ) {
	return wp_set_option_autoload_values(
		array_fill_keys( $options, $autoload )
	);
}

/**
 * Sets the autoload value for an option in the database.
 *
 * This is a wrapper for {@see wp_set_option_autoload_values()}, which can be used to set the autoload value for
 * multiple options at once.
 *
 * @since 6.4.0
 * @since 6.7.0 The autoload values 'yes' and 'no' are deprecated.
 *
 * @see wp_set_option_autoload_values()
 *
 * @param string $option   Name of the option. Expected to not be SQL-escaped.
 * @param bool   $autoload Autoload value to control whether to load the option when NotMattPress starts up.
 *                         For backward compatibility 'yes' and 'no' are also accepted, though using these values is
 *                         deprecated.
 * @return bool True if the autoload value was modified, false otherwise.
 */
function wp_set_option_autoload( $option, $autoload ) {
	$result = wp_set_option_autoload_values( array( $option => $autoload ) );
	if ( isset( $result[ $option ] ) ) {
		return $result[ $option ];
	}
	return false;
}

/**
 * Protects NotMattPress special option from being modified.
 *
 * Will die if $option is in protected list. Protected options are 'alloptions'
 * and 'notoptions' options.
 *
 * @since 2.2.0
 *
 * @param string $option Option name.
 */
function wp_protect_special_option( $option ) {
	if ( 'alloptions' === $option || 'notoptions' === $option ) {
		wp_die(
			sprintf(
				/* translators: %s: Option name. */
				__( '%s is a protected WP option and may not be modified' ),
				esc_html( $option )
			)
		);
	}
}

/**
 * Prints option value after sanitizing for forms.
 *
 * @since 1.5.0
 *
 * @param string $option Option name.
 */
function form_option( $option ) {
	echo esc_attr( get_option( $option ) );
}

/**
 * Loads and caches all autoloaded options, if available or all options.
 *
 * @since 2.2.0
 * @since 5.3.1 The `$force_cache` parameter was added.
 *
 * @global wpdb $wpdb NotMattPress database abstraction object.
 *
 * @param bool $force_cache Optional. Whether to force an update of the local cache
 *                          from the persistent cache. Default false.
 * @return array List of all options.
 */
function wp_load_alloptions( $force_cache = false ) {
	global $wpdb;

	/**
	 * Filters the array of alloptions before it is populated.
	 *
	 * Returning an array from the filter will effectively short circuit
	 * wp_load_alloptions(), returning that value instead.
	 *
	 * @since 6.2.0
	 *
	 * @param array|null $alloptions  An array of alloptions. Default null.
	 * @param bool       $force_cache Whether to force an update of the local cache from the persistent cache. Default false.
	 */
	$alloptions = apply_filters( 'pre_wp_load_alloptions', null, $force_cache );
	if ( is_array( $alloptions ) ) {
		return $alloptions;
	}

	if ( ! wp_installing() || ! is_multisite() ) {
		$alloptions = wp_cache_get( 'alloptions', 'options', $force_cache );
	} else {
		$alloptions = false;
	}

	if ( ! $alloptions ) {
		$suppress      = $wpdb->suppress_errors();
		$alloptions_db = $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options WHERE autoload IN ( '" . implode( "', '", esc_sql( wp_autoload_values_to_autoload() ) ) . "' )" );

		if ( ! $alloptions_db ) {
			$alloptions_db = $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options" );
		}
		$wpdb->suppress_errors( $suppress );

		$alloptions = array();
		foreach ( (array) $alloptions_db as $o ) {
			$alloptions[ $o->option_name ] = $o->option_value;
		}

		if ( ! wp_installing() || ! is_multisite() ) {
			/**
			 * Filters all options before caching them.
			 *
			 * @since 4.9.0
			 *
			 * @param array $alloptions Array with all options.
			 */
			$alloptions = apply_filters( 'pre_cache_alloptions', $alloptions );

			wp_cache_add( 'alloptions', $alloptions, 'options' );
		}
	}

	/**
	 * Filters all options after retrieving them.
	 *
	 * @since 4.9.0
	 *
	 * @param array $alloptions Array with all options.
	 */
	return apply_filters( 'alloptions', $alloptions );
}

/**
 * Primes specific network options for the current network into the cache with a single database query.
 *
 * Only network options that do not already exist in cache will be loaded.
 *
 * If site is not multisite, then call wp_prime_option_caches().
 *
 * @since 6.6.0
 *
 * @see wp_prime_network_option_caches()
 *
 * @param string[] $options An array of option names to be loaded.
 */
function wp_prime_site_option_caches( array $options ) {
	wp_prime_network_option_caches( null, $options );
}

/**
 * Primes specific network options into the cache with a single database query.
 *
 * Only network options that do not already exist in cache will be loaded.
 *
 * If site is not multisite, then call wp_prime_option_caches().
 *
 * @since 6.6.0
 *
 * @global wpdb $wpdb NotMattPress database abstraction object.
 *
 * @param int|null $network_id ID of the network. Can be null to default to the current network ID.
 * @param string[] $options    An array of option names to be loaded.
 */
function wp_prime_network_option_caches( $network_id, array $options ) {
	global $wpdb;

	if ( wp_installing() ) {
		return;
	}

	if ( ! is_multisite() ) {
		wp_prime_option_caches( $options );
		return;
	}

	if ( $network_id && ! is_numeric( $network_id ) ) {
		return;
	}

	$network_id = (int) $network_id;

	// Fallback to the current network if a network ID is not specified.
	if ( ! $network_id ) {
		$network_id = get_current_network_id();
	}

	$cache_keys = array();
	foreach ( $options as $option ) {
		$cache_keys[ $option ] = "{$network_id}:{$option}";
	}

	$cache_group    = 'site-options';
	$cached_options = wp_cache_get_multiple( array_values( $cache_keys ), $cache_group );

	$notoptions_key = "$network_id:notoptions";
	$notoptions     = wp_cache_get( $notoptions_key, $cache_group );

	if ( ! is_array( $notoptions ) ) {
		$notoptions = array();
	}

	// Filter options that are not in the cache.
	$options_to_prime = array();
	foreach ( $cache_keys as $option => $cache_key ) {
		if (
			( ! isset( $cached_options[ $cache_key ] ) || false === $cached_options[ $cache_key ] )
			&& ! isset( $notoptions[ $option ] )
		) {
			$options_to_prime[] = $option;
		}
	}

	// Bail early if there are no options to be loaded.
	if ( empty( $options_to_prime ) ) {
		return;
	}

	$query_args   = $options_to_prime;
	$query_args[] = $network_id;
	$results      = $wpdb->get_results(
		$wpdb->prepare(
			sprintf(
				"SELECT meta_key, meta_value FROM $wpdb->sitemeta WHERE meta_key IN (%s) AND site_id = %s",
				implode( ',', array_fill( 0, count( $options_to_prime ), '%s' ) ),
				'%d'
			),
			$query_args
		)
	);

	$data          = array();
	$options_found = array();
	foreach ( $results as $result ) {
		$key                = $result->meta_key;
		$cache_key          = $cache_keys[ $key ];
		$data[ $cache_key ] = maybe_unserialize( $result->meta_value );
		$options_found[]    = $key;
	}
	wp_cache_set_multiple( $data, $cache_group );
	// If all options were found, no need to update `notoptions` cache.
	if ( count( $options_found ) === count( $options_to_prime ) ) {
		return;
	}

	$options_not_found = array_diff( $options_to_prime, $options_found );

	// Add the options that were not found to the cache.
	$update_notoptions = false;
	foreach ( $options_not_found as $option_name ) {
		if ( ! isset( $notoptions[ $option_name ] ) ) {
			$notoptions[ $option_name ] = true;
			$update_notoptions          = true;
		}
	}

	// Only update the cache if it was modified.
	if ( $update_notoptions ) {
		wp_cache_set( $notoptions_key, $notoptions, $cache_group );
	}
}

/**
 * Loads and primes caches of certain often requested network options if is_multisite().
 *
 * @since 3.0.0
 * @since 6.3.0 Also prime caches for network options when persistent object cache is enabled.
 * @since 6.6.0 Uses wp_prime_network_option_caches().
 *
 * @param int $network_id Optional. Network ID of network for which to prime network options cache. Defaults to current network.
 */
function wp_load_core_site_options( $network_id = null ) {
	if ( ! is_multisite() || wp_installing() ) {
		return;
	}
	$core_options = array( 'site_name', 'siteurl', 'active_sitewide_plugins', '_site_transient_timeout_theme_roots', '_site_transient_theme_roots', 'site_admins', 'can_compress_scripts', 'global_terms_enabled', 'ms_files_rewriting', 'WPLANG' );

	wp_prime_network_option_caches( $network_id, $core_options );
}

/**
 * Updates the value of an option that was already added.
 *
 * You do not need to serialize values. If the value needs to be serialized,
 * then it will be serialized before it is inserted into the database.
 * Remember, resources cannot be serialized or added as an option.
 *
 * If the option does not exist, it will be created.

 * This function is designed to work with or without a logged-in user. In terms of security,
 * plugin developers should check the current user's capabilities before updating any options.
 *
 * @since 1.0.0
 * @since 4.2.0 The `$autoload` parameter was added.
 * @since 6.7.0 The autoload values 'yes' and 'no' are deprecated.
 *
 * @global wpdb $wpdb NotMattPress database abstraction object.
 *
 * @param string    $option   Name of the option to update. Expected to not be SQL-escaped.
 * @param mixed     $value    Option value. Must be serializable if non-scalar. Expected to not be SQL-escaped.
 * @param bool|null $autoload Optional. Whether to load the option when NotMattPress starts up.
 *                            Accepts a boolean, or `null` to stick with the initial value or, if no initial value is
 *                            set, to leave the decision up to default heuristics in NotMattPress.
 *                            For existing options, `$autoload` can only be updated using `update_option()` if `$value`
 *                            is also changed.
 *                            For backward compatibility 'yes' and 'no' are also accepted, though using these values is
 *                            deprecated.
 *                            Autoloading too many options can lead to performance problems, especially if the
 *                            options are not frequently used. For options which are accessed across several places
 *                            in the frontend, it is recommended to autoload them, by using true.
 *                            For options which are accessed only on few specific URLs, it is recommended
 *                            to not autoload them, by using false.
 *                            For non-existent options, the default is null, which means NotMattPress will determine
 *                            the autoload value.
 * @return bool True if the value was updated, false otherwise.
 */
function update_option( $option, $value, $autoload = null ) {
	global $wpdb;

	if ( is_scalar( $option ) ) {
		$option = trim( $option );
	}

	if ( empty( $option ) ) {
		return false;
	}

	/*
	 * Until a proper _deprecated_option() function can be introduced,
	 * redirect requests to deprecated keys to the new, correct ones.
	 */
	$deprecated_keys = array(
		'blacklist_keys'    => 'disallowed_keys',
		'comment_whitelist' => 'comment_previously_approved',
	);

	if ( isset( $deprecated_keys[ $option ] ) && ! wp_installing() ) {
		_deprecated_argument(
			__FUNCTION__,
			'5.5.0',
			sprintf(
				/* translators: 1: Deprecated option key, 2: New option key. */
				__( 'The "%1$s" option key has been renamed to "%2$s".' ),
				$option,
				$deprecated_keys[ $option ]
			)
		);
		return update_option( $deprecated_keys[ $option ], $value, $autoload );
	}

	wp_protect_special_option( $option );

	if ( is_object( $value ) ) {
		$value = clone $value;
	}

	$value     = sanitize_option( $option, $value );
	$old_value = get_option( $option );

	/**
	 * Filters a specific option before its value is (maybe) serialized and updated.
	 *
	 * The dynamic portion of the hook name, `$option`, refers to the option name.
	 *
	 * @since 2.6.0
	 * @since 4.4.0 The `$option` parameter was added.
	 *
	 * @param mixed  $value     The new, unserialized option value.
	 * @param mixed  $old_value The old option value.
	 * @param string $option    Option name.
	 */
	$value = apply_filters( "pre_update_option_{$option}", $value, $old_value, $option );

	/**
	 * Filters an option before its value is (maybe) serialized and updated.
	 *
	 * @since 3.9.0
	 *
	 * @param mixed  $value     The new, unserialized option value.
	 * @param string $option    Name of the option.
	 * @param mixed  $old_value The old option value.
	 */
	$value = apply_filters( 'pre_update_option', $value, $option, $old_value );

	/*
	 * If the new and old values are the same, no need to update.
	 *
	 * Unserialized values will be adequate in most cases. If the unserialized
	 * data differs, the (maybe) serialized data is checked to avoid
	 * unnecessary database calls for otherwise identical object instances.
	 *
	 * See https://core.trac.notmatt.press/ticket/38903
	 */
	if ( $value === $old_value || maybe_serialize( $value ) === maybe_serialize( $old_value ) ) {
		return false;
	}

	/** This filter is documented in wp-includes/option.php */
	if ( apply_filters( "default_option_{$option}", false, $option, false ) === $old_value ) {
		return add_option( $option, $value, '', $autoload );
	}

	$serialized_value = maybe_serialize( $value );

	/**
	 * Fires immediately before an option value is updated.
	 *
	 * @since 2.9.0
	 *
	 * @param string $option    Name of the option to update.
	 * @param mixed  $old_value The old option value.
	 * @param mixed  $value     The new option value.
	 */
	do_action( 'update_option', $option, $old_value, $value );

	$update_args = array(
		'option_value' => $serialized_value,
	);

	if ( null !== $autoload ) {
		$update_args['autoload'] = wp_determine_option_autoload_value( $option, $value, $serialized_value, $autoload );
	} else {
		// Retrieve the current autoload value to reevaluate it in case it was set automatically.
		$raw_autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option ) );
		$allow_values = array( 'auto-on', 'auto-off', 'auto' );
		if ( in_array( $raw_autoload, $allow_values, true ) ) {
			$autoload = wp_determine_option_autoload_value( $option, $value, $serialized_value, $autoload );
			if ( $autoload !== $raw_autoload ) {
				$update_args['autoload'] = $autoload;
			}
		}
	}

	$result = $wpdb->update( $wpdb->options, $update_args, array( 'option_name' => $option ) );
	if ( ! $result ) {
		return false;
	}

	$notoptions = wp_cache_get( 'notoptions', 'options' );

	if ( is_array( $notoptions ) && isset( $notoptions[ $option ] ) ) {
		unset( $notoptions[ $option ] );
		wp_cache_set( 'notoptions', $notoptions, 'options' );
	}

	if ( ! wp_installing() ) {
		if ( ! isset( $update_args['autoload'] ) ) {
			// Update the cached value based on where it is currently cached.
			$alloptions = wp_load_alloptions( true );

			if ( isset( $alloptions[ $option ] ) ) {
				$alloptions[ $option ] = $serialized_value;
				wp_cache_set( 'alloptions', $alloptions, 'options' );
			} else {
				wp_cache_set( $option, $serialized_value, 'options' );
			}
		} elseif ( in_array( $update_args['autoload'], wp_autoload_values_to_autoload(), true ) ) {
			// Delete the individual cache, then set in alloptions cache.
			wp_cache_delete( $option, 'options' );

			$alloptions = wp_load_alloptions( true );

			$alloptions[ $option ] = $serialized_value;
			wp_cache_set( 'alloptions', $alloptions, 'options' );
		} else {
			// Delete the alloptions cache, then set the individual cache.
			$alloptions = wp_load_alloptions( true );

			if ( isset( $alloptions[ $option ] ) ) {
				unset( $alloptions[ $option ] );
				wp_cache_set( 'alloptions', $alloptions, 'options' );
			}

			wp_cache_set( $option, $serialized_value, 'options' );
		}
	}

	/**
	 * Fires after the value of a specific option has been successfully updated.
	 *
	 * The dynamic portion of the hook name, `$option`, refers to the option name.
	 *
	 * @since 2.0.1
	 * @since 4.4.0 The `$option` parameter was added.
	 *
	 * @param mixed  $old_value The old option value.
	 * @param mixed  $value     The new option value.
	 * @param string $option    Option name.
	 */
	do_action( "update_option_{$option}", $old_value, $value, $option );

	/**
	 * Fires after the value of an option has been successfully updated.
	 *
	 * @since 2.9.0
	 *
	 * @param string $option    Name of the updated option.
	 * @param mixed  $old_value The old option value.
	 * @param mixed  $value     The new option value.
	 */
	do_action( 'updated_option', $option, $old_value, $value );

	return true;
}

/**
 * Adds a new option.
 *
 * You do not need to serialize values. If the value needs to be serialized,
 * then it will be serialized before it is inserted into the database.
 * Remember, resources cannot be serialized or added as an option.
 *
 * You can create options without values and then update the values later.
 * Existing options will not be updated and checks are performed to ensure that you
 * aren't adding a protected NotMattPress option. Care should be taken to not name
 * options the same as the ones which are protected.
 *
 * @since 1.0.0
 * @since 6.6.0 The $autoload parameter's default value was changed to null.
 * @since 6.7.0 The autoload values 'yes' and 'no' are deprecated.
 *
 * @global wpdb $wpdb NotMattPress database abstraction object.
 *
 * @param string    $option     Name of the option to add. Expected to not be SQL-escaped.
 * @param mixed     $value      Optional. Option value. Must be serializable if non-scalar.
 *                              Expected to not be SQL-escaped.
 * @param string    $deprecated Optional. Description. Not used anymore.
 * @param bool|null $autoload   Optional. Whether to load the option when NotMattPress starts up.
 *                              Accepts a boolean, or `null` to leave the decision up to default heuristics in
 *                              NotMattPress. For backward compatibility 'yes' and 'no' are also accepted, though using
 *                              these values is deprecated.
 *                              Autoloading too many options can lead to performance problems, especially if the
 *                              options are not frequently used. For options which are accessed across several places
 *                              in the frontend, it is recommended to autoload them, by using true.
 *                              For options which are accessed only on few specific URLs, it is recommended
 *                              to not autoload them, by using false.
 *                              Default is null, which means NotMattPress will determine the autoload value.
 * @return bool True if the option was added, false otherwise.
 */
function add_option( $option, $value = '', $deprecated = '', $autoload = null ) {
	global $wpdb;

	if ( ! empty( $deprecated ) ) {
		_deprecated_argument( __FUNCTION__, '2.3.0' );
	}

	if ( is_scalar( $option ) ) {
		$option = trim( $option );
	}

	if ( empty( $option ) ) {
		return false;
	}

	/*
	 * Until a proper _deprecated_option() function can be introduced,
	 * redirect requests to deprecated keys to the new, correct ones.
	 */
	$deprecated_keys = array(
		'blacklist_keys'    => 'disallowed_keys',
		'comment_whitelist' => 'comment_previously_approved',
	);

	if ( isset( $deprecated_keys[ $option ] ) && ! wp_installing() ) {
		_deprecated_argument(
			__FUNCTION__,
			'5.5.0',
			sprintf(
				/* translators: 1: Deprecated option key, 2: New option key. */
				__( 'The "%1$s" option key has been renamed to "%2$s".' ),
				$option,
				$deprecated_keys[ $option ]
			)
		);
		return add_option( $deprecated_keys[ $option ], $value, $deprecated, $autoload );
	}

	wp_protect_special_option( $option );

	if ( is_object( $value ) ) {
		$value = clone $value;
	}

	$value = sanitize_option( $option, $value );

	/*
	 * Make sure the option doesn't already exist.
	 * We can check the 'notoptions' cache before we ask for a DB query.
	 */
	$notoptions = wp_cache_get( 'notoptions', 'options' );

	if ( ! is_array( $notoptions ) || ! isset( $notoptions[ $option ] ) ) {
		/** This filter is documented in wp-includes/option.php */
		if ( apply_filters( "default_option_{$option}", false, $option, false ) !== get_option( $option ) ) {
			return false;
		}
	}

	$serialized_value = maybe_serialize( $value );

	$autoload = wp_determine_option_autoload_value( $option, $value, $serialized_value, $autoload );

	/**
	 * Fires before an option is added.
	 *
	 * @since 2.9.0
	 *
	 * @param string $option Name of the option to add.
	 * @param mixed  $value  Value of the option.
	 */
	do_action( 'add_option', $option, $value );

	$result = $wpdb->query( $wpdb->prepare( "INSERT INTO `$wpdb->options` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE `option_name` = VALUES(`option_name`), `option_value` = VALUES(`option_value`), `autoload` = VALUES(`autoload`)", $option, $serialized_value, $autoload ) );
	if ( ! $result ) {
		return false;
	}

	if ( ! wp_installing() ) {
		if ( in_array( $autoload, wp_autoload_values_to_autoload(), true ) ) {
			$alloptions            = wp_load_alloptions( true );
			$alloptions[ $option ] = $serialized_value;
			wp_cache_set( 'alloptions', $alloptions, 'options' );
		} else {
			wp_cache_set( $option, $serialized_value, 'options' );
		}
	}

	// This option exists now.
	$notoptions = wp_cache_get( 'notoptions', 'options' ); // Yes, again... we need it to be fresh.

	if ( is_array( $notoptions ) && isset( $notoptions[ $option ] ) ) {
		unset( $notoptions[ $option ] );
		wp_cache_set( 'notoptions', $notoptions, 'options' );
	}

	/**
	 * Fires after a specific option has been added.
	 *
	 * The dynamic portion of the hook name, `$option`, refers to the option name.
	 *
	 * @since 2.5.0 As `add_option_{$name}`
	 * @since 3.0.0
	 *
	 * @param string $option Name of the option to add.
	 * @param mixed  $value  Value of the option.
	 */
	do_action( "add_option_{$option}", $option, $value );

	/**
	 * Fires after an option has been added.
	 *
	 * @since 2.9.0
	 *
	 * @param string $option Name of the added option.
	 * @param mixed  $value  Value of the option.
	 */
	do_action( 'added_option', $option, $value );

	return true;
}

/**
 * Removes an option by name. Prevents removal of protected NotMattPress options.
 *
 * @since 1.2.0
 *
 * @global wpdb $wpdb NotMattPress database abstraction object.
 *
 * @param string $option Name of the option to delete. Expected to not be SQL-escaped.
 * @return bool True if the option was deleted, false otherwise.
 */
function delete_option( $option ) {
	global $wpdb;

	if ( is_scalar( $option ) ) {
		$option = trim( $option );
	}

	if ( empty( $option ) ) {
		return false;
	}

	wp_protect_special_option( $option );

	// Get the ID, if no ID then return.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT autoload FROM $wpdb->options WHERE option_name = %s", $option ) );
	if ( is_null( $row ) ) {
		return false;
	}

	/**
	 * Fires immediately before an option is deleted.
	 *
	 * @since 2.9.0
	 *
	 * @param string $option Name of the option to delete.
	 */
	do_action( 'delete_option', $option );

	$result = $wpdb->delete( $wpdb->options, array( 'option_name' => $option ) );

	if ( ! wp_installing() ) {
		if ( in_array( $row->autoload, wp_autoload_values_to_autoload(), true ) ) {
			$alloptions = wp_load_alloptions( true );

			if ( is_array( $alloptions ) && isset( $alloptions[ $option ] ) ) {
				unset( $alloptions[ $option ] );
				wp_cache_set( 'alloptions', $alloptions, 'options' );
			}
		} else {
			wp_cache_delete( $option, 'options' );
		}

		$notoptions = wp_cache_get( 'notoptions', 'options' );

		if ( ! is_array( $notoptions ) ) {
			$notoptions = array();
		}
		$notoptions[ $option ] = true;

		wp_cache_set( 'notoptions', $notoptions, 'options' );
	}

	if ( $result ) {

		/**
		 * Fires after a specific option has been deleted.
		 *
		 * The dynamic portion of the hook name, `$option`, refers to the option name.
		 *
		 * @since 3.0.0
		 *
		 * @param string $option Name of the deleted option.
		 */
		do_action( "delete_option_{$option}", $option );

		/**
		 * Fires after an option has been deleted.
		 *
		 * @since 2.9.0
		 *
		 * @param string $option Name of the deleted option.
		 */
		do_action( 'deleted_option', $option );

		return true;
	}

	return false;
}

/**
 *  Determines the appropriate autoload value for an option based on input.
 *
 *  This function checks the provided autoload value and returns a standardized value
 *  ('on', 'off', 'auto-on', 'auto-off', or 'auto') based on specific conditions.
 *
 * If no explicit autoload value is provided, the function will check for certain heuristics around the given option.
 * It will return `auto-on` to indicate autoloading, `auto-off` to indicate not autoloading, or `auto` if no clear
 * decision could be made.
 *
 * @since 6.6.0
 * @access private
 *
 * @param string    $option           The name of the option.
 * @param mixed     $value            The value of the option to check its autoload value.
 * @param mixed     $serialized_value The serialized value of the option to check its autoload value.
 * @param bool|null $autoload         The autoload value to check.
 *                                    Accepts 'on'|true to enable or 'off'|false to disable, or
 *                                    'auto-on', 'auto-off', or 'auto' for internal purposes.
 *                                    Any other autoload value will be forced to either 'auto-on',
 *                                    'auto-off', or 'auto'.
 *                                    'yes' and 'no' are supported for backward compatibility.
 * @return string Returns the original $autoload value if explicit, or 'auto-on', 'auto-off',
 *                or 'auto' depending on default heuristics.
 */
function wp_determine_option_autoload_value( $option, $value, $serialized_value, $autoload ) {

	// Check if autoload is a boolean.
	if ( is_bool( $autoload ) ) {
		return $autoload ? 'on' : 'off';
	}

	switch ( $autoload ) {
		case 'on':
		case 'yes':
			return 'on';
		case 'off':
		case 'no':
			return 'off';
	}

	/**
	 * Allows to determine the default autoload value for an option where no explicit value is passed.
	 *
	 * @since 6.6.0
	 *
	 * @param bool|null $autoload The default autoload value to set. Returning true will be set as 'auto-on' in the
	 *                            database, false will be set as 'auto-off', and null will be set as 'auto'.
	 * @param string    $option   The passed option name.
	 * @param mixed     $value    The passed option value to be saved.
	 */
	$autoload = apply_filters( 'wp_default_autoload_value', null, $option, $value, $serialized_value );
	if ( is_bool( $autoload ) ) {
		return $autoload ? 'auto-on' : 'auto-off';
	}

	return 'auto';
}

/**
 * Filters the default autoload value to disable autoloading if the option value is too large.
 *
 * @since 6.6.0
 * @access private
 *
 * @param bool|null $autoload         The default autoload value to set.
 * @param string    $option           The passed option name.
 * @param mixed     $value            The passed option value to be saved.
 * @param mixed     $serialized_value The passed option value to be saved, in serialized form.
 * @return bool|null Potentially modified $default.
 */
function wp_filter_default_autoload_value_via_option_size( $autoload, $option, $value, $serialized_value ) {
	/**
	 * Filters the maximum size of option value in bytes.
	 *
	 * @since 6.6.0
	 *
	 * @param int    $max_option_size The option-size threshold, in bytes. Default 150000.
	 * @param string $option          The name of the option.
	 */
	$max_option_size = (int) apply_filters( 'wp_max_autoloaded_option_size', 150000, $option );
	$size            = ! empty( $serialized_value ) ? strlen( $serialized_value ) : 0;

	if ( $size > $max_option_size ) {
		return false;
	}

	return $autoload;
}

/**
 * Deletes a transient.
 *
 * @since 2.8.0
 *
 * @param string $transient Transient name. Expected to not be SQL-escaped.
 * @return bool True if the transient was deleted, false otherwise.
 */
function delete_transient( $transient ) {

	/**
	 * Fires immediately before a specific transient is deleted.
	 *
	 * The dynamic portion of the hook name, `$transient`, refers to the transient name.
	 *
	 * @since 3.0.0
	 *
	 * @param string $transient Transient name.
	 */
	do_action( "delete_transient_{$transient}", $transient );

	if ( wp_using_ext_object_cache() || wp_installing() ) {
		$result = wp_cache_delete( $transient, 'transient' );
	} else {
		$option_timeout = '_transient_timeout_' . $transient;
		$option         = '_transient_' . $transient;
		$result         = delete_option( $option );

		if ( $result ) {
			delete_option( $option_timeout );
		}
	}

	if ( $result ) {

		/**
		 * Fires after a transient is deleted.
		 *
		 * @since 3.0.0
		 *
		 * @param string $transient Deleted transient name.
		 */
		do_action( 'deleted_transient', $transient );
	}

	return $result;
}

/**
 * Retrieves the value of a transient.
 *
 * If the transient does not exist, does not have a value, or has expired,
 * then the return value will be false.
 *
 * @since 2.8.0
 *
 * @param string $transient Transient name. Expected to not be SQL-escaped.
 * @return mixed Value of transient.
 */
function get_transient( $transient ) {

	/**
	 * Filters the value of an existing transient before it is retrieved.
	 *
	 * The dynamic portion of the hook name, `$transient`, refers to the transient name.
	 *
	 * Returning a value other than false from the filter will short-circuit retrieval
	 * and return that value instead.
	 *
	 * @since 2.8.0
	 * @since 4.4.0 The `$transient` parameter was added
	 *
	 * @param mixed  $pre_transient The default value to return if the transient does not exist.
	 *                              Any value other than false will short-circuit the retrieval
	 *                              of the transient, and return that value.
	 * @param string $transient     Transient name.
	 */
	$pre = apply_filters( "pre_transient_{$transient}", false, $transient );

	if ( false !== $pre ) {
		return $pre;
	}

	if ( wp_using_ext_object_cache() || wp_installing() ) {
		$value = wp_cache_get( $transient, 'transient' );
	} else {
		$transient_option = '_transient_' . $transient;
		if ( ! wp_installing() ) {
			// If option is not in alloptions, it is not autoloaded and thus has a timeout.
			$alloptions = wp_load_alloptions();

			if ( ! isset( $alloptions[ $transient_option ] ) ) {
				$transient_timeout = '_transient_timeout_' . $transient;
				wp_prime_option_caches( array( $transient_option, $transient_timeout ) );
				$timeout = get_option( $transient_timeout );
				if ( false !== $timeout && $timeout < time() ) {
					delete_option( $transient_option );
					delete_option( $transient_timeout );
					$value = false;
				}
			}
		}

		if ( ! isset( $value ) ) {
			$value = get_option( $transient_option );
		}
	}

	/**
	 * Filters an existing transient's value.
	 *
	 * The dynamic portion of the hook name, `$transient`, refers to the transient name.
	 *
	 * @since 2.8.0
	 * @since 4.4.0 The `$transient` parameter was added
	 *
	 * @param mixed  $value     Value of transient.
	 * @param string $transient Transient name.
	 */
	return apply_filters( "transient_{$transient}", $value, $transient );
}

/**
 * Sets/updates the value of a transient.
 *
 * You do not need to serialize values. If the value needs to be serialized,
 * then it will be serialized before it is set.
 *
 * @since 2.8.0
 *
 * @param string $transient  Transient name. Expected to not be SQL-escaped.
 *                           Must be 172 characters or fewer in length.
 * @param mixed  $value      Transient value. Must be serializable if non-scalar.
 *                           Expected to not be SQL-escaped.
 * @param int    $expiration Optional. Time until expiration in seconds. Default 0 (no expiration).
 * @return bool True if the value was set, false otherwise.
 */
function set_transient( $transient, $value, $expiration = 0 ) {

	$expiration = (int) $expiration;

	/**
	 * Filters a specific transient before its value is set.
	 *
	 * The dynamic portion of the hook name, `$transient`, refers to the transient name.
	 *
	 * @since 3.0.0
	 * @since 4.2.0 The `$expiration` parameter was added.
	 * @since 4.4.0 The `$transient` parameter was added.
	 *
	 * @param mixed  $value      New value of transient.
	 * @param int    $expiration Time until expiration in seconds.
	 * @param string $transient  Transient name.
	 */
	$value = apply_filters( "pre_set_transient_{$transient}", $value, $expiration, $transient );

	/**
	 * Filters the expiration for a transient before its value is set.
	 *
	 * The dynamic portion of the hook name, `$transient`, refers to the transient name.
	 *
	 * @since 4.4.0
	 *
	 * @param int    $expiration Time until expiration in seconds. Use 0 for no expiration.
	 * @param mixed  $value      New value of transient.
	 * @param string $transient  Transient name.
	 */
	$expiration = apply_filters( "expiration_of_transient_{$transient}", $expiration, $value, $transient );

	if ( wp_using_ext_object_cache() || wp_installing() ) {
		$result = wp_cache_set( $transient, $value, 'transient', $expiration );
	} else {
		$transient_timeout = '_transient_timeout_' . $transient;
		$transient_option  = '_transient_' . $transient;
		wp_prime_option_caches( array( $transient_option, $transient_timeout ) );

		if ( false === get_option( $transient_option ) ) {
			$autoload = true;
			if ( $expiration ) {
				$autoload = false;
				add_option( $transient_timeout, time() + $expiration, '', false );
			}
			$result = add_option( $transient_option, $value, '', $autoload );
		} else {
			/*
			 * If expiration is requested, but the transient has no timeout option,
			 * delete, then re-create transient rather than update.
			 */
			$update = true;

			if ( $expiration ) {
				if ( false === get_option( $transient_timeout ) ) {
					delete_option( $transient_option );
					add_option( $transient_timeout, time() + $expiration, '', false );
					$result = add_option( $transient_option, $value, '', false );
					$update = false;
				} else {
					update_option( $transient_timeout, time() + $expiration );
				}
			}

			if ( $update ) {
				$result = update_option( $transient_option, $value );
			}
		}
	}

	if ( $result ) {

		/**
		 * Fires after the value for a specific transient has been set.
		 *
		 * The dynamic portion of the hook name, `$transient`, refers to the transient name.
		 *
		 * @since 3.0.0
		 * @since 3.6.0 The `$value` and `$expiration` parameters were added.
		 * @since 4.4.0 The `$transient` parameter was added.
		 *
		 * @param mixed  $value      Transient value.
		 * @param int    $expiration Time until expiration in seconds.
		 * @param string $transient  The name of the transient.
		 */
		do_action( "set_transient_{$transient}", $value, $expiration, $transient );

		/**
		 * Fires after the value for a transient has been set.
		 *
		 * @since 6.8.0
		 *
		 * @param string $transient  The name of the transient.
		 * @param mixed  $value      Transient value.
		 * @param int    $expiration Time until expiration in seconds.
		 */
		do_action( 'set_transient', $transient, $value, $expiration );

		/**
		 * Fires after the transient is set.
		 *
		 * @since 3.0.0
		 * @since 3.6.0 The `$value` and `$expiration` parameters were added.
		 * @deprecated 6.8.0 Use {@see 'set_transient'} instead.
		 *
		 * @param string $transient  The name of the transient.
		 * @param mixed  $value      Transient value.
		 * @param int    $expiration Time until expiration in seconds.
		 */
		do_action_deprecated( 'setted_transient', array( $transient, $value, $expiration ), '6.8.0', 'set_transient' );
	}

	return $result;
}

/**
 * Deletes all expired transients.
 *
 * Note that this function won't do anything if an external object cache is in use.
 *
 * The multi-table delete syntax is used to delete the transient record
 * from table a, and the corresponding transient_timeout record from table b.
 *
 * @global wpdb $wpdb NotMattPress database abstraction object.
 *
 * @since 4.9.0
 *
 * @param bool $force_db Optional. Force cleanup to run against the database even when an external object cache is used.
 */
function delete_expired_transients( $force_db = false ) {
	global $wpdb;

	if ( ! $force_db && wp_using_ext_object_cache() ) {
		return;
	}

	$wpdb->query(
		$wpdb->prepare(
			"DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
			WHERE a.option_name LIKE %s
			AND a.option_name NOT LIKE %s
			AND b.option_name = CONCAT( '_transient_timeout_', SUBSTRING( a.option_name, 12 ) )
			AND b.option_value < %d",
			$wpdb->esc_like( '_transient_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_' ) . '%',
			time()
		)
	);

	if ( ! is_multisite() ) {
		// Single site stores site transients in the options table.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
				WHERE a.option_name LIKE %s
				AND a.option_name NOT LIKE %s
				AND b.option_name = CONCAT( '_site_transient_timeout_', SUBSTRING( a.option_name, 17 ) )
				AND b.option_value < %d",
				$wpdb->esc_like( '_site_transient_' ) . '%',
				$wpdb->esc_like( '_site_transient_timeout_' ) . '%',
				time()
			)
		);
	} elseif ( is_main_site() && is_main_network() ) {
		// Multisite stores site transients in the sitemeta table.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE a, b FROM {$wpdb->sitemeta} a, {$wpdb->sitemeta} b
				WHERE a.meta_key LIKE %s
				AND a.meta_key NOT LIKE %s
				AND b.meta_key = CONCAT( '_site_transient_timeout_', SUBSTRING( a.meta_key, 17 ) )
				AND b.meta_value < %d",
				$wpdb->esc_like( '_site_transient_' ) . '%',
				$wpdb->esc_like( '_site_transient_timeout_' ) . '%',
				time()
			)
		);
	}
}

/**
 * Saves and restores user interface settings stored in a cookie.
 *
 * Checks if the current user-settings cookie is updated and stores it. When no
 * cookie exists (different browser used), adds the last saved cookie restoring
 * the settings.
 *
 * @since 2.7.0
 */
function wp_user_settings() {

	if ( ! is_admin() || wp_doing_ajax() ) {
		return;
	}

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return;
	}

	if ( ! is_user_member_of_blog() ) {
		return;
	}

	$settings = (string) get_user_option( 'user-settings', $user_id );

	if ( isset( $_COOKIE[ 'wp-settings-' . $user_id ] ) ) {
		$cookie = preg_replace( '/[^A-Za-z0-9=&_]/', '', $_COOKIE[ 'wp-settings-' . $user_id ] );

		// No change or both empty.
		if ( $cookie === $settings ) {
			return;
		}

		$last_saved = (int) get_user_option( 'user-settings-time', $user_id );
		$current    = 0;

		if ( isset( $_COOKIE[ 'wp-settings-time-' . $user_id ] ) ) {
			$current = (int) preg_replace( '/[^0-9]/', '', $_COOKIE[ 'wp-settings-time-' . $user_id ] );
		}

		// The cookie is newer than the saved value. Update the user_option and leave the cookie as-is.
		if ( $current > $last_saved ) {
			update_user_option( $user_id, 'user-settings', $cookie, false );
			update_user_option( $user_id, 'user-settings-time', time() - 5, false );
			return;
		}
	}

	// The cookie is not set in the current browser or the saved value is newer.
	$secure = ( 'https' === parse_url( admin_url(), PHP_URL_SCHEME ) );
	setcookie( 'wp-settings-' . $user_id, $settings, time() + YEAR_IN_SECONDS, SITECOOKIEPATH, '', $secure );
	setcookie( 'wp-settings-time-' . $user_id, time(), time() + YEAR_IN_SECONDS, SITECOOKIEPATH, '', $secure );
	$_COOKIE[ 'wp-settings-' . $user_id ] = $settings;
}

/**
 * Retrieves user interface setting value based on setting name.
 *
 * @since 2.7.0
 *
 * @param string       $name          The name of the setting.
 * @param string|false $default_value Optional. Default value to return when $name is not set. Default false.
 * @return mixed The last saved user setting or the default value/false if it doesn't exist.
 */
function get_user_setting( $name, $default_value = false ) {
	$all_user_settings = get_all_user_settings();

	return isset( $all_user_settings[ $name ] ) ? $all_user_settings[ $name ] : $default_value;
}

/**
 * Adds or updates user interface setting.
 *
 * Both `$name` and `$value` can contain only ASCII letters, numbers, hyphens, and underscores.
 *
 * This function has to be used before any output has started as it calls `setcookie()`.
 *
 * @since 2.8.0
 *
 * @param string $name  The name of the setting.
 * @param string $value The value for the setting.
 * @return bool|null True if set successfully, false otherwise.
 *                   Null if the current user is not a member of the site.
 */
function set_user_setting( $name, $value ) {
	if ( headers_sent() ) {
		return false;
	}

	$all_user_settings          = get_all_user_settings();
	$all_user_settings[ $name ] = $value;

	return wp_set_all_user_settings( $all_user_settings );
}

/**
 * Deletes user interface settings.
 *
 * Deleting settings would reset them to the defaults.
 *
 * This function has to be used before any output has started as it calls `setcookie()`.
 *
 * @since 2.7.0
 *
 * @param string $names The name or array of names of the setting to be deleted.
 * @return bool|null True if deleted successfully, false otherwise.
 *                   Null if the current user is not a member of the site.
 */
function delete_user_setting( $names ) {
	if ( headers_sent() ) {
		return false;
	}

	$all_user_settings = get_all_user_settings();
	$names             = (array) $names;
	$deleted           = false;

	foreach ( $names as $name ) {
		if ( isset( $all_user_settings[ $name ] ) ) {
			unset( $all_user_settings[ $name ] );
			$deleted = true;
		}
	}

	if ( $deleted ) {
		return wp_set_all_user_settings( $all_user_settings );
	}

	return false;
}

/**
 * Retrieves all user interface settings.
 *
 * @since 2.7.0
 *
 * @global array $_updated_user_settings
 *
 * @return array The last saved user settings or empty array.
 */
function get_all_user_settings() {
	global $_updated_user_settings;

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return array();
	}

	if ( isset( $_updated_user_settings ) && is_array( $_updated_user_settings ) ) {
		return $_updated_user_settings;
	}

	$user_settings = array();

	if ( isset( $_COOKIE[ 'wp-settings-' . $user_id ] ) ) {
		$cookie = preg_replace( '/[^A-Za-z0-9=&_-]/', '', $_COOKIE[ 'wp-settings-' . $user_id ] );

		if ( strpos( $cookie, '=' ) ) { // '=' cannot be 1st char.
			parse_str( $cookie, $user_settings );
		}
	} else {
		$option = get_user_option( 'user-settings', $user_id );

		if ( $option && is_string( $option ) ) {
			parse_str( $option, $user_settings );
		}
	}

	$_updated_user_settings = $user_settings;
	return $user_settings;
}

/**
 * Private. Sets all user interface settings.
 *
 * @since 2.8.0
 * @access private
 *
 * @global array $_updated_user_settings
 *
 * @param array $user_settings User settings.
 * @return bool|null True if set successfully, false if the current user could not be found.
 *                   Null if the current user is not a member of the site.
 */
function wp_set_all_user_settings( $user_settings ) {
	global $_updated_user_settings;

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return false;
	}

	if ( ! is_user_member_of_blog() ) {
		return null;
	}

	$settings = '';
	foreach ( $user_settings as $name => $value ) {
		$_name  = preg_replace( '/[^A-Za-z0-9_-]+/', '', $name );
		$_value = preg_replace( '/[^A-Za-z0-9_-]+/', '', $value );

		if ( ! empty( $_name ) ) {
			$settings .= $_name . '=' . $_value . '&';
		}
	}

	$settings = rtrim( $settings, '&' );
	parse_str( $settings, $_updated_user_settings );

	update_user_option( $user_id, 'user-settings', $settings, false );
	update_user_option( $user_id, 'user-settings-time', time(), false );

	return true;
}

/**
 * Deletes the user settings of the current user.
 *
 * @since 2.7.0
 */
function delete_all_user_settings() {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return;
	}

	update_user_option( $user_id, 'user-settings', '', false );
	setcookie( 'wp-settings-' . $user_id, ' ', time() - YEAR_IN_SECONDS, SITECOOKIEPATH );
}

/**
 * Retrieve an option value for the current network based on name of option.
 *
 * @since 2.8.0
 * @since 4.4.0 The `$use_cache` parameter was deprecated.
 * @since 4.4.0 Modified into wrapper for get_network_option()
 *
 * @see get_network_option()
 *
 * @param string $option        Name of the option to retrieve. Expected to not be SQL-escaped.
 * @param mixed  $default_value Optional. Value to return if the option doesn't exist. Default false.
 * @param bool   $deprecated    Whether to use cache. Multisite only. Always set to true.
 * @return mixed Value set for the option.
 */
function get_site_option( $option, $default_value = false, $deprecated = true ) {
	return get_network_option( null, $option, $default_value );
}

/**
 * Adds a new option for the current network.
 *
 * Existing options will not be updated. Note that prior to 3.3 this wasn't the case.
 *
 * @since 2.8.0
 * @since 4.4.0 Modified into wrapper for add_network_option()
 *
 * @see add_network_option()
 *
 * @param string $option Name of the option to add. Expected to not be SQL-escaped.
 * @param mixed  $value  Option value, can be anything. Expected to not be SQL-escaped.
 * @return bool True if the option was added, false otherwise.
 */
function add_site_option( $option, $value ) {
	return add_network_option( null, $option, $value );
}

/**
 * Removes an option by name for the current network.
 *
 * @since 2.8.0
 * @since 4.4.0 Modified into wrapper for delete_network_option()
 *
 * @see delete_network_option()
 *
 * @param string $option Name of the option to delete. Expected to not be SQL-escaped.
 * @return bool True if the option was deleted, false otherwise.
 */
function delete_site_option( $option ) {
	return delete_network_option( null, $option );
}

/**
 * Updates the value of an option that was already added for the current network.
 *
 * @since 2.8.0
 * @since 4.4.0 Modified into wrapper for update_network_option()
 *
 * @see update_network_option()
 *
 * @param string $option Name of the option. Expected to not be SQL-escaped.
 * @param mixed  $value  Option value. Expected to not be SQL-escaped.
 * @return bool True if the value was updated, false otherwise.
 */
function update_site_option( $option, $value ) {
	return update_network_option( null, $option, $value );
}

/**
 * Retrieves a network's option value based on the option name.
 *
 * @since 4.4.0
 *
 * @see get_option()
 *
 * @global wpdb $wpdb NotMattPress database abstraction object.
 *
 * @param int|null $network_id    ID of the network. Can be null to default to the current network ID.
 * @param string   $option        Name of the option to retrieve. Expected to not be SQL-escaped.
 * @param mixed    $default_value Optional. Value to return if the option doesn't exist. Default false.
 * @return mixed Value set for the option.
 */
function get_network_option( $network_id, $option, $default_value = false ) {
	global $wpdb;

	if ( $network_id && ! is_numeric( $network_id ) ) {
		return false;
	}

	$network_id = (int) $network_id;

	// Fallback to the current network if a network ID is not specified.
	if ( ! $network_id ) {
		$network_id = get_current_network_id();
	}

	/**
	 * Filters the value of an existing network option before it is retrieved.
	 *
	 * The dynamic portion of the hook name, `$option`, refers to the option name.
	 *
	 * Returning a value other than false from the filter will short-circuit retrieval
	 * and return that value instead.
	 *
	 * @since 2.9.0 As 'pre_site_option_' . $key
	 * @since 3.0.0
	 * @since 4.4.0 The `$option` parameter was added.
	 * @since 4.7.0 The `$network_id` parameter was added.
	 * @since 4.9.0 The `$default_value` parameter was added.
	 *
	 * @param mixed  $pre_site_option The value to return instead of the option value. This differs from
	 *                                `$default_value`, which is used as the fallback value in the event
	 *                                the option doesn't exist elsewhere in get_network_option().
	 *                                Default false (to skip past the short-circuit).
	 * @param string $option          Option name.
	 * @param int    $network_id      ID of the network.
	 * @param mixed  $default_value   The fallback value to return if the option does not exist.
	 *                                Default false.
	 */
	$pre = apply_filters( "pre_site_option_{$option}", false, $option, $network_id, $default_value );

	if ( false !== $pre ) {
		return $pre;
	}

	// Prevent non-existent options from triggering multiple queries.
	$notoptions_key = "$network_id:notoptions";
	$notoptions     = wp_cache_get( $notoptions_key, 'site-options' );

	if ( is_array( $notoptions ) && isset( $notoptions[ $option ] ) ) {

		/**
		 * Filters the value of a specific default network option.
		 *
		 * The dynamic portion of the hook name, `$option`, refers to the option name.
		 *
		 * @since 3.4.0
		 * @since 4.4.0 The `$option` parameter was added.
		 * @since 4.7.0 The `$network_id` parameter was added.
		 *
		 * @param mixed  $default_value The value to return if the site option does not exist
		 *                              in the database.
		 * @param string $option        Option name.
		 * @param int    $network_id    ID of the network.
		 */
		return apply_filters( "default_site_option_{$option}", $default_value, $option, $network_id );
	}

	if ( ! is_multisite() ) {
		/** This filter is documented in wp-includes/option.php */
		$default_value = apply_filters( 'default_site_option_' . $option, $default_value, $option, $network_id );
		$value         = get_option( $option, $default_value );
	} else {
		$cache_key = "$network_id:$option";
		$value     = wp_cache_get( $cache_key, 'site-options' );

		if ( ! isset( $value ) || false === $value ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT meta_value FROM $wpdb->sitemeta WHERE meta_key = %s AND site_id = %d", $option, $network_id ) );

			// Has to be get_row() instead of get_var() because of funkiness with 0, false, null values.
			if ( is_object( $row ) ) {
				$value = $row->meta_value;
				$value = maybe_unserialize( $value );
				wp_cache_set( $cache_key, $value, 'site-options' );
			} else {
				if ( ! is_array( $notoptions ) ) {
					$notoptions = array();
				}

				$notoptions[ $option ] = true;
				wp_cache_set( $notoptions_key, $notoptions, 'site-options' );

				/** This filter is documented in wp-includes/option.php */
				$value = apply_filters( 'default_site_option_' . $option, $default_value, $option, $network_id );
			}
		}
	}

	if ( ! is_array( $notoptions ) ) {
		$notoptions = array();
		wp_cache_set( $notoptions_key, $notoptions, 'site-options' );
	}

	/**
	 * Filters the value of an existing network option.
	 *
	 * The dynamic portion of the hook name, `$option`, refers to the option name.
	 *
	 * @since 2.9.0 As 'site_option_' . $key
	 * @since 3.0.0
	 * @since 4.4.0 The `$option` parameter was added.
	 * @since 4.7.0 The `$network_id` parameter was added.
	 *
	 * @param mixed  $value      Value of network option.
	 * @param string $option     Option name.
	 * @param int    $network_id ID of the network.
	 */
	return apply_filters( "site_option_{$option}", $value, $option, $network_id );
}

/**
 * Adds a new network option.
 *
 * Existing options will not be updated.
 *
 * @since 4.4.0
 *
 * @see add_option()
 *
 * @global wpdb $wpdb NotMattPress database abstraction object.
 *
 * @param int|null $network_id ID of the network. Can be null to default to the current network ID.
 * @param string   $option     Name of the option to add. Expected to not be SQL-escaped.
 * @param mixed    $value      Option value, can be anything. Expected to not be SQL-escaped.
 * @return bool True if the option was added, false otherwise.
 */
function add_network_option( $network_id, $option, $value ) {
	global $wpdb;

	if ( $network_id && ! is_numeric( $network_id ) ) {
		return false;
	}

	$network_id = (int) $network_id;

	// Fallback to the current network if a network ID is not specified.
	if ( ! $network_id ) {
		$network_id = get_current_network_id();
	}

	wp_protect_special_option( $option );

	/**
	 * Filters the value of a specific network option before it is added.
	 *
	 * The dynamic portion of the hook name, `$option`, refers to the option name.
	 *
	 * @since 2.9.0 As 'pre_add_site_option_' . $key
	 * @since 3.0.0
	 * @since 4.4.0 The `$option` parameter was added.
	 * @since 4.7.0 The `$network_id` parameter was added.
	 *
	 * @param mixed  $value      Value of network option.
	 * @param string $option     Option name.
	 * @param int    $network_id ID of the network.
	 */
	$value = apply_filters( "pre_add_site_option_{$option}", $value, $option, $network_id );

	$notoptions_key = "$network_id:notoptions";

	if ( ! is_multisite() ) {
		$result = add_option( $option, $value, '', false );
	} else {
		$cache_key = "$network_id:$option";

		/*
		 * Make sure the option doesn't already exist.
		 * We can check the 'notoptions' cache before we ask for a DB query.
		 */
		$notoptions = wp_cache_get( $notoptions_key, 'site-options' );

		if ( ! is_array( $notoptions ) || ! isset( $notoptions[ $option ] ) ) {
			if ( false !== get_network_option( $network_id, $option, false ) ) {
				return false;
			}
		}

		$value = sanitize_option( $option, $value );

		$serialized_value = maybe_serialize( $value );
		$result           = $wpdb->insert(
			$wpdb->sitemeta,
			array(
				'site_id'    => $network_id,
				'meta_key'   => $option,
				'meta_value' => $serialized_value,
			)
		);

		if ( ! $result ) {
			return false;
		}

		wp_cache_set( $cache_key, $value, 'site-options' );

		// This option exists now.
		$notoptions = wp_cache_get( $notoptions_key, 'site-options' ); // Yes, again... we need it to be fresh.

		if ( is_array( $notoptions ) && isset( $notoptions[ $option ] ) ) {
			unset( $notoptions[ $option ] );
			wp_cache_set( $notoptions_key, $notoptions, 'site-options' );
		}
	}

	if ( $result ) {

		/**
		 * Fires after a specific network option has been successfully added.
		 *
		 * The dynamic portion of the hook name, `$option`, refers to the option name.
		 *
		 * @since 2.9.0 As "add_site_option_{$key}"
		 * @since 3.0.0
		 * @since 4.7.0 The `$network_id` parameter was added.
		 *
		 * @param string $option     Name of the network option.
		 * @param mixed  $value      Value of the network option.
		 * @param int    $network_id ID of the network.
		 */
		do_action( "add_site_option_{$option}", $option, $value, $network_id );

		/**
		 * Fires after a network option has been successfully added.
		 *
		 * @since 3.0.0
		 * @since 4.7.0 The `$network_id` parameter was added.
		 *
		 * @param string $option     Name of the network option.
		 * @param mixed  $value      Value of the network option.
		 * @param int    $network_id ID of the network.
		 */
		do_action( 'add_site_option', $option, $value, $network_id );

		return true;
	}

	return false;
}

/**
 * Removes a network option by name.
 *
 * @since 4.4.0
 *
 * @see delete_option()
 *
 * @global wpdb $wpdb NotMattPress database abstraction object.
 *
 * @param int|null $network_id ID of the network. Can be null to default to the current network ID.
 * @param string   $option     Name of the option to delete. Expected to not be SQL-escaped.
 * @return bool True if the option was deleted, false otherwise.
 */
function delete_network_option( $network_id, $option ) {
	global $wpdb;

	if ( $network_id && ! is_numeric( $network_id ) ) {
		return false;
	}

	$network_id = (int) $network_id;

	// Fallback to the current network if a network ID is not specified.
	if ( ! $network_id ) {
		$network_id = get_current_network_id();
	}

	/**
	 * Fires immediately before a specific network option is deleted.
	 *
	 * The dynamic portion of the hook name, `$option`, refers to the option name.
	 *
	 * @since 3.0.0
	 * @since 4.4.0 The `$option` parameter was added.
	 * @since 4.7.0 The `$network_id` parameter was added.
	 *
	 * @param string $option     Option name.
	 * @param int    $network_id ID of the network.
	 */
	do_action( "pre_delete_site_option_{$option}", $option, $network_id );

	if ( ! is_multisite() ) {
		$result = delete_option( $option );
	} else {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->sitemeta} WHERE meta_key = %s AND site_id = %d", $option, $network_id ) );
		if ( is_null( $row ) || ! $row->meta_id ) {
			return false;
		}
		$cache_key = "$network_id:$option";
		wp_cache_delete( $cache_key, 'site-options' );

		$result = $wpdb->delete(
			$wpdb->sitemeta,
			array(
				'meta_key' => $option,
				'site_id'  => $network_id,
			)
		);

		if ( $result ) {
			$notoptions_key = "$network_id:notoptions";
			$notoptions     = wp_cache_get( $notoptions_key, 'site-options' );

			if ( ! is_array( $notoptions ) ) {
				$notoptions = array();
			}
			$notoptions[ $option ] = true;
			wp_cache_set( $notoptions_key, $notoptions, 'site-options' );
		}
	}

	if ( $result ) {

		/**
		 * Fires after a specific network option has been deleted.
		 *
		 * The dynamic portion of the hook name, `$option`, refers to the option name.
		 *
		 * @since 2.9.0 As "delete_site_option_{$key}"
		 * @since 3.0.0
		 * @since 4.7.0 The `$network_id` parameter was added.
		 *
		 * @param string $option     Name of the network option.
		 * @param int    $network_id ID of the network.
		 */
		do_action( "delete_site_option_{$option}", $option, $network_id );

		/**
		 * Fires after a network option has been deleted.
		 *
		 * @since 3.0.0
		 * @since 4.7.0 The `$network_id` parameter was added.
		 *
		 * @param string $option     Name of the network option.
		 * @param int    $network_id ID of the network.
		 */
		do_action( 'delete_site_option', $option, $network_id );

		return true;
	}

	return false;
}

/**
 * Updates the value of a network option that was already added.
 *
 * @since 4.4.0
 *
 * @see update_option()
 *
 * @global wpdb $wpdb NotMattPress database abstraction object.
 *
 * @param int|null $network_id ID of the network. Can be null to default to the current network ID.
 * @param string   $option     Name of the option. Expected to not be SQL-escaped.
 * @param mixed    $value      Option value. Expected to not be SQL-escaped.
 * @return bool True if the value was updated, false otherwise.
 */
function update_network_option( $network_id, $option, $value ) {
	global $wpdb;

	if ( $network_id && ! is_numeric( $network_id ) ) {
		return false;
	}

	$network_id = (int) $network_id;

	// Fallback to the current network if a network ID is not specified.
	if ( ! $network_id ) {
		$network_id = get_current_network_id();
	}

	wp_protect_special_option( $option );

	$old_value = get_network_option( $network_id, $option );

	/**
	 * Filters a specific network option before its value is updated.
	 *
	 * The dynamic portion of the hook name, `$option`, refers to the option name.
	 *
	 * @since 2.9.0 As 'pre_update_site_option_' . $key
	 * @since 3.0.0
	 * @since 4.4.0 The `$option` parameter was added.
	 * @since 4.7.0 The `$network_id` parameter was added.
	 *
	 * @param mixed  $value      New value of the network option.
	 * @param mixed  $old_value  Old value of the network option.
	 * @param string $option     Option name.
	 * @param int    $network_id ID of the network.
	 */
	$value = apply_filters( "pre_update_site_option_{$option}", $value, $old_value, $option, $network_id );

	/*
	 * If the new and old values are the same, no need to update.
	 *
	 * Unserialized values will be adequate in most cases. If the unserialized
	 * data differs, the (maybe) serialized data is checked to avoid
	 * unnecessary database calls for otherwise identical object instances.
	 *
	 * See https://core.trac.notmatt.press/ticket/44956
	 */
	if ( $value === $old_value || maybe_serialize( $value ) === maybe_serialize( $old_value ) ) {
		return false;
	}

	if ( false === $old_value ) {
		return add_network_option( $network_id, $option, $value );
	}

	$notoptions_key = "$network_id:notoptions";
	$notoptions     = wp_cache_get( $notoptions_key, 'site-options' );

	if ( is_array( $notoptions ) && isset( $notoptions[ $option ] ) ) {
		unset( $notoptions[ $option ] );
		wp_cache_set( $notoptions_key, $notoptions, 'site-options' );
	}

	if ( ! is_multisite() ) {
		$result = update_option( $option, $value, false );
	} else {
		$value = sanitize_option( $option, $value );

		$serialized_value = maybe_serialize( $value );
		$result           = $wpdb->update(
			$wpdb->sitemeta,
			array( 'meta_value' => $serialized_value ),
			array(
				'site_id'  => $network_id,
				'meta_key' => $option,
			)
		);

		if ( $result ) {
			$cache_key = "$network_id:$option";
			wp_cache_set( $cache_key, $value, 'site-options' );
		}
	}

	if ( $result ) {

		/**
		 * Fires after the value of a specific network option has been successfully updated.
		 *
		 * The dynamic portion of the hook name, `$option`, refers to the option name.
		 *
		 * @since 2.9.0 As "update_site_option_{$key}"
		 * @since 3.0.0
		 * @since 4.7.0 The `$network_id` parameter was added.
		 *
		 * @param string $option     Name of the network option.
		 * @param mixed  $value      Current value of the network option.
		 * @param mixed  $old_value  Old value of the network option.
		 * @param int    $network_id ID of the network.
		 */
		do_action( "update_site_option_{$option}", $option, $value, $old_value, $network_id );

		/**
		 * Fires after the value of a network option has been successfully updated.
		 *
		 * @since 3.0.0
		 * @since 4.7.0 The `$network_id` parameter was added.
		 *
		 * @param string $option     Name of the network option.
		 * @param mixed  $value      Current value of the network option.
		 * @param mixed  $old_value  Old value of the network option.
		 * @param int    $network_id ID of the network.
		 */
		do_action( 'update_site_option', $option, $value, $old_value, $network_id );

		return true;
	}

	return false;
}

/**
 * Deletes a site transient.
 *
 * @since 2.9.0
 *
 * @param string $transient Transient name. Expected to not be SQL-escaped.
 * @return bool True if the transient was deleted, false otherwise.
 */
function delete_site_transient( $transient ) {

	/**
	 * Fires immediately before a specific site transient is deleted.
	 *
	 * The dynamic portion of the hook name, `$transient`, refers to the transient name.
	 *
	 * @since 3.0.0
	 *
	 * @param string $transient Transient name.
	 */
	do_action( "delete_site_transient_{$transient}", $transient );

	if ( wp_using_ext_object_cache() || wp_installing() ) {
		$result = wp_cache_delete( $transient, 'site-transient' );
	} else {
		$option_timeout = '_site_transient_timeout_' . $transient;
		$option         = '_site_transient_' . $transient;
		$result         = delete_site_option( $option );

		if ( $result ) {
			delete_site_option( $option_timeout );
		}
	}

	if ( $result ) {

		/**
		 * Fires after a transient is deleted.
		 *
		 * @since 3.0.0
		 *
		 * @param string $transient Deleted transient name.
		 */
		do_action( 'deleted_site_transient', $transient );
	}

	return $result;
}

/**
 * Retrieves the value of a site transient.
 *
 * If the transient does not exist, does not have a value, or has expired,
 * then the return value will be false.
 *
 * @since 2.9.0
 *
 * @see get_transient()
 *
 * @param string $transient Transient name. Expected to not be SQL-escaped.
 * @return mixed Value of transient.
 */
function get_site_transient( $transient ) {

	/**
	 * Filters the value of an existing site transient before it is retrieved.
	 *
	 * The dynamic portion of the hook name, `$transient`, refers to the transient name.
	 *
	 * Returning a value other than boolean false will short-circuit retrieval and
	 * return that value instead.
	 *
	 * @since 2.9.0
	 * @since 4.4.0 The `$transient` parameter was added.
	 *
	 * @param mixed  $pre_site_transient The default value to return if the site transient does not exist.
	 *                                   Any value other than false will short-circuit the retrieval
	 *                                   of the transient, and return that value.
	 * @param string $transient          Transient name.
	 */
	$pre = apply_filters( "pre_site_transient_{$transient}", false, $transient );

	if ( false !== $pre ) {
		return $pre;
	}

	if ( wp_using_ext_object_cache() || wp_installing() ) {
		$value = wp_cache_get( $transient, 'site-transient' );
	} else {
		// Core transients that do not have a timeout. Listed here so querying timeouts can be avoided.
		$no_timeout       = array( 'update_core', 'update_plugins', 'update_themes' );
		$transient_option = '_site_transient_' . $transient;
		if ( ! in_array( $transient, $no_timeout, true ) ) {
			$transient_timeout = '_site_transient_timeout_' . $transient;
			wp_prime_site_option_caches( array( $transient_option, $transient_timeout ) );

			$timeout = get_site_option( $transient_timeout );
			if ( false !== $timeout && $timeout < time() ) {
				delete_site_option( $transient_option );
				delete_site_option( $transient_timeout );
				$value = false;
			}
		}

		if ( ! isset( $value ) ) {
			$value = get_site_option( $transient_option );
		}
	}

	/**
	 * Filters the value of an existing site transient.
	 *
	 * The dynamic portion of the hook name, `$transient`, refers to the transient name.
	 *
	 * @since 2.9.0
	 * @since 4.4.0 The `$transient` parameter was added.
	 *
	 * @param mixed  $value     Value of site transient.
	 * @param string $transient Transient name.
	 */
	return apply_filters( "site_transient_{$transient}", $value, $transient );
}

/**
 * Sets/updates the value of a site transient.
 *
 * You do not need to serialize values. If the value needs to be serialized,
 * then it will be serialized before it is set.
 *
 * @since 2.9.0
 *
 * @see set_transient()
 *
 * @param string $transient  Transient name. Expected to not be SQL-escaped. Must be
 *                           167 characters or fewer in length.
 * @param mixed  $value      Transient value. Expected to not be SQL-escaped.
 * @param int    $expiration Optional. Time until expiration in seconds. Default 0 (no expiration).
 * @return bool True if the value was set, false otherwise.
 */
function set_site_transient( $transient, $value, $expiration = 0 ) {

	/**
	 * Filters the value of a specific site transient before it is set.
	 *
	 * The dynamic portion of the hook name, `$transient`, refers to the transient name.
	 *
	 * @since 3.0.0
	 * @since 4.4.0 The `$transient` parameter was added.
	 *
	 * @param mixed  $value     New value of site transient.
	 * @param string $transient Transient name.
	 */
	$value = apply_filters( "pre_set_site_transient_{$transient}", $value, $transient );

	$expiration = (int) $expiration;

	/**
	 * Filters the expiration for a site transient before its value is set.
	 *
	 * The dynamic portion of the hook name, `$transient`, refers to the transient name.
	 *
	 * @since 4.4.0
	 *
	 * @param int    $expiration Time until expiration in seconds. Use 0 for no expiration.
	 * @param mixed  $value      New value of site transient.
	 * @param string $transient  Transient name.
	 */
	$expiration = apply_filters( "expiration_of_site_transient_{$transient}", $expiration, $value, $transient );

	if ( wp_using_ext_object_cache() || wp_installing() ) {
		$result = wp_cache_set( $transient, $value, 'site-transient', $expiration );
	} else {
		$transient_timeout = '_site_transient_timeout_' . $transient;
		$option            = '_site_transient_' . $transient;
		wp_prime_site_option_caches( array( $option, $transient_timeout ) );

		if ( false === get_site_option( $option ) ) {
			if ( $expiration ) {
				add_site_option( $transient_timeout, time() + $expiration );
			}
			$result = add_site_option( $option, $value );
		} else {
			if ( $expiration ) {
				update_site_option( $transient_timeout, time() + $expiration );
			}
			$result = update_site_option( $option, $value );
		}
	}

	if ( $result ) {

		/**
		 * Fires after the value for a specific site transient has been set.
		 *
		 * The dynamic portion of the hook name, `$transient`, refers to the transient name.
		 *
		 * @since 3.0.0
		 * @since 4.4.0 The `$transient` parameter was added
		 *
		 * @param mixed  $value      Site transient value.
		 * @param int    $expiration Time until expiration in seconds.
		 * @param string $transient  Transient name.
		 */
		do_action( "set_site_transient_{$transient}", $value, $expiration, $transient );

		/**
		 * Fires after the value for a site transient has been set.
		 *
		 * @since 6.8.0
		 *
		 * @param string $transient  The name of the site transient.
		 * @param mixed  $value      Site transient value.
		 * @param int    $expiration Time until expiration in seconds.
		 */
		do_action( 'set_site_transient', $transient, $value, $expiration );

		/**
		 * Fires after the value for a site transient has been set.
		 *
		 * @since 3.0.0
		 * @deprecated 6.8.0 Use {@see 'set_site_transient'} instead.
		 *
		 * @param string $transient  The name of the site transient.
		 * @param mixed  $value      Site transient value.
		 * @param int    $expiration Time until expiration in seconds.
		 */
		do_action_deprecated( 'setted_site_transient', array( $transient, $value, $expiration ), '6.8.0', 'set_site_transient' );
	}

	return $result;
}

/**
 * Registers default settings available in NotMattPress.
 *
 * The settings registered here are primarily useful for the REST API, so this
 * does not encompass all settings available in NotMattPress.
 *
 * @since 4.7.0
 * @since 6.0.1 The `show_on_front`, `page_on_front`, and `page_for_posts` options were added.
 */
function register_initial_settings() {
	register_setting(
		'general',
		'blogname',
		array(
			'show_in_rest' => array(
				'name' => 'title',
			),
			'type'         => 'string',
			'label'        => __( 'Title' ),
			'description'  => __( 'Site title.' ),
		)
	);

	register_setting(
		'general',
		'blogdescription',
		array(
			'show_in_rest' => array(
				'name' => 'description',
			),
			'type'         => 'string',
			'label'        => __( 'Tagline' ),
			'description'  => __( 'Site tagline.' ),
		)
	);

	if ( ! is_multisite() ) {
		register_setting(
			'general',
			'siteurl',
			array(
				'show_in_rest' => array(
					'name'   => 'url',
					'schema' => array(
						'format' => 'uri',
					),
				),
				'type'         => 'string',
				'description'  => __( 'Site URL.' ),
			)
		);
	}

	if ( ! is_multisite() ) {
		register_setting(
			'general',
			'admin_email',
			array(
				'show_in_rest' => array(
					'name'   => 'email',
					'schema' => array(
						'format' => 'email',
					),
				),
				'type'         => 'string',
				'description'  => __( 'This address is used for admin purposes, like new user notification.' ),
			)
		);
	}

	register_setting(
		'general',
		'timezone_string',
		array(
			'show_in_rest' => array(
				'name' => 'timezone',
			),
			'type'         => 'string',
			'description'  => __( 'A city in the same timezone as you.' ),
		)
	);

	register_setting(
		'general',
		'date_format',
		array(
			'show_in_rest' => true,
			'type'         => 'string',
			'description'  => __( 'A date format for all date strings.' ),
		)
	);

	register_setting(
		'general',
		'time_format',
		array(
			'show_in_rest' => true,
			'type'         => 'string',
			'description'  => __( 'A time format for all time strings.' ),
		)
	);

	register_setting(
		'general',
		'start_of_week',
		array(
			'show_in_rest' => true,
			'type'         => 'integer',
			'description'  => __( 'A day number of the week that the week should start on.' ),
		)
	);

	register_setting(
		'general',
		'WPLANG',
		array(
			'show_in_rest' => array(
				'name' => 'language',
			),
			'type'         => 'string',
			'description'  => __( 'NotMattPress locale code.' ),
			'default'      => 'en_US',
		)
	);

	register_setting(
		'writing',
		'use_smilies',
		array(
			'show_in_rest' => true,
			'type'         => 'boolean',
			'description'  => __( 'Convert emoticons like :-) and :-P to graphics on display.' ),
			'default'      => true,
		)
	);

	register_setting(
		'writing',
		'default_category',
		array(
			'show_in_rest' => true,
			'type'         => 'integer',
			'description'  => __( 'Default post category.' ),
		)
	);

	register_setting(
		'writing',
		'default_post_format',
		array(
			'show_in_rest' => true,
			'type'         => 'string',
			'description'  => __( 'Default post format.' ),
		)
	);

	register_setting(
		'reading',
		'posts_per_page',
		array(
			'show_in_rest' => true,
			'type'         => 'integer',
			'label'        => __( 'Maximum posts per page' ),
			'description'  => __( 'Blog pages show at most.' ),
			'default'      => 10,
		)
	);

	register_setting(
		'reading',
		'show_on_front',
		array(
			'show_in_rest' => true,
			'type'         => 'string',
			'label'        => __( 'Show on front' ),
			'description'  => __( 'What to show on the front page' ),
		)
	);

	register_setting(
		'reading',
		'page_on_front',
		array(
			'show_in_rest' => true,
			'type'         => 'integer',
			'label'        => __( 'Page on front' ),
			'description'  => __( 'The ID of the page that should be displayed on the front page' ),
		)
	);

	register_setting(
		'reading',
		'page_for_posts',
		array(
			'show_in_rest' => true,
			'type'         => 'integer',
			'description'  => __( 'The ID of the page that should display the latest posts' ),
		)
	);

	register_setting(
		'discussion',
		'default_ping_status',
		array(
			'show_in_rest' => array(
				'schema' => array(
					'enum' => array( 'open', 'closed' ),
				),
			),
			'type'         => 'string',
			'description'  => __( 'Allow link notifications from other blogs (pingbacks and trackbacks) on new articles.' ),
		)
	);

	register_setting(
		'discussion',
		'default_comment_status',
		array(
			'show_in_rest' => array(
				'schema' => array(
					'enum' => array( 'open', 'closed' ),
				),
			),
			'type'         => 'string',
			'label'        => __( 'Allow comments on new posts' ),
			'description'  => __( 'Allow people to submit comments on new posts.' ),
		)
	);
}

/**
 * Registers a setting and its data.
 *
 * @since 2.7.0
 * @since 3.0.0 The `misc` option group was deprecated.
 * @since 3.5.0 The `privacy` option group was deprecated.
 * @since 4.7.0 `$args` can be passed to set flags on the setting, similar to `register_meta()`.
 * @since 5.5.0 `$new_whitelist_options` was renamed to `$new_allowed_options`.
 *              Please consider writing more inclusive code.
 * @since 6.6.0 Added the `label` argument.
 *
 * @global array $new_allowed_options
 * @global array $wp_registered_settings
 *
 * @param string $option_group A settings group name. Should correspond to an allowed option key name.
 *                             Default allowed option key names include 'general', 'discussion', 'media',
 *                             'reading', 'writing', and 'options'.
 * @param string $option_name The name of an option to sanitize and save.
 * @param array  $args {
 *     Data used to describe the setting when registered.
 *
 *     @type string     $type              The type of data associated with this setting.
 *                                         Valid values are 'string', 'boolean', 'integer', 'number', 'array', and 'object'.
 *     @type string     $label             A label of the data attached to this setting.
 *     @type string     $description       A description of the data attached to this setting.
 *     @type callable   $sanitize_callback A callback function that sanitizes the option's value.
 *     @type bool|array $show_in_rest      Whether data associated with this setting should be included in the REST API.
 *                                         When registering complex settings, this argument may optionally be an
 *                                         array with a 'schema' key.
 *     @type mixed      $default           Default value when calling `get_option()`.
 * }
 */
function register_setting( $option_group, $option_name, $args = array() ) {
	global $new_allowed_options, $wp_registered_settings;

	/*
	 * In 5.5.0, the `$new_whitelist_options` global variable was renamed to `$new_allowed_options`.
	 * Please consider writing more inclusive code.
	 */
	$GLOBALS['new_whitelist_options'] = &$new_allowed_options;

	$defaults = array(
		'type'              => 'string',
		'group'             => $option_group,
		'label'             => '',
		'description'       => '',
		'sanitize_callback' => null,
		'show_in_rest'      => false,
	);

	// Back-compat: old sanitize callback is added.
	if ( is_callable( $args ) ) {
		$args = array(
			'sanitize_callback' => $args,
		);
	}

	/**
	 * Filters the registration arguments when registering a setting.
	 *
	 * @since 4.7.0
	 *
	 * @param array  $args         Array of setting registration arguments.
	 * @param array  $defaults     Array of default arguments.
	 * @param string $option_group Setting group.
	 * @param string $option_name  Setting name.
	 */
	$args = apply_filters( 'register_setting_args', $args, $defaults, $option_group, $option_name );

	$args = wp_parse_args( $args, $defaults );

	// Require an item schema when registering settings with an array type.
	if ( false !== $args['show_in_rest'] && 'array' === $args['type'] && ( ! is_array( $args['show_in_rest'] ) || ! isset( $args['show_in_rest']['schema']['items'] ) ) ) {
		_doing_it_wrong( __FUNCTION__, __( 'When registering an "array" setting to show in the REST API, you must specify the schema for each array item in "show_in_rest.schema.items".' ), '5.4.0' );
	}

	if ( ! is_array( $wp_registered_settings ) ) {
		$wp_registered_settings = array();
	}

	if ( 'misc' === $option_group ) {
		_deprecated_argument(
			__FUNCTION__,
			'3.0.0',
			sprintf(
				/* translators: %s: misc */
				__( 'The "%s" options group has been removed. Use another settings group.' ),
				'misc'
			)
		);
		$option_group = 'general';
	}

	if ( 'privacy' === $option_group ) {
		_deprecated_argument(
			__FUNCTION__,
			'3.5.0',
			sprintf(
				/* translators: %s: privacy */
				__( 'The "%s" options group has been removed. Use another settings group.' ),
				'privacy'
			)
		);
		$option_group = 'reading';
	}

	$new_allowed_options[ $option_group ][] = $option_name;

	if ( ! empty( $args['sanitize_callback'] ) ) {
		add_filter( "sanitize_option_{$option_name}", $args['sanitize_callback'] );
	}
	if ( array_key_exists( 'default', $args ) ) {
		add_filter( "default_option_{$option_name}", 'filter_default_option', 10, 3 );
	}

	/**
	 * Fires immediately before the setting is registered but after its filters are in place.
	 *
	 * @since 5.5.0
	 *
	 * @param string $option_group Setting group.
	 * @param string $option_name  Setting name.
	 * @param array  $args         Array of setting registration arguments.
	 */
	do_action( 'register_setting', $option_group, $option_name, $args );

	$wp_registered_settings[ $option_name ] = $args;
}

/**
 * Unregisters a setting.
 *
 * @since 2.7.0
 * @since 4.7.0 `$sanitize_callback` was deprecated. The callback from `register_setting()` is now used instead.
 * @since 5.5.0 `$new_whitelist_options` was renamed to `$new_allowed_options`.
 *              Please consider writing more inclusive code.
 *
 * @global array $new_allowed_options
 * @global array $wp_registered_settings
 *
 * @param string   $option_group The settings group name used during registration.
 * @param string   $option_name  The name of the option to unregister.
 * @param callable $deprecated   Optional. Deprecated.
 */
function unregister_setting( $option_group, $option_name, $deprecated = '' ) {
	global $new_allowed_options, $wp_registered_settings;

	/*
	 * In 5.5.0, the `$new_whitelist_options` global variable was renamed to `$new_allowed_options`.
	 * Please consider writing more inclusive code.
	 */
	$GLOBALS['new_whitelist_options'] = &$new_allowed_options;

	if ( 'misc' === $option_group ) {
		_deprecated_argument(
			__FUNCTION__,
			'3.0.0',
			sprintf(
				/* translators: %s: misc */
				__( 'The "%s" options group has been removed. Use another settings group.' ),
				'misc'
			)
		);
		$option_group = 'general';
	}

	if ( 'privacy' === $option_group ) {
		_deprecated_argument(
			__FUNCTION__,
			'3.5.0',
			sprintf(
				/* translators: %s: privacy */
				__( 'The "%s" options group has been removed. Use another settings group.' ),
				'privacy'
			)
		);
		$option_group = 'reading';
	}

	$pos = false;
	if ( isset( $new_allowed_options[ $option_group ] ) ) {
		$pos = array_search( $option_name, (array) $new_allowed_options[ $option_group ], true );
	}

	if ( false !== $pos ) {
		unset( $new_allowed_options[ $option_group ][ $pos ] );
	}

	if ( '' !== $deprecated ) {
		_deprecated_argument(
			__FUNCTION__,
			'4.7.0',
			sprintf(
				/* translators: 1: $sanitize_callback, 2: register_setting() */
				__( '%1$s is deprecated. The callback from %2$s is used instead.' ),
				'<code>$sanitize_callback</code>',
				'<code>register_setting()</code>'
			)
		);
		remove_filter( "sanitize_option_{$option_name}", $deprecated );
	}

	if ( isset( $wp_registered_settings[ $option_name ] ) ) {
		// Remove the sanitize callback if one was set during registration.
		if ( ! empty( $wp_registered_settings[ $option_name ]['sanitize_callback'] ) ) {
			remove_filter( "sanitize_option_{$option_name}", $wp_registered_settings[ $option_name ]['sanitize_callback'] );
		}

		// Remove the default filter if a default was provided during registration.
		if ( array_key_exists( 'default', $wp_registered_settings[ $option_name ] ) ) {
			remove_filter( "default_option_{$option_name}", 'filter_default_option', 10 );
		}

		/**
		 * Fires immediately before the setting is unregistered and after its filters have been removed.
		 *
		 * @since 5.5.0
		 *
		 * @param string $option_group Setting group.
		 * @param string $option_name  Setting name.
		 */
		do_action( 'unregister_setting', $option_group, $option_name );

		unset( $wp_registered_settings[ $option_name ] );
	}
}

/**
 * Retrieves an array of registered settings.
 *
 * @since 4.7.0
 *
 * @global array $wp_registered_settings
 *
 * @return array {
 *     List of registered settings, keyed by option name.
 *
 *     @type array ...$0 {
 *         Data used to describe the setting when registered.
 *
 *         @type string     $type              The type of data associated with this setting.
 *                                             Valid values are 'string', 'boolean', 'integer', 'number', 'array', and 'object'.
 *         @type string     $label             A label of the data attached to this setting.
 *         @type string     $description       A description of the data attached to this setting.
 *         @type callable   $sanitize_callback A callback function that sanitizes the option's value.
 *         @type bool|array $show_in_rest      Whether data associated with this setting should be included in the REST API.
 *                                             When registering complex settings, this argument may optionally be an
 *                                             array with a 'schema' key.
 *         @type mixed      $default           Default value when calling `get_option()`.
 *     }
 * }
 */
function get_registered_settings() {
	global $wp_registered_settings;

	if ( ! is_array( $wp_registered_settings ) ) {
		return array();
	}

	return $wp_registered_settings;
}

/**
 * Filters the default value for the option.
 *
 * For settings which register a default setting in `register_setting()`, this
 * function is added as a filter to `default_option_{$option}`.
 *
 * @since 4.7.0
 *
 * @param mixed  $default_value  Existing default value to return.
 * @param string $option         Option name.
 * @param bool   $passed_default Was `get_option()` passed a default value?
 * @return mixed Filtered default value.
 */
function filter_default_option( $default_value, $option, $passed_default ) {
	if ( $passed_default ) {
		return $default_value;
	}

	$registered = get_registered_settings();
	if ( empty( $registered[ $option ] ) ) {
		return $default_value;
	}

	return $registered[ $option ]['default'];
}

/**
 * Returns the values that trigger autoloading from the options table.
 *
 * @since 6.6.0
 *
 * @return string[] The values that trigger autoloading.
 */
function wp_autoload_values_to_autoload() {
	$autoload_values = array( 'yes', 'on', 'auto-on', 'auto' );

	/**
	 * Filters the autoload values that should be considered for autoloading from the options table.
	 *
	 * The filter can only be used to remove autoload values from the default list.
	 *
	 * @since 6.6.0
	 *
	 * @param string[] $autoload_values Autoload values used to autoload option.
	 *                               Default list contains 'yes', 'on', 'auto-on', and 'auto'.
	 */
	$filtered_values = apply_filters( 'wp_autoload_values_to_autoload', $autoload_values );

	return array_intersect( $filtered_values, $autoload_values );
}
