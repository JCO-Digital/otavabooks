<?php
/*
Plugin Name: Otava Kirjat
Plugin URI: http://otava.fi/
Description: CPT, ACF & sync / import functionality.
Version: 1.0
Author: JCO Digital
Author URI: http://jco.fi/
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin, like WordPress, is licensed under the GPL.
Use it to make something cool, have fun, and share what you've learned with others.
*/

namespace otavabooks;

require_once 'vendor/autoload.php';

require_once 'cpt-otava-book.php';
require_once 'acf-fields.php';
require_once 'ui.php';
require_once 'import-functions.php';
require_once 'utility-functions.php';

define( 'IMPORT_FILE_PATH', 'https://tuotetiedot.otava.fi/otava-kirjallisuus.json' );
define( 'IMPORT_TRANSIENT', 'book_import_data' );
define( 'IMPORT_PUBLISHERS', [ 'otava', 'moreeni', 'f-kustannus', 'nemo' ] );
define( 'IMPORT_POST_TYPE', 'otava_book' );
define( 'IMPORT_AUTHOR_TYPE', 'otava_author' );
define( 'IMPORT_BOOK_DATA', get_upload_dir() . '/book_data.json' );

add_action( 'admin_menu', 'otavabooks\book_import_menu' );

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

