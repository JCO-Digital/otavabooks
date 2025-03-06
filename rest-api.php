<?php

namespace otavabooks;

add_action( 'rest_api_init', 'otavabooks\restEndpoints' );

const ns = 'otavabooks/v1';

function restEndpoints() {
	register_rest_route(
		ns,
		'/cover/(?P<isbn>.+)',
		array(
			'methods'             => 'PATCH',
			'callback'            => 'otavabooks\updateCover',
			'permission_callback' => '__return_true',
		)
	);
}

/*
 * Endpoints.
 */
function updateCover( $request ): \WP_REST_Response {
	$response  = new \WP_REST_Response();
	$isbn      = $request->get_param( 'isbn' );
	$has_cover = $request->get_param( 'has_cover' );

	$covers = get_json( BOOK_COVER_DATA );

	$covers[ $isbn ] = array(
		'has_cover' => $has_cover,
		'timestamp' => time(),
	);
	foreach ( $covers as $key => $cover ) {
		$response->set_data( $key );
	}

	put_json( BOOK_COVER_DATA, $covers );

	return $response;
}
