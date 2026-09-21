<?php

namespace otavabooks;

/**
 * Creates the book as a post, and adds the meta fields to it.
 *
 * @param array $item The json data from the import.
 * @param array $tags Optional extra tags.
 *
 * @return false|int|\WP_Error|null Output to pass to the user.
 */
function create_book_object( array $item, array $tags = array() ) {
	if ( ! empty( $item['isbn'] ) ) {
		$new_book = array(
			'post_type'    => IMPORT_POST_TYPE,
			'post_title'   => $item['title'],
			'post_content' => $item['content'],
			'post_status'  => 'publish',
			'post_author'  => get_author_setting(),
		);
		$date     = parse_dates( $new_book, $item['dates'] );

		if ( ! empty( $date ) ) {
			// Insert the post into the database. $wp_error = true so failures say why.
			$post_id = wp_insert_post( $new_book, true );
			if ( is_wp_error( $post_id ) ) {
				printf( "Failed to insert %s: %s\n", esc_html( $item['isbn'] ), esc_html( $post_id->get_error_message() ) );
				return false;
			}
			if ( ! empty( $post_id ) ) {
				update_post_meta( $post_id, 'isbn', trim( $item['isbn'] ) );
				// Mark provenance, so hand-made books can be told apart from imported ones.
				update_post_meta( $post_id, '_otava_imported', 1 );
				set_ilmestymis( $post_id, $date );
				update_book_meta( $post_id, $item, $date );
				update_book_versions( $post_id, $item['versions'] );

				return $post_id;
			}
			return false;
		}
	}

	return null;
}

/**
 * Updates an existing book post and its meta fields.
 *
 * This function updates the post with the provided ID using the data from the import array.
 * It sets the post title, content, and status, updates the post date if a valid date is found,
 * and updates associated meta fields and versions. If the date is more than half a year in the
 * future, the post status is set to 'draft'.
 *
 * @param int   $id   The post ID to update.
 * @param array $item The JSON data from the import.
 * @param array $tags Optional extra tags.
 *
 * @return false|int|\WP_Error The updated post ID on success, false on failure, or WP_Error on error.
 */
function update_book_object( int $id, array $item, array $tags = array() ) {
	$update_book = array(
		'ID'           => $id,
		'post_title'   => $item['title'],
		'post_content' => $item['content'],
		'post_status'  => 'publish',
	);
	$date        = parse_dates( $update_book, $item['dates'] );

	if ( ! empty( $date ) ) {
		$post_id = wp_update_post( $update_book, true );
		if ( is_wp_error( $post_id ) ) {
			printf( "Failed to update %d: %s\n", (int) $id, esc_html( $post_id->get_error_message() ) );
			return false;
		}
		if ( ! empty( $post_id ) ) {
			/*
			 * Re-assert the ISBN. A post whose meta was lost, or stored in an older format,
			 * would otherwise never be repaired -- the importer would create a duplicate and
			 * the delete runner would then remove this original.
			 */
			update_post_meta( $post_id, 'isbn', trim( $item['isbn'] ) );
			// The book is in the feed, so clear any pending-removal markers.
			delete_post_meta( $post_id, OTAVABOOKS_MISSING_SINCE_META );
			delete_post_meta( $post_id, OTAVABOOKS_MISSING_RUNS_META );
			set_ilmestymis( $post_id, $date );
			update_book_meta( $post_id, $item, $date );
			update_book_versions( $post_id, $item['versions'] );

			return $post_id;
		}
	}

	return false;
}

/**
 * Parses date information from the provided array and formats it for use in a WordPress post.
 *
 * This function attempts to extract a valid date from the 'dates' array within the input item.
 * It prioritizes 'ensimmainen', then 'ilmestymis', and finally 'vvvvkk' keys. If a valid
 * 8-character date string (YYYYMMDD) is found, it's converted to 'YYYY-MM-DD' format and
 * used to set the post's 'post_date'.  If the resulting date is more than half a year in the
 * future, the post status is set to 'draft'.
 *
 * @param array $post  Reference to the post array to be modified.  The 'post_date' and 'post_status' keys may be updated.
 * @param array $dates An array containing potential date strings, with keys like 'ensimmainen', 'ilmestymis', and 'vvvvkk'.
 *
 * @return string|false The formatted date string ('YYYY-MM-DD') if a valid date is found and processed; otherwise, false.
 */
function parse_dates( array &$post, array $dates ) {
	// Do the date magic.
	$date = $dates['ensimmainen'];
	if ( empty( $date ) ) {
		$date = $dates['ilmestymis'];
	}
	if ( empty( $date ) ) {
		$date = $dates['vvvvkk'];
	}

	if ( strlen( $date ) === 8 ) {
		$date_string       = substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-' . substr( $date, 6, 2 );
		$post['post_date'] = $date_string . ' 00:00:00';
		if ( ( strtotime( $date_string ) - time() ) > 15768000 ) { // More than half a year in the future.
			$post['post_status'] = 'draft';
		}
		return $date_string;
	}
	return false;
}

/**
 * Sets the 'ilmestymispvm' (release date) custom field for a post.
 *
 * $date is already 'YYYY-MM-DD' (parse_dates() formats it), which is also what the ACF field and
 * set_tulossa()'s str_to_date( '%Y-%m-%d' ) expect, so it is stored as-is.
 *
 * @param int    $post_id The ID of the post to update.
 * @param string $date    The release date in 'YYYY-MM-DD' format.
 *
 * @return void
 */
function set_ilmestymis( $post_id, $date ): void {
	update_field( 'ilmestymispvm', $date, $post_id );
}

/**
 * Updates the book's meta fields, categories, tags, and taxonomies.
 *
 * Fields and terms are written unconditionally, including when the feed value is empty, so that
 * a value removed upstream is also removed here instead of lingering forever.
 *
 * @param int    $post_id The ID of the post to update.
 * @param array  $item    The JSON data containing the book's information.
 * @param string $date    The release date in 'YYYY-MM-DD' format, as returned by parse_dates().
 */
function update_book_meta( int $post_id, array $item, string $date = '' ) {
	// Get the categories.
	$tags       = array();
	$categories = array();

	if ( ! empty( $item['categories'] ) ) {
		foreach ( array_unique( $item['categories'] ) as $category ) {
			$categories[] = $category;
			$tags[]       = $category;
		}
	}

	update_field( 'alkuteos', $item['alkuteos'] ?? '', $post_id );
	update_field( 'kirjastoluokka', $item['kirjastoluokka'] ?? '', $post_id );

	$kuvittaja = array();
	foreach ( $item['kuvittaja'] ?? array() as $name ) {
		$kuvittaja[] = parse_name( $name );
	}
	match_authors( $post_id, $item['kuvittaja'] ?? array(), $tags, 'kuvittaja' );
	wp_set_post_terms( $post_id, $kuvittaja, 'otava_kuvittaja', false );

	$suomentaja = array();
	foreach ( $item['suomentaja'] ?? array() as $name ) {
		$parsed_name  = parse_name( $name );
		$suomentaja[] = $parsed_name;
		$tags[]       = $parsed_name;
	}
	wp_set_post_terms( $post_id, $suomentaja, 'otava_kaantaja', false );

	$sarja = $item['sarja'] ?? '';
	if ( is_array( $sarja ) ) {
		$sarja = $sarja[0] ?? '';
	}
	if ( '' !== $sarja ) {
		wp_set_post_terms( $post_id, array( $sarja ), 'otava_sarja', false );
		$tags[] = $sarja;
	} else {
		wp_set_post_terms( $post_id, array(), 'otava_sarja', false );
	}

	$asu = array();
	foreach ( $item['versions'] as $version ) {
		if ( '' !== $version['asu_text'] && ! in_array( $version['asu_text'], $asu, true ) ) {
			$asu[] = $version['asu_text'];
		}
	}
	wp_set_post_terms( $post_id, $asu, 'otava_sidosasu', false );

	/*
	 * Otava's own titles carry an empty 'tulosyksikko' in the feed (the vast majority of rows),
	 * so without this mapping they would be the only books with no publisher term at all.
	 */
	$julkaisija = '' !== ( $item['tulosyksikko'] ?? '' ) ? $item['tulosyksikko'] : 'Otava';
	wp_set_post_terms( $post_id, array( $julkaisija ), 'otava_julkaisija', false );
	$tags[] = $julkaisija;

	$toimittaja = match_authors( $post_id, $item['authors'], $tags );
	foreach ( $item['toimittaja'] as $name ) {
		$parsed_name  = parse_name( $name );
		$toimittaja[] = $parsed_name;
		$tags[]       = $parsed_name;
	}

	/*
	 * otava_kategoria is replaced wholesale here, which drops the 'tulossa' term. set_tulossa()
	 * only runs at the very end of an import run, so without re-adding it inline every upcoming
	 * book would drop out of the "tulossa" listings for the length of the run.
	 */
	if ( '' !== $date && strtotime( $date ) > time() ) {
		$categories[] = 'tulossa';
	}

	wp_set_post_terms( $post_id, $toimittaja, 'otava_toimittaja', false );
	wp_set_post_terms( $post_id, $tags, 'post_tag', false );
	wp_set_post_terms( $post_id, $categories, 'otava_kategoria', false );
	if ( ! empty( $item['kausi'] ) && get_import_catalog() ) {
		wp_set_post_terms( $post_id, $item['kausi'], 'otava_katalogi', false );
	}
}

/**
 * Update the versions for the book.
 *
 * @param $post_id  - the post id.
 * @param $versions - The json data from the import.
 */
function update_book_versions( $post_id, $versions ) {
	delete_field( 'versions', $post_id );
	foreach ( $versions as $version ) {
		add_row( 'versions', $version, $post_id );
	}
}

/**
 * Post statuses the delete runner is allowed to consider.
 *
 * Deliberately excludes 'trash' and 'auto-draft'. Keeping trashed posts in this list is what
 * would let a later page re-visit a post the previous page trashed -- and a second
 * wp_delete_post() on an already-trashed post destroys it for good.
 *
 * Drafts belong here: books released more than half a year out are legitimately drafts
 * (see parse_dates()).
 *
 * @return string[]
 */
function get_managed_statuses(): array {
	return array( 'publish', 'draft', 'pending', 'private', 'future' );
}

/**
 * Map of post ID => ISBN for every book the importer manages.
 *
 * Books with no ISBN meta are omitted. They cannot be matched against the feed, so treating
 * them as "missing from the feed" would delete every hand-made book on the site. Call
 * get_books_without_isbn() to report on them instead.
 *
 * @param string[]|null $statuses Post statuses to include. Defaults to get_managed_statuses().
 * @return array<int,string>
 */
function get_isbn_list( ?array $statuses = null ) {
	$isbn = array();
	foreach ( get_books( $statuses ) as $book ) {
		if ( '' === trim( (string) $book['isbn'] ) ) {
			continue;
		}
		$isbn[ (int) $book['ID'] ] = $book['isbn'];
	}

	return $isbn;
}

/**
 * Books that carry no ISBN meta, so the importer cannot match them against the feed.
 *
 * @param string[]|null $statuses Post statuses to include. Defaults to get_managed_statuses().
 * @return array
 */
function get_books_without_isbn( ?array $statuses = null ): array {
	$books = array();
	foreach ( get_books( $statuses ) as $book ) {
		if ( '' === trim( (string) $book['isbn'] ) ) {
			$books[] = $book;
		}
	}

	return $books;
}

/**
 * Fetch book posts joined to their ISBN meta.
 *
 * @param string[]|null $statuses Post statuses to include. Defaults to get_managed_statuses().
 *                                Pass an empty array for every status, including trash.
 * @return array
 */
function get_books( ?array $statuses = null ) {
	global $wpdb;

	if ( null === $statuses ) {
		$statuses = get_managed_statuses();
	}

	$where  = 'post.post_type = %s';
	$params = array( IMPORT_POST_TYPE );

	if ( ! empty( $statuses ) ) {
		$where   .= ' AND post.post_status IN ( ' . implode( ', ', array_fill( 0, count( $statuses ), '%s' ) ) . ' )';
		$params   = array_merge( $params, array_values( $statuses ) );
	}

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is built from literals and placeholders above.
	$sql = $wpdb->prepare(
		"SELECT post.ID, post.post_title, post.post_status, post.post_date, meta.meta_value as isbn
		FROM {$wpdb->posts} AS post
		LEFT JOIN {$wpdb->postmeta} AS meta
			ON post.ID = meta.post_id AND meta.meta_key = 'isbn'
		WHERE {$where}
		ORDER BY post.post_date DESC",
		$params
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	return $wpdb->get_results( $sql, ARRAY_A );
}

/**
 * Searches the locally stored categories.json for matches to the provided category.
 *
 * @param string $raw               The category to be searched.
 * @param string $$default_category The default category to return, if a match isn't found.
 *
 * @return string  The matched category, or the default if no match is found.
 */
function get_otava_cat( $raw, $default_category = '' ) {
	if ( get_disable_categories_setting() ) {
		return $raw;
	}
	global $otava_loaded_categories;
	if ( empty( $otava_loaded_categories ) ) {
		echo "Loading categories.\n";
		$otava_loaded_categories = get_json( __DIR__ . '/categories.json' );
		printf( "Loaded %d categories.\n", count( $otava_loaded_categories ) );
	}

	$orig     = trim( $raw );
	$category = $default_category;
	$found    = false;
	foreach ( $otava_loaded_categories as $cat => $search ) {
		if ( in_array( $orig, $search ) ) {
			$category = $cat;
			$found    = true;
			break;
		}
	}
	if ( ! $found ) {
		$needle = preg_replace( '/[^a-zåäö]+/u', '', mb_strtolower( $orig ) );
		$dist   = ceil( strlen( $needle ) / 16 );
		foreach ( $otava_loaded_categories as $cat => $search ) {
			foreach ( $search as $item ) {
				if ( levenshtein( $needle, preg_replace( '/[^a-zåäö]+/u', '', mb_strtolower( $item ) ) ) <= $dist ) {
					$category = $cat;
					break 2;
				}
			}
		}
	}

	return $category;
}

/**
 * Changes the name into firstname lastname format.
 *
 * @param string $name Name to process.
 *
 * @return string
 */
function parse_name( string $name ): string {
	$parts = explode( ',', trim( $name, ' ,' ), 2 );
	if ( count( $parts ) > 1 ) {
		return trim( $parts[1] ) . ' ' . trim( $parts[0] );
	}

	return $name;
}

function set_tulossa() {
	global $wpdb;
	$sql = "
		SELECT
			post.ID,
			post.post_title,
			ilmestymis.meta_value as pvm
		FROM {$wpdb->prefix}posts as post
		LEFT JOIN {$wpdb->prefix}postmeta as ilmestymis
		ON post.ID = ilmestymis.post_id
		AND ilmestymis.meta_key = 'ilmestymispvm'
		WHERE post.post_type = 'otava_book'
		AND post.post_status = 'publish'
		AND str_to_date(ilmestymis.meta_value, '%Y-%m-%d') > now()
		";

	$set = 0;
	foreach ( $wpdb->get_results( $sql, ARRAY_A ) as $row ) {
		wp_set_post_terms( $row['ID'], 'tulossa', 'otava_kategoria', true );
		++$set;
	}

	return $set;
}

function clean_tulossa() {
	$args    = array(
		'post_type'      => IMPORT_POST_TYPE,
		'posts_per_page' => -1,
		'tax_query'      => array(
			array(
				'taxonomy' => 'otava_kategoria',
				'field'    => 'slug',
				'terms'    => 'tulossa',
			),
		),
	);
	$cleaned = 0;
	foreach ( get_posts( $args ) as $post ) {
		$date = get_field( 'ilmestymispvm', $post->ID );
		if ( strtotime( $date ) < time() ) {
			++$cleaned;
			wp_remove_object_terms( $post->ID, 'tulossa', 'otava_kategoria' );
			echo esc_html( "Cleaned {$post->post_title}." );
		}
	}

	return $cleaned;
}
