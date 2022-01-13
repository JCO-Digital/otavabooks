<?php

namespace otavabooks;

/**
 * Activate the WP cron hook.
 */
function import_activation() {
	if ( ! wp_next_scheduled( IMPORT_TASK ) ) {
		wp_schedule_event( time(), 'hourly', IMPORT_TASK );
	}
}

/**
 * Deactivate the WP cron hook.
 */
function import_deactivation() {
	wp_clear_scheduled_hook( IMPORT_TASK );
}

add_action( IMPORT_TASK, 'otavabooks\book_import_cron', 10, 0);

/**
 * Function to run every hour.
 */
function book_import_cron() {
	$timestamp = file_exists( IMPORT_BOOK_DATA ) ? filemtime( IMPORT_BOOK_DATA ) : 0;

	if ((time() - $timestamp) > (4 * 60 * 60)) {
		// Fetch books if data is older than 4 hours.
		$books = make_book_list();
		$json  = wp_json_encode( $books );
		file_put_contents( IMPORT_BOOK_DATA, $json );
		echo "Fetched file.";
	}

	$imported = import_books( 10 );
	echo "Imported $imported books.";

	$updated = update_books( 10 );
	echo "Updated $updated books.";
}
