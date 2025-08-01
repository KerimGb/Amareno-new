<?php defined( 'ABSPATH' ) OR die( 'This script cannot be accessed directly.' );

/**
 * Configuration for shortcode: vc_column
 */

$conditional_params = us_config( 'elements_conditional_options' );
$design_options_params = us_config( 'elements_design_options' );
$effect_options_params = us_config( 'elements_effect_options' );

/**
 * General section
 *
 * @var array
 */
$general_params = array(
	'sticky' => array(
		'switch_text' => __( 'Fix this column at the top of a page during scroll', 'us' ),
		'type' => 'switch',
		'std' => 0,
		'usb_preview' => array(
			'toggle_class' => 'type_sticky',
		),
	),
	'sticky_pos_top' => array(
		'title' => __( 'Sticky Column Top Position', 'us' ),
		'description' => __( 'Set the distance from the top of a page where the column will stick.', 'us' ) . ' ' . __( 'Leave blank to use the default.', 'us' ) . ' ' . __( 'Examples:', 'us' ) . ' <span class="usof-example">0</span>, <span class="usof-example">80px</span>, <span class="usof-example">6rem</span>',
		'type' => 'text',
		'std' => '',
		'show_if' => array( 'sticky', '=', 1 ),
		'usb_preview' => array(
			'elm' => '.vc_column-inner',
			'css' => 'top',
		),
	),
	'stretch' => array(
		'switch_text' => __( 'Stretch to the screen edge', 'us' ),
		'type' => 'switch',
		'std' => 0,
		'usb_preview' => array(
			'toggle_class' => 'stretched',
		),
	),
	'us_bg_overlay_color' => array(
		'title' => __( 'Background Overlay', 'us' ),
		'type' => 'color',
		'clear_pos' => 'right',
		'std' => '',
		'usb_preview' => array(
			'elm' => '.vc_column-overlay:first',
			'css' => 'background',
		),
	),
	'link' => array(
		'title' => us_translate( 'Link' ),
		'description' => __( 'All inner elements become not clickable.', 'us' ),
		'type' => 'link',
		'dynamic_values' => TRUE,
		'std' => '{"url":""}',
	),
);

$remove_params = array(
	'css_animation',
	'parallax',
	'parallax_image',
	'parallax_speed_bg',
	'parallax_speed_video',
	'video_bg',
	'video_bg_parallax',
	'video_bg_url',
);

// If the Grid CSS columns layout is used, remove column's WPB Responsive options
if ( us_get_option( 'live_builder' ) AND us_get_option( 'grid_columns_layout' ) ) {
	$remove_params[] = 'width';
	$remove_params[] = 'offset';
}

/**
 * @return array
 */
return array(
	'title' => __( 'Column', 'us' ),
	'category' => __( 'Containers', 'us' ),
	'icon' => 'fas fa-columns',
	'is_container' => TRUE,
	'hide_on_adding_list' => TRUE,
	'as_child' => array(
		'only' => 'vc_row',
	),
	'usb_preload' => TRUE,
	'usb_root_container_selector' => (
		us_get_option( 'grid_columns_layout' )
			? '.vc_column-inner:first'
			: '.vc_column-inner:first > .wpb_wrapper'
	),
	'params' => us_set_params_weight(
		$general_params,
		$effect_options_params,
		$conditional_params,
		$design_options_params
	),

	// Default VC params which are not supported by the theme
	'vc_remove_params' => $remove_params,

	// Not used params, required for correct fallback
	'fallback_params' => array(
		'animate',
		'animate_delay',
	),
);
