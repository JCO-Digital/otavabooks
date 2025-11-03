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
 * @param int    $post_id The ID of the post to update the 'kirjailija' field for.
 * @param array  $authors An array of author names to match against the database.
 * @param array  $tags    An optional array to which all author names are added. Passed by reference.
 *                        Defaults to an empty array.
 * @param string $field The field name to update in the post. Defaults to 'kirjailija'.
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
	foreach ( $authors as $kt ) {
		$author          = parse_name( $kt );
		$tags[]          = $author;
		$match_authors[] = $author;
	}
	$linked = array();
	foreach ( $GLOBALS['author_list'] as $id => $names ) {
		$miss = false;
		foreach ( $names as $name ) {
			if ( in_array( $name, $match_authors, true ) ) {
				echo "Match author ({$field}): {$name}\n";
			} else {
				$miss = true;
			}
		}
		if ( ! $miss ) {
			// Save the ID:s for linking.
			$linked[ $id ] = $names;
		}
	}

	$unmatched    = array();
	$linked_array = array();
	$remaining    = count( $match_authors );
	// Use this instead of foreach because we want to modify the array.
	while ( $remaining > 0 ) {
		// Shift the first element off the array.
		$author = array_shift( $match_authors );
		// Find the longest match for the author.
		$match_length = 0;
		$match_id     = 0;
		foreach ( $linked as $id => $names ) {
			if ( in_array( $author, $names, true ) ) {
				if ( count( $names ) > $match_length ) {
					$match_length = count( $names );
					$match_id     = $id;
				}
			}
		}
		if ( $match_id ) {
			$linked_array[] = $match_id;
			// If more than one match, remove the others from the array.
			if ( $match_length > 1 ) {
				foreach ( $linked[ $match_id ] as $name ) {
					if ( $name !== $author ) {
						$key = array_search( $name, $match_authors, true );
						if ( false !== $key ) {
							unset( $match_authors[ $key ] );
						}
					}
				}
			}
		} else {
			$unmatched[] = $author;
		}
		$remaining = count( $match_authors );
	}
	update_field( $field, $linked_array, $post_id );

	return $unmatched;
}

/**
 * Retrieves a list of authors from the database, organized by author ID.
 *
 * This function fetches all authors from the database using the get_authors() function.
 * For each author, it splits the 'name_index' field into individual names using a regular
 * expression that matches ' - ', ' ja ', or ' & ' as separators. Each name is parsed
 * using the parse_name() function to ensure consistent formatting. Duplicate names are
 * removed, and the resulting array of names is sorted and associated with the author's ID.
 *
 * @return array An associative array where the keys are author IDs and the values are arrays of parsed author names.
 */
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


/**
 * Retrieves all authors from the database.
 *
 * This function queries the WordPress database for all posts of the type defined by
 * IMPORT_AUTHOR_TYPE. It joins the posts table with the postmeta table to retrieve
 * the 'sukunimi_etunimi' meta value for each author, which is used as the 'name_index'.
 * The results are returned as an array of associative arrays, each representing an author.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @return array An array of associative arrays, each containing author post data and the 'name_index' field.
 */
function get_authors() {
	global $wpdb;
	$tablename = $wpdb->prefix . 'posts';
	$tablemeta = $wpdb->prefix . 'postmeta';
	$sql       = "SELECT post.*, meta.meta_value as name_index FROM $tablename AS post LEFT JOIN $tablemeta AS meta on post.ID = meta.post_id AND meta.meta_key = 'sukunimi_etunimi' WHERE post.post_type = '" . IMPORT_AUTHOR_TYPE . "' ORDER BY post.post_date DESC";

	return $wpdb->get_results( $sql, ARRAY_A );
}
