<?php defined( 'ABSPATH' ) OR die( 'This script cannot be accessed directly.' );

/**
 * Shortcode: us_person
 *
 * Dev note: if you want to change some of the default values or acceptable attributes, overload the shortcodes config.
 *
 * @param $image          int Photo (from WP Media Library)
 * @param $image_hover    int Photo on hover (from WP Media Library)
 * @param $name           string Name
 * @param $role           string Role
 * @param $link           string Link in a serialized format: 'url:http%3A%2F%2Fwordpress.org|title:WP%20Website|target:_blank|rel:nofollow'
 * @param $layout         string Layout: 'simple' / 'simple_circle' / 'circle' / 'square' / 'card' / 'modern' / 'trendy'
 * @param $effect         string Photo Effect: 'none' / 'sepia' / 'bw' / 'faded' / 'colored'
 * @param $email          string Email
 * @param $facebook       string Facebook link
 * @param $twitter        string X (Twitter) link
 * @param $google_plus    string Google link
 * @param $linkedin       string LinkedIn link
 * @param $skype          string Skype link
 * @param $custom_icon    string Custom icon
 * @param $custom_link    string Custom link
 * @param $el_class       string Extra class name
 * @var   $shortcode      string Current shortcode name
 * @var   $shortcode_base string The original called shortcode name (differs if called an alias)
 * @var   $content        string Shortcode's inner content
 * @var   $classes        string Extend class names
 *
 */

$_atts['class'] = 'w-person';
$_atts['class'] .= $classes ?? '';
$_atts['class'] .= ' layout_' . $layout;

if ( $effect != 'none' ) {
	$_atts['class'] .= ' effect_' . $effect;
}
if ( ! empty( $content ) ) {
	$_atts['class'] .= ' with_desc';
}

if ( ! empty( $el_id ) ) {
	$_atts['id'] = $el_id;
}

// Generate schema.org markup
$schema_image = $schema_name = $schema_job = $schema_desc = '';
if ( us_get_option( 'schema_markup' ) ) {
	$_atts['itemscope'] = '';
	$_atts['itemtype'] = 'https://schema.org/Person';
	$schema_image = array( 'itemprop' => 'image' );
	$schema_name = ' itemprop="name"';
	$schema_job = ' itemprop="jobTitle"';
	$schema_desc = ' itemprop="description"';
}

// Get the image
$image = us_replace_dynamic_value( $image, /* acf_format */ FALSE );
$img_html = wp_get_attachment_image( $image, $img_size, FALSE, $schema_image );
if ( empty( $img_html ) ) {
	$img_html = us_get_img_placeholder( $img_size );
}

// Append image on hover
if ( $image_hover = us_replace_dynamic_value( $image_hover, /* acf_format */ FALSE ) ) {
	$img_hover = wp_get_attachment_image_url( $image_hover, $img_size );
	if ( $img_hover !== FALSE ) {
		$img_html .= '<div class="img_hover" style="background-image:url(' . $img_hover . ')"></div>';
	}
}

// Predefined social links
$social_links_html = '';
$social_links = array(
	'email' => us_translate( 'Email' ),
	'facebook' => 'Facebook',
	'twitter' => 'X',
	'google_plus' => 'Google',
	'linkedin' => 'LinkedIn',
	'skype' => 'Skype',
	'custom_link' => '',
);
foreach ( $social_links as $type => $title ) {
	$social_link = $$type;

	if ( empty( $social_link ) ) {
		continue;
	}

	if ( $type == 'google_plus' ) {
		$type = 'google';
	}

	if ( $type == 'email' ) {
		if ( is_email( $social_link ) ) {
			$social_links_atts['href'] = 'mailto:' . $social_link;
		}
		$social_icon = 'fas|fa-envelope';

	} elseif ( $type == 'skype' ) {
		if ( strpos( $social_link, ':' ) === FALSE ) {
			$social_links_atts['href'] = 'skype:' . $social_link;
		} else {
			$social_links_atts['href'] = $social_link;
		}
		$social_icon = 'fab|fa-' . $type;

		// Custom Link
	} elseif ( $type == 'custom_link' ) {

		$social_links_atts = us_generate_link_atts( $social_link, /* additional data */array( 'img_id' => $image ) );
		$social_icon = $custom_icon;

	} else {
		$social_links_atts['href'] = esc_url( $social_link );
		$social_links_atts['target'] = '_blank';
		$social_links_atts['rel'] = 'nofollow';
		$social_icon = 'fab|fa-' . $type;
	}

	$social_links_atts['class'] = 'w-person-links-item type_' . $type;

	if ( $title ) {
		$social_links_atts['title'] = $title;
	}

	if ( ! empty( $social_links_atts['href'] ) ) {
		$social_links_html .= '<a' . us_implode_atts( $social_links_atts ) . '>' . us_prepare_icon_tag( $social_icon ) . '</a>';
	}
}

if ( ! empty( $social_links_html ) ) {
	$_atts['class'] .= ' with_socials';
	$social_links_html = '<div class="w-person-links"><div class="w-person-links-list">' . $social_links_html . '</div></div>';
}

// Link
$link_opener = $link_closer = '';
$link_atts = us_generate_link_atts( $link, /* additional data */array( 'img_id' => $image ) );
if ( ! empty( $link_atts['href'] ) ) {
	$link_atts['class'] = 'w-person-link';
	$link_atts['aria-label'] = strip_tags( $name );
	$link_opener = '<a' . us_implode_atts( $link_atts ) . '>';
	$link_closer = '</a>';
}

// Output the element
$output = '<div' . us_implode_atts( $_atts ) . '>';

// Image
$output .= '<div class="w-person-image">';
$output .= $link_opener . $img_html . $link_closer;

if ( in_array( $layout, array( 'square', 'circle' ) ) ) {
	$output .= $social_links_html;
}

$output .= '</div>';

$output .= '<div class="w-person-content">';

// Name
if ( ! empty( $name ) ) {
	$name_atts = array(
		'class' => 'w-person-name',
		'style' => '',
	);
	if ( ! empty( $name_size ) ) {
		$name_atts['style'] = 'font-size:' . $name_size;
	}

	// Apply filters to name
	$name = us_replace_dynamic_value( $name );
	$name = wptexturize( $name );

	$output .= '<' . $name_tag . $schema_name . us_implode_atts( $name_atts ) . '>';
	$output .= $link_opener;
	$output .= '<span>' . $name . '</span>';
	$output .= $link_closer;
	$output .= '</' . $name_tag . '>';
}

// Role
if ( ! empty( $role ) ) {
	$role = us_replace_dynamic_value( $role );
	$role = wptexturize( $role );

	$output .= '<div class="w-person-role"' . $schema_job . '>' . $role . '</div>';
}

if ( $layout == 'trendy' AND ( ! empty( $content ) OR ! empty( $social_links_html ) ) ) {
	$output .= '</div><div class="w-person-content-alt">' . $link_opener . $link_closer;
}

// Icons
if ( ! in_array( $layout, array( 'square', 'circle' ) ) ) {
	$output .= $social_links_html;
}

// Content
if ( ! empty( $content ) OR usb_is_post_preview() ) {
	$output .= '<div class="w-person-description"' . $schema_desc . '>' . do_shortcode( wpautop( $content ) ) . '</div>';
}

$output .= '</div>'; // w-person-content
$output .= '</div>'; // w-person

echo $output;
