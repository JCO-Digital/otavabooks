<?php

function delete_books( $delete = 20 ) {
	$deleted = 0;
	$args    = array(
		'post_type'   => IMPORT_POST_TYPE,
		'numberposts' => $delete,
	);
	foreach ( get_posts( $args ) as $post ) {
		wp_delete_post( $post->ID, true );
		$deleted ++;
		echo "Deleted: " . $post->ID . "<br/>\n";
	}

	return $deleted;
}

function clean_terms( $delete = 20 ) {
	foreach ( \otavabooks\get_otava_taxonomies() as $taxo => $labels ) {
		$taxo_name = 'otava_' . $taxo;
		$terms     = get_terms( array(
			'taxonomy'   => $taxo_name,
			'hide_empty' => false,
		) );
		foreach ( $terms as $t ) {
			if ( $t->count === 0 ) {
				wp_delete_term( $t->term_id, $taxo_name );
				echo "Deleted term: $t->name <br/>\n";
			}
		}
	}
}


function get_upload_dir( $path = 'otavabooks' ) {
	$upload = wp_get_upload_dir();
	$folder = $upload['basedir'] . '/' . $path;
	if ( ! is_dir( $folder ) ) {
		mkdir( $folder );
	}

	return $folder;
}

function read_books() {
	$json = file_get_contents( IMPORT_BOOK_DATA );

	return json_decode( $json, true );
}

function get_json( $filename ) {
	$json = file_get_contents( $filename );
	$data = json_decode( $json, true );
	if ( empty( $data ) ) {
		return array();
	}

	return $data;
}
