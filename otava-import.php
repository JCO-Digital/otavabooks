<?php

namespace otavabooks;

use Nicebooks\Isbn\Exception\InvalidIsbnException;
use Nicebooks\Isbn\Isbn;
use Nicebooks\Isbn\IsbnTools;

/**
 * Generate the list of books.
 *
 * @return array
 */
function make_book_list() {
	$tools      = new IsbnTools();
	$data       = array();
	$publishers = get_publishers_setting();
	foreach ( get_import_data() as $row ) {
		if ( isset( $row->kantanumero ) && in_array( strtolower( $row->tulosyksikko ), $publishers, true ) && $tools->isValidIsbn( $row->isbn ) ) {
			try {
				$isbn   = Isbn::of( $row->isbn );
				$master = 'Kyllä' === $row->master_tuote;
				$id     = $row->kantanumero;
				// Get the book, or create an empty object if not exists.
				$book = $data[ $id ] ?? array(
						'categories' => array(),
						'versions'   => array(),
						'timestamp'  => 0,
					);

				if ( $master ) {
					// If product is master, create the real book object.
					$book = add_book( $row, $isbn->format(), $book['categories'], $book['versions'], $book['timestamp'] );
				} else {
					// If not master, check if timestamp needs to be updated.
					if ( $row->muutosaikaleima > $book['timestamp'] ) {
						$book['timestamp'] = $row->muutosaikaleima;
					}
				}

				// Push current product onto version stack.
				array_push( $book['categories'], get_otava_cat( $row->tuoteryhma ) );
				// Push current product onto version stack.
				array_push( $book['versions'], add_version( $isbn->format(), $row ) );
				// Write/overwrite book into array.
				$data[ $id ] = $book;
			} catch ( InvalidIsbnException $exception ) {
				echo "error";
				write_log( $exception->getMessage() );
			}
		}
	}
	foreach ( $data as $id => $book ) {
		if ( empty( $book['isbn'] ) ) {
			unset( $data[ $id ] );
		}
	}

	return $data;
}

function add_version( $isbn, $row ) {
	$asu = explode( ' ', $row->asu, 2 );

	return array(
		'isbn'       => $isbn,
		'tuotemuoto' => $row->tuotemuoto,
		'tyyppi'     => $row->tyyppi,
		'pages'      => $row->laajus_sivua,
		'asu_code'   => $asu[0],
		'asu_text'   => $asu[1],
	);
}

function add_book( $row, $isbn, $categories = array(), $versions = array(), $timestamp = 0 ) {
	$thema = array();

	return array(
		'isbn'           => $isbn,
		'title'          => wp_strip_all_tags( $row->onix_tuotenimi ),
		'sub_title'      => wp_strip_all_tags( $row->alaotsikko ),
		'content'        => $row->markkinointiteksti,
		'ilmestymis'     => $row->ilmestymis_vvvvkk . '01',
		'authors'        => parse_list( $row->kirjantekija ),
		'kuvittaja'      => parse_list( $row->kuvittaja ),
		'suomentaja'     => parse_list( $row->suomentaja ),
		'toimittaja'     => parse_list( $row->toimittaja ),
		'categories'     => $categories,
		'tulosyksikko'   => $row->tulosyksikko ?? 'otava',
		'alkuteos'       => $row->alkuteos,
		'kirjastoluokka' => $row->kirjastoluokka,
		'sarja'          => $row->sarja ?? '',
		'kausi'          => $row->kausi,
		'dates'          => array(
			'ilmestymis'       => $row->ilmestymispvm,
			'embargo'          => $row->embargopvm,
			'yleiseenmyyntiin' => $row->yleiseenmyyntiinpvm,
		),
		'thema'          => $thema,
		'keywords'       => parse_list( $row->avainsanat ),
		'versions'       => $versions,
		'timestamp'      => $row->muutosaikaleima,
	);
}

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
 * @return array|mixed|object
 */
function get_import_data() {
	$data  = file_get_contents( get_import_url_setting() );
	$start = strpos( $data, '[' );
	if ( $start > 0 ) {
		$data = substr( $data, $start );
	}
	if ( ! empty( $data ) ) {
		// Replace ¤¤¤¤¤ with newlines.
		$data = str_replace( '¤¤¤¤¤', '\\n', $data );

		// Parse JSON.
		$parsed_data = json_decode( $data, null, 512, JSON_INVALID_UTF8_SUBSTITUTE );
		if ( ! empty( $parsed_data ) && is_array( $parsed_data ) ) {
			return $parsed_data;
		}
		echo 'Parsing error: ', json_last_error_msg(), PHP_EOL, PHP_EOL;
		write_log( 'Parsing failed' );
	}

	return array();
}

/**
 * @param int $max Maximum imported items per run.
 */
function import_books( $max = 1 ) {
	$imported   = 0;
	$skipped    = 0;
	$failed     = 0;
	$isbn       = get_isbn_list();
	$books      = get_json( IMPORT_BOOK_DATA );
	$timestamps = get_json( IMPORT_TIMESTAMP_DATA );

	foreach ( $books as $id => $book ) {
		if ( ! in_array( $book['isbn'], $isbn, true ) ) {
			$id = create_book_object( $book );
			if ( $id ) {
				echo "Imported: $book[title]<br/>\n";
				$timestamps[ $book['isbn'] ] = $book['timestamp'];
				$imported ++;
			} elseif ( is_null( $id ) ) {
				$skipped ++;
			} else {
				$failed ++;
			}
		} else {
			$skipped ++;
		}
		if ( $imported >= $max ) {
			break;
		}
	}
	put_json( IMPORT_TIMESTAMP_DATA, $timestamps );
	echo "<br/>";
	if ( $skipped ) {
		echo "Skipped $skipped books<br/>";
	}
	if ( $failed ) {
		echo "Failed to import $failed books<br/>";
	}

	return $imported;
}

function update_books( $max = 1 ) {
	$updated    = 0;
	$skipped    = 0;
	$failed     = 0;
	$isbn       = get_isbn_list();
	$books      = get_json( IMPORT_BOOK_DATA );
	$timestamps = get_json( IMPORT_TIMESTAMP_DATA );

	foreach ( $books as $book ) {
		$post_id = array_search( $book['isbn'], $isbn, true );
		if ( false !== $post_id && ( empty( $timestamps[ $book['isbn'] ] ) || $timestamps[ $book['isbn'] ] !== $book['timestamp'] ) ) {
			$id = update_book_object( $post_id, $book );
			if ( false === $id ) {
				$failed ++;
			} else {
				echo "Updated: $book[title]<br/>\n";
				$timestamps[ $book['isbn'] ] = $book['timestamp'];
				$updated ++;
			}
		} else {
			$skipped ++;
		}
		if ( $updated >= $max ) {
			break;
		}
	}
	put_json( IMPORT_TIMESTAMP_DATA, $timestamps );
	echo "<br/>";
	if ( $skipped ) {
		echo "Skipped $skipped books<br/>";
	}
	if ( $failed ) {
		echo "Failed to update $failed books<br/>";
	}

	return $updated;
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
