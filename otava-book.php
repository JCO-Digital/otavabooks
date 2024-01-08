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
 * @param array $post The post array.
 * @param array $item The json data from the import.
 *
 * @return string
 */
function parse_dates( &$post, $dates ) {
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
	}

	return $date;
}

function set_ilmestymis( $post_id, $date ) {
	update_field( 'ilmestymispvm', $date, $post_id );
	$date_string = substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-' . substr( $date, 6, 2 );

	return ( strtotime( $date_string ) > time() );
}


/**
 * Update the meta values for the books.
 *
 * @param int   $post_id The post id.
 * @param array $item    The json data from the import.
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
		$toimittaja[] = parse_name( $name );
		$tags[]       = parse_name( $name );
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

$categories = get_json( __DIR__ . '/categories.json' );
function get_otava_cat( $raw, $default = '' ) {
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
 * @param string $name Name to process.
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

function get_book_covers( $max_delete ) {
	$nr          = 0;
	$attachments = get_posts(
		array(
			'post_type'      => 'attachment',
			'posts_per_page' => - 1,
		)
	);
	$count       = count( $attachments );
	echo esc_html( "Found $count covers\n" );
	foreach ( $attachments as $attachment ) {
		if ( strpos( $attachment->guid, '/wp-content/uploads/isbn' ) !== false ) {
			if ( empty( $attachment->post_parent ) ) {
				wp_delete_attachment( $attachment->ID, true );
				if ( ++$nr >= $max_delete ) {
					echo 'Max reached';
					break;
				}
			}
		}
	}

	return $nr;
}

function set_tulossa() {
	global $wpdb;
	$sql = "
		SELECT
			post.ID,
			post.post_title,
            ilmestymis.meta_value as pvm
		FROM wp_posts as post
		LEFT JOIN wp_postmeta as ilmestymis
		ON post.ID = ilmestymis.post_id
		AND ilmestymis.meta_key = 'ilmestymispvm'
		WHERE post.post_type = 'otava_book'
		AND post.post_status = 'publish'
		AND str_to_date(ilmestymis.meta_value, '%Y%m%d') > now()
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
		'posts_per_page' => - 1,
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
			echo "Cleaned {$post->post_title}.<br/>";
		}
	}

	return $cleaned;
}
