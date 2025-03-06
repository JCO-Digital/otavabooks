<?php

add_action( 'acf/init', 'activate_acf_options' );

function activate_acf_options() {

	// Check function exists.
	if ( function_exists( 'acf_add_options_page' ) ) {

		// Register options page.
		$option_page = acf_add_options_sub_page(
			array(
				'page_title'  => __( 'Kirjatuojan asetukset' ),
				'menu_title'  => __( 'Kirjatuojan asetukset' ),
				'menu_slug'   => 'book-importer-settings',
				'capability'  => 'edit_posts',
				'parent_slug' => 'options-general.php',
			)
		);
	}

	if ( function_exists( 'acf_add_local_field_group' ) ) {

		acf_add_local_field_group(
			array(
				'key'                   => 'group_61a9f26766957',
				'title'                 => 'Kirjatuojan asetukset',
				'fields'                => array(
					array(
						'key'               => 'field_61a9f27048cd8',
						'label'             => 'Import URL',
						'name'              => 'otavabooks_import_url',
						'type'              => 'url',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'default_value'     => 'https://otava.fi/tuotepainos.json',
						'placeholder'       => '',
					),
					array(
						'key'               => 'field_61a9f2a848cd9',
						'label'             => 'Import Publishers',
						'name'              => 'otavabooks_import_publishers',
						'type'              => 'checkbox',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'choices'           => array(),
						'allow_custom'      => 1,
						'save_custom'       => 0,
						'default_value'     => array(),
						'layout'            => 'vertical',
						'toggle'            => 0,
						'return_format'     => 'value',
					),
					array(
						'key'               => 'field_61a9f2e248cda',
						'label'             => 'Import Author',
						'name'              => 'otavabooks_import_author',
						'type'              => 'number',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'default_value'     => '',
						'placeholder'       => '',
						'prepend'           => '',
						'append'            => '',
						'min'               => '',
						'max'               => '',
						'step'              => '',
					),
					array(
						'key'               => 'field_61af3dd588ec9',
						'label'             => 'Disable category conversion',
						'name'              => 'otavabooks_disable_category_conversion',
						'type'              => 'true_false',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'message'           => '',
						'default_value'     => 0,
						'ui'                => 1,
						'ui_on_text'        => '',
						'ui_off_text'       => '',
					),
					array(
						'key'               => 'field_61bdf90fd8cd5',
						'label'             => 'Import catalog',
						'name'              => 'otavabooks_import_catalog',
						'type'              => 'true_false',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'message'           => '',
						'default_value'     => 0,
						'ui'                => 1,
						'ui_on_text'        => '',
						'ui_off_text'       => '',
					),
				),
				'location'              => array(
					array(
						array(
							'param'    => 'options_page',
							'operator' => '==',
							'value'    => 'book-importer-settings',
						),
					),
				),
				'menu_order'            => 0,
				'position'              => 'normal',
				'style'                 => 'default',
				'label_placement'       => 'top',
				'instruction_placement' => 'label',
				'hide_on_screen'        => '',
				'active'                => true,
				'description'           => '',
			)
		);
	}
}

/**
 * Getters for the different settings values.
 */
function get_import_url_setting() {
	if ( function_exists( 'get_field' ) ) {
		$url = get_field( 'otavabooks_import_url', 'option' );
		if ( ! empty( $url ) ) {
			return $url;
		}
	}

	return 'https://tuotetiedot.otava.fi/Likeotavafi_tuotepainos.json';
}

function get_publishers_setting() {
	if ( function_exists( 'get_field' ) ) {
		$publishers = get_field( 'otavabooks_import_publishers', 'option' ) ?? array();

		return str_replace( 'otava', '', $publishers );
	}

	return array();
}

function get_author_setting() {
	if ( function_exists( 'get_field' ) ) {
		return get_field( 'otavabooks_import_author', 'option' );
	}

	return 0;
}

function get_disable_categories_setting() {
	if ( function_exists( 'get_field' ) ) {
		return (bool) get_field( 'otavabooks_disable_category_conversion', 'option' );
	}

	return false;
}

function get_import_catalog() {
	if ( function_exists( 'get_field' ) ) {
		return (bool) get_field( 'otavabooks_import_catalog', 'option' );
	}

	return false;
}
