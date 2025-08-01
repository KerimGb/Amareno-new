<?php defined( 'ABSPATH' ) OR die( 'This script cannot be accessed directly.' );

/**
 * Configuration for shortcode: vc_column_text
 */

$conditional_params = us_config( 'elements_conditional_options' );
$design_options_params = us_config( 'elements_design_options' );
$effect_options_params = us_config( 'elements_effect_options' );

/**
 * @return array
 */
return array(
	'title' => __( 'Text Block', 'us' ),
	'category' => __( 'Basic', 'us' ),
	'icon' => 'fas fa-align-left',
	'weight' => 390, // sets the SECOND position in "Add element" lists
	'usb_preload' => TRUE,
	'params' => us_set_params_weight(
		array(
			'content' => array(
				'title' => us_translate( 'Text' ),
				'std' => '<p>' . us_translate( 'I am text block. Click edit button to change this text. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.', 'js_composer' ) . '</p>',
				'type' => 'editor',
				'show_ai_icon' => TRUE,
				'holder' => 'div',
				// TODO maybe create JS function should it be used anywehre else
				'usb_preview' => array(
					'callback' => 'var youtubeRegex = /(?:https?:\/\/)?(?:www\.)?(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))((\w|-){11})(?:\S+)?/,
						vimeoRegex = /http(?:s)?:\/\/(?:.*?)\.?vimeo\.com\/(\d+)/;
					return ( value.indexOf( \'[\' ) !== -1 || value.match( youtubeRegex ) || value.match( vimeoRegex ) )
						? true
						:{ \'elm\': \'.wpb_wrapper\', \'attr\': \'html\'} ;',
				),
			),
			'background_inside_text' => array(
				'type' => 'switch',
				'switch_text' => __( 'Use background inside text', 'us' ),
				'description' => __( 'Specify a background image or gradient for this element to take effect.', 'us' ),
				'std' => 0,
				'classes' => 'desc_2',
				'group' => __( 'More Options', 'us' ),
				'usb_preview' => array(
					'toggle_class' => 'background_inside_text',
				),
			),
			'show_more_toggle' => array(
				'switch_text' => __( 'Hide part of a content with the "Show More" link', 'us' ),
				'type' => 'switch',
				'std' => 0,
				'group' => __( 'More Options', 'us' ),
				'usb_preview' => TRUE,
			),
			'show_more_toggle_height' => array(
				'title' => __( 'Height of visible content', 'us' ),
				'type' => 'slider',
				'options' => array(
					'px' => array(
						'min' => 50,
						'max' => 300,
						'step' => 10,
					),
				),
				'std' => '200px',
				'show_if' => array( 'show_more_toggle', '=', 1 ),
				'group' => __( 'More Options', 'us' ),
				'usb_preview' => TRUE,
			),
			'show_more_toggle_text_more' => array(
				'title' => __( 'Text when content is hidden', 'us' ),
				'type' => 'text',
				'std' => __( 'Show More', 'us' ),
				'show_if' => array( 'show_more_toggle', '=', 1 ),
				'group' => __( 'More Options', 'us' ),
				'usb_preview' => array(
					'elm' => '.collapsible-content-more',
					'attr' => 'html',
				),
			),
			'show_more_toggle_text_less' => array(
				'title' => __( 'Text when content is shown', 'us' ),
				'description' => __( 'Leave blank to prevent content from being hidden again.', 'us' ),
				'type' => 'text',
				'std' => __( 'Show Less', 'us' ),
				'show_if' => array( 'show_more_toggle', '=', 1 ),
				'group' => __( 'More Options', 'us' ),
				'usb_preview' => array(
					'elm' => '.collapsible-content-less',
					'attr' => 'html',
				),
			),
			'show_more_toggle_alignment' => array(
				'title' => us_translate( 'Alignment' ),
				'type' => 'radio',
				'labels_as_icons' => 'fas fa-align-*',
				'options' => array(
					'none' => us_translate( 'Default' ),
					'left' => us_translate( 'Left' ),
					'center' => us_translate( 'Center' ),
					'right' => us_translate( 'Right' ),
				),
				'std' => 'none',
				'show_if' => array( 'show_more_toggle', '=', 1 ),
				'group' => __( 'More Options', 'us' ),
				'usb_preview' => array(
					'elm' => '.toggle-links',
					'mod' => 'align',
				),
			),
		),
		$conditional_params,
		$effect_options_params,
		$design_options_params
	),

	// Default VC params which are not supported by the theme
	'vc_remove_params' => array(
		'css_animation',
	),

	'usb_init_js' => '$elm.filter( \'[data-content-height]\' ).usCollapsibleContent()',
);
