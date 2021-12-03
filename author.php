<?php

namespace otavabooks;

/**
 * Match Authors to author pages.
 *
 * @param int $post_id
 * @param string $authors
 * @param array $tags
 *
 * @return array
 */
function match_authors( int $post_id, array $authors, array &$tags = [] ) {
	if ( empty( $authors ) ) {
		return array();
	}

	if ( empty( $GLOBALS['author_list'] ) ) {
		$GLOBALS['author_list'] = get_author_list();
	}

	$match_authors = array();
	$toimittanut   = array();
	foreach ( $authors as $kt ) {
		$author          = parse_name( $kt );
		$tags[]          = $author;
		$match_authors[] = $author;
		$toimittanut[]   = $author;
	}
	$linked = array();
	foreach ( $GLOBALS['author_list'] as $id => $names ) {
		$match = true;
		foreach ( $names as $name ) {
			if ( ! in_array( $name, $match_authors, true ) ) {
				$match = false;
			}
		}
		if ( $match ) {
			// Save the ID:s for linking.
			$linked[ $id ] = count( $names );
			// Remove names from toimittanut.
			foreach ( $names as $name ) {
				$key = array_search( $name, $toimittanut, true );
				if ( false !== $key ) {
					unset( $toimittanut[ $key ] );
				}
			}
		}
	}

	arsort( $linked );
	$linked_array = array();
	foreach ( $linked as $id => $count ) {
		$linked_array[] = "$id";
	}
	update_field( 'kirjailija', $linked_array, $post_id );

	return $toimittanut;
}

function get_author_list() {
	$authors = array();
	foreach ( get_authors() as $author ) {
		$names = array();
		foreach ( preg_split( '/ (-|ja|&) /', $author['name_index'] ) as $name ) {
			$parts = explode( ',', trim( $name, ' ,' ), 2 );
			if ( ! empty( $parts[0] ) && ! empty( $parts[1] ) ) {
				$names[] = trim( $parts[1], ' ,' ) . ' ' . trim( $parts[0], ' ,' );
			} else {
				$parts = explode( ' ', trim( $name, ' ,' ), 2 );
				if ( count( $parts ) > 1 ) {
					$names[] = trim( $parts[0], ' ,' ) . ' ' . trim( $parts[1], ' ,' );
				}
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
