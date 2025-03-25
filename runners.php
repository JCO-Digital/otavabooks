<?php
/**
 * This file contains functions for managing book data, including fetching, updating,
 * importing, and deleting book posts. It also includes functions for checking book covers
 * and updating terms related to book publishing dates.
 *
 * @package otavabooks
 */

namespace otavabooks;

/**
 * Update a number of book posts.
 *
 * @param \Jcore\Runner\Arguments $data Data given by the runner.
 * @return \Jcore\Runner\Arguments
 */
function update_books( \Jcore\Runner\Arguments $data ): \Jcore\Runner\Arguments {
	if ( 1 === $data->page ) {
		$books = do_book_data_fetch();
	} else {
		$books = get_json( IMPORT_BOOK_DATA );
	}
	$per_page = 45;
	$isbn     = get_isbn_list();
	$offset   = $per_page * ( $data->page - 1 );
	if ( empty( $data->data['updated'] ) ) {
		$data->data['updated'] = 0;
	}
	if ( empty( $data->data['imported'] ) ) {
		$data->data['imported'] = 0;
	}
	if ( empty( $data->data['skipped'] ) ) {
		$data->data['skipped'] = 0;
	}
	if ( empty( $data->data['failed'] ) ) {
		$data->data['failed'] = 0;
	}

	foreach ( array_slice( $books, $offset, $per_page ) as $book ) {
		$post_id = array_search( $book['isbn'], $isbn, true );
		if ( false === $post_id ) {
			// Import Book.
			$id = create_book_object( $book );
			if ( $id ) {
				echo 'Imported: ' . esc_html( $book['title'] ) . "\n";
				++$data->data['imported'];
			} elseif ( is_null( $id ) ) {
				++$data->data['skipped'];
			} else {
				++$data->data['failed'];
			}
		} else {
			// Update Book.
			$id = update_book_object( $post_id, $book );
			if ( false === $id ) {
				++$data->data['failed'];
			} else {
				echo 'Updated: ' . esc_html( $book['title'] ) . "\n";
				++$data->data['updated'];
			}
		}
	}

	$total     = count( $books );
	$processed = $data->page * $per_page;
	if ( $total > $processed ) {
		$data->set_next_page();
	} else {
		$processed = $total;
	}
	$data->return = array(
		'status' => 'Processed books ' . $processed . ' of ' . $total,
	);

	if ( empty( $data->next_page ) ) {
		echo "\n";
		echo 'Updated ' . esc_html( $data->data['updated'] ) . " books\n";
		if ( $data->data['imported'] ) {
			echo 'Imported ' . esc_html( $data->data['imported'] ) . " books\n";
		}
		if ( $data->data['skipped'] ) {
			echo 'Skipped ' . esc_html( $data->data['skipped'] ) . " books\n";
		}
		if ( $data->data['failed'] ) {
			echo 'Failed to update ' . esc_html( $data->data['failed'] ) . " books\n";
		}
		$cleaned = clean_tulossa();
		echo 'Cleaned terms from ' . esc_html( $cleaned ) . " books.\n";
		$set = set_tulossa();
		echo 'Set terms to ' . esc_html( $set ) . " books.\n";
	}

	return $data;
}

/**
 * Set Tulossa term for books with future publish date, and remove it for books that have passed the date.
 *
 * @param \Jcore\Runner\Arguments $data Data given by the runner.
 * @return \Jcore\Runner\Arguments
 */
function update_tulossa( \Jcore\Runner\Arguments $data ): \Jcore\Runner\Arguments {
	$cleaned = clean_tulossa();
	echo 'Cleaned terms from ' . esc_html( $cleaned ) . " books.\n";
	$set = set_tulossa();
	echo 'Set terms to ' . esc_html( $set ) . " books.\n";

	$data->return = array(
		'status' => 'Set terms to ' . esc_html( $set ) . ' books.',
	);
	return $data;
}

/**
 * Delete books not in the feed anymore.
 *
 * @param \Jcore\Runner\Arguments $data Data given by the runner.
 * @return \Jcore\Runner\Arguments
 */
function delete_books( \Jcore\Runner\Arguments $data ): \Jcore\Runner\Arguments {
	$max     = 25;
	$deleted = 0;
	$isbns   = get_isbn_list();
	$books   = get_json( IMPORT_BOOK_DATA );
	$feed    = array();
	foreach ( $books as $book ) {
		$feed[] = $book['isbn'];
	}
	if ( empty( $data->data['deleted'] ) ) {
		$data->data['deleted'] = 0;
	}

	foreach ( $isbns as $id => $isbn ) {
		if ( ! in_array( $isbn, $feed, true ) ) {
			if ( wp_delete_post( $id ) ) {
				++$deleted;
				echo esc_html( $isbn ) . ' / ' . esc_html( $id ) . " deleted\n";
			} else {
				echo 'Failed to delete ' . esc_html( $id ) . "\n";
			}
			if ( $deleted >= $max ) {
				$data->set_next_page();
				$data->data['deleted'] += $deleted;
				break;
			}
		}
	}
	$data->return = array(
		'status' => 'Deleted ' . esc_html( $data->data['deleted'] ) . ' books.',
	);

	return $data;
}

/**
 * Fetch data from JSON file.
 *
 * @param \Jcore\Runner\Arguments $data Data given by the runner.
 * @return \Jcore\Runner\Arguments
 */
function fetch_book_data( \Jcore\Runner\Arguments $data ): \Jcore\Runner\Arguments {
	echo "Starting fetch.\n";
	$books     = do_book_data_fetch();
	$timestamp = time();
	$text      = sprintf( 'Books: %d Imported at %s', count( $books ), gmdate( 'Y-m-d H:i:s', $timestamp ) );

	echo esc_html( $text );

	$data->return = array(
		'status' => $text,
	);

	return $data;
}

/**
 * Fetch book data and save it to a JSON file.
 *
 * @return array An array of book data.
 */
function do_book_data_fetch(): array {
	$books = make_book_list();
	printf( 'Made book list with %d books.', count( $books ) );
	echo "\n";
	$json = wp_json_encode( $books );
	// phpcs:ignore
	printf( 'Writing %d characters to file.', strlen( $json ) );
	echo "\n";
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	file_put_contents( IMPORT_BOOK_DATA, $json );

	return $books;
}


/**
 * Handles checking books if they have a cover or not.
 * If a book does not have a cover it is rechecked every day.
 *
 * @param \Jcore\Runner\Arguments $data Data given by the runner.
 * @return \Jcore\Runner\Arguments
 */
function cover_check( \Jcore\Runner\Arguments $data ): \Jcore\Runner\Arguments {
	// Setup parameters and get the "cover" cache.
	$covers         = get_json( BOOK_COVER_DATA );
	$books_per_page = 24;
	$cat_target     = 12;

	if ( empty( $data->data['skipped'] ) ) {
		$data->data['skipped'] = 0;
	}
	if ( empty( $data->data['checked'] ) ) {
		$data->data['checked'] = 0;
	}
	if ( empty( $data->data['cat'] ) ) {
		$data->data['cat'] = array();
	}

	$update_ts = time();

	/*
	 * This loop will always begin checking at the $books_per_page amount of newest books.
	 * If all of them are checked, it will continue until it hits a chunk of $books_per_page that has not yet been checked, it will then check them and exit.
	 * This means that each cron run of this will continually scan backwards unless we have to recheck a newer chunk.
	 */

	$books = get_recent_books_sql( $books_per_page, $data->page );
	if ( ! empty( $books ) ) {

		echo esc_html( sprintf( "Iteration: %d of Cover checking\n", $data->page ) );

		foreach ( $books as $book ) {
			$isbn = $book['isbn'];
			if ( empty( $isbn ) ) {
				continue;
			}

			// If it exists and has been checked less than a day ago.
			if (
				isset( $covers[ $isbn ] ) &&
				! empty( $covers[ $isbn ]['id'] ) &&
				! empty( $covers[ $isbn ]['category'] ) &&
				( time() - $covers[ $isbn ]['timestamp'] ) < ( 24 * 60 * 60 )
			) {
				echo "Found already checked book with isbn: $isbn\n";
				++$data->data['skipped'];
				$covers[ $isbn ]['pvm']     = $book['pvm'];
				$covers[ $isbn ]['updated'] = $update_ts;
				if ( $covers[ $isbn ]['has_cover'] ) {
					foreach ( $covers[ $isbn ]['category'] as $term ) {
						$data->data['cat'][ $term ] = ( $data->data['cat'][ $term ] ?? 0 ) + 1;
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

			++$data->data['checked'];
			echo 'Checking ' . $isbn . ' with response: ' . wp_remote_retrieve_response_code( $response ) . "\n";

			$has_cover = wp_remote_retrieve_response_code( $response ) === 200;

			$terms = array();
			foreach ( wp_get_post_terms( $book['ID'], 'otava_kategoria' ) as $term ) {
				if ( $has_cover ) {
					$data->data['cat'][ $term->slug ] = ( $data->data['cat'][ $term->slug ] ?? 0 ) + 1;
				}
				$terms[] = $term->slug;
			}

			// Update this books cover cache object.
			$covers[ $isbn ] = array(
				'id'        => $book['ID'],
				'title'     => $book['post_title'],
				'category'  => $terms,
				'has_cover' => wp_remote_retrieve_response_code( $response ) === 200,
				'pvm'       => $book['pvm'],
				'timestamp' => time(),
				'updated'   => $update_ts,
			);
		}

		// Check that each category has enough books.
		$kaunokirjat = $data->data['cat']['kaunokirjat'] ?? 0;
		$tietokirjat = $data->data['cat']['tietokirjat'] ?? 0;
		$lasten      = $data->data['cat']['lasten-ja-nuortenkirjat'] ?? 0;
		echo "Kaunokirjat: $kaunokirjat <br/>\n";
		echo "Tietokirjat: $tietokirjat<br/>\n";
		echo "Lastenkirjat: $lasten <br/>\n";

		$data->return = array(
			'status' => 'Checked: ' . $data->data['checked'] . ' Skipped: ' . $data->data['skipped'],
		);

		if ( $kaunokirjat < $cat_target || $tietokirjat < $cat_target || $lasten < $cat_target ) {
			$data->set_next_page();
		}
	}

	// Remove stale covers.
	foreach ( $covers as $isbn => $cover ) {
		if ( empty( $cover['updated'] ) || $cover['updated'] < ( $update_ts - 3600 ) ) {
			echo "Remove cover from $cover[title] \n";
			unset( $covers[ $isbn ] );
		}
	}

	// Update the cache.
	put_json( BOOK_COVER_DATA, $covers );

	return $data;
}


function get_recent_books_sql( $nr = 64, $page = 1 ) {
	if ( ! is_int( $nr ) || ! is_int( $page ) ) {
		echo 'Malformed arguments!';

		return array();
	}
	global $wpdb;

	$offset = $nr * ( $page - 1 );

	$sql = $wpdb->prepare(
		"
		SELECT
			post.ID,
			post.post_title,
			isbn.meta_value as isbn,
			str_to_date(ilmestymis.meta_value, '%%Y-%%m-%%d') as pvm
		FROM {$wpdb->prefix}posts as post
		LEFT JOIN {$wpdb->prefix}postmeta as isbn
		ON post.ID = isbn.post_id
		AND isbn.meta_key = 'isbn'
		LEFT JOIN {$wpdb->prefix}postmeta as ilmestymis
		ON post.ID = ilmestymis.post_id
		AND ilmestymis.meta_key = 'ilmestymispvm'
		WHERE post.post_type = 'otava_book'
		AND post.post_status = 'publish'
		AND str_to_date(ilmestymis.meta_value, '%%Y-%%m-%%d') < now()
		ORDER BY pvm DESC
		LIMIT %d
		OFFSET %d
		",
		$nr,
		$offset
	);
	return $wpdb->get_results( $sql, ARRAY_A );
}
