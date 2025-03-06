<?php

use Nicebooks\Isbn\{Exception\InvalidIsbnException, Isbn};

function delete_books( $delete = 20 ) {
	$deleted = 0;
	$args    = array(
		'post_type'   => IMPORT_POST_TYPE,
		'numberposts' => $delete,
	);
	foreach ( get_posts( $args ) as $post ) {
		wp_delete_post( $post->ID, true );
		++$deleted;
		echo 'Deleted: ' . $post->ID . "<br/>\n";
	}

	return $deleted;
}

function clean_terms( $delete = 20 ) {
	foreach ( \otavabooks\get_otava_taxonomies() as $taxo => $labels ) {
		$taxo_name = 'otava_' . $taxo;
		$terms     = get_terms(
			array(
				'taxonomy'   => $taxo_name,
				'hide_empty' => false,
			)
		);
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


function get_json( $filename ) {
	if ( ! file_exists( $filename ) ) {
		return array();
	}
	$json = file_get_contents( $filename );
	$data = json_decode( $json, true );
	if ( empty( $data ) ) {
		return array();
	}

	return $data;
}

function put_json( $filename, $data ) {
	$json = json_encode( $data );

	return file_put_contents( $filename, $json );
}

function check_for_cover( $isbn ) {
	if ( ! isset( $GLOBALS['book_covers'] ) || ! is_array( $GLOBALS['book_covers'] ) ) {
		$GLOBALS['book_covers'] = get_json( BOOK_COVER_DATA );
	}
	if ( isset( $GLOBALS['book_covers'][ $isbn ] ) ) {
		if ( $GLOBALS['book_covers'][ $isbn ]['has_cover'] ) {
			// Book has cover.
			return true;
		} elseif ( $GLOBALS['book_covers'][ $isbn ]['timestamp'] > time() - 60 * 60 * 48 ) {
			// Book doesn't have cover, but is checked in the last two days.
			return false;
		}
	}

	return null;
}

const CDN_BASE_URL = 'https://mediapankki.otava.fi/api/v1/assets/by-isbn/';

function get_cdn_cover_url( $isbn, $max_width = false, $max_height = false ) {
	try {
		$isbn_object = Isbn::of( $isbn );
		$arguments   = array();
		if ( $max_width ) {
			$arguments['maxWidth'] = $max_width;
		}
		if ( $max_height ) {
			$arguments['maxHeight'] = $max_height;
		}
		$query = '';

		if ( ! empty( $arguments ) ) {
			$query_string = http_build_query( $arguments );
			$query       .= "?{$query_string}";
		}

		return CDN_BASE_URL . $isbn_object->format() . '.jpg' . $query;

	} catch ( InvalidIsbnException  $exception ) {
		if ( function_exists( 'write_log' ) ) {
			write_log( $exception->getMessage() );
		}

		return false;
	}
}


function redirect_books() {
	global $wp;
	if ( preg_match( '|(kirjat)/([^/]+)-([0-9]+)|', $wp->request, $matches ) ) {
		$page = get_page_by_path( $matches[2], OBJECT, 'otava_book' );
		if ( ! empty( $page ) ) {
			$url = get_permalink( $page->ID );
			if ( ! empty( $url ) && wp_redirect( $url ) ) {
				exit;
			}
		}
	}
}
