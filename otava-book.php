<?php

namespace otavabooks;

/**
 * Creates the book as a post, and adds the meta fields to it.
 *
 * @param array $item The json data from the import.
 * @param array $tags Optional extra tags.
 *
 * @return false|int|\WP_Error|null Output to pass to the user.
 */
function create_book_object( array $item, array $tags = array() ) {
	if ( ! empty( $item['isbn'] ) ) {
		$new_book = array(
			'post_type'    => IMPORT_POST_TYPE,
			'post_title'   => $item['title'],
			'post_content' => $item['content'],
			'post_status'  => 'publish',
			'post_author'  => get_author_setting(),
		);
		$date     = parse_dates( $new_book, $item['dates'] );

		if ( ! empty( $date ) ) {
			// Insert the post into the database.
			$post_id = wp_insert_post( $new_book );
			if ( ! empty( $post_id ) ) {
				update_post_meta( $post_id, 'isbn', trim( $item['isbn'] ) );
				set_ilmestymis( $post_id, $date );
				update_book_meta( $post_id, $item );
				update_book_versions( $post_id, $item['versions'] );

				return $post_id;
			} else {
				return false;
			}
		}
	}

	return null;
}

/**
 * @param int   $id   The post id.
 * @param array $item The json data from the import.
 * @param array $tags Optional extra tags.
 *
 * @return false|int|\WP_Error
 */
function update_book_object( int $id, array $item, array $tags = array() ) {
	$update_book = array(
		'ID'           => $id,
		'post_title'   => $item['title'],
		'post_content' => $item['content'],
		'post_status'  => 'publish',
	);
	$date        = parse_dates( $update_book, $item['dates'] );

	if ( ! empty( $date ) ) {
		$post_id = wp_update_post( $update_book );
		if ( ! empty( $post_id ) ) {
			set_ilmestymis( $post_id, $date );
			update_book_meta( $post_id, $item );
			update_book_versions( $post_id, $item['versions'] );

			return $post_id;
		}
	}

	return false;
}

/**
 * Parses date information from the provided array and formats it for use in a WordPress post.
 *
 * This function attempts to extract a valid date from the 'dates' array within the input item.
 * It prioritizes 'ensimmainen', then 'ilmestymis', and finally 'vvvvkk' keys. If a valid
 * 8-character date string (YYYYMMDD) is found, it's converted to 'YYYY-MM-DD' format and
 * used to set the post's 'post_date'.  If the resulting date is more than half a year in the
 * future, the post status is set to 'draft'.
 *
 * @param array $post  Reference to the post array to be modified.  The 'post_date' and 'post_status' keys may be updated.
 * @param array $dates An array containing potential date strings, with keys like 'ensimmainen', 'ilmestymis', and 'vvvvkk'.
 *
 * @return string|false The formatted date string ('YYYY-MM-DD') if a valid date is found and processed; otherwise, false.
 */
function parse_dates( array &$post, array $dates ) {
	// Do the date magic.
	$date = $dates['ensimmainen'];
	if ( empty( $date ) ) {
		$date = $dates['ilmestymis'];
	}
	if ( empty( $date ) ) {
		$date = $dates['vvvvkk'];
	}

	if ( strlen( $date ) === 8 ) {
		$date_string       = substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-' . substr( $date, 6, 2 );
		$post['post_date'] = $date_string . ' 00:00:00';
		if ( ( strtotime( $date_string ) - time() ) > 15768000 ) { // More than half a year in the future.
			$post['post_status'] = 'draft';
		}
		return $date_string;
	}
	return false;
}

/**
 * Sets the 'ilmestymispvm' (release date) custom field for a post and determines if the date is in the future.
 *
 * @param int    $post_id The ID of the post to update.
 * @param string $date    The release date in 'YYYY-MM-DD' format.
 *
 * @return bool True if the release date is in the future, false otherwise.
 */
function set_ilmestymis( $post_id, $date ) {
	update_field( 'ilmestymispvm', $date, $post_id );
	$date_string = substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-' . substr( $date, 6, 2 );

	return ( strtotime( $date_string ) > time() );
}

/**
 * Updates the book's meta fields, categories, tags, and taxonomies.
 *
 * @param int   $post_id The ID of the post to update.
 * @param array $item    The JSON data containing the book's information.
 */
function update_book_meta( int $post_id, array $item ) {
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
	if ( ! empty( $item['kirjastoluokka'] ) ) {
		update_field( 'kirjastoluokka', $item['kirjastoluokka'], $post_id );
	}
	if ( ! empty( $item['kuvittaja'] ) ) {
		match_authors( $post_id, $item['kuvittaja'], $tags, 'kuvittaja' );
		$kuvittaja = array();
		foreach ( $item['kuvittaja'] as $name ) {
			$kuvittaja[] = parse_name( $name );
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
		if ( is_array( $item['sarja'] ) ) {
			$item['sarja'] = $item['sarja'][0];
		}
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
		$parsed_name  = parse_name( $name );
		$toimittaja[] = $parsed_name;
		$tags[]       = $parsed_name;
	}

	wp_set_post_terms( $post_id, $toimittaja, 'otava_toimittaja', false );
	wp_set_post_terms( $post_id, $tags, 'post_tag', false );
	wp_set_post_terms( $post_id, $categories, 'otava_kategoria', false );
	if ( ! empty( $item['kausi'] ) && get_import_catalog() ) {
		wp_set_post_terms( $post_id, $item['kausi'], 'otava_katalogi', false );
	}
}

/**
 * Update the versions for the book.
 *
 * @param $post_id  - the post id.
 * @param $versions - The json data from the import.
 */
function update_book_versions( $post_id, $versions ) {
	delete_field( 'versions', $post_id );
	foreach ( $versions as $version ) {
		add_row( 'versions', $version, $post_id );
	}
}

function get_isbn_list() {
	$isbn = array();
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

/**
 * Searches the locally stored categories.json for matches to the provided category.
 *
 * @param string $raw               The category to be searched.
 * @param string $$default_category The default category to return, if a match isn't found.
 *
 * @return string  The matched category, or the default if no match is found.
 */
function get_otava_cat( $raw, $default_category = '' ) {
	if ( get_disable_categories_setting() ) {
		return $raw;
	}
	global $otava_loaded_categories;
	if ( empty( $otava_loaded_categories ) ) {
		echo "Loading categories.\n";
		$otava_loaded_categories = get_json( __DIR__ . '/categories.json' );
		printf( "Loaded %d categories.\n", count( $otava_loaded_categories ) );
	}

	$orig     = trim( $raw );
	$category = $default_category;
	$found    = false;
	foreach ( $otava_loaded_categories as $cat => $search ) {
		if ( in_array( $orig, $search ) ) {
			$category = $cat;
			$found    = true;
			break;
		}
	}
	if ( ! $found ) {
		$needle = preg_replace( '/[^a-zåäö]+/u', '', mb_strtolower( $orig ) );
		$dist   = ceil( strlen( $needle ) / 16 );
		foreach ( $otava_loaded_categories as $cat => $search ) {
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
 * @param string $name Name to process.
 *
 * @return string
 */
function parse_name( string $name ): string {
	$parts = explode( ',', trim( $name, ' ,' ), 2 );
	if ( count( $parts ) > 1 ) {
		return trim( $parts[1] ) . ' ' . trim( $parts[0] );
	}

	return $name;
}

function set_tulossa() {
	global $wpdb;
	$sql = "
		SELECT
			post.ID,
			post.post_title,
			ilmestymis.meta_value as pvm
		FROM {$wpdb->prefix}posts as post
		LEFT JOIN {$wpdb->prefix}postmeta as ilmestymis
		ON post.ID = ilmestymis.post_id
		AND ilmestymis.meta_key = 'ilmestymispvm'
		WHERE post.post_type = 'otava_book'
		AND post.post_status = 'publish'
		AND str_to_date(ilmestymis.meta_value, '%Y-%m-%d') > now()
		";

	$set = 0;
	foreach ( $wpdb->get_results( $sql, ARRAY_A ) as $row ) {
		wp_set_post_terms( $row['ID'], 'tulossa', 'otava_kategoria', true );
		++$set;
	}

	return $set;
}

function clean_tulossa() {
	$args    = array(
		'post_type'      => IMPORT_POST_TYPE,
		'posts_per_page' => -1,
		'tax_query'      => array(
			array(
				'taxonomy' => 'otava_kategoria',
				'field'    => 'slug',
				'terms'    => 'tulossa',
			),
		),
	);
	$cleaned = 0;
	foreach ( get_posts( $args ) as $post ) {
		$date = get_field( 'ilmestymispvm', $post->ID );
		if ( strtotime( $date ) < time() ) {
			++$cleaned;
			wp_remove_object_terms( $post->ID, 'tulossa', 'otava_kategoria' );
			echo esc_html( "Cleaned {$post->post_title}." );
		}
	}

	return $cleaned;
}
