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
		if ( is_wp_error( $books ) ) {
			// Never import from a stale cache on the assumption the fetch worked.
			$data->status = 'error';
			$data->return = array(
				'status' => 'Fetch failed, import aborted: ' . $books->get_error_message(),
			);

			return $data;
		}
	} else {
		$books = get_json( IMPORT_BOOK_DATA );
	}
	$per_page = 100;

	/*
	 * Include trashed books here (but not in the delete runner): a book that reappears in the
	 * feed should be revived by update_book_object() rather than duplicated as a new post.
	 */
	$isbn   = get_isbn_list( array_merge( get_managed_statuses(), array( 'trash' ) ) );
	$offset = $per_page * ( $data->page - 1 );
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
 * Build the set of ISBNs the feed vouches for.
 *
 * This deliberately protects far more than the books we managed to build. A book is kept if the
 * feed mentions its ISBN *at all*:
 *
 *  - the master ISBN of each book;
 *  - every version ISBN (there are roughly twice as many of these, and the master flag moves
 *    between editions upstream -- when it does, the post still carries the old ISBN);
 *  - every ISBN seen for one of our publishers, including those on books the importer dropped
 *    because it could not classify them.
 *
 * Being unclassifiable is an importer problem. Only a book the feed has stopped mentioning
 * entirely is a candidate for removal.
 *
 * @param array $books Cached book list.
 * @return array<string,bool> ISBN => true, for isset() lookups.
 */
function build_feed_isbn_map( array $books ): array {
	$feed = array();
	foreach ( $books as $book ) {
		if ( ! empty( $book['isbn'] ) ) {
			$feed[ $book['isbn'] ] = true;
		}
		foreach ( $book['versions'] ?? array() as $version ) {
			if ( ! empty( $version['isbn'] ) ) {
				$feed[ $version['isbn'] ] = true;
			}
		}
	}
	foreach ( get_json( IMPORT_ISBN_INDEX ) as $isbn ) {
		// Be strict about the shape: a malformed index must not take the whole runner down.
		if ( is_scalar( $isbn ) && '' !== (string) $isbn ) {
			$feed[ (string) $isbn ] = true;
		}
	}

	return $feed;
}

/**
 * Check that the cached feed is trustworthy enough to delete against.
 *
 * @param array $books Cached book list.
 * @return string|null Reason to abort, or null when the cache can be trusted.
 */
function delete_abort_reason( array $books ): ?string {
	$status = get_fetch_status();
	if ( empty( $status['status'] ) || 'ok' !== $status['status'] ) {
		return 'No record of a successful feed fetch. Run "Fetch Data" first.';
	}

	$age     = time() - (int) ( $status['fetched_at'] ?? 0 );
	$max_age = (int) apply_filters( 'otavabooks_feed_max_age', OTAVABOOKS_FEED_MAX_AGE );
	if ( $age > $max_age ) {
		return sprintf( 'Last successful fetch was %d days ago; refusing to delete against a stale cache.', (int) floor( $age / DAY_IN_SECONDS ) );
	}

	$minimum = (int) apply_filters( 'otavabooks_minimum_book_count', OTAVABOOKS_FEED_MIN_BOOKS );
	if ( count( $books ) < $minimum ) {
		return sprintf( 'Cached feed holds only %d books, minimum is %d.', count( $books ), $minimum );
	}

	return null;
}

/**
 * Trash books that have disappeared from the feed.
 *
 * Runs in two phases, held in $data->data['phase']:
 *
 *  - 'scan' walks every managed book once, clearing the missing-marker on books that are present
 *    and advancing it on books that are not. A book only becomes a candidate once it has been
 *    missing from several consecutive successful fetches *and* for a minimum stretch of time.
 *  - 'delete' trashes the candidates the scan collected.
 *
 * Fixing the candidate list up front is what makes the blast-radius check possible, and means no
 * post is ever visited twice within a run.
 *
 * Note this uses wp_trash_post(), not wp_delete_post(). wp_delete_post() only routes to the trash
 * for the built-in 'post' and 'page' types; on a custom post type it destroys the row outright.
 *
 * @param \Jcore\Runner\Arguments $data Data given by the runner.
 * @return \Jcore\Runner\Arguments
 */
function delete_books( \Jcore\Runner\Arguments $data ): \Jcore\Runner\Arguments {
	$scan_per_page   = 500;
	$delete_per_page = 25;
	// Cron supplies no input, so the safe default here is the live one -- scheduling the job is
	// the deliberate act. A click in the admin UI defaults to 'report'.
	$dry_run = 'report' === ( $data->input['mode'] ?? ( $data->data['mode'] ?? 'delete' ) );

	$books = get_json( IMPORT_BOOK_DATA );

	if ( 1 === $data->page ) {
		$reason = delete_abort_reason( $books );
		if ( null !== $reason ) {
			$data->status = 'error';
			echo esc_html( $reason ), "\n";
			$data->return = array( 'status' => 'Aborted: ' . $reason );

			return $data;
		}

		$data->data = array(
			'mode'       => $dry_run ? 'report' : 'delete',
			'phase'      => 'scan',
			'scan_page'  => 1,
			'candidates' => array(),
			'missing'    => 0,
			'no_isbn'    => 0,
			'restored'   => 0,
			'deleted'    => 0,
			'failed'     => 0,
		);
		printf( "Mode: %s\n", $dry_run ? 'report only' : 'trash missing books' );

		foreach ( get_books_without_isbn() as $book ) {
			++$data->data['no_isbn'];
			$data->export->add_row(
				array(
					'action' => 'skipped-no-isbn',
					'id'     => $book['ID'],
					'isbn'   => '',
					'title'  => $book['post_title'],
					'status' => $book['post_status'],
				)
			);
		}
		printf( "Skipping %d books with no ISBN meta.\n", (int) $data->data['no_isbn'] );
	}

	if ( 'scan' === $data->data['phase'] ) {
		return delete_books_scan( $data, $books, $scan_per_page, $dry_run );
	}

	return delete_books_trash( $data, $delete_per_page, $dry_run );
}

/**
 * Scan phase: work out which books the feed has stopped mentioning.
 *
 * @param \Jcore\Runner\Arguments $data     Runner data.
 * @param array                   $books    Cached book list.
 * @param int                     $per_page Books to examine per page.
 * @param bool                    $dry_run  Whether to leave markers untouched.
 * @return \Jcore\Runner\Arguments
 */
function delete_books_scan( \Jcore\Runner\Arguments $data, array $books, int $per_page, bool $dry_run ): \Jcore\Runner\Arguments {
	$feed   = build_feed_isbn_map( $books );
	$isbns  = get_isbn_list();
	$total  = count( $isbns );
	$offset = $per_page * ( $data->data['scan_page'] - 1 );

	$min_runs = (int) apply_filters( 'otavabooks_missing_runs', OTAVABOOKS_MISSING_RUNS );
	$min_age  = (int) apply_filters( 'otavabooks_missing_min_age', OTAVABOOKS_MISSING_MIN_AGE );

	foreach ( array_slice( $isbns, $offset, $per_page, true ) as $id => $isbn ) {
		if ( isset( $feed[ $isbn ] ) ) {
			// Present in the feed. Clear any pending-removal markers.
			if ( ! $dry_run && metadata_exists( 'post', $id, OTAVABOOKS_MISSING_SINCE_META ) ) {
				delete_post_meta( $id, OTAVABOOKS_MISSING_SINCE_META );
				delete_post_meta( $id, OTAVABOOKS_MISSING_RUNS_META );
				++$data->data['restored'];
			}
			continue;
		}

		++$data->data['missing'];
		$since = (int) get_post_meta( $id, OTAVABOOKS_MISSING_SINCE_META, true );
		$runs  = (int) get_post_meta( $id, OTAVABOOKS_MISSING_RUNS_META, true );
		if ( ! $since ) {
			$since = time();
		}
		++$runs;

		if ( ! $dry_run ) {
			update_post_meta( $id, OTAVABOOKS_MISSING_SINCE_META, $since );
			update_post_meta( $id, OTAVABOOKS_MISSING_RUNS_META, $runs );
		}

		$age = time() - $since;
		if ( $runs >= $min_runs && $age >= $min_age ) {
			$data->data['candidates'][] = $id;
			$action                     = 'candidate';
		} else {
			$action = 'missing';
		}

		$data->export->add_row(
			array(
				'action' => $action,
				'id'     => $id,
				'isbn'   => $isbn,
				'title'  => get_the_title( $id ),
				'status' => get_post_status( $id ),
				'runs'   => $runs,
				'since'  => gmdate( 'Y-m-d H:i:s', $since ),
			)
		);
	}

	$scanned = min( $data->data['scan_page'] * $per_page, $total );
	printf( "Scanned %d of %d books.\n", (int) $scanned, (int) $total );

	if ( $scanned < $total ) {
		++$data->data['scan_page'];
		$data->set_next_page();
		$data->return = array( 'status' => sprintf( 'Scanned %d of %d books.', $scanned, $total ) );

		return $data;
	}

	// Scan finished. Report, then decide whether the delete phase is safe to enter.
	printf(
		"Missing from feed: %d. Past the grace period: %d. Came back: %d. No ISBN (skipped): %d.\n",
		(int) $data->data['missing'],
		count( $data->data['candidates'] ),
		(int) $data->data['restored'],
		(int) $data->data['no_isbn']
	);

	$ratio = (float) apply_filters( 'otavabooks_max_delete_ratio', OTAVABOOKS_MAX_DELETE_RATIO );
	$limit = max( 100, (int) round( $ratio * $total ) );
	if ( count( $data->data['candidates'] ) > $limit ) {
		$data->status = 'error';
		$message      = sprintf(
			'Aborted: %d books would be trashed, more than the limit of %d (%d%% of %d). Nothing was removed; see the export for the full list.',
			count( $data->data['candidates'] ),
			$limit,
			(int) round( $ratio * 100 ),
			$total
		);
		echo esc_html( $message ), "\n";
		write_log( 'otavabooks delete: ' . $message );
		$data->return = array( 'status' => $message );

		return $data;
	}

	if ( $dry_run ) {
		$data->return = array(
			'status' => sprintf( 'Report only: %d books would be trashed.', count( $data->data['candidates'] ) ),
		);

		return $data;
	}

	if ( empty( $data->data['candidates'] ) ) {
		$data->return = array( 'status' => 'Nothing to remove.' );

		return $data;
	}

	$data->data['phase'] = 'delete';
	$data->set_next_page();
	$data->return = array( 'status' => sprintf( 'Trashing %d books.', count( $data->data['candidates'] ) ) );

	return $data;
}

/**
 * Delete phase: trash the candidates collected by the scan.
 *
 * @param \Jcore\Runner\Arguments $data     Runner data.
 * @param int                     $per_page Books to trash per page.
 * @param bool                    $dry_run  Whether to leave posts untouched.
 * @return \Jcore\Runner\Arguments
 */
function delete_books_trash( \Jcore\Runner\Arguments $data, int $per_page, bool $dry_run ): \Jcore\Runner\Arguments {
	$batch = array_splice( $data->data['candidates'], 0, $per_page );

	foreach ( $batch as $id ) {
		$isbn  = get_post_meta( $id, 'isbn', true );
		$title = get_the_title( $id );
		// Announce before acting, so the log says what was removed even if the run dies here.
		printf( "Trashing %s / %d / %s\n", esc_html( $isbn ), (int) $id, esc_html( $title ) );

		if ( $dry_run || wp_trash_post( $id ) ) {
			++$data->data['deleted'];
			$data->export->add_row(
				array(
					'action' => 'trashed',
					'id'     => $id,
					'isbn'   => $isbn,
					'title'  => $title,
				)
			);
		} else {
			++$data->data['failed'];
			printf( "Failed to trash %d\n", (int) $id );
		}
	}

	if ( ! empty( $data->data['candidates'] ) ) {
		$data->set_next_page();
	}

	$status = sprintf( 'Trashed %d books.', $data->data['deleted'] );
	if ( $data->data['failed'] ) {
		$status .= sprintf( ' %d failed.', $data->data['failed'] );
	}
	if ( empty( $data->next_page ) ) {
		echo esc_html( $status ), "\n";
		echo "Trashed books are kept for review; they are not emptied automatically.\n";
	}
	$data->return = array( 'status' => $status );

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
	$books = do_book_data_fetch();
	if ( is_wp_error( $books ) ) {
		$data->status = 'error';
		$data->return = array(
			'status' => 'Fetch failed: ' . $books->get_error_message(),
		);

		return $data;
	}

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
 * Nothing is written unless the result passes the sanity gates below. The cached book list is
 * the delete runner's only idea of what still exists, so replacing a good cache with a bad one
 * is the single most destructive thing this plugin can do.
 *
 * On success this also writes:
 *  - IMPORT_ISBN_INDEX, the flat list of protected ISBNs, so the delete runner does not have to
 *    parse the multi-megabyte book file on every page;
 *  - IMPORT_TIMESTAMP_DATA, a small sidecar recording that a fetch actually succeeded and when.
 *    Its absence or staleness is what tells the delete runner not to act.
 *
 * @return array|\WP_Error Book data, or WP_Error when the feed was unusable or implausible.
 */
function do_book_data_fetch() {
	$result = make_book_list();
	if ( is_wp_error( $result ) ) {
		echo "Feed unusable, keeping the previous book data.\n";
		return $result;
	}

	$books = $result['books'];
	$isbns = $result['isbns'];
	printf( "Made book list with %d books and %d protected ISBNs.\n", count( $books ), count( $isbns ) );

	$rejection = feed_rejection_reason( count( $books ) );
	if ( null !== $rejection ) {
		echo esc_html( $rejection ), "\n";
		echo "Keeping the previous book data. Dumping the rejected list for inspection.\n";
		put_json_atomic( IMPORT_BOOK_DATA . '.rejected', $books );
		write_log( 'otavabooks import: ' . $rejection );

		return new \WP_Error( 'otava_feed_implausible', $rejection );
	}

	if ( ! put_json_atomic( IMPORT_BOOK_DATA, $books ) || ! put_json_atomic( IMPORT_ISBN_INDEX, $isbns ) ) {
		$message = 'Failed to write the book data to disk.';
		echo esc_html( $message ), "\n";
		write_log( 'otavabooks import: ' . $message );

		return new \WP_Error( 'otava_write_failed', $message );
	}

	put_json_atomic(
		IMPORT_TIMESTAMP_DATA,
		array_merge(
			array(
				'status'     => 'ok',
				'fetched_at' => time(),
			),
			$result['stats']
		)
	);
	update_option( 'otavabooks_last_good_feed_count', count( $books ), false );

	return $books;
}

/**
 * Decide whether a freshly built book list is plausible enough to persist.
 *
 * Real churn in this feed is a handful of titles a day against a catalogue of several thousand,
 * so a sudden collapse means something upstream broke, not that the books were withdrawn.
 *
 * @param int $count Number of books in the new list.
 * @return string|null Reason to reject, or null when the list looks sane.
 */
function feed_rejection_reason( int $count ): ?string {
	$minimum = (int) apply_filters( 'otavabooks_minimum_book_count', OTAVABOOKS_FEED_MIN_BOOKS );
	if ( $count < $minimum ) {
		return sprintf( 'Refusing feed: only %d books, minimum is %d.', $count, $minimum );
	}

	$previous = (int) get_option( 'otavabooks_last_good_feed_count', 0 );
	$ratio    = (float) apply_filters( 'otavabooks_max_shrink_ratio', OTAVABOOKS_FEED_FLOOR_RATIO );
	if ( $previous > 0 && $count < $previous * $ratio ) {
		return sprintf(
			'Refusing feed: %d books is below %d%% of the last good count (%d).',
			$count,
			(int) round( $ratio * 100 ),
			$previous
		);
	}

	return null;
}

/**
 * Read the sidecar written by the last successful fetch.
 *
 * @return array
 */
function get_fetch_status(): array {
	return get_json( IMPORT_TIMESTAMP_DATA );
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

	/*
	 * Remove stale covers, but only once the run is finished. Each page only refreshes the books
	 * it looked at, so purging every page reduced the cache to whatever the last page touched --
	 * which is why only ~96 of several thousand books ever had cover data. Key the check off
	 * 'timestamp' (when the cover was actually checked) rather than 'updated' (when this run
	 * happened to revisit the entry).
	 */
	if ( empty( $data->next_page ) ) {
		$max_age = (int) apply_filters( 'otavabooks_cover_max_age', 30 * DAY_IN_SECONDS );
		foreach ( $covers as $isbn => $cover ) {
			if ( empty( $cover['timestamp'] ) || $cover['timestamp'] < ( $update_ts - $max_age ) ) {
				printf( "Remove stale cover: %s %s\n", esc_html( $isbn ), esc_html( $cover['title'] ?? '' ) );
				unset( $covers[ $isbn ] );
			}
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
