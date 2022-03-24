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

	$book_args = array(
		'post_type'      => 'otava_book',
		'post_status'    => 'publish',
		'posts_per_page' => $books_per_page,
		'orderby'        => 'meta_value_num',
		'order'          => 'DESC',
		'meta_key'       => 'julkaisuaika',
		'meta_query'     => array(
			array(
				'key'     => 'julkaisuaika',
				'value'   => date( 'Ymd' ),
				'compare' => '<=',
			),
		),
		'tax_query'      => array(
			'relation' => 'AND',
			array(
				'taxonomy' => 'otava_kategoria',
				'field'    => 'slug',
				'terms'    => array(
					'aanikirjat',
					'erikoiskirjat',
					'kalenterit',
					'miki',
					'muut',
					'muut-yleiset-kirjat',
					'tuotteet',
					'yhteiset-erikoiskirjat',
					'nopia',
				),
				'operator' => 'NOT IN',
			),
		),
	);

	$start   = 0;
	$max     = 1;
	$checked = array();

	/*
	 * This loop will always begin checking at the $books_per_page amount of newest books.
	 * If all of them are checked, it will continue until it hits a chunk of $books_per_page that has not yet been checked, it will then check them and exit.
	 * This means that each cron run of this will continually scan backwards unless we have to recheck a newer chunk.
	 */
	while ( $start < $max ) {
		$books = get_posts( $book_args );
		if ( empty( $books ) ) {
			break;
		}

		echo '<p>Iteration: ' . ( $start + 1 ) . ' of Cover checking</p>';

		foreach ( $books as $book ) {
			$isbn = get_field( 'isbn', $book->ID );
			if ( empty( $isbn ) ) {
				continue;
			}

			// If it exists, and either has a cover, or has been checked over a day ago, we add it to our checked array.
			if ( isset( $covers[ $isbn ] ) && ( $covers[ $isbn ]['has_cover'] || ( time() - $covers[ $isbn ]['timestamp'] ) < ( 24 * 60 * 60 ) ) ) {
				echo '<p>Found already checked book with isbn: ' . $isbn . '</p>';
				$checked[] = $isbn;
				continue;
			}

			// Otherwise we checks if the cover exists.
			$book->isbn = $isbn;

			$response = wp_safe_remote_get( get_book_cover_url( $book ) );

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

			echo '<p>Checking ' . $isbn . ' with response: ' . wp_remote_retrieve_response_code( $response ) . '</p>';

			// Update this books cover cache object.
			$covers[ $isbn ] = array(
				'has_cover' => wp_remote_retrieve_response_code( $response ) === 200,
				'timestamp' => time(),
			);

		}
		// Check if up until this iteration we have checked/found all covers, so we can continue to the next iteration.
		if ( count( $checked ) >= $books_per_page * ( $start + 1 ) ) {
			$start++;
			$max++;
			$book_args['paged'] = $start;
			continue;
		}
		// Otherwise we exit.
		break;
	}
	// Update the cache.
	put_json( BOOK_COVER_DATA, $covers );
}
