<?php defined( 'ABSPATH' ) OR die( 'This script cannot be accessed directly.' );

/**
 * WPML Support
 *
 * @link https://wpml.org/
 */

if ( ! ( class_exists( 'SitePress' ) AND defined( 'ICL_LANGUAGE_CODE' ) ) ) {
	return;
}

if ( is_admin() ) {
	if ( ! function_exists( 'us_dequeue_wpml_select2' ) ) {
		/**
		 * Remove select2 CSS to avoid overlapping with theme styles
		 */
		function us_dequeue_wpml_select2() {
			global $pagenow;

			if (
				(
					$pagenow == 'admin.php'
					AND us_arr_path( $_GET, 'page' ) == 'us-theme-options'
				)
				OR (
					$pagenow === 'post.php'
					AND isset( $_GET['post'] )
					AND (
						get_post_type( $_GET['post'] ) === 'us_header'
						OR get_post_type( $_GET['post'] ) === 'us_grid_layout'
					)
				)
			) {
				wp_dequeue_style( 'wpml-select-2' );
			}
		}
		add_action( 'admin_init', 'us_dequeue_wpml_select2' );
	}
}

if ( ! function_exists( 'wpml_pb_shortcode_encode_us_link' ) ) {
	/**
	 * Add support for "link" control
	 *
	 * @param string $string
	 * @param string $encoding
	 * @param string $original_string
	 * @return string
	 */
	function wpml_pb_shortcode_encode_us_link( $string, $encoding, $original_string ) {
		if ( $encoding === 'us_link' ) {
			$string = rawurlencode( json_encode( (array) $original_string ) );
		}
		return $string;
	}
	add_filter( 'wpml_pb_shortcode_encode', 'wpml_pb_shortcode_encode_us_link', 10, 3 );
}

if ( ! function_exists( 'wpml_pb_shortcode_decode_us_link' ) ) {
	/**
	 * Add support for "link" control
	 *
	 * @param string $string
	 * @param string $encoding
	 * @param string $original_string
	 * @return array|string
	 */
	function wpml_pb_shortcode_decode_us_link( $string, $encoding, $original_string ) {
		if ( $encoding !== 'us_link' ) {
			return $string;
		}

		$decoded_array = array();

		// If it is string and begins with "url", use WPBakery way to create array
		if ( strpos( $original_string, 'url:' ) === 0 OR strpos( $original_string, '|' ) !== FALSE ) {
			$params_pairs = explode( '|', $original_string );
			if ( ! empty( $params_pairs ) ) {
				foreach ( $params_pairs as $pair ) {
					$param = explode( ':', $pair, 2 );
					if ( ! empty( $param[0] ) AND isset( $param[1] ) ) {
						$decoded_array[ $param[/* key */0] ] = rawurldecode( $param[/* value */1] );
					}
				}
			}
		} else {
			$decoded_array = json_decode( rawurldecode( $original_string ), /* as array */TRUE );
		}

		$result = array();
		if ( is_array( $decoded_array ) ) {
			foreach ( $decoded_array as $key => $value ) {
				if ( in_array( $key, array( 'url', 'title' ) ) ) {
					$result[ $key ] = array(
						'value' => $value,
						'translate' => TRUE,
					);
				} else {
					$result[ $key ] = array(
						'value' => $value,
						'translate' => FALSE,
					);
				}
			}
		}

		return $result;
	}
	add_filter( 'wpml_pb_shortcode_decode', 'wpml_pb_shortcode_decode_us_link', 10, 3 );
}

if ( ! function_exists( 'wpml_pb_shortcode_encode_us_urlencoded_json' ) ) {
	/**
	 * Add support for encoded shortcodes
	 *
	 * @param string $string
	 * @param string $encoding
	 * @param string $original_string
	 * @return string
	 */
	function wpml_pb_shortcode_encode_us_urlencoded_json( $string, $encoding, $original_string ) {
		if ( $encoding !== 'us_urlencoded_json' ) {
			return $string;
		}

		$output = array();
		foreach ( $original_string as $combined_key => $value ) {
			$parts = explode( '_', $combined_key );
			$i = array_pop( $parts );
			$key = implode( '_', $parts );
			$output[ $i ][ $key ] = $value;
		}

		return rawurlencode( json_encode( $output ) );
	}
	add_filter( 'wpml_pb_shortcode_encode', 'wpml_pb_shortcode_encode_us_urlencoded_json', 10, 3 );
}

if ( ! function_exists( 'wpml_pb_shortcode_decode_us_urlencoded_json' ) ) {
	/**
	 * Get shortcode data and decode string
	 *
	 * @param string $string
	 * @param string $encoding
	 * @param string $original_string
	 * @return array
	 */
	function wpml_pb_shortcode_decode_us_urlencoded_json( $string, $encoding, $original_string ) {
		if ( $encoding !== 'us_urlencoded_json' ) {
			return $string;
		}

		$fields_to_translate = array(
			'bool_value_label',
			'btn_link',
			'btn_text',
			'date_format',
			'date_picker_placeholder',
			'date_picker_placeholder_2',
			'date_values_format',
			'description',
			'features',
			'first_value_label',
			'image',
			'label',
			'link',
			'marker_address',
			'marker_text',
			'placeholder',
			'price',
			'search_placeholder',
			'substring',
			'title',
			'url',
			'value',
			'values',
		);
		$rows = json_decode( rawurldecode( $original_string ), TRUE );
		$result = array();
		foreach ( $rows as $i => $row ) {
			foreach ( $row as $key => $value ) {
				if ( in_array( $key, $fields_to_translate ) ) {
					$result[ $key . '_' . $i ] = array( 'value' => $value, 'translate' => TRUE );
				} else {
					$result[ $key . '_' . $i ] = array( 'value' => $value, 'translate' => FALSE );
				}
			}
		}

		return $result;
	}
	add_filter( 'wpml_pb_shortcode_decode', 'wpml_pb_shortcode_decode_us_urlencoded_json', 10, 3 );
}

/**
 * us_tr_selected_lang_page filter
 */
if ( ! function_exists( 'us_wpml_tr_selected_lang_page' ) ) {
	/**
	 * Check Selected language on page
	 *
	 * @param bool $default_value
	 * @return bool
	 */
	function us_wpml_tr_selected_lang_page( $default_value = FALSE ) {
		if ( ! empty( $_REQUEST['lang'] ) ) {
			return strtolower( $_REQUEST['lang'] ) !== 'all';
		} elseif ( ! empty( $_COOKIE[ 'wp-wpml_current_language' ] ) ) {
			return strtolower( $_COOKIE[ 'wp-wpml_current_language' ] ) !== 'all';
		}
		return $default_value;
	}
	add_filter( 'us_tr_selected_lang_page', 'us_wpml_tr_selected_lang_page', 10 );
}

/**
 * us_tr_default_language filter
 */
if ( ! function_exists( 'us_wpml_tr_default_language' ) ) {
	/**
	 * Returns the default language
	 *
	 * @param mixed $empty_value Filter plug
	 * @return string
	 */
	function us_wpml_tr_default_language ( $empty_value = NULL ) {
		return apply_filters( 'wpml_default_language', NULL );
	}
	add_filter( 'us_tr_default_language', 'us_wpml_tr_default_language', 10, 1 );
}

/**
 * us_tr_current_language filter
 */
if ( ! function_exists( 'us_wpml_tr_current_language' ) ) {
	/**
	 * Getting the current language for an interface
	 *
	 * @param mixed $empty_value Filter plug
	 * @return string
	 */
	function us_wpml_tr_current_language ( $empty_value = NULL ) {
		return apply_filters( 'wpml_current_language', NULL );
	}
	add_filter( 'us_tr_current_language', 'us_wpml_tr_current_language', 10, 1 );
}

/**
 * us_tr_object_id filter
 */
if ( ! function_exists( 'us_wpml_tr_object_id' ) ) {
	/**
	 * Returns a translated post
	 *
	 * @param integer $elm_id
	 * @param string $type
	 * @param bool $return_original_if_missing
	 * @param mixed $lang_code
	 * @return int|bool
	 */
	function us_wpml_tr_object_id ( $elm_id, $post_type = 'post', $return_original_if_missing = FALSE, $lang_code = NULL ) {
		if ( $tr_elm_id = apply_filters( 'wpml_object_id', $elm_id, $post_type, $return_original_if_missing, $lang_code ) ) {
			return $tr_elm_id;
		}
		// If there is no translation, we will return the original $elm_id
		return $elm_id;
	}
	add_filter( 'us_tr_object_id', 'us_wpml_tr_object_id', 10, 4 );
}

/**
 * us_tr_get_post_language_code filter
 */
if ( ! function_exists( 'us_wpml_tr_get_post_language_code' ) ) {
	/**
	 * Get post language code
	 *
	 * @param intval|string $post_id
	 * @return bool|string
	 */
	function us_wpml_tr_get_post_language_code( $post_id = '' ) {
		$wpml_post_language_details = apply_filters( 'wpml_post_language_details', NULL, $post_id );
		if (
			is_array( $wpml_post_language_details )
			AND isset( $wpml_post_language_details['language_code'] )
		) {
			return $wpml_post_language_details['language_code'];
		} else {
			return NULL;
		}
	}

	add_filter( 'us_tr_get_post_language_code', 'us_wpml_tr_get_post_language_code', 10, 2 );
}

/**
 * us_tr_home_url filter
 */
if ( ! function_exists( 'us_wpml_tr_home_url' ) ) {
	function us_wpml_tr_home_url() {
		return apply_filters( 'wpml_home_url', home_url() );
	}
	add_filter( 'us_tr_home_url', 'us_wpml_tr_home_url', 10, 2 );
}

/**
 * us_tr_switch_language action
 */
if ( ! function_exists( 'us_wpml_tr_switch_language' ) ) {
	/**
	 * Switch a global language
	 *
	 * @param string $language_code
	 */
	function us_wpml_tr_switch_language ( $language_code = NULL ) {
		do_action( 'wpml_switch_language', $language_code );
	}
	add_action( 'us_tr_switch_language', 'us_wpml_tr_switch_language', 10, 1 );
}

/**
 * us_tr_get_term_language filter
 */
if ( ! function_exists( 'us_wpml_tr_get_term_language' ) ) {
	/**
	 * Returns the term language.
	 *
	 * @param int $term_id
	 * @return bool|string
	 */
	function us_wpml_tr_get_term_language( $term_id ) {
		$term = get_term( $term_id );
		if ( ! ( $term instanceof WP_Term ) ) {
			return FALSE;
		}

		return apply_filters(
			'wpml_element_language_code',
			NULL,
			array( 'element_id' => (int) $term_id, 'element_type' => $term->taxonomy )
		);
	}

	add_filter( 'us_tr_get_term_language', 'us_wpml_tr_get_term_language', 10, 1 );
}

/**
 * us_tr_setting filter
 */
if ( ! function_exists( 'us_wpml_tr_setting' ) ) {
	/**
	 * Returns a WPML setting value
	 *
	 * @param mixed|bool $default
	 * @param string $key
	 * @return bool
	 */
	function us_wpml_tr_setting ( $key, $default ) {
		return apply_filters( 'wpml_setting', $default, $key );
	}
	add_filter( 'us_tr_setting', 'us_wpml_tr_setting', 10, 2 );
}

/**
 * Adds multi-currency support for Grid AJAX calls
 *
 * https://wpml.org/wcml-hook/wcml_multi_currency_ajax_actions/
 */
if ( ! function_exists( 'us_add_grid_to_wpml_ajax_actions' ) ) {
	add_filter( 'wcml_multi_currency_ajax_actions', 'us_add_grid_to_wpml_ajax_actions' );
	function us_add_grid_to_wpml_ajax_actions( $ajax_actions ) {
		$ajax_actions[] = 'us_ajax_grid';

		return $ajax_actions;
	}
}


if ( ! function_exists( 'us_wpml_media_category_update_count' ) ) {
	add_action( 'us_media_category_update_count_callback', 'us_wpml_media_category_update_count', 10 );
	/**
	 * WPML sync count
	 */
	function us_wpml_media_category_update_count() {
		global $sitepress;
		if (
			class_exists( 'WPML_Troubleshoot_Sync_Posts_Taxonomies' )
			AND class_exists( 'WPML_Term_Translation_Utils' )
			AND class_exists( 'WPML_Post_Types' )
		) {
			// WPML_SP_User class is abstract and required for WPML_Troubleshoot_Sync_Posts_Taxonomies, so we use WPML_Post_Types instead
			$wpml_user = new WPML_Post_Types( $sitepress );
			$term_translation_utils = new WPML_Term_Translation_Utils( $sitepress );
			$sync = new WPML_Troubleshoot_Sync_Posts_Taxonomies( $sitepress, $term_translation_utils );

			// Imitate post data
			$_POST['post_type'] = 'attachment';
			$_POST['batch_number'] = 0;

			$sync->run();
		}
	}
}

if ( ! function_exists( 'us_wpml_add_og_meta_tags' ) ) {
	add_filter( 'us_meta_tags', 'us_wpml_add_og_meta_tags', 10, 1 );

	/**
	 * Add og:locale:alternate meta tags for WPML
	 * @param $meta_tags
	 * @return array
	 */
	function us_wpml_add_og_meta_tags( $meta_tags ) {
		if ( function_exists( 'icl_get_languages' ) AND defined( 'ICL_LANGUAGE_CODE' ) ) {
			$languages = icl_get_languages( 'skip_missing=0' );
			$current_language = ICL_LANGUAGE_CODE;
			if ( ! empty( $languages ) ) {
				foreach ( $languages as $lang ) {
					if ( ! empty( $lang['language_code'] )
						AND $lang['language_code'] !== $current_language
						AND ! empty( $lang['default_locale'] )
					) {
						$meta_tags['og:locale:alternate'][] = $lang['default_locale'];
					}
				}
			}
		}

		return (array) $meta_tags;
	}
}
