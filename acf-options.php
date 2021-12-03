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
				'parent_slug' => 'options-general.php'
			)
		);
	}

	if ( function_exists( 'acf_add_local_field_group' ) ) {

		acf_add_local_field_group( array(
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
		) );

	}
}

function get_import_url() {
	return get_field( "otavabooks_import_url", "option" ) ?? "https://otava.fi/tuotepainos.json";
}

function get_publishers() {
	return get_field( "otavabooks_import_publishers", "option" ) ?? [];
}

function get_author() {
	return get_field( "otavabooks_import_author", "option" );
}
