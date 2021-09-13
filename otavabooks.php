<?php
/*
Plugin Name: Otava Kirjat
Plugin URI: http://otava.fi/
Description: CPT Otavan kirjoista
Version: 1.0
Author: JCO Digital
Author URI: http://jco.fi/
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin, like WordPress, is licensed under the GPL.
Use it to make something cool, have fun, and share what you've learned with others.
*/

// Register Custom Post Type: otava_book.
function otava_book() {
	$labels = array(
		'name'               => _x( 'Kirjat', 'Post Type General Name', 'text_domain' ),
		'singular_name'      => _x( 'Kirja', 'Post Type Singular Name', 'text_domain' ),
		'menu_name'          => __( 'Kirjat', 'text_domain' ),
		'parent_item_colon'  => __( 'Parent Book:', 'text_domain' ),
		'all_items'          => __( 'Kaikki kirjat', 'text_domain' ),
		'view_item'          => __( 'N&auml;yt&auml; kirja', 'text_domain' ),
		'add_new_item'       => __( 'Lis&auml;&auml; uusi kirja', 'text_domain' ),
		'add_new'            => __( 'Uusi kirja', 'text_domain' ),
		'edit_item'          => __( 'Muokkaa kirjaa', 'text_domain' ),
		'update_item'        => __( 'P&auml;ivit&auml; kirja', 'text_domain' ),
		'search_items'       => __( 'Etsi kirjoja', 'text_domain' ),
		'not_found'          => __( 'Kirjoja ei l&ouml;ytynyt', 'text_domain' ),
		'not_found_in_trash' => __( 'Kirjoja ei l&ouml;ytynyt roskakorista', 'text_domain' ),
	);
	$rewrite = array(
		'slug'       => 'kirjat',
		'with_front' => true,
		'pages'      => true,
		'feeds'      => true,
	);
	$args = array(
		'label'               => __( 'kirja', 'text_domain' ),
		'description'         => __( 'Otavan kirjat', 'text_domain' ),
		'labels'              => $labels,
		'supports'            => array( 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'revisions', 'custom-fields' ),
		'taxonomies'          => array( 'otava_sarja', 'otava_kuvittaja', 'otava_kaantaja', 'otava_julkaisija', 'otava_kategoria', 'otava_sidosasu', 'otava_katalogi', 'post_tag'),
		'hierarchical'        => false,
		'public'              => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_nav_menus'   => true,
		'show_in_admin_bar'   => true,
		'menu_position'       => 5,
		'menu_icon'           => 'dashicons-book',
		'can_export'          => true,
		'has_archive'         => true,
		'exclude_from_search' => false,
		'rewrite'             => $rewrite,
		'publicly_queryable'  => true,
		'capability_type'     => 'post',
		'query_var'           => true,
	);
	register_post_type( 'otava_book', $args );
}
// Hook into the 'init' action.
add_action( 'init', 'otava_book', 0 );


// Register Custom Taxonomy: otava_toimittanut.
function otava_toimittanut() {
	$labels  = array(
		'name'                       => _x( 'Toimittanut', 'Taxonomy General Name', 'text_domain' ),
		'singular_name'              => _x( 'Toimittanut', 'Taxonomy Singular Name', 'text_domain' ),
		'menu_name'                  => __( 'Toimittanut', 'text_domain' ),
		'all_items'                  => __( 'Kaikki teoksia toimittaneet', 'text_domain' ),
		'parent_item'                => __( 'Parent Genre', 'text_domain' ),
		'parent_item_colon'          => __( 'Parent Genre:', 'text_domain' ),
		'new_item_name'              => __( 'Uuden toimittajan nimi', 'text_domain' ),
		'add_new_item'               => __( 'Lisää uusi toimittaja', 'text_domain' ),
		'edit_item'                  => __( 'Muokkaa toimittajaa', 'text_domain' ),
		'update_item'                => __( 'Päivitä toimittajan nimi', 'text_domain' ),
		'separate_items_with_commas' => __( 'Lisää nimi tähän VAIN jos henkilö on toimittanut teoksen TAI henkilölle ei tehdä omaa kirjailijan esittelysivua. Nimi muodossa Etunimi Sukunimi TAI Etunimi Sukunimi (toim.).', 'text_domain' ),
		'search_items'               => __( 'Etsi toimittajia', 'text_domain' ),
		'add_or_remove_items'        => __( 'Lisää tai poista toimittajia', 'text_domain' ),
		'choose_from_most_used'      => __( 'Valitse eniten käytetyistä toimittajien nimistä', 'text_domain' ),
	);
	$rewrite = array(
		'slug'         => 'toimittaja',
		'with_front'   => false,
		'hierarchical' => false,
	);
	$args    = array(
		'labels'            => $labels,
		'hierarchical'      => false,
		'public'            => true,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_nav_menus' => true,
		'show_tagcloud'     => true,
		'rewrite'           => $rewrite,
		'query_var'         => true,
	);
	register_taxonomy( 'otava_toimittanut', array( 'otava_book' ), $args );
}
// Hook into the 'init' action.
add_action( 'init', 'otava_toimittanut', 0 );

// Register Custom Taxonomy: otava_sarja.
function otava_sarja() {
	$labels = array(
		'name'                       => _x('Sarjat', 'Taxonomy General Name', 'text_domain'),
		'singular_name'              => _x('Sarja', 'Taxonomy Singular Name', 'text_domain'),
		'menu_name'                  => __('Sarja', 'text_domain'),
		'all_items'                  => __('Kaikki sarjat', 'text_domain'),
		'parent_item'                => __('Parent Genre', 'text_domain'),
		'parent_item_colon'          => __('Parent Genre:', 'text_domain'),
		'new_item_name'              => __('Uuden sarjan nimi', 'text_domain'),
		'add_new_item'               => __('Lisää uusi sarja', 'text_domain'),
		'edit_item'                  => __('Muokkaa sarjaa', 'text_domain'),
		'update_item'                => __('Päivitä sarjan nimi', 'text_domain'),
		'separate_items_with_commas' => __('Erottele sarjojen nimet pilkulla', 'text_domain'),
		'search_items'               => __('Etsi sarjoja', 'text_domain' ),
		'add_or_remove_items'        => __('Lisää tai poista sarjoja', 'text_domain'),
		'choose_from_most_used'      => __('Valitse eniten käytetyistä sarjojen nimistä', 'text_domain'),
	);
	$rewrite = array(
		'slug'                       => 'sarja',
		'with_front'                 => false,
		'hierarchical'               => false,
	);
	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => false,
		'public'                     => true,
		'show_ui'                    => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => true,
		'show_tagcloud'              => true,
		'rewrite'                    => $rewrite,
		'query_var'                  => true,
	);
	register_taxonomy('otava_sarja', array('otava_book'), $args);
}
//Hook into the 'init' action
add_action('init', 'otava_sarja', 0);

/*******************************************/
/*******************************************/

//Register Custom Taxonomy: otava_sidosasu
function otava_sidosasu()  {
	$labels = array(
		'name'                       => _x('Sidosasut', 'Taxonomy General Name', 'text_domain'),
		'singular_name'              => _x('Sidosasu', 'Taxonomy Singular Name', 'text_domain'),
		'menu_name'                  => __('Sidosasu', 'text_domain'),
		'all_items'                  => __('Kaikki sidosasut', 'text_domain'),
		'parent_item'                => __('Parent Format', 'text_domain'),
		'parent_item_colon'          => __('Parent Format:', 'text_domain'),
		'new_item_name'              => __('Uuden sidosasun nimi', 'text_domain'),
		'add_new_item'               => __('Lisää uusi sidosasu', 'text_domain'),
		'edit_item'                  => __('Muokkaa sidosasun nimeä', 'text_domain'),
		'update_item'                => __('Päivitä sidosasun nimi', 'text_domain'),
		'separate_items_with_commas' => __('Erottele sidosasut toisistaan pilkulla', 'text_domain'),
		'search_items'               => __('Etsi sidosasuja', 'text_domain'),
		'add_or_remove_items'        => __('Lisää tai poista sidosasuja', 'text_domain'),
		'choose_from_most_used'      => __('Valitse eniten käytetyistä sidosasuista', 'text_domain'),
	);
	$rewrite = array(
		'slug'                       => 'sidosasu',
		'with_front'                 => false,
		'hierarchical'               => false,
	);
	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => false,
		'public'                     => true,
		'show_ui'                    => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => true,
		'show_tagcloud'              => true,
		'rewrite'                    => $rewrite,
		'query_var'                  => true,
	);
	register_taxonomy('otava_sidosasu', array('otava_book'), $args);
}
//Hook into the 'init' action
add_action('init', 'otava_sidosasu', 0);

/*******************************************/
/*******************************************/

//Register Custom Taxonomy: otava_julkaisija
function otava_julkaisija(){
	$labels = array(
		'name'                       => _x('Julkaisija', 'Taxonomy General Name', 'text_domain'),
		'singular_name'              => _x('Julkaisija', 'Taxonomy Singular Name', 'text_domain'),
		'menu_name'                  => __('Julkaisija', 'text_domain'),
		'all_items'                  => __('Kaikki julkaisijat', 'text_domain'),
		'parent_item'                => __('Parent Publisher', 'text_domain'),
		'parent_item_colon'          => __('Parent Publisher:', 'text_domain'),
		'new_item_name'              => __('Uuden julkaisijan nimi', 'text_domain'),
		'add_new_item'               => __('Lisää uusi julkaisija', 'text_domain'),
		'edit_item'                  => __('Muokkaa julkaisijaa', 'text_domain'),
		'update_item'                => __('Päivitä julkaisijan nimi', 'text_domain'),
		'separate_items_with_commas' => __('Erottele julkaisijat toisistaan pilkulla', 'text_domain'),
		'search_items'               => __('Etsi julkaisijoita', 'text_domain'),
		'add_or_remove_items'        => __('Lisää tai poista julkaisijoita', 'text_domain'),
		'choose_from_most_used'      => __('Valitse eniten käytetyistä julkaisijoista', 'text_domain'),
	);
	$rewrite = array(
		'slug'                       => 'julkaisija',
		'with_front'                 => false,
		'hierarchical'               => false,
	);
	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => false,
		'public'                     => true,
		'show_ui'                    => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => true,
		'show_tagcloud'              => true,
		'rewrite'                    => $rewrite,
		'query_var'                  => true,
	);
	register_taxonomy('otava_julkaisija', array('otava_book'), $args);
}
//Hook into the 'init' action
add_action('init', 'otava_julkaisija', 0);

/*******************************************/
/*******************************************/

// Register Custom Taxonomy: otava_kuvittaja
function otava_kuvittaja(){
	$labels = array(
		'name'                       => _x('Kuvittaja', 'Taxonomy General Name', 'text_domain'),
		'singular_name'              => _x('Kuvittaja', 'Taxonomy Singular Name', 'text_domain'),
		'menu_name'                  => __('Kuvittaja', 'text_domain'),
		'all_items'                  => __('Kaikki kuvittajat', 'text_domain'),
		'parent_item'                => __('Parent Publisher', 'text_domain'),
		'parent_item_colon'          => __('Parent Publisher:', 'text_domain'),
		'new_item_name'              => __('Uuden kuvittajan nimi', 'text_domain'),
		'add_new_item'               => __('Lisää uusi kuvittaja', 'text_domain'),
		'edit_item'                  => __('Muokkaa kuvittajaa', 'text_domain'),
		'update_item'                => __('Päivitä kuvittajan nimi', 'text_domain'),
		'separate_items_with_commas' => __('Erottele kuvittajat toisistaan pilkulla', 'text_domain'),
		'search_items'               => __('Etsi kuvittajia', 'text_domain'),
		'add_or_remove_items'        => __('Lisää tai poista kuvittajia', 'text_domain'),
		'choose_from_most_used'      => __('Valitse eniten käytetyistä kuvittajista', 'text_domain'),
	);
	$rewrite = array(
		'slug'                       => 'kuvittaja',
		'with_front'                 => false,
		'hierarchical'               => false,
	);
	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => false,
		'public'                     => true,
		'show_ui'                    => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => true,
		'show_tagcloud'              => true,
		'rewrite'                    => $rewrite,
		'query_var'                  => true,
	);
	register_taxonomy('otava_kuvittaja', array('otava_book'), $args);
}
//Hook into the 'init' action
add_action('init', 'otava_kuvittaja', 0);

/*******************************************/
/*******************************************/

// Register Custom Taxonomy: otava_kaantaja
function otava_kaantaja(){
	$labels = array(
		'name'                       => _x('Kääntäjä', 'Taxonomy General Name', 'text_domain'),
		'singular_name'              => _x('Kääntäjä', 'Taxonomy Singular Name', 'text_domain'),
		'menu_name'                  => __('Kääntäjä', 'text_domain'),
		'all_items'                  => __('Kaikki kääntäjät', 'text_domain'),
		'parent_item'                => __('Parent Publisher', 'text_domain'),
		'parent_item_colon'          => __('Parent Publisher:', 'text_domain'),
		'new_item_name'              => __('Uuden kääntäjän nimi', 'text_domain'),
		'add_new_item'               => __('Lisää uusi kääntäjä', 'text_domain'),
		'edit_item'                  => __('Muokkaa kääntäjää', 'text_domain'),
		'update_item'                => __('Päivitä kääntäjän nimi', 'text_domain'),
		'separate_items_with_commas' => __('Erottele kääntäjät toisistaan pilkulla', 'text_domain'),
		'search_items'               => __('Etsi kääntäjiä', 'text_domain'),
		'add_or_remove_items'        => __('Lisää tai poista kääntäjiä', 'text_domain'),
		'choose_from_most_used'      => __('Valitse eniten käytetyistä kääntäjistä', 'text_domain'),
	);
	$rewrite = array(
		'slug'                       => 'kaantaja',
		'with_front'                 => false,
		'hierarchical'               => false,
	);
	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => false,
		'public'                     => true,
		'show_ui'                    => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => true,
		'show_tagcloud'              => true,
		'rewrite'                    => $rewrite,
		'query_var'                  => true,
	);
	register_taxonomy('otava_kaantaja', array('otava_book'), $args);
}
//Hook into the 'init' action
add_action('init', 'otava_kaantaja', 0);

/*******************************************/
/*******************************************/

//Register Custom Taxonomy: otava_kategoria
function otava_kategoria(){
	$labels = array(
		'name'                       => _x('Teoksen kategoria', 'Taxonomy General Name', 'text_domain'),
		'singular_name'              => _x('Teoksen kategoria', 'Taxonomy Singular Name', 'text_domain'),
		'menu_name'                  => __('Teoksen kategoria', 'text_domain'),
		'all_items'                  => __('Kaikki teoskategoriat', 'text_domain'),
		'parent_item'                => __('Parent Publisher', 'text_domain'),
		'parent_item_colon'          => __('Parent Publisher:', 'text_domain'),
		'new_item_name'              => __('Uuden kategorian nimi', 'text_domain'),
		'add_new_item'               => __('Lisää uusi kategoria', 'text_domain'),
		'edit_item'                  => __('Muokkaa kategoriaa', 'text_domain'),
		'update_item'                => __('Päivitä kategorian nimi', 'text_domain'),
		'separate_items_with_commas' => __('Erottele kategoriat toisistaan pilkulla', 'text_domain'),
		'search_items'               => __('Etsi teoskategorioita', 'text_domain'),
		'add_or_remove_items'        => __('Lisää tai poista teoskategorioita', 'text_domain'),
		'choose_from_most_used'      => __('Valitse eniten käytetyistä teoskategorioista', 'text_domain'),
	);
	$rewrite = array(
		'slug'                       => 'kategoria',
		'with_front'                 => false,
		'hierarchical'               => false,
	);
	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => false,
		'public'                     => true,
		'show_ui'                    => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => true,
		'show_tagcloud'              => true,
		'rewrite'                    => $rewrite,
		'query_var'                  => true,
	);
	register_taxonomy('otava_kategoria', array('otava_book'), $args);
}
//Hook into the 'init' action
add_action( 'init', 'otava_kategoria', 0 );

