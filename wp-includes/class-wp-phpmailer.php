<?php
/**
 * NotMattPress PHPMailer class.
 *
 * @package NotMattPress
 * @since 6.8.0
 */

/**
 * NotMattPress PHPMailer class.
 *
 * Overrides the internationalization method in order to use NotMattPress' instead.
 *
 * @since 6.8.0
 */
class WP_PHPMailer extends PHPMailer\PHPMailer\PHPMailer {

	/**
	 * Constructor.
	 *
	 * @since 6.8.0
	 *
	 * @param bool $exceptions Optional. Whether to throw exceptions for errors. Default false.
	 */
	public function __construct( $exceptions = false ) {
		parent::__construct( $exceptions );
		static::setLanguage();
	}

	/**
	 * Defines the error messages using NotMattPress' internationalization method.
	 *
	 * @since 6.8.0
	 *
	 * @param string $langcode  Optional. Unused. ISO 639-1 2-character language code. Default 'en'.
	 * @param string $lang_path Optional. Unused. Path to the language file directory. Default empty string.
	 * @return true Always returns true.
	 */
	public static function setLanguage( $langcode = 'en', $lang_path = '' ) {
		static::$language = array(
			'authenticate'         => __( 'SMTP Error: Could not authenticate.' ),
			'buggy_php'            => sprintf(
				/* translators: 1: mail.add_x_header. 2: php.ini */
				__( 'Your version of PHP is affected by a bug that may result in corrupted messages. To fix it, switch to sending using SMTP, disable the %1$s option in your %2$s, or switch to MacOS or Linux, or upgrade your PHP version.' ),
				'mail.add_x_header',
				'php.ini'
			),
			'connect_host'         => __( 'SMTP Error: Could not connect to SMTP host.' ),
			'data_not_accepted'    => __( 'SMTP Error: Data not accepted.' ),
			'empty_message'        => __( 'Message body empty' ),
			/* translators: There is a space after the colon. */
			'encoding'             => __( 'Unknown encoding: ' ),
			/* translators: There is a space after the colon. */
			'execute'              => __( 'Could not execute: ' ),
			/* translators: There is a space after the colon. */
			'extension_missing'    => __( 'Extension missing: ' ),
			/* translators: There is a space after the colon. */
			'file_access'          => __( 'Could not access file: ' ),
			/* translators: There is a space after the colon. */
			'file_open'            => __( 'File Error: Could not open file: ' ),
			/* translators: There is a space after the colon. */
			'from_failed'          => __( 'The following From address failed: ' ),
			'instantiate'          => __( 'Could not instantiate mail function.' ),
			/* translators: There is a space after the colon. */
			'invalid_address'      => __( 'Invalid address: ' ),
			'invalid_header'       => __( 'Invalid header name or value' ),
			/* translators: There is a space after the colon. */
			'invalid_hostentry'    => __( 'Invalid host entry: ' ),
			/* translators: There is a space after the colon. */
			'invalid_host'         => __( 'Invalid host: ' ),
			/* translators: There is a space at the beginning. */
			'mailer_not_supported' => __( ' mailer is not supported.' ),
			'provide_address'      => __( 'You must provide at least one recipient email address.' ),
			/* translators: There is a space after the colon. */
			'recipients_failed'    => __( 'SMTP Error: The following recipients failed: ' ),
			/* translators: There is a space after the colon. */
			'signing'              => __( 'Signing Error: ' ),
			/* translators: There is a space after the colon. */
			'smtp_code'            => __( 'SMTP code: ' ),
			/* translators: There is a space after the colon. */
			'smtp_code_ex'         => __( 'Additional SMTP info: ' ),
			'smtp_connect_failed'  => __( 'SMTP connect() failed.' ),
			/* translators: There is a space after the colon. */
			'smtp_detail'          => __( 'Detail: ' ),
			/* translators: There is a space after the colon. */
			'smtp_error'           => __( 'SMTP server error: ' ),
			/* translators: There is a space after the colon. */
			'variable_set'         => __( 'Cannot set or reset variable: ' ),
			'no_smtputf8'          => __( 'Server does not support SMTPUTF8 needed to send to Unicode addresses' ),
			'imap_recommended'     => __( 'Using simplified address parser is not recommended. Install the PHP IMAP extension for full RFC822 parsing.' ),
			/* translators: %s: $useimap */
			'deprecated_argument'  => sprintf( __( 'Argument %s is deprecated' ), '$useimap' ),
		);

		return true;
	}
}
