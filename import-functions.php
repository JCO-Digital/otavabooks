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
	$tools = new IsbnTools();
	$data  = array();
	foreach ( get_import_data() as $row ) {
		if ( isset( $row->kantanumero) && in_array( strtolower( $row->tulosyksikkö ), IMPORT_PUBLISHERS, true ) && $tools->isValidIsbn( $row->isbn ) ) {
			try {
				$isbn    = Isbn::of( $row->isbn );
				$version = add_version( $isbn->to13()->format(), $row );
				$master  = 'Kyllä' === $row->master_tuote;
				$id      = $row->kantanumero;
				if ( $master ) {
					// If product is master.

					// Get the old placeholder, or create an empty object.
					$placeholder = $data[ $id ] ?? array(
							'versions'  => array(),
							'timestamp' => 0,
						);

					// Create the book object.
					$book = add_book( $row, $isbn->format(), $placeholder['versions'], $placeholder['timestamp'] );
				} else {
					// If not master, check if record exist, record can be product or placeholder.
					$book = $data[ $id ] ?? array(
							'versions'  => array(),
							'timestamp' => $row->muutosaikaleima,
						);
					if ( $row->muutosaikaleima > $book['timestamp'] ) {
						$book['timestamp'] = $row->muutosaikaleima;
					}
				}
				// Push current product onto version stack.
				array_push( $book['versions'], $version );
				// Write/overwrite book into array.
				$data[ $id ] = $book;
			} catch ( InvalidIsbnException $exception ) {
				echo "error";
				write_log( $exception->getMessage() );
			}
		}
	}

	return $data;
}

function add_version( $isbn, $row ) {
	return array(
		'isbn'       => $isbn,
		'tuotemuoto' => $row->tuotemuoto,
		'tyyppi'     => $row->tyyppi,
		'pages'      => $row->laajus_sivua,
		'asu'        => $row->asu,
	);
}

function add_book( $row, $isbn, $versions = array(), $timestamp = 0 ) {
	$thema = array();

	return array(
		'isbn'           => $isbn,
		'title'          => $row->onix_tuotenimi,
		'sub_title'      => $row->alaotsikko,
		'description'    => $row->markkinointiteksti,
		'ilmestymis'     => $row->ilmestymis_vvvvkk,
		'authors'        => parse_names( $row->kirjantekija ),
		'kuvittaja'      => parse_names( $row->kuvittaja ),
		'suomentaja'     => parse_names( $row->suomentaja ),
		'toimittaja'     => parse_names( $row->toimittaja ),
		'tuoteryhma'     => array( trim( $row->tuoteryhma ) ),
		'tulosyksikko'   => $row->tulosyksikkö,
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
		'keywords'       => $row->avainsanat,
		'versions'       => $versions,
		'timestamp'      => $row->muutosaikaleima,
	);
}

function parse_names( $field ) {
	$names = explode( ';', $field );

	return $names;
}

/**
 * Fetches the import file and parses it into array of objects.
 *
 * @return array|mixed|object
 */
function get_import_data() {
	$data = file_get_contents( IMPORT_FILE_PATH );
	//$data = str_replace( "\n", '', $body );
	//$data = str_replace( "\r", '', $data );

	if ( ! empty( $data ) ) {
		$parsed_data = json_decode( $data );
		if ( ! empty( $parsed_data ) && is_array( $parsed_data ) ) {
			return $parsed_data;
		}
		echo 'Parsing error: ', json_last_error_msg(), PHP_EOL, PHP_EOL;
		write_log( 'Parsing failed' );
	}

	return array();
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
