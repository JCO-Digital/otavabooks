<?php
/**
 * Handles scheduling and unscheduling WPCron jobs.
 *
 * @package OtavaBooks
 */

namespace otavabooks;

/**
 * Activate the WP cron hook.
 */
function import_activation() {
	if ( ! wp_next_scheduled( IMPORT_TASK ) ) {
		wp_schedule_event( time(), 'hourly', IMPORT_TASK );
	}
	if ( ! wp_next_scheduled( COVER_TASK ) ) {
		wp_schedule_event( time(), 'hourly', COVER_TASK );
	}
}

/**
 * Deactivate the WP cron hook.
 */
function import_deactivation() {
	wp_clear_scheduled_hook( IMPORT_TASK );
	wp_clear_scheduled_hook( COVER_TASK );
}

add_action( IMPORT_TASK, 'otavabooks\book_import_cron', 10, 0 );
add_action( COVER_TASK, 'otavabooks\cover_check_cron', 10, 0 );

/**
 * Function to run every hour.
 */
function book_import_cron() {
	$timestamp = file_exists( IMPORT_BOOK_DATA ) ? filemtime( IMPORT_BOOK_DATA ) : 0;

	if ( ( time() - $timestamp ) > ( 4 * 60 * 60 ) ) {
		// Fetch books if data is older than 4 hours.
		$books = make_book_list();
		$json  = wp_json_encode( $books );
		file_put_contents( IMPORT_BOOK_DATA, $json );
		echo 'Fetched file.';
	}

	$imported = import_books( 10 );
	echo "Imported $imported books.";

	$updated = update_books( 10 );
	echo "Updated $updated books.";
}

/**
 * Handles checking books if they have a cover or not.
 * If a book does not have a cover it is rechecked every day.
 *
 * @return void
 */
function cover_check_cron() {
	// Setup parameters and get the "cover" cache.
	$covers         = get_json( BOOK_COVER_DATA );
	$books_per_page = 64;
	$cat_target     = 12;

	$page    = 0;
	$max     = 50;
	$skipped = 0;
	$checked = 0;
	$cat     = array();

	/*
	 * This loop will always begin checking at the $books_per_page amount of newest books.
	 * If all of them are checked, it will continue until it hits a chunk of $books_per_page that has not yet been checked, it will then check them and exit.
	 * This means that each cron run of this will continually scan backwards unless we have to recheck a newer chunk.
	 */

	do {
		$books = get_recent_books_sql( $books_per_page, $page ++ );
		if ( empty( $books ) ) {
			break;
		}

		echo "<p>Iteration: $page of Cover checking</p>";

		foreach ( $books as $book ) {
			$isbn = $book['isbn'];
			if ( empty( $isbn ) ) {
				continue;
			}

			// If it exists and has been checked less than a day ago.
			if ( isset( $covers[ $isbn ] ) && ( time() - $covers[ $isbn ]['timestamp'] ) < ( 24 * 60 * 60 ) ) {
				echo "<p>Found already checked book with isbn: $isbn</p>";
				$skipped ++;
				$covers[ $isbn ]['pvm'] = $book['pvm'];
				if ( $covers[ $isbn ]['has_cover'] ) {
					foreach ( $covers[ $isbn ]['category'] as $term ) {
						$cat[ $term ] = ( $cat[ $term ] ?? 0 ) + 1;
					}
				}
				continue;
			}

			// Otherwise we checks if the cover exists.
			$response = wp_safe_remote_get( get_cdn_cover_url( $isbn ) );

			// We have an error (not http error code).
			if ( is_wp_error( $response ) ) {
				write_log(
					array(
						'handler' => 'cover_check_cron',
						'message' => 'wp_remote_get error',
						'error'   => $response->get_all_error_data(),
					)
				);
				continue;
			}

			$checked ++;
			echo '<p>Checking ' . $isbn . ' with response: ' . wp_remote_retrieve_response_code( $response ) . '</p>';

			$has_cover = wp_remote_retrieve_response_code( $response ) === 200;

			$terms = array();
			foreach ( wp_get_post_terms( $book['ID'], 'otava_kategoria' ) as $term ) {
				if ( $has_cover ) {
					$cat[ $term->slug ] = ( $cat[ $term->slug ] ?? 0 ) + 1;
				}
				$terms[] = $term->slug;
			}

			// Update this books cover cache object.
			$covers[ $isbn ] = array(
				'id'        => $book['ID'],
				'category'  => $terms,
				'has_cover' => wp_remote_retrieve_response_code( $response ) === 200,
				'pvm'       => $book['pvm'],
				'timestamp' => time(),
			);

		}
		// Check that each category has enough books.
		$kaunokirjat = $cat['kaunokirjat'] ?? 0;
		$tietokirjat = $cat['tietokirjat'] ?? 0;
		$lasten      = $cat['lasten-ja-nuortenkirjat'] ?? 0;
		echo "Kaunokirjat: $kaunokirjat <br/>";
		echo "Tietokirjat: $tietokirjat<br/>";
		echo "Lastenkirjat: $lasten <br/>";
	} while ( $checked < $max && ( $kaunokirjat < $cat_target || $tietokirjat < $cat_target || $lasten < $cat_target ) );

	// Sort covers
	uasort(
		$covers,
		function ( $a, $b ) {
			if ( $a['pvm'] ?? '' === $b['pvm'] ?? '' ) {
				return 0;
			}
			if ( $a['pvm'] ?? '' > $b['pvm'] ?? '' ) {
				return - 1;
			}

			return 1;
		}
	);

	// Update the cache.
	put_json( BOOK_COVER_DATA, $covers );
}


function get_recent_books_sql( $nr = 64, $page = 0 ) {
	if ( ! is_int( $nr ) || ! is_int( $page ) ) {
		echo "Malformed arguments!";

		return array();
	}
	global $wpdb;

	$offset = $nr * $page;

	$sql = "
		SELECT
			post.ID,
			post.post_title,
			isbn.meta_value as isbn,
			IF (LENGTH(ilmestymis.meta_value) > 7, str_to_date(ilmestymis.meta_value, '%Y%m%d'), str_to_date(julkaisu.meta_value, '%Y%m%d')) as pvm
		FROM wp_posts as post
		LEFT JOIN wp_postmeta as isbn
		ON post.ID = isbn.post_id
		AND isbn.meta_key = 'isbn'
		LEFT JOIN wp_postmeta as ilmestymis
		ON post.ID = ilmestymis.post_id
		AND ilmestymis.meta_key = 'ilmestymispvm'
		LEFT JOIN wp_postmeta as embargo
		ON post.ID = embargo.post_id
		AND embargo.meta_key = 'embargopvm'
		LEFT JOIN wp_postmeta as julkaisu
		ON post.ID = julkaisu.post_id
		AND julkaisu.meta_key = 'julkaisuaika'
		WHERE post.post_type = 'otava_book'
		AND post.post_status = 'publish'
		AND IF (LENGTH(embargo.meta_value) > 7, str_to_date(embargo.meta_value, '%Y%m%d'), DATE_ADD(IF (LENGTH(ilmestymis.meta_value) > 7, str_to_date(ilmestymis.meta_value, '%Y%m%d'), str_to_date(julkaisu.meta_value, '%Y%m%d')), INTERVAL -30 DAY)) < now()
		ORDER BY pvm DESC
		LIMIT $nr
		OFFSET $offset
		";

	return $wpdb->get_results( $sql, ARRAY_A );
}
