<?php
/*
Plugin Name: Otava Kirjat
Plugin URI: http://otava.fi/
Description: CPT, ACF & sync / import functionality.
Version: 1.4.0
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
define( 'IMPORT_BOOK_DATA', get_upload_dir() . '/book_data.json' );
define( 'IMPORT_TIMESTAMP_DATA', get_upload_dir() . '/timestamp_data.json' );
define( 'IMPORT_CHECKSUM_DATA', get_upload_dir() . '/checksum_data.json' );
define( 'BOOK_COVER_DATA', get_upload_dir() . '/cover_data.json' );
define( 'IMPORT_TASK', 'otavabooks_import_cron_task' );
define( 'COVER_TASK', 'otavabooks_check_cover_task' );

require_once 'cpt-otava-book.php';
require_once 'cpt-otava-author.php';
require_once 'acf-fields.php';
require_once 'acf-options.php';
require_once 'ui.php';
require_once 'runners.php';
require_once 'otava-import.php';
require_once 'otava-book.php';
require_once 'author.php';
require_once 'rest-api.php';
require_once 'cron.php';

add_action( 'init', 'otavabooks\import_activation' );
register_deactivation_hook( __FILE__, 'otavabooks\import_deactivation' );

add_action( 'admin_menu', 'otavabooks\book_import_menu' );
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
	'jcore_runner_functions',
	function ( $functions ) {
		$functions['import'] = array(
			'title'    => 'Import Books',
			'callback' => '\otavabooks\import_books',
		);
		$functions['update'] = array(
			'title'    => 'Update Books',
			'callback' => '\otavabooks\update_books',
		);
		$functions['tulossa'] = array(
			'title'    => 'Update Tulossa',
			'callback' => '\otavabooks\update_tulossa',
		);
		$functions['delete'] = array(
			'title'    => 'Delete Old Books',
			'callback' => '\otavabooks\delete_books',
		);

		return $functions;
	}
);


/**
 * Adds menu to WP Admin
 */
function book_import_menu() {
	$parent_slug = 'tools.php';
	$page_title  = 'Otava Kirjatuonti';
	$menu_title  = 'Tuo Kirjoja';
	$capability  = 'manage_options';
	$menu_slug   = 'book-import';
	$function    = 'otavabooks\show_book_import_page';

	add_submenu_page(
		$parent_slug,
		$page_title,
		$menu_title,
		$capability,
		$menu_slug,
		$function
	);
}
