<?php defined( 'ABSPATH' ) OR die( 'This script cannot be accessed directly.' );

/**
 * Configuration for shortcode: hwrapper
 */

$conditional_params = us_config( 'elements_conditional_options' );
$design_options_params = us_config( 'elements_design_options' );
$effect_options_params = us_config( 'elements_effect_options' );
$hover_options_params = us_config( 'elements_hover_options' );

/**
 * @return array
 */
return array(
	'title' => __( 'Horizontal Wrapper', 'us' ),
	'category' => __( 'Containers', 'us' ),
	'icon' => 'fas fa-ellipsis-h',
	'is_container' => TRUE,
	'usb_moving_only_x_axis' => TRUE,
	'as_parent' => array(
		'except' => 'vc_row,vc_row_inner,vc_column,vc_tta_tabs,vc_tta_tour,vc_tta_accordion,vc_tta_section,us_hwrapper,us_content_carousel',
	),
	'show_settings_on_create' => FALSE,
	'js_view' => 'VcColumnView',
	'params' => us_set_params_weight(

		// General section
		array(
			'alignment' => array(
				'title' => __( 'Items Horizontal Alignment', 'us' ),
				'type' => 'radio',
				'labels_as_icons' => 'fas fa-align-*',
				'options' => array(
					'none' => us_translate( 'Default' ),
					'left' => us_translate( 'Left' ),
					'center' => us_translate( 'Center' ),
					'right' => us_translate( 'Right' ),
					'justify' => us_translate( 'Justify' ),
				),
				'std' => 'none',
				'is_responsive' => TRUE,
				'usb_preview' => array(
					'mod' => 'align',
				),
			),
			'valign' => array(
				'title' => __( 'Items Vertical Alignment', 'us' ),
				'type' => 'select',
				'options' => array(
					'top' => us_translate( 'Top' ),
					'middle' => us_translate( 'Middle' ),
					'bottom' => us_translate( 'Bottom' ),
					'baseline' => __( 'With baseline', 'us' ),
					'stretch' => __( 'Stretch', 'us' ),
				),
				'std' => 'top',
				'usb_preview' => array(
					'mod' => 'valign',
				),
			),
			'inner_items_gap' => array(
				'title' => __( 'Gap between Items', 'us' ),
				'type' => 'slider',
				'options' => array(
					'px' => array(
						'min' => 0,
						'max' => 60,
					),
					'rem' => array(
						'min' => 0.0,
						'max' => 3.0,
						'step' => 0.1,
					),
					'em' => array(
						'min' => 0.0,
						'max' => 3.0,
						'step' => 0.1,
					),
				),
				'std' => '1.2rem',
				// 'is_responsive' => TRUE,
				'usb_preview' => array(
					'css' => '--hwrapper-gap',
				),
			),
			'wrap' => array(
				'switch_text' => __( 'Allow move items to the next line', 'us' ),
				'type' => 'switch',
				'std' => 0,
				'usb_preview' => array(
					'toggle_class' => 'wrap',
				),
			),
			'stack_on_mobiles' => array(
				'switch_text' => __( 'Show items in one column on mobiles', 'us' ),
				'type' => 'switch',
				'std' => 0,
				'usb_preview' => array(
					'toggle_class' => 'stack_on_mobiles',
				),
			),
			'link' => array(
				'title' => us_translate( 'Link' ),
				'description' => __( 'All inner elements become not clickable.', 'us' ),
				'type' => 'link',
				'dynamic_values' => TRUE,
				'std' => '{"url":""}',
			),
		),

		$effect_options_params,
		$conditional_params,
		$design_options_params,
		$hover_options_params
	),
);
