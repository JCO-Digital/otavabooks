<?php
/*
Plugin Name: Otava Kirjat
Plugin URI: http://otava.fi/
Description: CPT, ACF & sync / import functionality.
Version: 1.4.1
Author: JCO Digital
Author URI: http://jco.fi/
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin, like WordPress, is licensed under the GPL.
Use it to make something cool, have fun, and share what you've learned with others.
*/

namespace otavabooks;

require_once 'vendor/autoload.php';
require_once 'utility.php';

define( 'IMPORT_POST_TYPE', 'otava_book' );
define( 'IMPORT_AUTHOR_TYPE', 'otava_author' );
define( 'IMPORT_RAW_DATA', get_upload_dir() . '/raw_data.json' );
define( 'IMPORT_BOOK_DATA', get_upload_dir() . '/book_data.json' );
define( 'IMPORT_TIMESTAMP_DATA', get_upload_dir() . '/timestamp_data.json' );
define( 'IMPORT_ISBN_INDEX', get_upload_dir() . '/isbn_index.json' );
define( 'BOOK_COVER_DATA', get_upload_dir() . '/cover_data.json' );

/*
 * Safety limits for the import and the "Delete Old Books" runner.
 *
 * The delete runner used to treat "the feed list is empty" as "every book was withdrawn", so a
 * single failed fetch could wipe the catalogue. These thresholds, and the grace period below,
 * are what stop that. Each one is filterable at its call site.
 */

// A feed smaller than this is never believed, whatever the previous run saw.
define( 'OTAVABOOKS_FEED_MIN_BOOKS', 1000 );
// A feed that shrank to less than this share of the last good count is not written to disk.
define( 'OTAVABOOKS_FEED_FLOOR_RATIO', 0.9 );
// The delete runner refuses to act on a cache older than this.
define( 'OTAVABOOKS_FEED_MAX_AGE', 2 * DAY_IN_SECONDS );
// The delete runner aborts if a single run would remove more than this share of the catalogue.
define( 'OTAVABOOKS_MAX_DELETE_RATIO', 0.05 );
// A book must be missing from this many consecutive successful fetches before it is trashed.
define( 'OTAVABOOKS_MISSING_RUNS', 3 );
// ...and must have been missing at least this long, so a runaway cron cannot burn through them.
define( 'OTAVABOOKS_MISSING_MIN_AGE', 7 * DAY_IN_SECONDS );

// Post meta used to track books that have gone missing from the feed.
define( 'OTAVABOOKS_MISSING_SINCE_META', '_otava_missing_since' );
define( 'OTAVABOOKS_MISSING_RUNS_META', '_otava_missing_runs' );

require_once 'cpt-otava-book.php';
require_once 'cpt-otava-author.php';
require_once 'acf-fields.php';
require_once 'acf-options.php';
require_once 'runners.php';
require_once 'otava-import.php';
require_once 'otava-book.php';
require_once 'author.php';
require_once 'rest-api.php';

add_filter(
	'jcore_runner_menu',
	function ( $title ) {
		return 'Kirjatuonti';
	}
);
add_filter(
	'jcore_runner_title',
	function ( $title ) {
		return 'Otava Kirjatuonti';
	}
);

add_filter(
	'jcore_runner_status_status',
	function ( $content ) {
		$books     = get_json( IMPORT_BOOK_DATA );
		$timestamp = file_exists( IMPORT_BOOK_DATA ) ? filemtime( IMPORT_BOOK_DATA ) : 0;
		return $content . 'Books: ' . count( $books ) . ' Imported at ' . date( 'Y-m-d H:i:s', $timestamp );
	}
);

/**
 * Don't set the future type when date is in the future.
 *
 * @param mixed $post_data The post data passed to the filter.
 * @return mixed
 */
function prevent_future_type( $post_data ) {
	if ( $post_data['post_status'] === 'future' && $post_data['post_type'] === IMPORT_POST_TYPE ) {
		$post_data['post_status'] = 'publish';
	}
	return $post_data;
}

add_filter( 'wp_insert_post_data', '\otavabooks\prevent_future_type' );
remove_action( 'future_post', '_future_post_hook' );

add_filter(
	'jcore_runner_functions',
	function ( $functions ) {
		$functions['fetch']   = array(
			'title'    => 'Fetch Data',
			'callback' => '\otavabooks\fetch_book_data',
		);
		$functions['update']  = array(
			'title'    => 'Update Books',
			'callback' => '\otavabooks\update_books',
		);
		$functions['tulossa'] = array(
			'title'    => 'Update Tulossa',
			'callback' => '\otavabooks\update_tulossa',
		);
		$functions['delete']  = array(
			'title'    => 'Delete Old Books',
			'callback' => '\otavabooks\delete_books',
			'input'    => array(
				'mode' => array(
					'title'   => 'Mode',
					'type'    => 'select',
					'options' => array(
						'report' => 'Report only (no changes)',
						'delete' => 'Trash missing books',
					),
					'default' => 'report',
				),
			),
		);
		$functions['covers']  = array(
			'title'    => 'Check Covers',
			'callback' => '\otavabooks\cover_check',
		);

		return $functions;
	}
);
