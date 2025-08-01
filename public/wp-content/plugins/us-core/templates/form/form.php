<?php defined( 'ABSPATH' ) OR die( 'This script cannot be accessed directly.' );

/**
 * Output a single form
 *
 * @var $type          string Form type: 'contact' / 'search' / 'comment' / 'protectedpost' / ...
 * @var $action        string Form action
 * @var $method        string Form method: 'post' / 'get'
 * @var $fields        array Form fields (see any of the fields template header for details)
 * @var $json_data     array Json data to pass to JavaScript
 * @var $classes       string Additional classes to append to form
 * @var $start_html    string HTML to append to the form's start
 * @var $end_html      string HTML to append to the form's end
 *
 * @action Before the template: 'us_before_template:templates/form/form'
 * @action After the template:  'us_after_template:templates/form/form'
 * @filter Template variables:  'us_template_vars:templates/form/form'
 */

$fields = isset( $fields ) ? (array) $fields : array();
$start_html = isset( $start_html ) ? $start_html : '';
$end_html = isset( $end_html ) ? $end_html : '';

// Repeatable fields IDs start from 1
$repeatable_fields = array(
	'agreement' => 1,
	'checkboxes' => 1,
	'date' => 1,
	'email' => 1,
	'file' => 1,
	'radio' => 1,
	'select' => 1,
	'text' => 1,
	'textarea' => 1,
);

foreach ( $fields as $field_name => $field ) {
	if ( isset( $field['type'] ) ) {
		$fields[ $field_name ]['type'] = $field['type'];
		if ( in_array( $field['type'], array_keys( $repeatable_fields ) ) ) {
			$fields[ $field_name ]['field_id'] = $repeatable_fields[ $field['type'] ];
			$repeatable_fields[ $field['type'] ] += 1;
		}

	} else {
		$fields[ $field_name ]['type'] = 'text';
	}
}

// Add param to existing data, if set
if ( ! empty( $json_data ) AND is_array( $json_data ) ) {
	$json_data['ajaxurl'] = admin_url( 'admin-ajax.php' );
} else {
	$json_data = array(
		'ajaxurl' => admin_url( 'admin-ajax.php' ),
	);
}

// Validation error messages
$json_data['messages'] = array(
	'err_empty' => __( 'Fill out this field', 'us' ),
	'err_size' => __( 'File size cannot exceed %s MB', 'us' ),
	'err_extension' => __( '%s file type is not allowed', 'us' ),
	'err_recaptcha_keys' => __( 'reCAPTCHA keys are incorrect', 'us' ),
);

global $us_cform_index;

$_atts['class'] = 'w-form';
$_atts['autocomplete'] = 'off';
$_atts['action'] = isset( $action ) ? $action : home_url( us_get_safe_var( 'REQUEST_URI' ) );
$_atts['method'] = isset( $method ) ? $method : 'post';

if ( ! empty( $classes ) ) {
	$_atts['class'] .= ' ' . $classes;
}
if ( ! empty( $type ) ) {
	$_atts['class'] .= ' for_' . $type;
}
if ( ! empty( $us_cform_index ) ) {
	$_atts['class'] .= ' us_form_' . $us_cform_index;
}

// Fallback for forms without layout class
if ( strpos( $_atts['class'], 'layout_' ) === FALSE ) {
	$_atts['class'] .= ' layout_ver';
}

// Set CSS inline var for gap between fields
if ( ! us_amp() AND isset( $fields_gap ) AND trim( (string) $fields_gap ) != '1rem' ) {
	$_atts['style'] = '--fields-gap:' . $fields_gap;
}

if ( ! empty( $el_id ) ) {
	$_atts['id'] = $el_id;
}

// Add form file upload support
if ( ! empty( $fields ) ) {
	foreach( $fields as $field ) {
		if ( us_arr_path( $field, 'type' ) == 'file' ) {
			$_atts['enctype'] = 'multipart/form-data';
			break;
		}
	}
}

// Enable reCAPTCHA script
$reCAPTCHA_policy_text = '';

if ( ! empty( $fields ) AND $reCAPTCHA_site_key = us_get_option( 'reCAPTCHA_site_key', '' ) ) {
	foreach ( $fields as $field ) {
		if ( us_arr_path( $field, 'type' ) == 'reCAPTCHA' ) {

			// Hide reCAPTCHA badge
			if ( us_get_option( 'reCAPTCHA_hide_badge' ) ) {
				$reCAPTCHA_policy_text = us_get_option( 'reCAPTCHA_policy_text', '' );
			}

			$_atts['class'] .= ' validate_by_recaptcha';
			$json_data['recaptcha_site_key'] = $reCAPTCHA_site_key;

			wp_enqueue_script( 'us-recaptcha' );

			break;
		}
	}
}

// Add AMP related attributes
if ( us_amp() ) {
	$_atts['action'] = $json_data['ajaxurl'];
	$_atts['custom-validation-reporting'] = 'show-all-on-submit';
}

// Output the form
echo '<form' . us_implode_atts( $_atts ) . '>';
echo '<div class="w-form-h">';
echo $start_html;
foreach ( $fields as $field ) {

	// Show reCAPTCHA policy text above the submit button
	if ( $field['type'] == 'submit' AND $reCAPTCHA_policy_text ) {
		echo '<div class="w-form-row for_recaptcha_text">' . $reCAPTCHA_policy_text . '</div>';
	}

	us_load_template( 'templates/form/' . $field['type'], $field );
}
echo $end_html;
echo '</div>';
echo '<div class="w-form-message"></div>';
if ( ! us_amp() ) {
	echo '<div class="w-form-json hidden"' . us_pass_data_to_js( $json_data ) . '></div>';
}
echo '</form>';
