<?php
/**
 * The date the media was last modified, a copy of the `date_modified` field
 * of `@wordpress/media-fields`. It is plain data: it has no JavaScript parts.
 *
 * @package NotMattPress
 */

return array(
	'type'     => 'datetime',
	'label'    => __( 'Date modified' ),
	'filterBy' => array(
		'operators' => array( 'before', 'after' ),
	),
	'readOnly' => true,
);
