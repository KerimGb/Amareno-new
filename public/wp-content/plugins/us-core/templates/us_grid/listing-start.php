<?php defined( 'ABSPATH' ) OR die( 'This script cannot be accessed directly.' );

/**
 * Opening part of Grid output
 */

global $us_used_grid_layouts;
$us_used_grid_layouts = $us_used_grid_layouts ?? array();

$us_grid_index = $us_grid_index ?? 0;
$is_widget = $is_widget ?? FALSE;
$filter_html = $filter_html ?? '';
$data_atts = $data_atts ?? array();

// Set that grid starts showing its items
global $us_grid_outputs_items;
$us_grid_outputs_items = TRUE;

// Set global variable for Image size to use in grid layout elements
global $us_grid_img_size;
$us_grid_img_size = ( ! empty( $img_size ) AND $img_size != 'default' ) ? $img_size : NULL;

// Check Grid params and use default values from config, if its not set (like in User List)
$default_grid_params = us_shortcode_atts( array(), 'us_grid' );
foreach ( $default_grid_params as $param => $value ) {
	if ( ! isset( $$param ) ) {
		$$param = $value;
	}
}

// Check Carousel params and use default values from config, if its not set
if ( $type == 'carousel' ) {
	$default_carousel_params = us_shortcode_atts( array(), 'us_carousel' );
	foreach ( $default_carousel_params as $param => $value ) {
		if ( ! isset( $$param ) ) {
			$$param = $value;
		}
	}
}

// Force items aspect ratio to "square" for Metro type
if ( $type == 'metro' ) {
	$items_ratio = '1x1';
}

// Check if grid items has specific Aspect Ratio
if ( $items_ratio != 'default' OR us_arr_path( $grid_layout_settings, 'default.options.fixed' ) ) {
	$items_have_ratio = TRUE;
} else {
	$items_have_ratio = FALSE;
}

// Grid HTML attributes
$grid_atts = array(
	'class' => 'w-grid',
	'id' => $grid_elm_id,
	'style' => '',
);
$grid_atts['class'] .= $classes ?? '';
$grid_atts['class'] .= ' type_' . $type;
$grid_atts['class'] .= ' layout_' . $items_layout;

// If the grid of type 'current_query' is output on the archive or search page,
// by default we will bind it to filters if there are
if (
	$post_type === 'current_query'
	AND (
		is_archive()
		OR is_search()
	)
) {
	$grid_atts['class'] .= ' used_by_grid_filter';
}

// Add Grid Layout extra class if it's not empty
if ( $_extra_class = trim( (string) us_arr_path( $grid_layout_settings, 'default.options.el_class', /* default */ '' ) ) ) {
	$grid_atts['class'] .= ' ' . $_extra_class;
}

// If there is no results, hide the grid
if ( $no_results ) {
	$grid_atts['class'] .= ' hidden';
}
if ( $type != 'metro' AND $type != 'carousel' ) {
	$grid_atts['class'] .= ' cols_' . $columns;
	$grid_atts['style'] .= '--columns:' . $columns . ';';
}
if ( $type != 'carousel' AND $items_valign ) {
	$grid_atts['class'] .= ' valign_center';
}
if ( $pagination != 'none' ) {
	$grid_atts['class'] .= ' pagination_' . $pagination;
}
if ( ! $items_have_ratio AND us_arr_path( $grid_layout_settings, 'default.options.overflow' ) ) {
	$grid_atts['class'] .= ' overflow_hidden';
}
if ( $filter_html ) {
	$grid_atts['class'] .= ' with_filters';
}

// Not available in Grid
if ( isset( $items_preload_style ) ) {
	$grid_atts['class'] .= ' preload_style_' . $items_preload_style;
}

// Old and new values to trigger opening grid items in popup
if ( strpos( $overriding_link, 'popup_post' ) !== FALSE ) {
	$grid_atts['class'] .= ' open_items_in_popup';
}

// Apply isotope script for Masonry
if ( $type === 'masonry' AND $columns > 1 ) {
	wp_enqueue_script( 'us-isotope' );

	$grid_atts['class'] .= ' with_isotope';
}

// Determine if Grid Layout elements have animations
$has_animation = FALSE;
if ( $layout_elms = (array) us_arr_path( $grid_layout_settings, 'data', array() ) ) {
	foreach( $layout_elms as $layout_elm ) {
		if ( ! empty( $layout_elm['css'] ) AND us_design_options_has_property( $layout_elm['css'], 'animation-name' ) ) {
			$has_animation = TRUE;
			break;
		}
	}
}

// Apply items appearance animation on loading
if ( $load_animation !== 'none' OR $has_animation ) {
	$grid_atts['class'] .= ' with_css_animation';
}

$list_atts = array(
	'class' => 'w-grid-list',
	'style' => '',
);

// Output attributes for Carousel type
if ( $type == 'carousel' ) {
	wp_enqueue_script( 'us-owl' );

	if ( $items != '1' ) {
		$grid_atts['class'] .= ' items_' . $items;
		$grid_atts['style'] .= '--items:' . $items . ';';
	} else {
		$items_gap = 0; // reset gap if one item is showing
	}

	$list_atts['class'] .= ' owl-carousel';
	$list_atts['class'] .= ' valign_' . $items_valign;
	$list_atts['class'] .= ' dotstyle_' . $dots_style;
	$list_atts['class'] .= ' navstyle_' . $arrows_style;
	$list_atts['class'] .= ' navpos_' . $arrows_pos;
	$list_atts['class'] .= ' owl-responsive-2000'; // needed for responsive states switch
	$list_atts['style'] = '--transition-duration:' . $transition_speed . ';';
	if ( us_design_options_has_property( $css, array( 'height', 'max-height' ) ) ) {
		$list_atts['class'] .= ' has_height';
	}

	if ( $items == '1' AND $autoheight ) {
		$list_atts['class'] .= ' autoheight';
	}
	if ( $center_item ) {
		$list_atts['class'] .= ' center_item';
	}
	if ( $dots ) {
		$list_atts['class'] .= ' with_dots';
	}
	if ( $arrows ) {
		$list_atts['class'] .= ' with_arrows';
		if ( ! empty( $arrows_size ) ) {
			$list_atts['style'] .= '--arrows-size:' . $arrows_size . ';';
		}
		if ( ! in_array( $arrows_offset, array( '', '0', '0em', '0px' ) ) ) {
			$list_atts['style'] .= '--arrows-offset:' . $arrows_offset . ';';
		}
	}
}

// Add gap value as CSS var if it's not empty
if ( ! empty( $items_gap ) ) {
	$grid_atts['style'] .= '--gap:' . $items_gap . ';';
}

$current_grid_css = '';

// Generate responsive CSS for 3 breakpoints
if ( ! in_array( $type, array( 'carousel', 'metro' ) ) AND ! $is_widget ) {
	for ( $i = 1; $i < 4; $i ++ ) {
		$breakpoint_width = (int) ${'breakpoint_' . $i . '_width'};

		$breakpoint_cols = ( ${'breakpoint_' . $i . '_cols'} == 'default' )
			? $columns
			: (int) ${'breakpoint_' . $i . '_cols'};

		// Columns amount
		if ( $breakpoint_cols AND $breakpoint_cols < $columns ) {
			$current_grid_css .= '@media (max-width:' . ( $breakpoint_width - 1 ) . 'px) {';
			$current_grid_css .= '#' . $grid_elm_id . ' { --columns: ' . $breakpoint_cols . '!important }';
			$current_grid_css .= '}';
		}

		// Gap between items
		if ( isset( ${'breakpoint_' . $i . '_gap'} ) AND ${'breakpoint_' . $i . '_gap'} != '' ) {
			$current_grid_css .= '@media (max-width:' . ( $breakpoint_width - 1 ) . 'px) {';
			$current_grid_css .= '#' . $grid_elm_id . ' { --gap:' . str_replace( '%', 'cqw', ( ${'breakpoint_' . $i . '_gap'} ) ) . '!important }';
			$current_grid_css .= '}';
		}

		// Quantity
		if (
			$pagination == 'none'
			AND isset( ${'breakpoint_' . $i . '_quantity'} )
			AND $_quantity = ${'breakpoint_' . $i . '_quantity'}
		) {
			$_min_width = ${'breakpoint_' . ( $i + 1 ) . '_width'} ?? 0;

			$current_grid_css .= '@media (max-width:' . ( $breakpoint_width - 1 ) . 'px) and (min-width:' . (int) $_min_width . 'px) {';
			$current_grid_css .= '#' . $grid_elm_id . ' .w-grid-item:nth-child(n+' . ( $_quantity + 1 ) . ') { display: none !important }';
			$current_grid_css .= '}';
		}
	}
}

// Add Post Title font-size for current Grid only
if ( trim( $title_size ) AND ! $is_widget ) {
	$current_grid_css .= '@media (min-width:' . us_get_option( 'tablets_breakpoint', '1024px' ) . ') {';
	$current_grid_css .= '#' . $grid_elm_id . ' .w-post-elm.post_title { font-size: ' . esc_attr( $title_size ) . ' !important }';
	$current_grid_css .= '}';
}

$grid_layout_css = '';

// Generate CSS for items Aspect Ratio
if ( $items_have_ratio ) {

	// Aspect Ratio from used Grid Layout
	if ( $items_ratio == 'default' ) {
		$layout_ratio = us_arr_path( $grid_layout_settings, 'default.options.ratio' );
		$layout_ratio_width = us_arr_path( $grid_layout_settings, 'default.options.ratio_width' );
		$layout_ratio_height = us_arr_path( $grid_layout_settings, 'default.options.ratio_height' );

		$ratio_array = us_get_aspect_ratio_values( $layout_ratio, $layout_ratio_width, $layout_ratio_height );

		$grid_atts['class'] .= ' ratio_' . $layout_ratio;

		// Aspect Ratio from the current Grid settings
	} else {
		$ratio_array = us_get_aspect_ratio_values( $items_ratio, $items_ratio_width, $items_ratio_height );

		$grid_atts['class'] .= ' ratio_' . $items_ratio;
	}

	$grid_atts['style'] .= '--item-ratio:' . round( $ratio_array[1] / $ratio_array[0], 5 ) . ';';
}

// Generate Grid Layout CSS, if it doesn't previously added
if ( ! in_array( $items_layout, $us_used_grid_layouts ) ) {
	$item_bg_color = us_arr_path( $grid_layout_settings, 'default.options.color_bg' );
	$item_bg_color = us_get_color( $item_bg_color, /* Gradient */ TRUE );
	$item_text_color = us_arr_path( $grid_layout_settings, 'default.options.color_text' );
	$item_text_color = us_get_color( $item_text_color );
	$item_bg_img_source = us_arr_path( $grid_layout_settings, 'default.options.bg_img_source' );
	$item_bg_file_size = us_arr_path( $grid_layout_settings, 'default.options.bg_file_size', 'full' );
	$item_border_radius = (float) us_arr_path( $grid_layout_settings, 'default.options.border_radius' );
	$item_box_shadow = (float) us_arr_path( $grid_layout_settings, 'default.options.box_shadow' );
	$item_box_shadow_hover = (float) us_arr_path( $grid_layout_settings, 'default.options.box_shadow_hover' );

	// Generate Background Image output
	$item_bg_img = '';
	if (
		$item_bg_img_source == 'media'
		AND $item_bg_img_url = wp_get_attachment_image_url( us_arr_path( $grid_layout_settings, 'default.options.bg_img' ), $item_bg_file_size )
	) {
		$item_bg_img .= 'url(' . $item_bg_img_url . ') ';
		$item_bg_img .= us_arr_path( $grid_layout_settings, 'default.options.bg_img_position' );
		$item_bg_img .= '/';
		$item_bg_img .= us_arr_path( $grid_layout_settings, 'default.options.bg_img_size' );
		$item_bg_img .= ' ';
		$item_bg_img .= us_arr_path( $grid_layout_settings, 'default.options.bg_img_repeat' );

		// If the color value contains gradient, add comma for correct appearance
		if ( us_is_gradient( $item_bg_color ) ) {
			$item_bg_img .= ',';
		}
	}

	$grid_layout_css .= '.layout_' . $items_layout . ' .w-grid-item-h {';
	if ( $item_bg_img != '' OR $item_bg_color != '' ) {
		$grid_layout_css .= 'background:' . $item_bg_img . ' ' . $item_bg_color . ';';
	}
	if ( ! empty( $item_text_color ) ) {
		$grid_layout_css .= 'color:' . $item_text_color . ';';
	}
	if ( ! empty( $item_border_radius ) ) {
		$grid_layout_css .= 'border-radius:' . $item_border_radius . 'rem;';
	}
	if ( ! empty( $item_box_shadow ) OR ! empty( $item_box_shadow_hover ) ) {
		$grid_layout_css .= 'box-shadow:';
		$grid_layout_css .= '0 ' . round( $item_box_shadow / 10, 2 ) . 'rem ' . round( $item_box_shadow / 5, 2 ) . 'rem rgba(0,0,0,0.1),';
		$grid_layout_css .= '0 ' . round( $item_box_shadow / 3, 2 ) . 'rem ' . round( $item_box_shadow, 2 ) . 'rem rgba(0,0,0,0.1);';
		$grid_layout_css .= 'transition-duration: 0.3s;';
	}
	$grid_layout_css .= '}';
	if ( $item_box_shadow_hover != $item_box_shadow AND ! us_amp() ) {
		$grid_layout_css .= '.no-touch .layout_' . $items_layout . ' .w-grid-item-h:hover { box-shadow:';
		$grid_layout_css .= '0 ' . round( $item_box_shadow_hover / 10, 2 ) . 'rem ' . round( $item_box_shadow_hover / 5, 2 ) . 'rem rgba(0,0,0,0.1),';
		$grid_layout_css .= '0 ' . round( $item_box_shadow_hover / 3, 2 ) . 'rem ' . round( $item_box_shadow_hover, 2 ) . 'rem rgba(0,0,0,0.15);';
		$grid_layout_css .= 'z-index: 4;'; // needed for correct overlapping on hover
		$grid_layout_css .= '}';
	}
	// Define a global variable for the layout with dynamic values
	global $us_grid_layout_dynamic_values;
	$us_grid_layout_dynamic_values = array();

	// Generate Grid Layout elements CSS
	$grid_jsoncss_collection = array();
	foreach ( $grid_layout_settings['data'] as $elm_id => $elm ) {

		$elm_class = 'usg_' . str_replace( ':', '_', $elm_id );

		// CSS of Hover effects
		if ( ! empty( $elm['hover'] ) ) {
			$grid_layout_css .= '.layout_' . $items_layout . ' .' . $elm_class . '{';
			$grid_layout_css .= isset( $elm['transition_duration'] ) ? 'transition-duration:' . $elm['transition_duration'] . ';' : '';
			if ( isset( $elm['transform_origin_X'] ) AND isset( $elm['transform_origin_Y'] ) ) {
				$grid_layout_css .= 'transform-origin: ' . $elm['transform_origin_X'] . ' ' . $elm['transform_origin_Y'] . ';';
			}
			if ( isset( $elm['scale'] ) AND isset( $elm['translateX'] ) AND isset( $elm['translateY'] ) ) {
				$grid_layout_css .= 'transform: scale(' . $elm['scale'] . ') translate(' . $elm['translateX'] . ',' . $elm['translateY'] . ');';
			}
			$grid_layout_css .= ( isset( $elm['opacity'] ) AND (int) $elm['opacity'] != 1 ) ? 'opacity:' . $elm['opacity'] . ';' : '';
			$grid_layout_css .= '}';

			// Generate hover styles for not AMP only
			if ( ! us_amp() ) {
				$grid_layout_css .= '.layout_' . $items_layout . ' .w-grid-item-h:hover .' . $elm_class . '{';
				if ( isset( $elm['scale_hover'] ) AND isset( $elm['translateX_hover'] ) AND isset( $elm['translateY_hover'] ) ) {
					$grid_layout_css .= 'transform: scale(' . $elm['scale_hover'] . ') translate(' . $elm['translateX_hover'] . ',' . $elm['translateY_hover'] . ');';
				}
				$grid_layout_css .= isset( $elm['opacity_hover'] ) ? 'opacity:' . $elm['opacity_hover'] . ';' : '';

				if ( $color_bg_hover = us_arr_path( $elm, 'color_bg_hover', FALSE ) ) {
					$grid_layout_css .= sprintf( 'background: %s !important;', us_get_color( $color_bg_hover, /* Gradient */ TRUE ) );
				}
				if ( $color_border_hover = us_arr_path( $elm, 'color_border_hover', FALSE ) ) {
					$grid_layout_css .= sprintf( 'border-color: %s !important;', us_get_color( $color_border_hover ) );
				}
				if ( $color_text_hover = us_arr_path( $elm, 'color_text_hover', FALSE ) ) {
					$grid_layout_css .= sprintf( 'color: %s !important;', us_get_color( $color_text_hover ) );
				}

				$grid_layout_css .= '}';
			}
		}

		// Hide regarding 2 screen width breakpoints
		$elm_hide_below = isset( $elm['hide_below'] ) ? (int) $elm['hide_below'] : 0;
		$elm_hide_above = isset( $elm['hide_above'] ) ? (int) $elm['hide_above'] : 0;
		if ( ! empty( $elm_hide_below ) OR ! empty( $elm_hide_above ) ) {
			$grid_layout_css .= '@media';
			if ( $elm_hide_above ) {
				$grid_layout_css .= '(min-width:' . ( $elm_hide_above + 1 ) . 'px)';
			}
			if ( $elm_hide_above AND $elm_hide_below ) {
				$grid_layout_css .= ( $elm_hide_below > $elm_hide_above ) ? ' and ' : ' or ';
			}
			if ( $elm_hide_below ) {
				$grid_layout_css .= '(max-width:' . ( $elm_hide_below - 1 ) . 'px)';
			}
			$grid_layout_css .= '{';
			$grid_layout_css .= '.layout_' . $items_layout . ' .' . $elm_class . '{ display: none !important; }';
			$grid_layout_css .= '}';
		}

		// CSS Design Options
		if ( ! empty( $elm['css'] ) AND is_array( $elm['css'] ) ) {
			foreach ( (array) us_get_responsive_states( /* only keys */ TRUE ) as $state ) {
				if ( $css_options = us_arr_path( $elm, 'css.' . $state, FALSE ) ) {
					$css_selector = sprintf( 'layout_%s .{{grid-item-id}} .%s', $items_layout, $elm_class );

					// If there is a {{dynamic_variable}} in CSS property, save it to the separate global array
					$css_props = array(
						'color',
						'background-color',
						'background-image',
						'border-color',
						'text-shadow-color',
						'box-shadow-color',
					);
					foreach ( $css_props as $css_prop ) {
						if (
							! isset( $css_options[ $css_prop ] )
							OR ! us_is_dynamic_variable( $css_options[ $css_prop ] )
						) {
							continue;
						}
						// Add all properties for the current iteration
						if ( strpos( $css_prop, 'background' ) === 0 ) {
							$current_props = array(
								'background-color',
								'background-image',
								'background-repeat',
								'background-attachment',
								'background-position',
								'background-size',
							);
						} elseif ( strpos( $css_prop, 'text-shadow' ) === 0 ) {
							$current_props = array(
								'text-shadow-h-offset',
								'text-shadow-v-offset',
								'text-shadow-blur',
								'text-shadow-color',
							);
						} elseif ( strpos( $css_prop, 'box-shadow' ) === 0 ) {
							$current_props = array(
								'box-shadow-h-offset',
								'box-shadow-v-offset',
								'box-shadow-blur',
								'box-shadow-spread',
								'box-shadow-color',
							);
						} else {
							$current_props = array( $css_prop );
						}
						foreach ( $current_props as $current_prop ) {
							if ( ! isset( $css_options[ $current_prop ] ) ) {
								continue;
							}
							$us_grid_layout_dynamic_values[ $state ][ $css_selector ][ $current_prop ] = $css_options[ $current_prop ];
							unset( $css_options[ $current_prop ] );
						}
					}

					$css_options = apply_filters( 'us_replace_variable_color_with_value', $css_options );
					foreach ( $css_options as $prop_name => $prop_value ) {
						$grid_jsoncss_collection[ $state ][ 'layout_' . $items_layout . ' .' . $elm_class ][ $prop_name ] = $prop_value;
					}
				}
			}
		}
	}

	$grid_layout_css .= us_jsoncss_compile( $grid_jsoncss_collection );
}

// Define if the Grid is available for filtering via Grid Filter and sorting via Grid Order
global $us_is_page_block_in_no_results, $us_is_page_block_in_menu, $us_is_page_has_current_query_grid, $us_grid_item_type;
if (
	! $filter_html
	AND $type !== 'carousel'
	AND $us_grid_item_type !== 'term' // skip because it is not possible to filter terms through grid filters
	AND ! $us_is_page_block_in_menu // skip for grid inside Reusable Block in Header Menu
	AND ! $us_is_page_block_in_no_results // skip for grid inside Reusable Block in "no results"
) {
	if ( is_archive() OR is_search() ) {
		$grid_atts['data-filterable'] = 'true';
	} elseif (
		! $us_is_page_has_current_query_grid // skip on the archive page when there is a current_query grid on the page
		AND ! us_post_type_is_available( $post_type, array(
			'ids',
			'ids_terms',
			'taxonomy_terms',
			'current_child_terms',
		) )
	) {
		$grid_atts['data-filterable'] = 'true';
	}
}

// Output the Grid semantics
echo '<div' . us_implode_atts( $grid_atts ) .'>';

// Add Grid Layout CSS, if it wasn't previously added
if ( ! in_array( $items_layout, $us_used_grid_layouts ) ) {
	$us_used_grid_layouts[] = $items_layout;
	$current_grid_css .= $grid_layout_css;
}

// Add CSS customizations/Grid Layout for the current Grid only
if ( ! empty( $current_grid_css ) ) {
	echo '<style>' . us_minify_css( $current_grid_css ) . '</style>';
}

echo $filter_html;

echo '<div' . us_implode_atts( $list_atts + $data_atts ) . '>';
