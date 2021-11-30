<?php

namespace otavabooks;

/**
 * Main function hooked to the tools menu.
 */
function show_book_import_page() {
	$books = read_books();

	echo '
        <p>
            <a class="button action" href="?page=book-import&amp;fetchdata=1">Fetch Data</a>
        </p>';

	if ( ! empty( $books ) ) {
		echo "Books: " . count( $books );
		echo '
        <p>
            <a class="button action" href="?page=book-import&amp;bookimport=1">Import Book</a>
        </p>';
	}

	if ( WP_DEBUG ) {
		echo '
        <p>
            <a class="button action" href="?page=book-import&amp;bookdelete=30">Delete all books (remove for production)</a>
        </p>';
	}

	echo '<p>';
	if ( is_admin() && ! empty( $_GET['fetchdata'] ) ) {

		$books = make_book_list();
		$json  = wp_json_encode( $books );
		file_put_contents( IMPORT_BOOK_DATA, $json );
	}
	echo '</p>';

	echo '<p>';
	if ( is_admin() && ! empty( $_GET['bookimport'] ) && ctype_digit( $_GET['bookimport'] ) ) {
		echo import_books( wp_unslash( $_GET['bookimport'] ) );
	}
	echo '</p>';

	echo '<p>';
	if ( is_admin() && ! empty( $_GET['bookdelete'] ) && ctype_digit( $_GET['bookdelete'] ) ) {
		$deleted = delete_books( $_GET['bookdelete'] );
		if ( $deleted == $_GET['bookdelete'] ) {
			echo '<script type="text/javascript">window.location.reload();</script>';
		}
	}
	echo '</p>';

}
