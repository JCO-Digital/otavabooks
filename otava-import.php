<?php

namespace otavabooks;

use Nicebooks\Isbn\Exception\InvalidIsbnException;
use Nicebooks\Isbn\Isbn;
use Nicebooks\Isbn\IsbnTools;

/**
 * Generate the list of books.
 *
 * This function generates a list of books from the imported data.
 * It iterates through each row of the imported data, checks if the row is valid,
 * and then adds the book to the list. It handles both master products and
 * product versions, ensuring that the most up-to-date information is used.
 * It also categorizes books and adds version information.
 *
 * The function uses the IsbnTools class to validate ISBNs and the Isbn class
 * to format them. It also uses helper functions like add_book and add_version
 * to structure the book data.
 *
 * Alongside the books it returns `isbns`: every ISBN seen on a row belonging to one of our
 * publishers, whether or not the book it belongs to survived the cleanup pass below. The delete
 * runner protects that wider set, so that a book the importer merely failed to classify is never
 * mistaken for one that was withdrawn from sale.
 *
 * @return array|\WP_Error {
 *     Import result, or WP_Error when the feed could not be fetched or parsed.
 *
 *     @type array $books   Book objects keyed by kantanumero.
 *     @type array $isbns   Every ISBN seen for a matching publisher.
 *     @type array $stats   Row and drop counts for logging.
 * }
 */
function make_book_list() {
	$tools      = new IsbnTools();
	$data       = array();
	$seen_isbns = array();
	$publishers = get_publishers_setting();
	$import     = get_import_data();
	if ( is_wp_error( $import ) ) {
		// Fetch or parse failed. Propagate, so callers never mistake this for an empty feed.
		return $import;
	}
	printf( "Read %d objects from file.\n", count( $import ) );
	printf( "Matching publishers: %s\n", esc_html( (string) wp_json_encode( $publishers ) ) );

	$rejected_publisher = 0;
	$rejected_isbn      = 0;
	foreach ( $import as $row ) {
		if ( ! isset( $row['kantanumero'] ) ) {
			continue;
		}
		if ( ! in_array( strtolower( $row['tulosyksikko'] ?? '' ), $publishers, true ) ) {
			++$rejected_publisher;
			continue;
		}
		if ( ! $tools->isValidIsbn( $row['isbn'] ) ) {
			++$rejected_isbn;
			continue;
		}
		try {
			$isbn   = Isbn::of( $row['isbn'] );
			$master = 'Kyllä' === $row['master_tuote'];
			$id     = $row['kantanumero'];

			// Protect this ISBN from deletion even if the book is dropped during cleanup below.
			$seen_isbns[ $isbn->format() ] = true;

			// Get the book, or create an empty object if not exists.
			$book = $data[ $id ] ?? array(
				'categories' => array(),
				'versions'   => array(),
				'timestamp'  => 0,
			);

			if ( $master ) {
				// If product is master, create the real book object.
				$book = add_book( $row, $isbn->format(), $book['categories'], $book['versions'], $book['timestamp'] );
			} elseif ( newer_timestamp( $row['muutosaikaleima'] ?? 0, $book['timestamp'] ) ) {
				// If not master, check if timestamp needs to be updated.
				$book['timestamp'] = $row['muutosaikaleima'];
			}

			// Record the category, once. Every version row carries one, so they repeat.
			$cat = get_otava_cat( $row['tuoteryhma'] );
			if ( ! empty( $cat ) && ! in_array( $cat, $book['categories'], true ) ) {
				$book['categories'][] = $cat;
			}
			// Push current product onto version stack.
			$book['versions'][] = add_version( $isbn->format(), $row );
			// Write/overwrite book into array.
			$data[ $id ] = $book;
		} catch ( InvalidIsbnException $exception ) {
			++$rejected_isbn;
			write_log( $exception->getMessage() );
		}
	}
	printf( "Rejected rows - publisher: %d / ISBN: %d\n", (int) $rejected_publisher, (int) $rejected_isbn );
	printf( "Added %d books to list before cleanup.\n", count( $data ) );

	/*
	 * Drop books we cannot represent. Their ISBNs stay in $seen_isbns, so the delete runner
	 * leaves any existing posts alone -- being unclassifiable is an importer problem, not a
	 * signal that the book was withdrawn. Name each one so the cause is visible in the log.
	 */
	$clean      = array();
	$empty_isbn = 0;
	$empty_cat  = 0;
	foreach ( $data as $id => $book ) {
		if ( empty( $book['isbn'] ) ) {
			++$empty_isbn;
			printf( "Dropped (no master row): %s\n", esc_html( (string) $id ) );
			continue;
		}
		if ( empty( $book['categories'] ) ) {
			++$empty_cat;
			printf(
				"Dropped (no category): %s / %s / %s\n",
				esc_html( (string) $id ),
				esc_html( $book['isbn'] ),
				esc_html( $book['title'] ?? '' )
			);
			continue;
		}
		$book['checksum'] = md5( wp_json_encode( $book ) );
		$clean[ $id ]     = $book;
	}
	printf( "Empty - ISBN: %d / Categories: %d\n", intval( $empty_isbn ), intval( $empty_cat ) );

	return array(
		'books' => $clean,
		'isbns' => array_keys( $seen_isbns ),
		'stats' => array(
			'rows'               => count( $import ),
			'books'              => count( $clean ),
			'seen_isbns'         => count( $seen_isbns ),
			'dropped_empty_isbn' => $empty_isbn,
			'dropped_empty_cat'  => $empty_cat,
			'rejected_publisher' => $rejected_publisher,
			'rejected_isbn'      => $rejected_isbn,
		),
	);
}

/**
 * Compare two feed change-timestamps.
 *
 * The feed sends these as fixed-width 'YYYYMMDDHHMMSSmmm' strings. They are compared as strings
 * on purpose: at 17 digits a numeric comparison goes through float and loses the last digits.
 *
 * @param mixed $candidate The timestamp to test.
 * @param mixed $current   The timestamp to beat.
 * @return bool True when $candidate is newer than $current.
 */
function newer_timestamp( $candidate, $current ): bool {
	return strcmp( (string) $candidate, (string) $current ) > 0;
}

/**
 * Add a spearate version of the book that can be added to the book object.
 *
 * @param mixed $isbn ISBN of book version.
 * @param mixed $row Book data.
 * @return array
 */
function add_version( $isbn, $row ) {
	// 'asu' is a code and a label separated by a space, but the label can be missing.
	$asu = explode( ' ', (string) ( $row['asu'] ?? '' ), 2 );

	return array(
		'isbn'       => $isbn,
		'tuotemuoto' => $row['tuotemuoto'],
		'tyyppi'     => $row['tyyppi'],
		'pages'      => $row['laajus_sivua'],
		'asu_code'   => $asu[0] ?? '',
		'asu_text'   => $asu[1] ?? '',
	);
}

/**
 * Add a book to the list.
 *
 * @param array  $row Book data.
 * @param string $isbn ISBN of book.
 * @param array  $categories Categories of book.
 * @param array  $versions Versions of book.
 * @param mixed  $timestamp Newest change timestamp seen so far for this book.
 * @return array
 */
function add_book( $row, $isbn, $categories = array(), $versions = array(), $timestamp = 0 ) {
	$thema = array();
	foreach ( array( $row['thema_1'], $row['thema_2'], $row['thema_3'] ) as $item ) {
		if ( preg_match( '/([A-Z]+) (.*)/', $item, $match ) ) {
			$thema[ $match[1] ] = $match[2];
		}
	}

	return array(
		'isbn'           => $isbn,
		'title'          => wp_strip_all_tags( $row['onix_tuotenimi'] ),
		'sub_title'      => wp_strip_all_tags( $row['alaotsikko'] ),
		'content'        => $row['markkinointiteksti'],
		'authors'        => parse_list( $row['kirjantekija'] ),
		'kuvittaja'      => parse_list( $row['kuvittaja'] ),
		'suomentaja'     => parse_list( $row['suomentaja'] ),
		'toimittaja'     => parse_list( $row['toimittaja'] ),
		'categories'     => $categories,
		'tulosyksikko'   => $row['tulosyksikko'] ?? 'otava',
		'alkuteos'       => $row['alkuteos'],
		'kirjastoluokka' => $row['kirjastoluokka'],
		'sarja'          => $row['sarja'] ?? '',
		'kausi'          => $row['kausi'],
		'dates'          => array(
			'ensimmainen'      => $row['ensimmainenilmestymispvm'],
			'vvvvkk'           => $row['ilmestymis_vvvvkk'] . '01',
			'ilmestymis'       => $row['ilmestymispvm'],
			'embargo'          => $row['embargopvm'],
			'yleiseenmyyntiin' => $row['yleiseenmyyntiinpvm'],
		),
		'thema'          => $thema,
		'keywords'       => parse_list( $row['avainsanat'] ),
		'versions'       => $versions,
		// Keep whichever is newer: a non-master version may have been read before the master row.
		'timestamp'      => newer_timestamp( $row['muutosaikaleima'] ?? 0, $timestamp )
			? $row['muutosaikaleima']
			: $timestamp,
	);
}

/**
 * Parse a list of items from a string.
 *
 * @param string $field The string to parse.
 * @return array
 */
function parse_list( $field ) {
	$items = array();
	foreach ( explode( ';', $field ) as $raw ) {
		$item = trim( $raw );
		if ( ! empty( $item ) ) {
			$items[] = $item;
		}
	}

	return $items;
}

/**
 * Fetches the import file and parses it into array of objects.
 *
 * Failure and emptiness are different answers and must stay that way: an empty array here means
 * the publisher really sent us nothing, while a WP_Error means we never got a usable response.
 * Returning array() for both is what previously allowed a feed outage to empty the cache and
 * arm the delete runner.
 *
 * The body is streamed to IMPORT_RAW_DATA so a bad feed can be inspected after the fact.
 *
 * @return array|\WP_Error
 */
function get_import_data() {
	$url  = get_import_url_setting();
	$temp = IMPORT_RAW_DATA . '.tmp';

	$response = wp_safe_remote_get(
		$url,
		array(
			'timeout'  => apply_filters( 'otavabooks_fetch_timeout', 300 ),
			'stream'   => true,
			'filename' => $temp,
		)
	);

	if ( is_wp_error( $response ) ) {
		@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		return fetch_error( 'otava_fetch_failed', 'Feed request failed: ' . $response->get_error_message() );
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( 200 !== (int) $code ) {
		@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		return fetch_error( 'otava_fetch_failed', sprintf( 'Feed returned HTTP %s.', $code ) );
	}

	if ( ! file_exists( $temp ) || ! filesize( $temp ) ) {
		@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		return fetch_error( 'otava_fetch_failed', 'Feed returned an empty body.' );
	}

	// Keep the raw download for debugging, replacing the previous one only now that it is whole.
	// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
	rename( $temp, IMPORT_RAW_DATA );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$data = file_get_contents( IMPORT_RAW_DATA );
	if ( false === $data ) {
		return fetch_error( 'otava_fetch_failed', 'Could not read the downloaded feed.' );
	}

	// Filter file header data.
	$start = strpos( $data, '[' );
	if ( false === $start ) {
		return fetch_error( 'otava_parse_failed', 'Feed contains no JSON array.' );
	}
	if ( $start > 0 ) {
		$data = substr( $data, $start );
	}

	// Replace ¤¤¤¤¤ with newlines.
	$data = str_replace( '¤¤¤¤¤', '\\n', $data );

	// Parse JSON.
	$parsed_data = json_decode( $data, true, 512, JSON_INVALID_UTF8_SUBSTITUTE );
	if ( ! is_array( $parsed_data ) ) {
		return fetch_error( 'otava_parse_failed', 'Parsing error: ' . json_last_error_msg() );
	}

	return $parsed_data;
}

/**
 * Report a feed failure to the runner output and the log, and return it as a WP_Error.
 *
 * @param string $code    Error code.
 * @param string $message Human readable reason.
 * @return \WP_Error
 */
function fetch_error( string $code, string $message ): \WP_Error {
	echo esc_html( $message ), PHP_EOL;
	write_log( 'otavabooks import: ' . $message );

	return new \WP_Error( $code, $message );
}


if ( ! function_exists( 'write_log' ) ) {
	function write_log( $log ) {
		if ( is_array( $log ) || is_object( $log ) ) {
			error_log( print_r( $log, true ) );
		} else {
			error_log( $log );
		}
	}
}
