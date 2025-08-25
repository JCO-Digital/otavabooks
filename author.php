<?php

namespace otavabooks;

/**
 * Matches authors from a given array of author names to existing authors in the database.
 *
 * This function attempts to find matching authors in the database based on the provided
 * author names. It updates the 'kirjailija' field (using `update_field`) of a given post
 * with the IDs of the matched authors. It also returns an array of author names that
 * were not found in the database.
 *
 * @param int   $post_id The ID of the post to update the 'kirjailija' field for.
 * @param array $authors An array of author names to match against the database.
 * @param array $tags    An optional array to which all author names are added. Passed by reference.
 *                       Defaults to an empty array.
 *
 * @return array An array of author names that were not found in the database.
 */
function match_authors( int $post_id, array $authors, array &$tags = array(), string $field = 'kirjailija' ) {
	if ( empty( $authors ) ) {
		return array();
	}

	if ( empty( $GLOBALS['author_list'] ) ) {
		$GLOBALS['author_list'] = get_author_list();
	}

	$match_authors = array();
	$unmatched     = array();
	foreach ( $authors as $kt ) {
		$author          = parse_name( $kt );
		$tags[]          = $author;
		$match_authors[] = $author;
		$unmatched[]     = $author;
	}
	$linked = array();
	foreach ( $GLOBALS['author_list'] as $id => $names ) {
		$match = 0;
		foreach ( $names as $name ) {
			if ( in_array( $name, $match_authors, true ) ) {
				echo "Match author ({$field}): {$name}\n";
				++$match;
			}
		}
		if ( $match === count( $names ) ) {
			// Save the ID:s for linking.
			$linked[ $id ] = $match;
			// Remove names from unmatched.
			foreach ( $names as $name ) {
				$key = array_search( $name, $unmatched, true );
				if ( false !== $key ) {
					unset( $unmatched[ $key ] );
				}
			}
		}
	}

	arsort( $linked );
	$linked_array = array();
	foreach ( $linked as $id => $count ) {
		$linked_array[] = "$id";
	}
	update_field( $field, $linked_array, $post_id );

	return $unmatched;
}

function get_author_list() {
	$authors = array();
	foreach ( get_authors() as $author ) {
		$names = array();
		foreach ( preg_split( '/ (-|ja|&) /', $author['name_index'] ) as $name ) {
			$parsed_name = parse_name( $name );
			if ( ! in_array( $parsed_name, $names, true ) ) {
				$names[] = $parsed_name;
			}
		}
		if ( ! empty( $names ) ) {
			sort( $names );
			$authors[ $author['ID'] ] = $names;
		}
	}

	return $authors;
}

function get_authors() {
	global $wpdb;
	$tablename = $wpdb->prefix . 'posts';
	$tablemeta = $wpdb->prefix . 'postmeta';
	$sql       = "SELECT post.*, meta.meta_value as name_index FROM $tablename AS post LEFT JOIN $tablemeta AS meta on post.ID = meta.post_id AND meta.meta_key = 'sukunimi_etunimi' WHERE post.post_type = '" . IMPORT_AUTHOR_TYPE . "' ORDER BY post.post_date DESC";

	return $wpdb->get_results( $sql, ARRAY_A );
}
