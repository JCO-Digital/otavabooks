<?php

namespace otavabooks;

/**
 * Creates the book as a post, and adds the meta fields to it.
 *
 * @param array $item The object from the import file.
 * @param array $tags Optional extra tags.
 *
 * @return string Output to pass to the user.
 */
function create_book_object( array $item, array $tags = [] ) {
	if ( ! empty( $item['isbn'] ) ) {
		$new_book = array(
			'post_type'    => IMPORT_POST_TYPE,
			'post_title'   => $item['title'],
			'post_content' => $item['content'],
			'post_status'  => 'publish',
			'post_author'  => get_author_setting(),
		);
		parse_dates( $new_book, $item );

		// Insert the post into the database.
		$post_id = wp_insert_post( $new_book );
		if ( ! empty( $post_id ) ) {
			add_post_meta( $post_id, 'isbn', trim( $item['isbn'] ) );
			update_book_meta( $post_id, $item );
			update_book_versions( $post_id, $item['versions'] );

			return $post_id;
		} else {
			return false;
		}
	}

	return null;
}

function update_book_object( int $id, array $item, array $tags = [] ) {
	$update_book = array(
		'ID'           => $id,
		'post_title'   => $item['title'],
		'post_content' => $item['content'],
	);
	parse_dates( $update_book, $item );

	$post_id = wp_update_post( $update_book );
	if ( ! empty( $post_id ) ) {
		update_book_meta( $post_id, $item );
		update_book_versions( $post_id, $item['versions'] );

		return $post_id;
	}

	return false;
}

function parse_dates( &$post, $item ) {
	$date = $item['dates']['ilmestymis'];
	if ( ! empty( $item['dates']['embargo'] ) ) {
		$date = $item['dates']['embargo'];
	} elseif ( ! empty( $item['dates']['yleiseenmyyntiin'] ) ) {
		$date = $item['dates']['yleiseenmyyntiin'];
	}
	if ( ! empty( $date ) ) {
		if ( strtotime( $date ) < time() ) {
			$post['post_date'] = $date . ' 00:00:00';
		}
	}
}


/**
 * Update the meta values for the books.
 *
 * @param $post_id - the post id.
 * @param $item - The json data from the import.
 */
function update_book_meta( $post_id, $item ) {
	// Get the categories.
	$tags       = array();
	$categories = array();

	if ( ! empty( $item['categories'] ) ) {
		foreach ( $item['categories'] as $category ) {
			$categories[] = $category;
			$tags[]       = $category;
		}
	}

	if ( ! empty( $item['alkuteos'] ) ) {
		update_field( 'alkuteos', $item['alkuteos'], $post_id );
	}
	if ( ! empty( $item['ilmestymis'] ) ) {
		update_field( 'julkaisuaika', $item['ilmestymis'], $post_id );
	}
	if ( ! empty( $item['kirjastoluokka'] ) ) {
		update_field( 'kirjastoluokka', $item['kirjastoluokka'], $post_id );
	}
	if ( ! empty( $item['kuvittaja'] ) ) {
		$kuvittaja = array();
		foreach ( $item['kuvittaja'] as $name ) {
			$kuvittaja[] = parse_name( $name );
			$tags[]      = parse_name( $name );
		}
		if ( ! empty( $kuvittaja ) ) {
			wp_set_post_terms( $post_id, $kuvittaja, 'otava_kuvittaja', false );
		}
	}
	if ( ! empty( $item['suomentaja'] ) ) {
		$suomentaja = array();
		foreach ( $item['suomentaja'] as $name ) {
			$suomentaja[] = parse_name( $name );
			$tags[]       = parse_name( $name );
		}
		if ( ! empty( $suomentaja ) ) {
			wp_set_post_terms( $post_id, $suomentaja, 'otava_kaantaja', false );
		}
	}
	if ( ! empty( $item['sarja'] ) ) {
		wp_set_post_terms( $post_id, array( $item['sarja'] ), 'otava_sarja', false );
		$tags[] = $item['sarja'];
	}
	$asu = array();
	foreach ( $item['versions'] as $version ) {
		if ( ! in_array( $version['asu_text'], $asu, true ) ) {
			$asu[] = $version['asu_text'];
		}
	}
	if ( ! empty( $asu ) ) {
		wp_set_post_terms( $post_id, $asu, 'otava_sidosasu', false );
	}
	if ( ! empty( $item['tulosyksikko'] ) ) {
		wp_set_post_terms( $post_id, array( $item['tulosyksikko'] ), 'otava_julkaisija', false );
		$tags[] = $item['tulosyksikko'];
	}

	$toimittaja = match_authors( $post_id, $item['authors'], $tags );
	foreach ( $item['toimittaja'] as $name ) {
		$toimittaja[] = parse_name( $name );
		$tags[]       = parse_name( $name );
	}
	wp_set_post_terms( $post_id, $toimittaja, 'otava_toimittaja', false );
	if ( ! empty( $tags ) ) {
		wp_set_post_terms( $post_id, $tags, 'post_tag', false );
	}
	if ( ! empty( $categories ) ) {
		wp_set_post_terms( $post_id, $categories, 'otava_kategoria', false );
	}
	if ( ! empty( $item['kausi'] ) && get_import_catalog() ) {
		wp_set_post_terms( $post_id, $item['kausi'], 'otava_katalogi', false );
	}
}

/**
 * Update the versions for the book.
 *
 * @param $post_id - the post id.
 * @param $versions - The json data from the import.
 */
function update_book_versions( $post_id, $versions ) {
	delete_field( 'versions', $post_id );
	foreach ( $versions as $version ) {
		add_row( 'versions', $version, $post_id );
	}
}


function get_isbn_list() {
	$isbn = [];
	foreach ( get_books() as $book ) {
		$isbn[ $book['ID'] ] = $book['isbn'];
	}

	return $isbn;
}

function get_books() {
	global $wpdb;
	$tablename = $wpdb->prefix . 'posts';
	$tablemeta = $wpdb->prefix . 'postmeta';
	$sql       = "SELECT post.*, meta.meta_value as isbn FROM $tablename AS post LEFT JOIN $tablemeta AS meta on post.ID = meta.post_id AND meta.meta_key = 'isbn' WHERE post.post_type = '" . IMPORT_POST_TYPE . "' ORDER BY post.post_date DESC";

	return $wpdb->get_results( $sql, ARRAY_A );
}

$categories = get_json( __DIR__ . '/categories.json' );
function get_otava_cat( $raw, $default = 'Muut' ) {
	if ( get_disable_categories_setting() ) {
		return $raw;
	}
	global $categories;
	$orig     = trim( $raw );
	$category = $default;
	$found    = false;
	foreach ( $categories as $cat => $search ) {
		if ( in_array( $orig, $search ) ) {
			$category = $cat;
			$found    = true;
			break;
		}
	}
	if ( ! $found ) {
		$needle = preg_replace( '/[^a-zåäö]+/u', '', mb_strtolower( $orig ) );
		$dist   = ceil( strlen( $needle ) / 16 );
		foreach ( $categories as $cat => $search ) {
			foreach ( $search as $item ) {
				if ( levenshtein( $needle, preg_replace( '/[^a-zåäö]+/u', '', mb_strtolower( $item ) ) ) <= $dist ) {
					$category = $cat;
					break 2;
				}
			}
		}
	}

	return $category;
}

/**
 * Changes the name into firstname lastname format.
 *
 * @param $name
 *
 * @return mixed|string
 */
function parse_name( $name ) {
	$parts = explode( ',', $name, 2 );
	if ( count( $parts ) > 1 ) {
		return trim( $parts[1] ) . ' ' . trim( $parts[0] );
	}

	return $name;
}
