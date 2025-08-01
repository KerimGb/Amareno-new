<?php defined( 'ABSPATH' ) OR die( 'This script cannot be accessed directly.' );

/**
 * Shortcode: us_grid
 *
 * Dev note: if you want to change some of the default values or acceptable attributes, overload the element config.
 *
 */

if ( apply_filters( 'us_stop_grid_execution', FALSE ) ) {
	return;
}

global $us_grid_outputs_items, $us_grid_no_results, $us_is_page_block_in_no_results, $us_is_page_block_in_menu;

// Never output a Grid element inside other Grids
if ( ! empty( $us_grid_outputs_items ) ) {
	return;
}

// Define relevant values into global variable
$us_grid_no_results = array(
	'action' => $no_items_action,
	'message' => $no_items_message,
	'page_block' => $no_items_page_block,
);

// "Hide on" values are needed for the "No results" block
global $us_grid_hide_on_states;
$us_grid_hide_on_states = $hide_on_states;

// Grid indexes for CSS, start from 1
global $us_grid_index;
$us_grid_index = isset( $us_grid_index ) ? ( $us_grid_index + 1 ) : 1;

// Get current post id
$current_post_id = us_get_current_id();

global $us_page_block_ids;
if ( ! empty( $us_page_block_ids ) ) {
	$post_id = $us_page_block_ids[0];
} else {
	$post_id = $current_post_id;
}

// Grid indexes for ajax, start from 1
if (
	$shortcode_base != 'us_carousel'
	AND ! $us_is_page_block_in_menu
	AND ! $us_is_page_block_in_no_results
) {
	global $us_grid_ajax_indexes;
	$us_grid_ajax_indexes[ $post_id ] = isset( $us_grid_ajax_indexes[ $post_id ] )
		? ( $us_grid_ajax_indexes[ $post_id ] + 1 )
		: 1;
} else {
	$us_grid_ajax_indexes = NULL;
}

// Preparing the query
$query_args = $filter_taxonomies = array();
$filter_taxonomy_name = $filter_default_taxonomies = '';
$terms = FALSE; // init this as array in terms case

// Items per page
if ( $items_quantity < 1 ) {
	$items_quantity = 999;
}

// Force single item in Carousel for AMP version
if ( us_amp() AND $shortcode_base == 'us_carousel' ) {
	$items_quantity = 1;
}

/*
 * THINGS TO OUTPUT
 */

// Substituting specific post types instead of query depended for US Builder preview of Page Templates
if (
	usb_is_template_preview()
	AND us_post_type_is_available(
		$post_type,
		array(
			'related',
			'current_query',
			'current_child_pages',
			'current_child_terms',
			'product_upsells',
			'product_crosssell',
		)
	)
) {
	// First check if there are products present, since they have most of custom fields
	if (
		class_exists( 'woocommerce' )
		AND $count_posts = wp_count_posts( 'product' )
		AND $count_posts->publish > 1
	) {
		$post_type = 'product';

		// then check if there are products present
	} elseif (
		$count_posts = wp_count_posts( 'post' )
		AND $count_posts->publish > 1
	) {
		$post_type = 'post';

		// otherwise using pages
	} else {
		$post_type = 'page';
	}
}

// Singulars
if ( in_array( $post_type, array_keys( us_grid_available_post_types( TRUE ) ) ) ) {
	$query_args['post_type'] = explode( ',', $post_type );

	$atts = ! empty( $atts ) ? $atts : array();
	if ( empty( $atts['post_type'] ) ) {
		$atts['post_type'] = $post_type;
	}

	// Get selected taxonomies for $query_args
	$selected_taxonomies = us_grid_get_selected_taxonomies( $atts );
	if ( is_array( $selected_taxonomies ) AND ! empty( $selected_taxonomies ) ) {
		$query_args = array_merge( $query_args, $selected_taxonomies );
	}

	// Media attachments should have some differ arguments
	if ( $post_type == 'attachment' ) {
		if ( ! empty( $images ) ) {
			$query_args['post__in'] = explode( ',', $images );
		}
		$query_args['post_status'] = 'inherit';
		$query_args['post_mime_type'] = 'image';

		$query_args = apply_filters( 'us_grid_attachment_query', $query_args, $current_post_id );

	} else {

		// Proper post statuses
		$query_args['post_status'] = array( 'publish' => 'publish' );
		$query_args['post_status'] += (array) get_post_stati( array( 'public' => TRUE ) );

		// Add private states if user is capable to view them
		if ( is_user_logged_in() AND current_user_can( 'read_private_posts' ) ) {
			$query_args['post_status'] += (array) get_post_stati( array( 'private' => TRUE ) );
		}
		$query_args['post_status'] = array_values( $query_args['post_status'] );
	}

	// Data for filter
	if ( ! empty( $atts[ 'filter_' . $post_type ] ) ) {
		$filter_taxonomy_name = $atts[ 'filter_' . $post_type ];
		$terms_args = array(
			'hierarchical' => FALSE,
			'taxonomy' => $filter_taxonomy_name,
			'number' => 100,
			'update_term_meta_cache' => FALSE,
		);

		// When choosing taxonomies in the settings, we display only the selected
		if ( ! empty( $atts[ 'taxonomy_' . $filter_taxonomy_name ] ) ) {
			$terms_args['slug'] = explode( ',', $atts[ 'taxonomy_' . $filter_taxonomy_name ] );

			// For logged in users, need to show private posts
			if ( is_user_logged_in() ) {
				$terms_args['hide_empty'] = FALSE;
			}
			$filter_default_taxonomies = $atts[ 'taxonomy_' . $filter_taxonomy_name ];
		}

		$filter_taxonomies = get_terms( $terms_args );
		if ( is_user_logged_in() ) {

			// Show private posts, but exclude empty posts
			foreach ( $filter_taxonomies as $key => $filter_term ) {
				if ( is_object( $filter_term ) AND $filter_term->count == 0 ) {
					$the_query = new WP_Query(
						array(
							'tax_query' => array(
								array(
									'taxonomy' => $filter_term->taxonomy,
									'field' => 'slug',
									'terms' => $filter_term->slug,
								),
							),
						)
					);

					// Unset empty terms
					if ( ! ( $the_query->have_posts() ) ) {
						unset ( $filter_taxonomies[ $key ] );
					}
				}
			}
		}
		if (
			isset( $filter_show_all )
			AND ! $filter_show_all
			AND ! empty( $filter_taxonomies[0] )
			AND $filter_taxonomies[0] instanceof WP_Term
		) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => $filter_taxonomy_name,
					'field' => 'slug',
					'terms' => $filter_taxonomies[0]->slug,
				),
			);
		}
	}

	// Specific items by IDs
} elseif ( $post_type == 'ids' ) {
	if ( empty( $ids ) ) {
		us_grid_shows_no_results();

		return;
	}

	$ids = explode( ',', $ids );
	$query_args['ignore_sticky_posts'] = 1;
	$query_args['post_type'] = 'any';
	$query_args['post__in'] = array_map( 'trim', $ids );

	// Items with the same taxonomy of current post
} elseif ( $post_type == 'related' ) {
	if ( ! is_singular() OR empty( $related_taxonomy ) ) {
		return;
	}

	if ( $related_post_type ) {
		$query_args['post_type'] = explode( ',', $related_post_type );
	} else {
		$query_args['post_type'] = 'any';
	}

	$query_args['ignore_sticky_posts'] = 1;
	$query_args['tax_query'] = array(
		array(
			'taxonomy' => $related_taxonomy,
			'terms' => wp_get_object_terms( $current_post_id, $related_taxonomy, array( 'fields' => 'ids' ) ),
		),
	);

	// Product upsells (WooCommerce only)
} elseif ( $post_type == 'product_upsells' ) {

	$upsell_ids = get_post_meta( $current_post_id, '_upsell_ids', TRUE );

	if ( ! is_singular( 'product' ) OR empty( $upsell_ids ) ) {
		us_grid_shows_no_results();

		return;
	}

	$query_args['post_type'] = array( 'product', 'product_variation' );
	$query_args['post__in'] = (array) $upsell_ids;

	// Product cross-sells (WooCommerce only)
} elseif ( $post_type == 'product_crosssell' ) {

	// Cart Cross-sells
	if ( function_exists( 'is_cart' ) AND is_cart() ) {
		$crosssell_ids = array();
		$cross_sells = array_filter( array_map( 'wc_get_product', WC()->cart->get_cross_sells() ), 'wc_products_array_filter_visible' );

		if ( count( $cross_sells ) ) {
			foreach ( $cross_sells as $cross_sell ) {
				$crosssell_ids[] = $cross_sell->get_id();
			}
		}
		// Single Product Cross-sells
	} elseif ( is_singular( 'product' ) ) {
		$crosssell_ids = get_post_meta( $current_post_id, '_crosssell_ids', TRUE );

	} else {
		return;
	}

	// Pass a negative number to reject random goods
	if ( empty( $crosssell_ids ) ) {
		$crosssell_ids = array( -1 );
	}

	$query_args['post_type'] = array( 'product', 'product_variation' );
	$query_args['post__in'] = (array) $crosssell_ids;

	// For all builder preview pages except single product edit page - display any available products
	if ( usb_is_post_preview() AND ! is_singular( 'product' ) ) {
		unset( $query_args['post__in'] );
	}

	// Child posts of current
} elseif ( $post_type == 'current_child_pages' ) {
	$query_args['post_parent'] = $current_post_id;
	$query_args['post_type'] = 'any';
	$query_args['ignore_sticky_posts'] = 1;

	// Terms of selected (or current) taxonomy
} elseif ( in_array( $post_type, array( 'taxonomy_terms', 'current_child_terms', 'ids_terms' ) ) ) {
	$current_term_id = $parent = 0;
	$hide_empty = TRUE;
	if (
		strpos( $terms_include, 'children' ) !== FALSE
		// Product attributes do not support nesting but are stored in a common table where there
		// is after `parent` which will be taken into account when using parameter `parent` in
		// the request arguments. Therefore, for correct work, you need to use the
		// default value ($terms_args_query['parent'] = '') for this taxonomy.
		OR strpos( $related_taxonomy, 'pa_' ) === 0
	) {
		$parent = '';
	}
	if ( strpos( $terms_include, 'empty' ) !== FALSE ) {
		$hide_empty = FALSE;
	}

	// If the current page is taxonomy page, we will output its children terms only
	if ( $post_type == 'current_child_terms' ) {
		if ( ! is_tag() AND ! is_category() AND ! is_tax() ) {
			return;
		}
		$current_term = get_queried_object();
		$related_taxonomy = $current_term->taxonomy;
		if ( strpos( $terms_include, 'children' ) !== FALSE ) {
			$current_term_id = $current_term->term_id;
		} else {
			$parent = $current_term->term_id;
		}
	}

	if ( $terms_orderby != 'rand' ) {
		// When using parameter `parent`, parameter `number` is ignored
		$terms_args_query = array(
			'taxonomy' => $related_taxonomy,
			'orderby' => $terms_orderby,
			'order' => ( $terms_orderby == 'count' ) ? 'DESC' : 'ASC',
			'number' => $items_quantity,
			'hide_empty' => $hide_empty,
			'child_of' => $current_term_id,
			'parent' => $parent, // Default is empty string
		);

		// Manually selected terms
		if ( $post_type == 'ids_terms' ) {
			if ( empty( $ids_terms ) ) {
				us_grid_shows_no_results();

				return;
			} else {
				if ( $terms_orderby == 'menu_order' ) {
					$terms_orderby = 'include';
				}
				$terms_args_query = array(
					'orderby' => $terms_orderby,
					'order' => ( $terms_orderby == 'count' ) ? 'DESC' : 'ASC',
					'number' => $items_quantity,
					'include' => array_map( 'trim', explode( ',', $ids_terms ) ),
				);
			}
		}
		$terms_raw = get_terms( $terms_args_query );
	} else {
		global $wpdb;
		$terms_query_where = '';
		if ( $post_type == 'ids_terms' ) {
			if ( empty( $ids_terms ) ) {
				us_grid_shows_no_results();

				return;
			} else {
				$terms_query_ids = array_map( 'intval', explode( ',', $ids_terms ) );
				$terms_query_where .= ' t.term_id IN(' . implode( ',', $terms_query_ids ) . ')';
			}
		} else {
			$terms_query_where .= '  tt.taxonomy="' . esc_sql( $related_taxonomy ) . '"';
		}
		if ( $hide_empty ) {
			$terms_query_where .= ' AND tt.count > 0';
		}
		if ( $parent !== '' AND ( $post_type !== 'ids_terms' ) ) {
			$terms_query_where .= ' AND tt.parent = ' . (int) $parent;
		}
		$terms_query = "
			SELECT
				t.*, tt.*
			FROM {$wpdb->terms} AS t
			INNER JOIN {$wpdb->term_taxonomy} AS tt
				ON t.term_id = tt.term_id
			WHERE
				 $terms_query_where
			ORDER BY RAND()
			LIMIT %d
		";
		$terms_query = $wpdb->prepare( $terms_query, $items_quantity );
		$terms_raw = $wpdb->get_results( $terms_query );
	}

	$terms = array();

	// When taxonomy doesn't exist, it returns WP_Error object, so we need to use empty array for further work
	if ( ! is_wp_error( $terms_raw ) ) {

		$ids_terms_map = ( $post_type == 'ids_terms' AND ! empty( $ids_terms ) )
			? array_flip( array_map( 'trim', explode( ',', $ids_terms ) ) )
			: array();

		$available_taxonomy = us_get_taxonomies( TRUE, FALSE );
		foreach ( $terms_raw as $key => $term_item ) {
			// if taxonomy of this term is not available, remove it
			if ( is_object( $term_item ) ) {
				if ( in_array( $term_item->taxonomy, array_keys( $available_taxonomy ) ) ) {
					if ( isset( $ids_terms_map[ $term_item->term_id ] ) ) {
						$terms[ $ids_terms_map[ $term_item->term_id ] ] = $term_item;
					} else {
						$terms[] = $term_item;
					}
				}
			}
		}

		// Apply sorting if it is not by title (name) and rand
		if ( $terms_orderby !== 'name' and $terms_orderby !== 'rand' ) {
			ksort( $terms );
		}
	}

	// Generate query for "Gallery" and "Post Object" types from ACF PRO plugin
} elseif ( preg_match( '/^acf_(gallery|posts|related)_?/', $post_type, $matches ) ) {

	$meta_key = str_replace( $matches[ /* full prefix */0 ], '', $post_type );

	// Get a custom value without the ACF "return Format"
	$post__in = us_get_custom_field( $meta_key, /* acf_format */FALSE );

	// Don't show the Grid, if there is no posts
	if ( empty( $post__in ) ) {
		us_grid_shows_no_results();

		return;
	}

	// ACF Galleries
	if ( $matches[ /* type */1 ] === 'gallery' ) {
		$query_args['post_type'] = 'attachment';
		$query_args['post_status'] = 'inherit';
	}

	// ACF Post objects
	if ( $matches[ /* type */1 ] === 'posts' OR $matches[ /* type */1 ] === 'related' ) {
		$query_args['post_type'] = 'any';
		$query_args['ignore_sticky_posts'] = 1;

		if ( is_user_logged_in() AND current_user_can( 'read_private_posts' ) ) {
			$query_args['post_status'] = 'any';
		}
	}

	$query_args['post__in'] = is_array( $post__in )
		? $post__in
		: array( $post__in );

	// Values from predefined custom fields
} elseif ( strpos( $post_type, 'cf|' ) === 0 ) {
	$key = str_replace( 'cf|', '', $post_type );

	// Get images from metabox "Additional Settings"
	if ( $key === 'us_tile_additional_image' ) {

		// Include Featured image
		if ( $include_post_thumbnail AND $post_thumbnail_id = get_post_thumbnail_id() ) {
			$ids = array( $post_thumbnail_id );
		} else {
			$ids = array();
		}

		if ( $custom_images = get_post_meta( $current_post_id, $key, TRUE ) ) {
			$ids = array_merge( $ids, explode( ',', $custom_images ) );
		}

		if ( $ids ) {
			$query_args['post__in'] = $ids;
			$query_args['post_status'] = 'inherit';
			$query_args['post_mime_type'] = 'image';
			$query_args['post_type'] = 'attachment';
		} else {
			us_grid_shows_no_results();

			return;
		}
	}

	// Product gallery images
} elseif ( $post_type == 'product_gallery' ) {
	if ( ! is_singular( 'product' ) ) {
		return;
	}

	// Include Featured image
	if ( $include_post_thumbnail AND $post_thumbnail_id = get_post_thumbnail_id() ) {
		$ids = array( $post_thumbnail_id );
	} else {
		$ids = array();
	}

	if ( $product_images = get_post_meta( $current_post_id, '_product_image_gallery', TRUE ) ) {
		$ids = array_merge( $ids, explode( ',', $product_images ) );
	}

	// Remove empty ids to avoid duplications in output
	$ids = array_diff( $ids, array( '' ) );

	if ( $ids ) {
		$query_args['post__in'] = $ids;
		$query_args['post_status'] = 'inherit';
		$query_args['post_mime_type'] = 'image';
		$query_args['post_type'] = 'attachment';
	} else {
		us_grid_shows_no_results();

		return;
	}
}

// Always exclude the current post from the query
if ( is_singular() ) {
	$query_args['post__not_in'] = array( $current_post_id );
}

// Exclude sticky posts
if ( ! empty( $ignore_sticky ) ) {
	$query_args['ignore_sticky_posts'] = 1;
}

// Fallback (after version 7.11)
if ( $orderby == 'alpha' ) {
	$orderby = 'title';
}

// Begin set orderby params to $query_args
$orderby_params = array(
	'custom_field' => $orderby_custom_field,
	'custom_field_numeric' => $orderby_custom_type,
	'invert' => $order_invert,
	'orderby' => $orderby,
	'post_type' => $post_type,
);

// Apply Grid OrderBy params
global $us_get_orderby, $us_is_grid_assigned_for;
if ( ! $us_is_grid_assigned_for ) {
	$us_is_grid_assigned_for = array();
}
$get_orderby = us_arr_path( $_GET, us_get_grid_url_prefix( 'order' ), $us_get_orderby );
if (
	! empty( $get_orderby )
	AND $shortcode_base != 'us_carousel'
	AND empty( $filter_taxonomies ) // checks if built-in filter is disabled
	AND empty( $us_is_grid_assigned_for['grid_order'] )
	AND ! $us_is_page_block_in_menu // skip for grid inside Reusable Block in Header Menu
	AND ! $us_is_page_block_in_no_results // skip for grid inside Reusable Block in "no results"
	AND ! us_post_type_is_available(
		$post_type, array(
			'ids',
			'ids_terms',
			'taxonomy_terms',
			'current_child_terms',
		)
	)
) {
	$us_is_grid_assigned_for['grid_order'] = TRUE;
	$orderby_params = array_merge(
		$orderby_params,
		(array) us_grid_orderby_str_to_params( $get_orderby )
	);
}
unset( $get_orderby );

$orderby_query_args = array();
us_grid_set_orderby_to_query_args( $orderby_query_args, $orderby_params );
unset( $orderby_params );
// End set orderby params to $query_args

// Force "Numbered" pagination for AMP version to avoid AMP ajax developing
if ( us_amp() AND $pagination != 'none' ) {
	$pagination = 'regular';
}

// Pagination
if ( $pagination == 'regular' ) {
	// Fix for get_query_var() that is empty on AMP frontpage
	$request_paged = ( is_front_page() AND ! us_amp() ) ? 'page' : 'paged';

	if ( get_query_var( $request_paged ) ) {
		$query_args['paged'] = get_query_var( $request_paged );
	}
}

// Extra arguments for WooCommerce products
if (
	class_exists( 'woocommerce' )
	AND (
		us_is_grid_products_defined_by_query_args( $query_args )
		OR us_post_type_is_available(
			$post_type, array(
				'product',
				'product_upsells',
				'product_crosssell',
			)
		)
	)
) {

	$query_args['meta_query'] = array();

	// Exclude out of stock products
	if (
		$exclude_items == 'out_of_stock'
		OR get_option( 'woocommerce_hide_out_of_stock_items', 'none' ) === 'yes'
	) {
		$query_args['meta_query'][] = array(
			'key' => '_stock_status',
			'value' => 'outofstock',
			'compare' => '!=',
		);
	}

	// Show Sale products
	if ( strpos( $products_include, 'sale' ) !== FALSE ) {
		if ( function_exists( 'wc_get_product_ids_on_sale' ) AND ! empty( wc_get_product_ids_on_sale() ) ) {
			$query_args['post__in'] = wc_get_product_ids_on_sale();
		} else {
			us_grid_shows_no_results();

			return;
		}

	}

	// Show Featured products
	if ( strpos( $products_include, 'featured' ) !== FALSE ) {
		$query_args['tax_query'][] = array(
			'taxonomy' => 'product_visibility',
			'field' => 'name',
			'terms' => 'featured',
			'operator' => 'IN',
		);
	}
}

// Exclude "Hidden" products
if (
	class_exists( 'woocommerce' )
	AND us_post_type_is_available(
		$post_type, array(
			'ids',
			'related',
			'product',
			'product_upsells',
			'product_crosssell',
		)
	)
) {
	$query_args['tax_query'][] = array(
		'taxonomy' => 'product_visibility',
		'field' => 'slug',
		'terms' => array( 'exclude-from-catalog' ),
		'operator' => 'NOT IN',
	);
}

// Exclude posts of previous grids on the same page
if ( $exclude_items == 'prev' ) {
	global $us_post_ids_shown_by_grid;
	if ( ! empty( $us_post_ids_shown_by_grid ) AND is_array( $us_post_ids_shown_by_grid ) ) {
		if ( empty( $query_args['post__not_in'] ) OR ! is_array( $query_args['post__not_in'] ) ) {
			$query_args['post__not_in'] = array();
		}
		$query_args['post__not_in'] = array_merge( $query_args['post__not_in'], $us_post_ids_shown_by_grid );
	}
}

$query_args['posts_per_page'] = $items_quantity;

// Reset query for using on archives
if ( us_post_type_is_available( $post_type, array( 'current_query' ) ) ) {
	if ( is_tax( 'tribe_events_cat' ) OR is_post_type_archive( 'tribe_events' ) ) {
		$the_content = apply_filters( 'the_content', get_the_content() );

		// The page may be paginated itself via <!--nextpage--> tags
		$the_pagination = us_wp_link_pages();

		echo $the_content . $the_pagination;

		return;
	} elseif ( is_archive() OR is_search() OR is_home() ) {
		$query_args = NULL;
	} else {
		return;
	}
}

// Default query_args created from grid settings
$_default_query_args = array();
if ( ! empty( $query_args ) ) {
	foreach ( array( 'tax_query', 'meta_query' ) as $key ) {
		if ( ! empty( $query_args[ $key ] ) ) {
			$_default_query_args[ $key ] = $query_args[ $key ];
		}
	}
}

// Load Grid Listing template with given params
$template_vars = array(
	'_default_query_args' => $_default_query_args,
	'_atts' => isset( $_atts ) ? $_atts : array(),
	'classes' => isset( $classes ) ? $classes : '',
	'filter_default_taxonomies' => $filter_default_taxonomies,
	'filter_taxonomies' => $filter_taxonomies,
	'filter_taxonomy_name' => $filter_taxonomy_name,
	'orderby_query_args' => $orderby_query_args,
	'post_id' => $post_id,
	'terms' => $terms,
	'us_grid_post_type' => $post_type,
	'us_grid_ajax_indexes' => $us_grid_ajax_indexes,
	'us_grid_index' => $us_grid_index,
);

// Check if some Grid Filter shortcode is assigned for the current Grid
$has_assigned_grid_filter = FALSE;
$page_content = us_get_page_content( $current_post_id );
if ( preg_match( '/' . get_shortcode_regex( array( 'us_grid_filter' ) ) . '/', $page_content, $matches ) ) {
	$grid_filter_atts = shortcode_parse_atts( $matches[/* text_atts */3] );
	$use_grid = us_arr_path( $grid_filter_atts, 'use_grid', /* default */'first' );

	// If this is the first grid on a page and isn't assigned for Grid Filter yet
	if ( $use_grid == 'first' AND empty( $us_is_grid_assigned_for['grid_filter'] ) ) {
		$has_assigned_grid_filter = TRUE;

		// If this grid has CSS selector (class or id) specified in Grid Filter
	} elseif ( $use_grid == 'selector' AND $grid_selector = us_arr_path( $grid_filter_atts, 'grid_selector', '' ) ) {

		// Check by class
		if (
			strpos( $grid_selector, '.' ) === 0
			AND ! empty( $el_class )
			AND in_array( substr( $grid_selector, 1 ), explode( ' ', $el_class ) )
		) {
			$has_assigned_grid_filter = TRUE;
		}

		// Check by id
		if (
			strpos( $grid_selector, '#' ) === 0
			AND ! empty( $el_id )
			AND substr( $grid_selector, 1 ) == $el_id
		) {
			$has_assigned_grid_filter = TRUE;
		}
	}
}

// Apply Grid Filter params
global $us_is_page_has_current_query_grid;
if (
	! $us_is_page_has_current_query_grid
	AND $post_type != 'current_query'
	AND $shortcode_base != 'us_carousel'
	AND $has_assigned_grid_filter
	AND empty( $filter_taxonomies ) // checks if built-in filter is disabled
	AND ! $us_is_page_block_in_menu // skip for grid inside Reusable Block in Header Menu
	AND ! $us_is_page_block_in_no_results // skip for grid inside Reusable Block in "no results"
) {
	$us_is_grid_assigned_for['grid_filter'] = TRUE;
	us_apply_grid_filters( $query_args );
}

$template_vars['query_args'] = $query_args;

// Add default values for unset variables from Grid config
$default_grid_params = us_shortcode_atts( array(), 'us_grid' );
foreach ( $default_grid_params as $param => $value ) {
	$template_vars[ $param ] = isset( $$param ) ? $$param : $value;
}

// Add default values for unset variables from Carousel config
if ( $shortcode_base == 'us_carousel' ) {
	$default_carousel_params = us_shortcode_atts( array(), 'us_carousel' );
	foreach ( $default_carousel_params as $param => $value ) {
		$template_vars[ $param ] = isset( $$param ) ? $$param : $value;
	}
	$template_vars['type'] = 'carousel'; // force 'carousel' type for us_carousel shortcode
}

us_load_template( 'templates/us_grid/listing', $template_vars );
