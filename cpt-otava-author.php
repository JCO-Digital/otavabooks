<?php

namespace otavabooks;

/**
 * Register Custom Post Type: otava_author
 */
function otava_author() {
	$labels  = array(
		'name'               => _x( 'Kirjailijat', 'Post Type General Name', 'text_domain' ),
		'singular_name'      => _x( 'Kirjailija', 'Post Type Singular Name', 'text_domain' ),
		'menu_name'          => __( 'Kirjailijat', 'text_domain' ),
		'parent_item_colon'  => __( 'Parent Author:', 'text_domain' ),
		'all_items'          => __( 'Kaikki kirjailijat', 'text_domain' ),
		'view_item'          => __( 'N&auml;yt&auml; kirjailija', 'text_domain' ),
		'add_new_item'       => __( 'Lis&auml;&auml; uusi kirjailija', 'text_domain' ),
		'add_new'            => __( 'Uusi kirjailija', 'text_domain' ),
		'edit_item'          => __( 'Muokkaa kirjailijaa', 'text_domain' ),
		'update_item'        => __( 'P&auml;ivit&auml; kirjailija', 'text_domain' ),
		'search_items'       => __( 'Etsi kirjailijoita', 'text_domain' ),
		'not_found'          => __( 'Kirjailijoita ei l&ouml;ytynyt', 'text_domain' ),
		'not_found_in_trash' => __( 'Kirjailijoita ei l&ouml;ytynyt roskakorista', 'text_domain' ),
	);
	$rewrite = array(
		'slug'       => 'kirjailijat',
		'with_front' => true,
		'pages'      => true,
		'feeds'      => true,
	);
	$args    = array(
		'label'               => __( 'kirjailijat', 'text_domain' ),
		'description'         => __( 'Otavan kirjailijat', 'text_domain' ),
		'labels'              => $labels,
		'supports'            => array(
			'title',
			'editor',
			'excerpt',
			'author',
			'thumbnail',
			'revisions',
			'custom-fields',
		),
		'taxonomies'          => array( 'post_tag' ),
		'hierarchical'        => false,
		'public'              => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_nav_menus'   => true,
		'show_in_admin_bar'   => true,
		'menu_position'       => 5,
		'menu_icon'           => 'dashicons-groups',
		'can_export'          => true,
		'has_archive'         => true,
		'exclude_from_search' => false,
		'rewrite'             => $rewrite,
		'publicly_queryable'  => true,
		'capability_type'     => 'post',
		'query_var'           => true,
	);
	register_post_type( 'otava_author', $args );
}

// Hook into the 'init' action.
add_action( 'init', 'otavabooks\otava_author', 0 );
