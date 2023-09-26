<?php

namespace otavabooks;


/**
 * Main function hooked to the tools menu.
 */
function show_book_import_page() {
	$books     = get_json( IMPORT_BOOK_DATA );
	$timestamp = file_exists( IMPORT_BOOK_DATA ) ? filemtime( IMPORT_BOOK_DATA ) : 0;

	$update_page = false;

	echo '<p>';
	if ( is_admin() && ! empty( $_GET['fetchdata'] ) ) {
		$books = make_book_list();
		$json  = wp_json_encode( $books );
		file_put_contents( IMPORT_BOOK_DATA, $json );
		$timestamp = time();
	}

	// Update One Book.
	if ( is_admin() && ! empty( $_GET['isbnupdate'] ) ) {
		update_book( $_GET['isbnupdate'] );
	}

	// Reset Timestamps.
	if ( is_admin() && ! empty( $_GET['resetupdate'] ) && ctype_digit( $_GET['resetupdate'] ) ) {
		if ( file_exists( IMPORT_TIMESTAMP_DATA ) ) {
			unlink( IMPORT_TIMESTAMP_DATA );
		}
		if ( file_exists( IMPORT_CHECKSUM_DATA ) ) {
			unlink( IMPORT_CHECKSUM_DATA );
		}
	}

	// Delete Books.
	if ( is_admin() && ! empty( $_GET['bookdelete'] ) && ctype_digit( $_GET['bookdelete'] ) ) {
		$deleted = delete_books( $_GET['bookdelete'] );
		if ( $deleted == $_GET['bookdelete'] ) {
			$update_page = true;
		}
	}

	// Clean up terms.
	if ( is_admin() && ! empty( $_GET['termdelete'] ) && ctype_digit( $_GET['termdelete'] ) ) {
		clean_terms( $_GET['termdelete'] );
	}

	// Run import cron.
	if ( is_admin() && ! empty( $_GET['runcron'] ) && ctype_digit( $_GET['runcron'] ) ) {
		book_import_cron();
	}

	// Run import cron.
	if ( is_admin() && ! empty( $_GET['runcovercron'] ) && ctype_digit( $_GET['runcovercron'] ) ) {
		cover_check_cron();
	}

	// Clean covers.
	if ( is_admin() && ! empty( $_GET['cleancovers'] ) && ctype_digit( $_GET['cleancovers'] ) ) {
		$deleted = get_book_covers( $_GET['cleancovers'] );
		if ( $deleted > 0 ) {
			$update_page = true;
		}
	}

	echo '</p>';

	echo '
        <p>
            <a class="button action" href="?page=book-import&amp;fetchdata=1">Fetch Data</a>
        </p>';

	if ( ! empty( $books ) ) {
		echo 'Books: ' . count( $books ) . '<br/>';
		echo 'Imported at ' . date( 'Y-m-d H:i:s', $timestamp ) . '<br/>';
		echo '
        <p>
            <a class="button action" href="?page=book-import&amp;resetupdate=1" onclick="return confirm(\'Are you sure?\');">Reset Update Timestamps</a>
        </p>';
	}

	if ( WP_DEBUG ) {
		echo '
        <p>
            <a class="button action" href="?page=book-import&amp;bookdelete=30">Delete all books (remove for production)</a>
        </p>';

		echo '
        <p>
            <a class="button action" href="?page=book-import&amp;termdelete=30">Delete unused terms (remove for production)</a>
        </p>';

		echo '
        <p>
            <a class="button action" href="?page=book-import&amp;runcron=1">Run Import cron</a>
        </p>';

		echo '
        <p>
            <a class="button action" href="?page=book-import&amp;runcovercron=1">Run cover checking cron</a>
        </p>';

		echo '
        <p>
            <a class="button action" href="?page=book-import&amp;cleancovers=30">Clean old covers</a>
        </p>';

	}

	if ( $update_page ) {
		echo '<script type="text/javascript">window.location.reload();</script>';
	}
}
