<?php

function delete_books( $delete = 20 ) {
	$deleted = 0;
	$args    = array(
		'post_type'   => IMPORT_POST_TYPE,
		'numberposts' => $delete
	);
	foreach ( get_posts( $args ) as $post ) {
		wp_delete_post( $post->ID, true );
		$deleted ++;
		echo "Deleted: " . $post->ID . "<br/>\n";
	}

	return $deleted;
}

function get_upload_dir( $path = 'otavabooks' ) {
	$upload = wp_get_upload_dir();
	$folder = $upload['basedir'] . '/' . $path;
	if ( ! is_dir( $folder ) ) {
		mkdir( $folder );
	}

	return $folder;
}
