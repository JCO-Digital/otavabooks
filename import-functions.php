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
		if ( in_array( strtolower( $row->kustantamo ), IMPORT_PUBLISHERS, true ) && $tools->isValidIsbn( $row->isbn ) ) {
			try {
				$isbn    = Isbn::of( $row->isbn );
				$version = add_version( $isbn->to13()->format(), $row );
				$id      = search_match( $data, $row );
				if ( $id === false ) {
					$book = add_book( $row );
					array_push( $book['versions'], $version );
					$data[ $isbn->to13()->format() ] = $book;
				} else {
					$book = &$data[ $id ];
					array_push( $book['versions'], $version );
					if ( $row->muutosaikaleima > $book['timestamp'] ) {
						$book['timestamp'] = $row->muutosaikaleima;
					}
				}
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
		'isbn'                 => $isbn,
		'tuotemuoto'           => $row->tuotemuoto,
		'tuotemuodon_tarkenne' => $row->tuotemuodon_tarkenne,
		'pages'                => $row->laajus_sivua,
		'asu'                  => $row->asu,
	);
}

function add_book( $row ) {
	return array(
		'title'          => $row->onix_tuotenimi,
		'description'    => $row->markkinointiteksti,
		'ilmestymiskk'   => $row->ilmestymis_vvvvkk,
		'authors'        => explode( ';', $row->kirjantekija ),
		'kuvittaja'      => $row->kuvittaja,
		'suomentaja'     => $row->suomentaja,
		'toimittaja'     => $row->toimittaja,
		'tuoteryhma'     => array( trim( $row->tuoteryhma ) ),
		'kustantamo'     => $row->kustantamo,
		'alkuteos'       => $row->alkuteos,
		'kirjastoluokka' => $row->kirjastoluokka,
		'sarja'          => $row->sarja,
		'versions'       => array(),
		'timestamp'      => $row->muutosaikaleima,
	);
}

function search_match( $data, $row ) {
	// Temp function for demo purposes, until real feed.
	foreach ( $data as $id => $book ) {
		if ( strtolower( $book['title'] ) === strtolower( $row->onix_tuotenimi ) ) {
			$authors = explode( ';', $row->kirjantekija );
			foreach ( $authors as $author ) {
				if ( in_array( $author, $book['authors'], true ) ) {
					return $id;
				}
			}
		}
	}

	return false;
}

/**
 * Fetches the import file and parses it into array of objects.
 *
 * @return array|mixed|object
 */
function get_import_data() {
	$data = get_transient( IMPORT_TRANSIENT );
	if ( empty( $data ) ) {
		write_log( 'No transient, reading import file' );
		$response = wp_remote_get(
			IMPORT_FILE_PATH,
			array(
				'timeout'     => 5,
				'redirection' => 5,
				'httpversion' => '1.0',
				'user-agent'  => 'BookImporter/1.0; ' . home_url(),
				'blocking'    => true,
				'cookies'     => array(),
				'body'        => null,
				'compress'    => false,
				'decompress'  => true,
				'sslverify'   => true,
				'stream'      => false,
				'filename'    => null,
			)
		);

		if ( wp_remote_retrieve_response_code( $response ) === 200 ) {
			$body = wp_remote_retrieve_body( $response );
			$data = substr( $body, strpos( $body, '[' ) );
			set_transient( IMPORT_TRANSIENT, $data, 2 * 60 );
		}
	}
	if ( ! empty( $data ) ) {
		$parsed_data = json_decode( $data );
		if ( ! empty( $parsed_data ) && is_array( $parsed_data ) ) {
			return $parsed_data;
		}
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
