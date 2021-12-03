<?php

namespace otavabooks;

/**
 * Main function hooked to the tools menu.
 */
function show_book_import_page() {
	$books = get_json( IMPORT_BOOK_DATA );

	echo '
        <p>
            <a class="button action" href="?page=book-import&amp;fetchdata=1">Fetch Data</a>
        </p>';

	if ( ! empty( $books ) ) {
		echo "Books: " . count( $books );
		echo '
        <p>
            <a class="button action" href="?page=book-import&amp;bookimport=1">Import Book</a>
            <a class="button action" href="?page=book-import&amp;bookimport=10">Import 10 Books</a>
            <a class="button action" href="?page=book-import&amp;bookimport=10&amp;reload=1">Import All Books</a>
        </p>';
		echo '
        <p>
            <a class="button action" href="?page=book-import&amp;bookupdate=1">Update Book</a>
            <a class="button action" href="?page=book-import&amp;bookupdate=10">Update 10 Books</a>
            <a class="button action" href="?page=book-import&amp;bookupdate=10&amp;reload=1">Update All Books</a>
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
	}

	$update_page = false;

	echo '<p>';
	if ( is_admin() && ! empty( $_GET['fetchdata'] ) ) {

		$books = make_book_list();
		$json  = wp_json_encode( $books );
		file_put_contents( IMPORT_BOOK_DATA, $json );
	}

	// Import Books.
	if ( is_admin() && ! empty( $_GET['bookimport'] ) && ctype_digit( $_GET['bookimport'] ) ) {
		$imported = import_books( $_GET['bookimport'] );
		if ( $imported == $_GET['bookimport'] && ! empty( $_GET['reload'] ) ) {
			$update_page = true;
		}
		echo "Imported $imported books.";
	}

	// Update Books.
	if ( is_admin() && ! empty( $_GET['bookupdate'] ) && ctype_digit( $_GET['bookupdate'] ) ) {
		$updated = update_books( $_GET['bookupdate'] );
		if ( $updated == $_GET['bookupdate'] && ! empty( $_GET['reload'] ) ) {
			$update_page = true;
		}
		echo "Updated $updated books.";
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

	echo '</p>';

	if ( $update_page ) {
		echo '<script type="text/javascript">window.location.reload();</script>';
	}
}
