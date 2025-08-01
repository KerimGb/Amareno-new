<?php defined( 'ABSPATH' ) OR die( 'This script cannot be accessed directly.' );

/**
 * [W]oo[C]ommerce Theme Support
 *
 * @link http://www.woothemes.com/woocommerce/
 */

if ( ! class_exists( 'woocommerce' ) ) {
	return FALSE;
}

add_action( 'after_setup_theme', 'us_wc_support' );
function us_wc_support() {
	add_theme_support(
		'woocommerce', array(
			'gallery_thumbnail_image_width' => 150, // changed gallery thumbnail size to default WP 'thumbnail'
		)
	);
	$product_gallery_options = us_get_option( 'product_gallery_options' );

	// Fallback for var type
	if ( is_array( $product_gallery_options ) ) {
		$product_gallery_options = implode( ',', $product_gallery_options );
	}

	// Replace gallery tag <a> to <div> if options empty
	if ( empty( $product_gallery_options ) ) {
		add_filter( 'woocommerce_single_product_image_thumbnail_html', 'us_wc_single_product_image_thumbnail_html' );
	}

	if ( strpos( $product_gallery_options, 'zoom' ) !== FALSE ) {
		add_theme_support( 'wc-product-gallery-zoom' );
	}
	if ( strpos( $product_gallery_options, 'lightbox' ) !== FALSE ) {
		add_theme_support( 'wc-product-gallery-lightbox' );
	}
	if ( us_get_option( 'product_gallery' ) == 'slider' ) {
		add_theme_support( 'wc-product-gallery-slider' );
	}
}

if ( ! function_exists( 'us_wc_single_product_image_thumbnail_html' ) ) {
	function us_wc_single_product_image_thumbnail_html( $html ) {
		return strip_tags( $html, '<div><img>' );
	}
}

// Change size of "Product gallery" thumbnails, when "Slider" is OFF, for showing like 1 column gallery
if ( us_get_option( 'product_gallery' ) !== 'slider' ) {
	add_filter( 'woocommerce_gallery_thumbnail_size', 'us_wc_gallery_thumbnail_size' );
	function us_wc_gallery_thumbnail_size() {
		return 'woocommerce_single';
	}
}

// Change columns count for Product gallery thumbs
if ( ! function_exists( 'us_wc_product_thumbnails_columns' ) ) {
	add_filter( 'woocommerce_product_thumbnails_columns', 'us_wc_product_thumbnails_columns', 100, 1 );
	function us_wc_product_thumbnails_columns( $cols ) {
		return (int) us_get_option( 'product_gallery_thumbs_cols', 4 );
	}
}

// Disable WooCommerce front CSS
add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );

// Disable select2 CSS on Checkout page
add_action( 'wp_enqueue_scripts', 'us_wc_dequeue_checkout_styles', 100 );
function us_wc_dequeue_checkout_styles() {
	wp_dequeue_style( 'select2' );
	wp_deregister_style( 'select2' );
}

// Disable WooCommerce Blocks assets if the Block Editor is disabled in Theme Options
if ( ! function_exists( 'us_disable_woocommerce_block_styles' ) ) {
	if ( ! us_get_option( 'block_editor' ) ) {
		add_action( 'wp_enqueue_scripts', 'us_disable_woocommerce_block_styles', 100 );
	}
	function us_disable_woocommerce_block_styles() {
		wp_deregister_style( 'wc-blocks-style' );
		wp_deregister_style( 'wc-block-editor' );
	}
}

// Enqueue minified CSS only when Optimize Assets is disabled (and DEV mode is disabled too)
if (
	! defined( 'US_DEV' )
	AND ! us_get_option( 'optimize_assets' )
	OR usb_is_post_preview()
) {
	add_action( 'wp_enqueue_scripts', 'us_wc_enqueue_styles', 14 );
}
function us_wc_enqueue_styles( $styles ) {
	global $us_template_directory_uri;
	wp_enqueue_style( 'us-woocommerce', $us_template_directory_uri . '/common/css/plugins/woocommerce.min.css', array(), US_THEMEVERSION, 'all' );
}

// Add classes to <body> of WooCommerce pages
add_action( 'body_class', 'us_wc_body_class' );
function us_wc_body_class( $classes ) {
	$classes[] = 'us-woo-cart_' . us_get_option( 'shop_cart', 'standard' );
	if ( us_get_option( 'shop_catalog' ) ) {
		$classes[] = 'us-woo-catalog';
	}

	return $classes;
}

/*
*************** Adjust HTML markup for all WooCommerce pages ***************
*/
add_action( 'template_redirect', 'us_maybe_change_woocommerce_template_path' );
function us_maybe_change_woocommerce_template_path() {
	$has_custom_template = FALSE;

	// Get WooCommerce taxonomies only
	$woo_taxonomies = array_keys( us_get_taxonomies( TRUE, FALSE, 'woocommerce_only' ) );

	// Get taxonomies linked to Products (created via CPT UI)
	$product_taxonomies = get_object_taxonomies( 'product' );

	// Check if the current page is Shop and it has custom Page Template
	if ( is_shop() AND us_get_option( 'content_shop_id' ) ) {
		$has_custom_template = TRUE;

		// Check if the Products Search Results has custom Page Template
	} elseif (
		is_post_type_archive( 'product' )
		AND is_search()
		AND us_get_option( 'content_shop_search_id' ) != '__defaults__'
	) {
		$has_custom_template = TRUE;

		// Check if the current page is WooCommerce taxonomy
	} elseif ( is_tax( $woo_taxonomies ) ) {
		$current_tax = get_query_var( 'taxonomy' );

		// Check if the current taxonomy has custom Page Template
		if ( us_get_option( 'content_tax_' . $current_tax . '_id' ) != '__defaults__' ) {
			$has_custom_template = TRUE;

		} elseif ( us_get_option( 'content_shop_id' ) ) {
			$has_custom_template = TRUE;

			// Check if the current term has custom Page Template for its Archive
		} elseif ( is_numeric( get_term_meta( get_queried_object_id(), 'archive_content_id', TRUE ) ) ) {
			$has_custom_template = TRUE;
		}

		// Check if the current page is Product custom taxonomy
	} elseif ( is_tax( $product_taxonomies ) ) {
		$current_tax = get_query_var( 'taxonomy' );

		// Check if the current taxonomy has custom Page Template
		if ( us_get_option( 'content_tax_' . $current_tax . '_id' ) != '__defaults__' ) {
			$has_custom_template = TRUE;
		} elseif ( us_get_option( 'content_archive_id' ) != '' ) {
			$has_custom_template = TRUE;
		}
	}

	// Change path to templates, if custom layout is set
	if ( $has_custom_template ) {
		add_filter( 'woocommerce_template_path', 'us_wc_template_path' );
		function us_wc_template_path() {
			return 'wc-templates/';
		}
	}
}

remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
if ( ! function_exists( 'us_wc_before_main_content' ) ) {
	function us_wc_before_main_content() {
		$show_shop_section = TRUE;

		if (
			is_single()
			AND $content_area_id = us_get_page_area_id( 'content' )
			AND get_post_status( $content_area_id ) != FALSE
		) {
			$show_shop_section = FALSE;
			add_filter( 'wc_get_template_part', 'us_wc_get_template_part_content_single_product', 10, 3 );
		}

		$classes = 'l-main';

		// Get all classes that are assigned to a product
		if ( is_product() AND function_exists( 'wc_get_product_class' ) ) {
			$product_id = get_queried_object_id();
			$classes = (string) implode( ' ', wc_get_product_class( $classes, $product_id ) );
		}

		echo '<main id="page-content" class="' . $classes . '">';

		if ( us_get_option( 'enable_sidebar_titlebar' ) ) {

			// Titlebar, if it is enabled in Theme Options
			us_load_template( 'templates/titlebar' );

			// START wrapper for Sidebar
			us_load_template( 'templates/sidebar', array( 'place' => 'before' ) );
		}

		// Output content of Shop page in a first separate section
		if (
			is_post_type_archive( 'product' )
			AND ! is_search()
			AND absint( get_query_var( 'paged' ) ) === 0
			AND $shop_page = get_post( wc_get_page_id( 'shop' ) )
			AND $shop_page_content = apply_filters( 'the_content', $shop_page->post_content )
		) {
			if ( strpos( $shop_page_content, ' class="l-section' ) === FALSE ) {
				$shop_page_content = '<section class="l-section for_shop_description"><div class="l-section-h i-cf">' . $shop_page_content . '</div></section>';
			}
			echo $shop_page_content;
		}

		if ( $show_shop_section ) {
			echo '<section id="shop" class="l-section height_' . us_get_option( 'row_height', 'medium' ) . ' for_shop">';
			echo '<div class="l-section-h i-cf">';
		}
	}

	add_action( 'woocommerce_before_main_content', 'us_wc_before_main_content', 10 );
}

function us_wc_get_template_part_content_single_product( $template, $slug, $name = '' ) {
	if ( $slug == 'content' AND $name == 'single-product' ) {

		// Output form only, if single Product is password protected
		if ( post_password_required() ) {
			echo '<section class="l-section height_' . us_get_option( 'row_height', 'medium' ) . '"><div class="l-section-h">' . get_the_password_form() . '</div></section>';

			return;
		} else {
			return us_locate_file( 'templates/content.php' );
		}

	} else {
		return $template;
	}
}

remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
if ( ! function_exists( 'us_wc_after_main_content' ) ) {
	add_action( 'woocommerce_after_main_content', 'us_wc_after_main_content', 20 );
	function us_wc_after_main_content() {
		$show_shop_section = TRUE;

		if (
			is_single()
			AND $content_area_id = us_get_page_area_id( 'content' )
			AND get_post_status( $content_area_id ) != FALSE
		) {
			$show_shop_section = FALSE;
		}

		if ( $show_shop_section ) {
			echo '</div></section>';
		}

		if ( us_get_option( 'enable_sidebar_titlebar' ) ) {
			// AFTER wrapper for Sidebar
			us_load_template( 'templates/sidebar', array( 'place' => 'after' ) );
		}
		echo '</main>';
	}
}

// Change columns number on Shop page (from Theme Options > Shop)
add_filter( 'loop_shop_columns', 'loop_columns' );
if ( ! function_exists( 'loop_columns' ) ) {
	function loop_columns() {
		return us_get_option( 'shop_columns', 4 );
	}
}

// Change items number on Shop page (from Theme Options > Shop)
add_filter( 'loop_shop_per_page', 'us_loop_shop_per_page' );
if ( ! function_exists( 'us_loop_shop_per_page' ) ) {
	function us_loop_shop_per_page() {
		return get_option( 'posts_per_page' );
	}
}

// Change Related Products quantity (from Theme Options > Shop)
add_filter( 'woocommerce_output_related_products_args', 'us_related_products_args' );
function us_related_products_args( $args ) {
	$args['posts_per_page'] = us_get_option( 'product_related_qty', 4 );
	$args['columns'] = us_get_option( 'product_related_qty', 4 );

	return $args;
}

// Change Cross-sells quantity (from Theme Options > Shop)
add_filter( 'woocommerce_cross_sells_total', 'us_wc_cross_sells_total' );
add_filter( 'woocommerce_cross_sells_columns', 'us_wc_cross_sells_total' );
function us_wc_cross_sells_total( $count ) {
	return us_get_option( 'product_related_qty', 4 );
}

// Change the reset variations link - remove the text to use only icon
add_filter( 'woocommerce_reset_variations_link', 'us_wc_reset_variations_link' );
function us_wc_reset_variations_link() {
	return '<a class="reset_variations" href="#" aria-label="' . esc_attr( us_translate( 'Clear options', 'woocommerce' ) ) . '"></a>';
}

// Remove default woocommerce sidebar
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

// Move cross sells bellow the shipping
remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );
add_action( 'woocommerce_after_cart', 'woocommerce_cross_sell_display', 10 );

// Move breadcrumbs before product title on Products default template
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
add_action( 'woocommerce_single_product_summary', 'woocommerce_breadcrumb', 3 );

// Alter Cart - add total number
add_filter( 'woocommerce_add_to_cart_fragments', 'us_add_to_cart_fragments' );
function us_add_to_cart_fragments( $fragments ) {
	global $woocommerce;

	$fragments['a.cart-contents'] = '<a class="cart-contents" href="' . esc_url( wc_get_cart_url() ) . '">' . $woocommerce->cart->get_cart_total() . '</a>';

	return $fragments;
}

// Correct pagination
if ( ! function_exists( 'woocommerce_pagination' ) ) {
	function woocommerce_pagination() {
		global $us_woo_disable_pagination;
		if ( isset( $us_woo_disable_pagination ) AND $us_woo_disable_pagination ) {
			return;
		}

		global $wp_query;
		if ( $wp_query->max_num_pages <= 1 ) {
			return;
		}
		the_posts_pagination(
			array(
				'mid_size' => 3,
				'before_page_number' => '<span>',
				'after_page_number' => '</span>',
			)
		);
	}
}

// Remove focus state on Checkout page
add_filter( 'woocommerce_checkout_fields', 'us_wc_disable_autofocus_billing_firstname' );
function us_wc_disable_autofocus_billing_firstname( $fields ) {
	$fields['shipping']['shipping_first_name']['autofocus'] = FALSE;

	return $fields;
}

// Wrap attributes <select> for better styling
add_filter( 'woocommerce_dropdown_variation_attribute_options_html', 'us_wc_dropdown_variation_attribute_options_html' );
function us_wc_dropdown_variation_attribute_options_html( $html ) {
	$html = '<div class="woocommerce-select">' . $html . '</div>';

	return $html;
}

// Add amount of products in cart to show in Header element
add_action( 'woocommerce_after_mini_cart', 'us_wc_after_mini_cart' );
function us_wc_after_mini_cart() {
	global $woocommerce;

	echo '<span class="us_mini_cart_amount" style="display: none;">' . $woocommerce->cart->cart_contents_count . '</span>';
}

// Wrap "Add To Cart" button's text with placehoders.
add_action( 'woocommerce_before_template_part', 'us_wc_before_loop_add_to_cart_template_part', 10, 4 );
function us_wc_before_loop_add_to_cart_template_part( $template_name, $template_path, $located, $args ) {
	if ( $template_name == 'loop/add-to-cart.php' ) {
		add_filter( 'woocommerce_product_add_to_cart_text', 'us_add_to_cart_text', 99, 2 );
		add_filter( 'woocommerce_loop_add_to_cart_link', 'us_add_to_cart_text_replace', 99, 3 );
	}
}

add_action( 'woocommerce_after_template_part', 'us_wc_after_loop_add_to_cart_template_part', 10, 4 );
function us_wc_after_loop_add_to_cart_template_part( $template_name, $template_path, $located, $args ) {
	if ( $template_name == 'loop/add-to-cart.php' ) {
		remove_filter( 'woocommerce_product_add_to_cart_text', 'us_add_to_cart_text', 99 );
		remove_filter( 'woocommerce_loop_add_to_cart_link', 'us_add_to_cart_text_replace', 99 );
	}
}

// Use placeholders instead of actual HTML semantics, because after this filter the esc_html() function is applied
function us_add_to_cart_text( $text, $product ) {
	$text = '{{us_add_to_cart_start}}' . $text . '{{us_add_to_cart_end}}';

	return $text;
}

// Replace placeholders with actual HTML wrapper for "Add To Cart" buttons
function us_add_to_cart_text_replace( $html, $product, $args ) {
	$html = str_replace( '{{us_add_to_cart_start}}', '<i class="g-preloader type_1"></i><span class="w-btn-label">', $html );
	$html = str_replace( '{{us_add_to_cart_end}}', '</span>', $html );

	return $html;
}

// Remove metaboxes from Shop page
add_filter( 'us_config_meta-boxes', 'us_remove_meta_for_shop_page' );
function us_remove_meta_for_shop_page( $config ) {
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : NULL;
	if ( $post_id !== NULL AND $post_id == get_option( 'woocommerce_shop_page_id' ) ) {
		foreach ( $config as $metabox_key => $metabox ) {
			if ( $metabox['id'] == 'us_portfolio_settings' ) {
				unset( $config[ $metabox_key ] );
			}
			if ( $metabox['id'] == 'us_page_settings' ) {
				$keys = array(
					'us_header_id',
					'us_header_sticky_pos',
					'us_titlebar_id',
					'us_sidebar_id',
					'us_sidebar_pos',
					'us_content_id',
					'us_footer_id',
				);
				foreach ( $keys as $key ) {
					if ( isset( $config[ $metabox_key ]['fields'][ $key ] ) ) {
						unset( $config[ $metabox_key ]['fields'][ $key ] );
					}
				}
			}
		}
	}

	return $config;
}

add_filter( 'us_stop_grid_execution', 'us_stop_grid_execution_wc_product_summary' );
function us_stop_grid_execution_wc_product_summary() {
	return doing_action( 'woocommerce_single_product_summary' );
}

if ( ! function_exists( 'us_wc_add_to_cart_message_html' ) ) {
	add_action( 'wc_add_to_cart_message_html', 'us_wc_add_to_cart_message_html', 10, 1 );
	/**
	 * Customizing add-to-cart messages for woocommerce notice
	 *
	 * @param string $message The HTML message
	 * @return string
	 */
	function us_wc_add_to_cart_message_html( $message ) {
		return preg_replace_callback(
			'/(<\s*a[^>]*>.*<\s*\/\s*a>)(.*)/',
			function ( $matches ) {
				return $matches[2] . ' ' . preg_replace( '/="button\s/', '="', $matches[1] );
			},
			$message
		);
	}
}

if ( ! function_exists( 'us_posts_clauses' ) ) {
	/**
	 * @param array $args The parameters for query request
	 * @return array
	 */
	function us_posts_clauses( $args, $wp_query ) {
		global $wpdb;
		$query_vars = $wp_query->query_vars;
		if (
			$query_vars['post_type'] === 'product'
			AND ! empty( $query_vars['orderby'] )
			AND in_array( $query_vars['orderby'], array( 'price', 'popularity', 'rating' ) )
		) {
			// Additional sorting for records that do not contain data in the adjacent table will allow you to organize the output.
			$args['orderby'] = rtrim( (string) $args['orderby'] ) . ', ' . $wpdb->posts . '.ID ' .
				( ( strrpos( $args['orderby'], 'ASC' ) !== FALSE ) ? 'ASC' : 'DESC' );
		}

		return $args;
	}

	add_action( 'posts_clauses', 'us_posts_clauses', 100, 2 );
}

if ( ! function_exists( 'us_wc_enable_setup_wizard' ) ) {
	/**
	 * Disable redirects wc-setup for developers after resetting the database
	 *
	 * @param bool $true
	 * @return bool
	 */
	function us_wc_enable_setup_wizard( $true ) {
		return defined( 'US_DEV' ) ? FALSE : $true;
	}

	add_filter( 'woocommerce_enable_setup_wizard', 'us_wc_enable_setup_wizard', 10, 1 );
}

if ( ! function_exists( 'us_wc_pre_get_posts' ) ) {
	/**
	 * Disable the output of products that are out of stock
	 *
	 * @param WP_Query $query
	 */
	function us_wc_pre_get_posts( $query ) {

		if (
			( is_admin() AND ! wp_doing_ajax() )
			OR ! class_exists( 'woocommerce' )
			OR get_option( 'woocommerce_hide_out_of_stock_items', 'no' ) !== 'yes'
			// If the search page is not for products then exit
			OR (
				$query->is_search
				AND $query->get( 'post_type' ) !== 'product'
			)
			OR (
				defined( 'REST_REQUEST' )
				AND REST_REQUEST
			)
			OR (
				wp_doing_ajax() AND isset( $_POST['action'] ) AND $_POST['action'] !== 'us_ajax_grid'
			)
		) {
			return;
		}

		$query_vars = &$query->query_vars;
		$has_product_post_type = FALSE;

		// Check if the query has post type(s) set
		// then check if it matches post types that support out of stock taxonomy
		if ( ! empty( $query_vars['post_type'] ) ) {
			$product_post_types = apply_filters(
				'woocommerce_taxonomy_objects_product_visibility',
				array(
					'product',
					'product_variation',
				)
			);
			foreach ( $product_post_types as $product_post_type ) {
				if ( $query_vars['post_type'] === $product_post_type
					OR (
						is_array( $query_vars['post_type'] )
						AND in_array( $product_post_type, $query_vars['post_type'] )
					)
				) {
					$has_product_post_type = TRUE;
					break;
				}
			}
			// If the query post type(s) do not match those supporting out of stock, abort following execution
			if ( ! $has_product_post_type ) {
				return;
			}
		}

		$include_outofstock_meta = FALSE;

		// We will add meta query with hide out of stock condition in following cases:
		if (
			// Product Archive Pages ...
			( isset( $query_vars['wc_query'] ) AND $query_vars['wc_query'] === 'product_query' )
			// OR query for products but not a single product page ...
			OR ( ! isset( $query_vars['product'] ) AND $has_product_post_type )
		) {
			$include_outofstock_meta = TRUE;

			// OR query has product categories
		} elseif ( ! empty( $query_vars['tax_query'] ) ) {
			foreach ( $query_vars['tax_query'] as $tax ) {
				if (
					! empty( $tax['taxonomy'] )
					AND (
						$tax['taxonomy'] === 'product_cat'
						OR taxonomy_is_product_attribute( $tax['taxonomy'] )
					)
				) {
					$include_outofstock_meta = TRUE;
					break;
				}
			}
		}

		// Add meta_query for outofstock
		if ( $include_outofstock_meta ) {
			$query_vars['meta_query'][] = array(
				'relation' => 'OR',
				array(
					'key' => '_stock_status',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key' => '_stock_status',
					'value' => 'outofstock',
					'compare' => '!=',
				),
			);
		}
	}

	add_action( 'pre_get_posts', 'us_wc_pre_get_posts', 10, 1 );
}

if ( ! function_exists( 'us_wc_get_min_max_price' ) ) {
	/**
	 * Get min max prices of products, taking into account tax etc.
	 *
	 * @param array $query_vars
	 * @return array
	 */
	function us_wc_get_min_max_price( $query_vars = array() ) {
		if ( ! wp_doing_ajax() AND defined( 'WP_ADMIN' ) ) {
			return array();
		}

		global $wpdb;

		$tax_query = us_arr_path( $query_vars, 'tax_query', array() );
		$meta_query = us_arr_path( $query_vars, 'meta_query', array() );

		$meta_query = new WP_Meta_Query( $meta_query );
		$tax_query = new WP_Tax_Query( $tax_query );

		$meta_query_sql = $meta_query->get_sql( 'post', $wpdb->posts, 'ID' );
		$tax_query_sql = $tax_query->get_sql( $wpdb->posts, 'ID' );
		// TODO: Add search criteria to $search_query_sql
		$search_query_sql = '';

		// Get post_types
		$post_types = array_map( 'esc_sql', apply_filters( 'woocommerce_price_filter_post_type', array( 'product' ) ) );

		// Preparing a SQL query to get the min and max price
		$query_sql = "
			SELECT
				MIN( min_price ) AS min_price, MAX( max_price ) AS max_price
			FROM {$wpdb->wc_product_meta_lookup}
			WHERE product_id IN (
				SELECT
					ID
				FROM {$wpdb->posts} " . $tax_query_sql['join'] . $meta_query_sql['join'] . "
				WHERE
					{$wpdb->posts}.post_type IN ('" . implode( "','", $post_types ) . "')
					AND {$wpdb->posts}.post_status = 'publish'
					" . $tax_query_sql['where'] . $meta_query_sql['where'] . $search_query_sql . '
			)';
		$query_sql = apply_filters( 'woocommerce_price_filter_sql', $query_sql, $meta_query_sql, $tax_query_sql );

		// Get the min and max price
		$prices = $wpdb->get_row( $query_sql );
		$min_price = (float) $prices->min_price;
		$max_price = (float) $prices->max_price;

		// Check to see if we should add taxes to the prices if store are excl tax but display incl.
		$tax_display_mode = get_option( 'woocommerce_tax_display_shop' );
		if ( wc_tax_enabled() && ! wc_prices_include_tax() && 'incl' === $tax_display_mode ) {
			$tax_rates = WC_Tax::get_rates( apply_filters( 'woocommerce_price_filter_widget_tax_class', '' ) );
			if ( $tax_rates ) {
				$min_price += WC_Tax::get_tax_total( WC_Tax::calc_exclusive_tax( $min_price, $tax_rates ) );
				$max_price += WC_Tax::get_tax_total( WC_Tax::calc_exclusive_tax( $max_price, $tax_rates ) );
			}
		}

		// Round values to nearest 10 by default.
		$step = max( apply_filters( 'woocommerce_price_filter_widget_step', 10 ), 1 );
		$min_price = apply_filters( 'woocommerce_price_filter_widget_min_amount', ( $min_price / $step ) * $step );
		$max_price = apply_filters( 'woocommerce_price_filter_widget_max_amount', ( $max_price / $step ) * $step );

		return array(
			'min' => $min_price,
			'max' => $max_price,
		);
	}
}

if ( ! function_exists( 'us_filter_woocommerce_get_catalog_ordering_args' ) ) {
	/**
	 * Injection of sorting parameters by custom grid_order shortcode settings
	 *
	 * @param array $args The arguments
	 * @return array
	 */
	function us_filter_woocommerce_get_catalog_ordering_args( $args ) {
		if ( wp_doing_ajax() ) {
			$template_vars = us_get_HTTP_POST_json( 'template_vars' );
			// The exception for search page
			if ( isset( $template_vars['query_args']['s'] ) ) {
				return $args;
			}
			$us_orderby = us_arr_path( $template_vars, 'grid_orderby' );
		} else {
			global $us_get_orderby;
			$us_orderby = us_arr_path( $_GET, us_get_grid_url_prefix( 'order' ), $us_get_orderby );
		}
		if ( $us_orderby ) {
			$params = (array) us_grid_orderby_str_to_params( $us_orderby );
			$params['post_type'] = 'product';
			us_grid_set_orderby_to_query_args( $args, $params );
		}

		return $args;
	}

	add_filter( 'woocommerce_get_catalog_ordering_args', 'us_filter_woocommerce_get_catalog_ordering_args', 1, 1 );
}

if ( ! function_exists( 'us_wc_get_template' ) ) {
	/**
	 * Add a handler to get paths to templates
	 */
	add_filter( 'wc_get_template', 'us_wc_get_template', 501, 2 );
	/**
	 * Get the path to WooCommerce templates
	 *
	 * @param string $template This is the full path to the template
	 * @param string $template_name The template name
	 * @return string Returns the full path to the template
	 */
	function us_wc_get_template( $template, $template_name ) {

		// Overriding specific WooCommerce templates, when using theme shortcodes. Needed for correct ajax work
		if (
			in_array( $template_name, array( 'cart/cart-totals.php', 'checkout/payment.php' ) )
			AND function_exists( 'wc_get_page_id' )
		) {
			// Get cart page ID
			if ( $template_name == 'cart/cart-totals.php' ) {
				$page_id = (int) wc_get_page_id( 'cart' );

				// Get checkout page ID
			} elseif ( $template_name == 'checkout/payment.php' ) {
				$page_id = (int) wc_get_page_id( 'checkout' );
			}

			// The path to the template that is located in the directory of the used theme
			$new_template = US_CORE_DIR . 'templates/woocommerce/' . $template_name;

			if (
				file_exists( $new_template )
				AND ! empty( $page_id )
				AND $post = get_post( $page_id )
				AND (
					strpos( $post->post_content, '[us_cart_totals' ) !== FALSE
					OR strpos( $post->post_content, '[us_checkout_payment' ) !== FALSE
				)
			) {
				return $new_template;
			}
		}

		return $template;
	}
}

if ( ! function_exists( 'us_checkout_page_content' ) ) {
	add_filter( 'the_content', 'us_checkout_page_content', 501, 1 );
	add_filter( 'us_content_template_the_content', 'us_checkout_page_content', 501, 1 );
	/**
	 * Add form wrapper if there are checkout elements
	 *
	 * @param string $content The content
	 * @return string
	 */
	// TODO: check if we can move this logic to a point before shortcode parsing
	function us_checkout_page_content( $content ) {

		// At first check if we are on checkout page ...
		if ( function_exists( 'is_checkout' ) AND ! is_checkout() ) {
			// ... if not - abort further execution and return original content
			return $content;
		}

		$_default_checkout_template = '[vc_row][vc_column][woocommerce_checkout][/vc_column][/vc_row]';

		// Checking if we should display a template for the "Order received" page
		if (
			us_is_order_received_page()
			AND $order_template_id = us_get_option( 'content_order_id' )
			AND $order_template = get_post( $order_template_id )
			AND $order_id = us_wc_get_order_id()
			AND $order = wc_get_order( $order_id )
			AND ! $order->has_status( 'failed' )
		) {
			$content = do_shortcode( $order_template->post_content );

			// If it's a checkout endpoint, output the default WooCoommerce template instead of theme elements
		} elseif (
			is_wc_endpoint_url()
			AND (
				// Case when `us_checkout_*` elements are in Checkout Page
				(
					$checkout_page = get_post( (int) get_option( 'woocommerce_checkout_page_id' ) )
					AND strpos( $checkout_page->post_content, "[us_checkout_" ) !== FALSE
				)
				// Case when `us_checkout_*` elements are in Page Template
				OR (
					$content_template = get_post( (int) us_get_page_area_id( 'content' ) )
					AND strpos( $content_template->post_content, "[us_checkout_" ) !== FALSE
				)
			)
		) {
			$content = do_shortcode( $_default_checkout_template );

			// If checkout is disabled for unregistered, output the default WooCoommerce template instead of theme elements
		} elseif (
			strpos( $content, 'class="w-checkout-' ) !== FALSE
			AND WC()->checkout()->is_registration_required()
			AND ! WC()->checkout()->is_registration_enabled()
			AND ! is_user_logged_in()
		) {
			$content = do_shortcode( $_default_checkout_template );

		} else {

			// If the content contains theme checkout elements, then wrap it in the form
			if ( strpos( $content, 'class="w-checkout-' ) !== FALSE ) {
				$form_atts = array(
					'action' => wc_get_checkout_url(),
					'class' => 'checkout woocommerce-checkout',
					'enctype' => 'multipart/form-data',
					'method' => 'POST',
					'name' => 'checkout',
				);
				$content = '<form' . us_implode_atts( $form_atts ) . '>' . $content . '</form>';
			}

			// If the content contains coupon form on the Checkout page, add a hidden form to work correctly
			if ( strpos( $content, 'class="w-wc-coupon-form' ) !== FALSE  ) {
				$content .= '<form class="checkout_coupon hidden" method="POST" onsubmit="return false">';
				$content .= '<input type="text" name="coupon_code">';
				$content .= '<button type="submit" name="apply_coupon" value="Apply coupon"></button>';
				$content .= '</form>';
			}
		}

		return $content;
	}
}

if ( ! function_exists( 'us_delete_notice_change_in_quantity' ) ) {
	add_filter( 'woocommerce_add_success', 'us_delete_notice_change_in_quantity', 501, 1 );
	/**
	 * Delete notice of change in quantity
	 *
	 * @param string $message The message text
	 * @return string
	 */
	function us_delete_notice_change_in_quantity( $message ) {
		if (
			us_arr_path( $_REQUEST, 'us_cart_quantity' )
			AND $message === us_translate( 'Cart updated.', 'woocommerce' )
		) {
			$message = '';
		}
		return $message;
	}
}

if ( ! function_exists( 'usb_before_render_shipping_shortcode' ) ) {
	add_action( 'usb_before_render_shortcode', 'usb_before_render_shipping_shortcode', 501, 1 );
	/**
	 * Add a constant to correctly process the `[us_cart_*`
	 *
	 * @param string $content The content
	 */
	function usb_before_render_shipping_shortcode( $content ) {
		if ( empty( $content ) OR strpos( $content, '[us_cart_' ) === FALSE ) {
			return;
		}
		// Set a constant will ensure proper processing in the bowels of the WooCommerce
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', TRUE );
		}
	}
}

if ( ! function_exists( 'us_wc_get_order_id' ) ) {
	/**
	 * Get the order ID
	 */
	function us_wc_get_order_id() {
		$order_id = FALSE;

		if ( us_is_order_received_page() ) {
			global $wp;
			$order_id = absint( $wp->query_vars['order-received'] );

		} elseif ( is_view_order_page() ) {
			$order_id = FALSE; // TODO: add the "view-order" check
		}

		return $order_id;
	}
}

if ( ! function_exists( 'us_wc_login_form_remember_me' ) ) {
	add_action( 'woocommerce_login_form', 'us_wc_login_form_remember_me', 501 );
	/**
	 * By default it turns on "Remember me"
	 */
	function us_wc_login_form_remember_me() {
		echo '<input type="hidden" name="rememberme" value="forever">';
	}
}

if ( ! function_exists( 'us_format_price_in_list_filter' ) ) {
	add_filter( 'us_list_filter_value_label', 'us_format_price_in_list_filter', 10, 3 );
	/**
	 * Format price numbers regarding to currency options
	 */
	function us_format_price_in_list_filter( $label, $value, $item_name ) {
		if ( isset( $item_name ) AND $item_name == 'price' ) {

			if ( $numbers = explode( '-', $label ) AND count( $numbers ) == 2 ) {
				$label = strip_tags( wc_price( $numbers[0] ) . ' - ' . wc_price( $numbers[1] ) );

			} elseif ( is_numeric( $label ) ) {
				$label = strip_tags( wc_price( $label ) );
			}
		}
		return $label;
	}
}

if ( ! function_exists( 'us_format_price_in_range_slider' ) ) {
	add_filter( 'us_list_filter_range_slider_options', 'us_format_price_in_range_slider', 10, 2 );
	/**
	 * Format price numbers regarding to currency options
	 */
	function us_format_price_in_range_slider( $options, $item_name ) {
		if ( isset( $item_name ) AND $item_name == 'price' ) {
			$options['numberFormat'] = array(
				'decimal_separator' => wc_get_price_decimal_separator(),
				'thousand_separator' => wc_get_price_thousand_separator(),
				'decimals' => wc_get_price_decimals(),
			);
			$options['unitFormat'] = sprintf( get_woocommerce_price_format(), get_woocommerce_currency_symbol(), '%d' );
		}
		return $options;
	}
}

if ( ! function_exists( 'us_wc_filter_indexer_insert_indexes' ) ) {
	add_filter( 'us_filter_indexer_insert_indexes', 'us_wc_filter_indexer_insert_indexes', 10, 3 );
	/**
	 * Add new indexes for products.
	 *
	 * @param array $indexes
	 * @param array $defaults
	 *
	 * @return array Returns a list of all indexes for a post.
	 */
	function us_wc_filter_indexer_insert_indexes( $indexes, $defaults ) {

		$post_id = $defaults['post_id'];
		$post_type = get_post_type( $post_id );

		if ( $post_type == 'product' OR $post_type == 'product_variation' ) {

			foreach ( US_Filter_Indexer::instance()->get_used_filter_params() as $name => $param ) {

				$source_type = $param['source_type'] ?? '';
				$source_name = $param['source_name'] ?? '';

				if ( $source_type != 'woo' ) {
					continue;
				}

				if ( $source_name == 'onsale' ) {

					static $onsale_ids = array();
					if ( empty( $onsale_ids ) ) {
						$onsale_ids = wc_get_product_ids_on_sale();
					}
					if ( in_array( $post_id, $onsale_ids ) ) {
						$indexes[] = array_merge(
							$defaults,
							array(
								'filter_name' => $source_name,
								'filter_value' => 1,
							)
						);
					}


				} else if ( $source_name == 'featured' ) {

					static $featured_ids = array();
					if ( empty( $featured_ids ) ) {
						$featured_ids = wc_get_featured_product_ids();
					}
					if ( in_array( $post_id, $featured_ids ) ) {
						$indexes[] = array_merge(
							$defaults,
							array(
								'filter_name' => $source_name,
								'filter_value' => 1,
							)
						);
					}
				}
			}

		}

		return $indexes;
	}
}
