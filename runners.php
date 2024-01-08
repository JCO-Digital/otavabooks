<?php

namespace otavabooks;

/**
 * @param int $max Maximum imported items per run.
 */
function import_books( $data ): array {
	$max       = $data['max'] ?? 5;
	$imported  = 0;
	$skipped   = 0;
	$failed    = 0;
	$isbn      = get_isbn_list();
	$books     = get_json( IMPORT_BOOK_DATA );
	$checksums = get_json( IMPORT_CHECKSUM_DATA );

	foreach ( $books as $id => $book ) {
		if ( ! in_array( $book['isbn'], $isbn, true ) ) {
			$id = create_book_object( $book );
			if ( $id ) {
				echo "Imported: $book[title]<br/>\n";
				$checksums[ $book['isbn'] ] = $book['checksum'];
				++$imported;
			} elseif ( is_null( $id ) ) {
				++$skipped;
			} else {
				++$failed;
			}
		} else {
			++$skipped;
		}
		if ( $imported >= $max ) {
			$data['next_page'] = $data['page'] + 1;
			break;
		}
	}
	put_json( IMPORT_CHECKSUM_DATA, $checksums );
	if ( empty( $data['next_page'] ) ) {
		echo "\n";
		if ( $skipped ) {
			echo "Skipped $skipped books<br/>";
		}
		if ( $failed ) {
			echo "Failed to import $failed books<br/>";
		}
	}

	return $data;
}

/**
 * Update a number of book posts.
 *
 * @param array $data Data passed from runner handler.
 *
 * @return array
 */
function update_books( $data ) {
	$max       = $data['max'] ?? 5;
	$updated   = 0;
	$skipped   = 0;
	$failed    = 0;
	$isbn      = get_isbn_list();
	$books     = get_json( IMPORT_BOOK_DATA );
	$checksums = get_json( IMPORT_CHECKSUM_DATA );

	foreach ( $books as $book ) {
		$post_id = array_search( $book['isbn'], $isbn, true );
		if ( false !== $post_id && ( empty( $checksums[ $book['isbn'] ] ) || $checksums[ $book['isbn'] ] !== $book['checksum'] ) ) {
			$id = update_book_object( $post_id, $book );
			if ( false === $id ) {
				++$failed;
			} else {
				echo 'Updated: ' . esc_html( $book['title'] ) . "\n";
				$checksums[ $book['isbn'] ] = $book['checksum'];
				++$updated;
			}
		} else {
			++$skipped;
		}
		if ( $updated >= $max ) {
			$data['next_page'] = $data['page'] + 1;
			break;
		}
	}
	put_json( IMPORT_CHECKSUM_DATA, $checksums );

	$data['return'] = array(
		'status' => 'Skipped ' . esc_html( $skipped ) . ' of ' . count( $books ) . ' books',
	);

	if ( empty( $data['next_page'] ) ) {
		echo "\n";
		if ( $skipped ) {
			echo 'Skipped ' . esc_html( $skipped ) . " books\n";
		}
		if ( $failed ) {
			echo 'Failed to update ' . esc_html( $failed ) . " books\n";
		}
	}

	return $data;
}

/**
 * 
 *
 * @param string $isbn 
 * @return void 
 */
function update_book( string $isbn ) {
	$isbns     = get_isbn_list();
	$books     = get_json( IMPORT_BOOK_DATA );
	$checksums = get_json( IMPORT_CHECKSUM_DATA );
	foreach ( $books as $book ) {
		if ( $book['isbn'] === $isbn ) {
			$post_id = array_search( $book['isbn'], $isbns, true );
			if ( false !== $post_id ) {
				$id = update_book_object( $post_id, $book );
				if ( false === $id ) {
					echo 'Failed';
				} else {
					echo "Updated: $book[title]<br/>\n";
					$checksums[ $book['isbn'] ] = $book['checksum'];
				}
			}
		}
	}
	put_json( IMPORT_CHECKSUM_DATA, $checksums );
}

function update_tulossa( $data ) {
	$cleaned = clean_tulossa();
	echo 'Cleaned terms from ' . esc_html( $cleaned ) . " books.\n";
	$set = set_tulossa();
	echo 'Set terms to ' . esc_html( $set ) . " books.\n";

	return $data;
}

function delete_books( $data ) {
	$max     = $data['max'] ?? 25;
	$deleted = 0;
	$isbns   = get_isbn_list();
	$books   = get_json( IMPORT_BOOK_DATA );
	$feed    = array();
	foreach ( $books as $book ) {
		$feed[] = $book['isbn'];
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
				$data['next_page'] = $data['page'] + 1;
				break;
			}
		}
	}

	return $data;
}

function fetch_book_data( $data ) {
	$books = array();
	$books = make_book_list();
	$json  = wp_json_encode( $books );
	file_put_contents( IMPORT_BOOK_DATA, $json );
	$timestamp = time();
	$text      = 'Books: ' . count( $books ) . ' Imported at ' . date( 'Y-m-d H:i:s', $timestamp );

	echo esc_html( $text );

	$data['return'] = array(
		'status' => $text,
	);

	return $data;
}
