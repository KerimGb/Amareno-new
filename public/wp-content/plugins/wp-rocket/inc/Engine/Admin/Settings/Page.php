<?php
namespace WP_Rocket\Engine\Admin\Settings;

use WP_Rocket\Engine\Admin\Database\Optimization;
use WP_Rocket\Engine\Admin\Beacon\Beacon;
use WP_Rocket\Engine\License\API\UserClient;
use WP_Rocket\Engine\Optimization\DelayJS\Admin\SiteList;
use WP_Rocket\Engine\Optimization\DelayJS\Admin\Settings as DelayJSSettings;
use WP_Rocket\Abstract_Render;
use WP_Rocket\Admin\Options_Data;

/**
 * Registers the admin page and WP Rocket settings.
 *
 * @since 3.5.5 Moves into the new architecture.
 * @since 3.0
 */
class Page extends Abstract_Render {
	/**
	 * Plugin slug.
	 *
	 * @since 3.0
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Plugin page title.
	 *
	 * @since 3.0
	 *
	 * @var string
	 */
	private $title;

	/**
	 * Required capability to access the page.
	 *
	 * @since 3.0
	 *
	 * @var string
	 */
	private $capability;

	/**
	 * Settings instance.
	 *
	 * @since 3.0
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Render implementation.
	 *
	 * @since 3.0
	 *
	 * @var Render
	 */
	private $render;

	/**
	 * Beacon instance.
	 *
	 * @since 3.2
	 *
	 * @var Beacon
	 */
	private $beacon;

	/**
	 * Database optimization instance.
	 *
	 * @since 3.3
	 *
	 * @var Optimization
	 */
	private $optimize;

	/**
	 * User client instance.
	 *
	 * @var UserClient
	 */
	private $user_client;

	/**
	 * Delay JS Site List controller.
	 *
	 * @var SiteList
	 */
	protected $delayjs_sitelist;

	/**
	 * WP Rocket options instance
	 *
	 * @var Options_Data
	 */
	private $options;

	/**
	 * Creates an instance of the Page object.
	 *
	 * @since 3.0
	 *
	 * @param array        $args        Array of required arguments to add the admin page.
	 * @param Settings     $settings    Instance of Settings class.
	 * @param Render       $render      Render instance.
	 * @param Beacon       $beacon      Beacon instance.
	 * @param Optimization $optimize    Database optimization instance.
	 * @param UserClient   $user_client User client instance.
	 * @param SiteList     $delayjs_sitelist User client instance.
	 * @param string       $template_path Path to views.
	 * @param Options_Data $options       WP Rocket options instance.
	 */
	public function __construct(
		array $args,
		Settings $settings,
		Render $render,
		Beacon $beacon,
		Optimization $optimize,
		UserClient $user_client,
		SiteList $delayjs_sitelist,
		$template_path,
		Options_Data $options
	) {
		parent::__construct( $template_path );
		$args = array_merge(
			[
				'slug'       => 'wprocket',
				'title'      => 'WP Rocket',
				'capability' => 'rocket_manage_options',
			],
			$args
		);

		$this->slug             = $args['slug'];
		$this->title            = $args['title'];
		$this->capability       = $args['capability'];
		$this->settings         = $settings;
		$this->render           = $render;
		$this->beacon           = $beacon;
		$this->optimize         = $optimize;
		$this->user_client      = $user_client;
		$this->delayjs_sitelist = $delayjs_sitelist;
		$this->options          = $options;
	}

	/**
	 * Returns the settings page title.
	 *
	 * @since 3.3
	 *
	 * @return string
	 */
	public function get_title() {
		return $this->title;
	}

	/**
	 * Returns the settings page slug.
	 *
	 * @since 3.3
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Returns the settings page capability.
	 *
	 * @since 3.3
	 *
	 * @return string
	 */
	public function get_capability() {
		return $this->capability;
	}

	/**
	 * Registers the settings, page sections, fields sections and fields.
	 *
	 * @since 3.0
	 */
	public function configure() {
		register_setting( $this->slug, WP_ROCKET_SLUG, [ $this->settings, 'sanitize_callback' ] );
	}

	/**
	 * Renders the settings page.
	 *
	 * @since 3.0
	 */
	public function render_page() {
		$rocket_valid_key = rocket_valid_key();
		if ( $rocket_valid_key ) {
			$this->dashboard_section();
			$this->assets_section();
			$this->media_section();
			$this->preload_section();
			$this->advanced_cache_section();
			$this->database_section();
			$this->cdn_section();
			$this->heartbeat_section();
			$this->addons_section();
			$this->cloudflare_section();
			$this->sucuri_section();
		} else {
			$this->license_section();
		}

		$this->render->set_settings( $this->settings->get_settings() );

		$this->hidden_fields();

		$this->render->set_hidden_settings( $this->settings->get_hidden_settings() );

		$btn_submit_text = $rocket_valid_key ? __( 'Save Changes', 'rocket' ) : __( 'Validate License', 'rocket' );
		echo $this->render->generate( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
			'page',
			[
				'slug'            => $this->slug, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
				'btn_submit_text' => $btn_submit_text, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
			]
		);
	}

	/**
	 * Enqueues WP Rocket scripts on the settings page
	 *
	 * @since 3.6.1
	 *
	 * @param string $hook The current admin page.
	 *
	 * @return void
	 */
	public function enqueue_rocket_scripts( $hook ) {
		if ( 'settings_page_wprocket' !== $hook ) {
			return;
		}

		wp_enqueue_script( 'wistia-e-v1', 'https://fast.wistia.com/assets/external/E-v1.js', [], null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	}

	/**
	 * Adds the async attribute to the Wistia script
	 *
	 * @since 3.6.1
	 *
	 * @param string $tag    The <script> tag for the enqueued script.
	 * @param string $handle The script's registered handle.
	 *
	 * @return string
	 */
	public function async_wistia_script( $tag, $handle ) {
		if ( 'wistia-e-v1' !== $handle ) {
			return $tag;
		}

		return str_replace( ' src', ' async src', $tag );
	}

	/**
	 * Returns the customer data to display on the dashboard
	 *
	 * @since 3.7.3 Update to use the user client class to get the data
	 * @since 3.0
	 *
	 * @return array
	 */
	public function customer_data() {
		$user = $this->user_client->get_user_data();
		$data = [
			'license_type'        => __( 'Unavailable', 'rocket' ),
			'license_expiration'  => __( 'Unavailable', 'rocket' ),
			'license_class'       => 'wpr-isInvalid',
			'is_from_one_dot_com' => false,
		];

		$data['license_type'] = rocket_get_license_type( $user );

		if ( ! is_object( $user ) ) {
			return $data;
		}

		if ( ! empty( $user->licence_expiration ) ) {
			$data['license_class'] = time() < $user->licence_expiration ? 'wpr-isValid' : 'wpr-isInvalid';
		}

		if ( ! empty( $user->licence_expiration ) ) {
			$data['license_expiration'] = date_i18n( get_option( 'date_format' ), (int) $user->licence_expiration );
		}

		if ( isset( $user->{'has_one-com_account'} ) ) {
			$data['is_from_one_dot_com'] = (bool) $user->{'has_one-com_account'};
		}

		return $data;
	}

	/**
	 * Toggle sliding checkboxes option value.
	 *
	 * @since 3.0
	 */
	public function toggle_option() {
		check_ajax_referer( 'rocket-ajax' );

		if ( ! current_user_can( 'rocket_manage_options' ) ) {
			wp_die();
		}

		$allowed = [
			'debug_enabled'               => 1,
			'varnish_auto_purge'          => 1,
			'do_cloudflare'               => 1,
			'cloudflare_protocol_rewrite' => 1,
			'sucury_waf_cache_sync'       => 1,
			'sucury_waf_api_key'          => 1,
			'cache_webp'                  => 1,
			'cache_logged_user'           => 1,
		];

		if ( ! isset( $_POST['option']['name'] ) || ! isset( $allowed[ $_POST['option']['name'] ] ) ) {
			wp_die();
		}

		$value = (int) ! empty( $_POST['option']['value'] );

		update_rocket_option( sanitize_key( $_POST['option']['name'] ), $value );

		wp_die();
	}

	/**
	 * Forces the value for the mobile options if a mobile plugin is active.
	 *
	 * @since  3.0
	 * @since  3.2 Not used anymore.
	 * @see    \WP_Rocket\Subscriber\Third_Party\Plugins\Mobile_Subscriber::is_mobile_plugin_active_callback()
	 *
	 * @param mixed $value Option value.
	 *
	 * @return mixed
	 */
	public function is_mobile_plugin_active( $value ) {
		if ( rocket_is_mobile_plugin_active() ) {
			return 1;
		}

		return $value;
	}

	/**
	 * Registers License section.
	 *
	 * @since 3.0
	 */
	private function license_section() {
		$this->settings->add_page_section(
			'license',
			[
				'title' => __( 'License', 'rocket' ),
			]
		);

		$this->settings->add_settings_sections(
			[
				'license_section' => [
					'type' => 'nocontainer',
					'page' => 'license',
				],
			]
		);

		$this->settings->add_settings_fields(
			[
				'consumer_key'   => [
					'type'              => 'text',
					'label'             => __( 'API key', 'rocket' ),
					'default'           => '',
					'container_class'   => [
						'wpr-field--split',
						'wpr-isDisabled',
					],
					'section'           => 'license_section',
					'page'              => 'license',
					'sanitize_callback' => 'sanitize_text_field',
					'input_attr'        => [
						'disabled' => 1,
					],
				],
				'consumer_email' => [
					'type'              => 'text',
					'label'             => __( 'Email address', 'rocket' ),
					'default'           => '',
					'container_class'   => [
						'wpr-field--split',
						'wpr-isDisabled',
					],
					'section'           => 'license_section',
					'page'              => 'license',
					'sanitize_callback' => 'sanitize_email',
					'input_attr'        => [
						'disabled' => 1,
					],
				],
			]
		);
	}

	/**
	 * Registers Dashboard section.
	 *
	 * @since 3.0
	 */
	private function dashboard_section() {
		$this->settings->add_page_section(
			'dashboard',
			[
				'title'            => __( 'Dashboard', 'rocket' ),
				'menu_description' => __( 'Get help, account info', 'rocket' ),
				'faq'              => $this->beacon->get_suggest( 'faq' ),
				'customer_data'    => $this->customer_data(),
			]
		);
	}

	/**
	 * Registers CSS & Javascript section.
	 *
	 * @since 3.0
	 */
	private function assets_section() {
		$combine_beacon             = $this->beacon->get_suggest( 'combine' );
		$defer_js_beacon            = $this->beacon->get_suggest( 'defer_js' );
		$async_beacon               = $this->beacon->get_suggest( 'async' );
		$files_beacon               = $this->beacon->get_suggest( 'file_optimization' );
		$inline_js_beacon           = $this->beacon->get_suggest( 'exclude_inline_js' );
		$exclude_js_beacon          = $this->beacon->get_suggest( 'exclude_js' );
		$exclude_css_beacon         = $this->beacon->get_suggest( 'exclude_css' );
		$delay_js_beacon            = $this->beacon->get_suggest( 'delay_js' );
		$delay_js_exclusions_beacon = $this->beacon->get_suggest( 'delay_js_exclusions' );
		$exclude_defer_js           = $this->beacon->get_suggest( 'exclude_defer_js' );
		$rucss_beacon               = $this->beacon->get_suggest( 'remove_unused_css' );
		$offline_beacon             = $this->beacon->get_suggest( 'offline' );
		$fallback_css_beacon        = $this->beacon->get_suggest( 'fallback_css' );

		$disable_combine_js = $this->disable_combine_js();
		$disable_ocd        = 'local' === wp_get_environment_type();

		/**
		 * Filters the status of the RUCSS option.
		 *
		 * @param array $should_disable will return array with disable status and text.
		 */
		$rucss_status = apply_filters(
			'rocket_disable_rucss_setting',
			[
				'disable' => false,
				'text'    => '',
			]
		);

		$invalid_license = get_option( 'wp_rocket_no_licence' );

		$this->settings->add_page_section(
			'file_optimization',
			[
				'title'            => __( 'File Optimization', 'rocket' ),
				'menu_description' => __( 'Optimize CSS & JS', 'rocket' ),
			]
		);

		$css_section_helper = [];

		if ( rocket_maybe_disable_minify_css() ) {
			// translators: %1$s = type of minification (HTML, CSS or JS), %2$s = “WP Rocket”.
			$css_section_helper[] = sprintf( __( '%1$s Minification is currently activated in <strong>Autoptimize</strong>. If you want to use %2$s’s minification, disable this option in Autoptimize.', 'rocket' ), 'CSS', WP_ROCKET_PLUGIN_NAME );
		}

		if ( $rucss_status['disable'] ) {
			$css_section_helper[] = $rucss_status['text'];
		}

		$this->settings->add_settings_sections(
			[
				'css' => [
					'title'  => __( 'CSS Files', 'rocket' ),
					'help'   => [
						'id'  => $this->beacon->get_suggest( 'css_section' ),
						'url' => $files_beacon['url'],
					],
					'page'   => 'file_optimization',
					'helper' => $css_section_helper,
				],
				'js'  => [
					'title'  => __( 'JavaScript Files', 'rocket' ),
					'help'   => [
						'id'  => $this->beacon->get_suggest( 'js_section' ),
						'url' => $files_beacon['url'],
					],
					'page'   => 'file_optimization',
					// translators: %1$s = type of minification (HTML, CSS or JS), %2$s = “WP Rocket”.
					'helper' => rocket_maybe_disable_minify_js() ? sprintf( __( '%1$s Minification is currently activated in <strong>Autoptimize</strong>. If you want to use %2$s’s minification, disable those options in Autoptimize.', 'rocket' ), 'JS', WP_ROCKET_PLUGIN_NAME ) : '',
				],
			]
		);

		$delay_js_list_helper = sprintf(
		// translators: %1$s = opening </a> tag, %2$s = closing </a> tag.
			esc_html__( 'Also, please check our %1$sdocumentation%2$s for a list of compatibility exclusions.', 'rocket' ),
			'<a href="' . esc_url( $delay_js_exclusions_beacon['url'] ) . '"  target="_blank" rel="noopener">',
			'</a>'
		);

		$delay_js_found_list_helper  = esc_html__( 'Internal scripts are excluded by default to prevent issues. Remove them to take full advantage of this option.', 'rocket' );
		$delay_js_found_list_helper .= '<br>' . sprintf(
		// translators: %1$s = opening </a> tag, %2$s = closing </a> tag.
			esc_html__( 'If this causes trouble, restore the default exclusions, found %1$shere%2$s', 'rocket' ),
			'<a href="' . esc_url( $delay_js_beacon['url'] ) . '"  target="_blank" rel="noopener">',
			'</a>'
		);

		$this->settings->add_settings_fields(
			[
				'minify_css'                   => [
					'type'              => 'checkbox',
					'label'             => __( 'Minify CSS files', 'rocket' ),
					'description'       => __( 'Minify CSS removes whitespace and comments to reduce the file size.', 'rocket' ),
					'container_class'   => [
						rocket_maybe_disable_minify_css() ? 'wpr-isDisabled' : '',
					],
					'section'           => 'css',
					'page'              => 'file_optimization',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
					'input_attr'        => [
						'disabled' => rocket_maybe_disable_minify_css() ? 1 : 0,
					],
				],
				'exclude_css'                  => [
					'type'              => 'textarea',
					'label'             => __( 'Excluded CSS Files', 'rocket' ),
					'description'       => __( 'Specify URLs of CSS files to be excluded from minification (one per line).', 'rocket' ),
					'helper'            => __( '<strong>Internal:</strong> The domain part of the URL will be stripped automatically. Use (.*).css wildcards to exclude all CSS files located at a specific path.', 'rocket' ) . '<br>' .
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					sprintf( __( '<strong>3rd Party:</strong> Use either the full URL path or only the domain name, to exclude external CSS. %1$sMore info%2$s', 'rocket' ), '<a href="' . esc_url( $exclude_css_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $exclude_css_beacon['id'] ) . '" rel="noopener noreferrer" target="_blank">', '</a>' ),
					'container_class'   => [
						'wpr-field--children',
					],
					'placeholder'       => '/wp-content/plugins/some-plugin/(.*).css',
					'parent'            => 'minify_css',
					'section'           => 'css',
					'page'              => 'file_optimization',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
				],
				'optimize_css_delivery'        => [
					'type'              => 'checkbox',
					'label'             => __( 'Optimize CSS delivery', 'rocket' ),
					'container_class'   => [
						$disable_ocd ? 'wpr-isDisabled' : '',
						'wpr-isParent',
					],
					'description'       => $invalid_license ? __( 'Optimize CSS delivery eliminates render-blocking CSS on your website. Only one method can be selected. Remove Unused CSS is recommended for optimal performance, but limited only to the users with active license.', 'rocket' ) : __( 'Optimize CSS delivery eliminates render-blocking CSS on your website. Only one method can be selected. Remove Unused CSS is recommended for optimal performance.', 'rocket' ),
					'section'           => 'css',
					'page'              => 'file_optimization',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
					'input_attr'        => [
						'disabled' => $disable_ocd ? 1 : 0,
					],
					'helper'            => $disable_ocd ? sprintf(
						// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
						__( 'Optimize CSS Delivery features are disabled on local environments. %1$sLearn more%2$s', 'rocket' ),
						'<a href="' . esc_url( $offline_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $offline_beacon['id'] ) . '" target="_blank">',
						'</a>'
					) : '',
				],
				'optimize_css_delivery_method' => [
					'type'                    => 'radio_buttons',
					'label'                   => __( 'Optimize CSS delivery', 'rocket' ),
					'container_class'         => [
						'wpr-field--children',
						'wpr-field--optimize-css-delivery',
					],
					'buttons_container_class' => '',
					'parent'                  => 'optimize_css_delivery',
					'section'                 => 'css',
					'page'                    => 'file_optimization',
					'default'                 => 'remove_unused_css',
					'sanitize_callback'       => 'sanitize_checkbox',
					'options'                 => [
						'remove_unused_css' => [
							'label'       => __( 'Remove Unused CSS', 'rocket' ),
							'disabled'    => $invalid_license || $rucss_status['disable'] ? 'disabled' : false,
							// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
							'description' => sprintf( __( 'Removes unused CSS per page and helps to reduce page size and HTTP requests. Recommended for best performance. Test thoroughly! %1$sMore info%2$s', 'rocket' ), '<a href="' . esc_url( $rucss_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $rucss_beacon['id'] ) . '" target="_blank">', '</a>' ),
							'warning'     => $invalid_license ? [] : [
								'title'        => __( 'This could break things!', 'rocket' ),
								'description'  => __( 'If you notice any errors on your website after having activated this setting, just deactivate it again, and your site will be back to normal.', 'rocket' ),
								'button_label' => __( 'Activate Remove Unused CSS', 'rocket' ),
							],
							'sub_fields'  => $invalid_license ? [] : [
								'remove_unused_css_safelist' =>
								[
									'type'              => 'textarea',
									'label'             => __( 'CSS safelist', 'rocket' ),
									'description'       => __( 'Specify CSS filenames, IDs or classes that should not be removed (one per line).', 'rocket' ),
									'placeholder'       => "/wp-content/plugins/some-plugin/(.*).css\n.css-class\n#css_id\ntag",
									'default'           => [],
									'value'             => [],
									'sanitize_callback' => 'sanitize_textarea',
									'parent'            => '',
									'section'           => 'css',
									'page'              => 'file_optimization',
									'input_attr'        => [
										'disabled' => get_rocket_option( 'remove_unused_css' ) ? 0 : 1,
									],
								],
							],
						],
						'async_css'         => [
							'label'       => __( 'Load CSS asynchronously', 'rocket' ),
							'description' => is_plugin_active( 'wp-criticalcss/wp-criticalcss.php' ) ?
								// translators: %1$s = plugin name.
								sprintf( _x( 'Load CSS asynchronously is currently handled by the %1$s plugin. If you want to use WP Rocket’s load CSS asynchronously option, disable the %1$s plugin.', 'WP Critical CSS compatibility', 'rocket' ), 'WP Critical CSS' ) :
								// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
								sprintf( __( 'Generates critical path CSS and loads CSS asynchronously. %1$sMore info%2$s', 'rocket' ), '<a href="' . esc_url( $async_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $async_beacon['id'] ) . '" target="_blank">', '</a>' ),
							'disabled'    => is_plugin_active( 'wp-criticalcss/wp-criticalcss.php' ) ? 'disabled' : '',
							'sub_fields'  => [
								'critical_css' =>
									[
										'type'        => 'textarea',
										'label'       => __( 'Fallback critical CSS', 'rocket' ),
										// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
										'helper'      => sprintf( __( 'Provides a fallback if auto-generated critical path CSS is incomplete. %1$sMore info%2$s', 'rocket' ), '<a href="' . esc_url( $fallback_css_beacon['url'] ) . '#fallback" data-beacon-article="' . esc_attr( $fallback_css_beacon['id'] ) . '" target="_blank">', '</a>' ),
										'sanitize_callback' => 'sanitize_textarea',
										'parent'      => '',
										'section'     => 'css',
										'page'        => 'file_optimization',
										'placeholder' => '',
										'default'     => [],
										'value'       => [],
									],
							],
						],
					],
				],
				'minify_js'                    => [
					'type'              => 'checkbox',
					'label'             => __( 'Minify JavaScript files', 'rocket' ),
					'description'       => __( 'Minify JavaScript removes whitespace and comments to reduce the file size.', 'rocket' ),
					'container_class'   => [
						rocket_maybe_disable_minify_js() ? 'wpr-isDisabled' : '',
					],
					'section'           => 'js',
					'page'              => 'file_optimization',
					'default'           => 0,
					'input_attr'        => [
						'disabled' => rocket_maybe_disable_minify_js() ? 1 : 0,
					],
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'minify_concatenate_js'        => [
					'type'              => 'checkbox',
					'label'             => __( 'Combine JavaScript files <em>(Enable Minify JavaScript files to select)</em>', 'rocket' ),
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description'       => sprintf( __( 'Combine JavaScript files combines your site’s internal, 3rd party and inline JS reducing HTTP requests. Not recommended if your site uses HTTP/2. %1$sMore info%2$s', 'rocket' ), '<a href="' . esc_url( $combine_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $combine_beacon['id'] ) . '" target="_blank">', '</a>' ),
					'helper'            => get_rocket_option( 'delay_js' ) ? __( 'For compatibility and best results, this option is disabled when delay javascript execution is enabled.', 'rocket' ) : '',
					'container_class'   => [
						$disable_combine_js ? 'wpr-isDisabled' : '',
						'wpr-field--parent',
						'wpr-NoPaddingBottom',
					],
					'section'           => 'js',
					'page'              => 'file_optimization',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
					'input_attr'        => [
						'disabled' => $disable_combine_js ? 1 : 0,
					],
					'warning'           => [
						'title'        => __( 'This could break things!', 'rocket' ),
						'description'  => __( 'If you notice any errors on your website after having activated this setting, just deactivate it again, and your site will be back to normal.', 'rocket' ),
						'button_label' => __( 'Activate combine JavaScript', 'rocket' ),
					],
				],
				'exclude_inline_js'            => [
					'type'              => 'textarea',
					'label'             => __( 'Excluded Inline JavaScript', 'rocket' ),
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description'       => sprintf( __( 'Specify patterns of inline JavaScript to be excluded from concatenation (one per line). %1$sMore info%2$s', 'rocket' ), '<a href="' . esc_url( $inline_js_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $inline_js_beacon['id'] ) . '" rel="noopener noreferrer" target="_blank">', '</a>' ),
					'container_class'   => [
						'wpr-field--children',
					],
					'placeholder'       => 'recaptcha',
					'parent'            => 'minify_concatenate_js',
					'section'           => 'js',
					'page'              => 'file_optimization',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
					'input_attr'        => [
						'disabled' => get_rocket_option( 'minify_concatenate_js' ) ? 0 : 1,
					],
				],
				'exclude_js'                   => [
					'type'              => 'textarea',
					'label'             => __( 'Excluded JavaScript Files', 'rocket' ),
					'description'       => __( 'Specify URLs of JavaScript files to be excluded from minification and concatenation (one per line).', 'rocket' ),
					'helper'            => __( '<strong>Internal:</strong> The domain part of the URL will be stripped automatically. Use (.*).js wildcards to exclude all JS files located at a specific path.', 'rocket' ) . '<br>' .
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					sprintf( __( '<strong>3rd Party:</strong> Use either the full URL path or only the domain name, to exclude external JS. %1$sMore info%2$s', 'rocket' ), '<a href="' . esc_url( $exclude_js_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $exclude_js_beacon['id'] ) . '" rel="noopener noreferrer" target="_blank">', '</a>' ),
					'container_class'   => [
						'wpr-field--children',
					],
					'placeholder'       => '/wp-content/themes/some-theme/(.*).js',
					'parent'            => 'minify_js',
					'section'           => 'js',
					'page'              => 'file_optimization',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
				],
				'defer_all_js'                 => [
					'container_class'   => [
						'wpr-isParent',
					],
					'type'              => 'checkbox',
					'label'             => __( 'Load JavaScript deferred', 'rocket' ),
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description'       => sprintf( __( 'Load JavaScript deferred eliminates render-blocking JS on your site and can improve load time. %1$sMore info%2$s', 'rocket' ), '<a href="' . esc_url( $defer_js_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $defer_js_beacon['id'] ) . '" target="_blank">', '</a>' ),
					'section'           => 'js',
					'page'              => 'file_optimization',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'exclude_defer_js'             => [
					'container_class'   => [
						'wpr-field--children',
					],
					'type'              => 'textarea',
					'label'             => __( 'Excluded JavaScript Files', 'rocket' ),
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description'       => sprintf( __( 'Specify URLs or keywords of JavaScript files to be excluded from defer (one per line). %1$sMore info%2$s', 'rocket' ), '<a href="' . esc_url( $exclude_defer_js['url'] ) . '" data-beacon-article="' . esc_attr( $exclude_defer_js['id'] ) . '" target="_blank">', '</a>' ),
					'placeholder'       => '/wp-content/themes/some-theme/(.*).js',
					'parent'            => 'defer_all_js',
					'section'           => 'js',
					'page'              => 'file_optimization',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
				],
				'delay_js'                     => apply_filters(
					'rocket_delay_js_settings_field',
					[
						'container_class'   => [
							'wpr-isParent',
							'wpr-Delayjs',
						],
						'type'              => 'checkbox',
						'label'             => __( 'Delay JavaScript execution', 'rocket' ),
						// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
						'description'       => sprintf( __( 'Improves performance by delaying the loading of JavaScript files until user interaction (e.g. scroll, click). %1$sMore info%2$s', 'rocket' ), '<a href="' . esc_url( $delay_js_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $delay_js_beacon['id'] ) . '" target="_blank">', '</a>' ),
						'section'           => 'js',
						'page'              => 'file_optimization',
						'default'           => 0,
						'sanitize_callback' => 'sanitize_checkbox',
					]
				),
				'delay_js_exclusions_selected' => [
					'type'              => 'categorized_multiselect',
					'label'             => __( 'One-click exclusions', 'rocket' ),
					'description'       => __( 'When using the Delay JavaScript feature, you might notice that some elements in the viewport take time to appear.', 'rocket' ),
					'sub_description'   => __( 'If you need these elements to load immediately, select the related plugins, themes, or services below to ensure they appear without delay.', 'rocket' ),
					'container_class'   => [
						'wpr-field--children',
					],
					'parent'            => 'delay_js',
					'section'           => 'js',
					'page'              => 'file_optimization',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
					'input_attr'        => [
						'disabled' => get_rocket_option( 'delay_js' ) ? 0 : 1,
					],
					'items'             => $this->delayjs_sitelist->prepare_delayjs_ui_list(),
				],
				'delay_js_exclusions'          => [
					'type'              => 'textarea',
					'container_class'   => [
						'wpr-field--children',
					],
					'label'             => __( 'Excluded JavaScript Files', 'rocket' ),
					'description'       => __( 'Specify URLs or keywords that can identify inline or JavaScript files to be excluded from delaying execution (one per line).', 'rocket' ),
					'parent'            => 'delay_js',
					'section'           => 'js',
					'page'              => 'file_optimization',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
					'input_attr'        => [
						'disabled' => get_rocket_option( 'delay_js' ) ? 0 : 1,
					],
					'helper'            => DelayJSSettings::exclusion_list_has_default() ? $delay_js_found_list_helper : $delay_js_list_helper,
					'placeholder'       => '',
				],
				'delay_js_execution_safe_mode' => [
					'type'              => 'checkbox',
					'label'             => __( 'Safe Mode for Delay JavaScript Execution', 'rocket' ),
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description'       => __( 'The Safe Mode mode prevents all internal scripts from being delayed.', 'rocket' ),
					'helper'            => '',
					'container_class'   => [
						'wpr-field--parent',
						'wpr-NoPaddingBottom',
						'wpr-field--children',
					],
					'section'           => 'js',
					'page'              => 'file_optimization',
					'parent'            => 'delay_js',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
					'input_attr'        => [
						'disabled' => 0,
					],
					'warning'           => [
						'title'        => __( 'Performance impact', 'rocket' ),
						'description'  => __( 'By enabling Safe Mode, you significantly reduce your website performance improvements. We recommend using it only as a temporary solution. If you’re experiencing issues with the Delay JavaScript feature, our support team can help you troubleshoot.', 'rocket' ),
						'button_label' => __( 'ACTIVATE SAFE MODE', 'rocket' ),
					],
				],
			],
		);
	}

	/**
	 * Registers Media section.
	 *
	 * @since 3.0
	 */
	private function media_section() {
		$lazyload_beacon  = $this->beacon->get_suggest( 'lazyload' );
		$exclude_lazyload = $this->beacon->get_suggest( 'exclude_lazyload' );
		$dimensions       = $this->beacon->get_suggest( 'image_dimensions' );
		$fonts            = $this->beacon->get_suggest( 'host_fonts_locally' );
		$fonts_preload    = $this->beacon->get_suggest( 'fonts_preload' );

		$this->settings->add_page_section(
			'media',
			[
				'title'            => __( 'Media', 'rocket' ),
				'menu_description' => __( 'LazyLoad, image dimensions, font optimization', 'rocket' ),
			]
		);

		$disable_images_lazyload  = [];
		$disable_iframes_lazyload = [];
		$disable_youtube_lazyload = [];

		if ( rocket_maybe_disable_lazyload() ) {
			$disable_images_lazyload[] = __( 'Autoptimize', 'rocket' );
		}

		/**
		 * Lazyload Helper filter which disables WPR lazyload functionality for images.
		 *
		 * @since  3.4.2
		 *
		 * @param array $disable_images_lazyload Will return the array with all plugin names which should disable LazyLoad
		 */
		$disable_images_lazyload = (array) apply_filters( 'rocket_maybe_disable_lazyload_helper', $disable_images_lazyload );
		$disable_images_lazyload = $this->sanitize_and_format_list( $disable_images_lazyload );

		/**
		 * Lazyload Helper filter which disables WPR lazyload functionality for iframes.
		 *
		 * @since 3.5.5
		 *
		 * @param array $disable_iframes_lazyload Will return the array with all plugin names which should disable LazyLoad
		 */
		$disable_iframes_lazyload = (array) apply_filters( 'rocket_maybe_disable_iframes_lazyload_helper', $disable_iframes_lazyload );
		$disable_iframes_lazyload = $this->sanitize_and_format_list( $disable_iframes_lazyload );

		$disable_css_bg_img_lazyload = false;

		/**
		 * Lazyload Helper filter which disables WPR lazyload functionality for bg css.
		 *
		 * @param bool $disable_css_bg_img_lazyload Should the lazyload CSS be disabled.
		 */
		$disable_css_bg_img_lazyload = (bool) apply_filters( 'rocket_maybe_disable_css_bg_img_lazyload_helper', $disable_css_bg_img_lazyload );

		/**
		 * Lazyload Helper filter which disables WPR lazyload functionality to replace YouTube iframe with preview image.
		 *
		 * @since 3.6.3
		 *
		 * @param array $disable_youtube_lazyload Will return the array with all plugin/themes names which should disable replace YouTube iframe with preview image
		 */
		$disable_youtube_lazyload = (array) apply_filters( 'rocket_maybe_disable_youtube_lazyload_helper', $disable_youtube_lazyload );
		$disable_youtube_lazyload = $this->sanitize_and_format_list( $disable_youtube_lazyload );
		$disable_youtube_lazyload = array_merge( $disable_youtube_lazyload, $disable_iframes_lazyload );
		$disable_youtube_lazyload = array_unique( $disable_youtube_lazyload );

		$disable_lazyload = array_merge( $disable_images_lazyload, $disable_iframes_lazyload );
		$disable_lazyload = array_unique( $disable_lazyload );

		$disable_lazyload         = wp_sprintf_l( '%l', $disable_lazyload );
		$disable_images_lazyload  = wp_sprintf_l( '%l', $disable_images_lazyload );
		$disable_youtube_lazyload = wp_sprintf_l( '%l', $disable_youtube_lazyload );

		$this->settings->add_settings_sections(
			[
				'lazyload_section'          => [
					'title'       => __( 'LazyLoad', 'rocket' ),
					'type'        => 'fields_container',
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description' => sprintf( __( 'It can improve actual and perceived loading time as images, iframes, and videos will be loaded only as they enter (or about to enter) the viewport and reduces the number of HTTP requests. %1$sMore Info%2$s', 'rocket' ), '<a href="' . esc_url( $lazyload_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $lazyload_beacon['id'] ) . '" target="_blank" rel="noopener noreferrer">', '</a>' ),
					'help'        => [
						'id'  => $this->beacon->get_suggest( 'lazyload_section' ),
						'url' => $lazyload_beacon['url'],
					],
					'page'        => 'media',
					// translators: %1$s = “WP Rocket”, %2$s = a list of plugin names.
					'helper'      => ! empty( $disable_lazyload ) ? sprintf( __( 'LazyLoad is currently activated in %2$s. If you want to use WP Rocket’s LazyLoad, disable this option in %2$s.', 'rocket' ), WP_ROCKET_PLUGIN_NAME, $disable_lazyload ) : '',
				],
				'dimensions_section'        => [
					'title'       => __( 'Image Dimensions', 'rocket' ),
					'type'        => 'fields_container',
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description' => sprintf( __( 'Add missing width and height attributes to images. Helps prevent layout shifts and improve the reading experience for your visitors. %1$sMore info%2$s', 'rocket' ), '<a href="' . esc_url( $dimensions['url'] ) . '" data-beacon-article="' . esc_attr( $dimensions['id'] ) . '" target="_blank" rel="noopener noreferrer">', '</a>' ),
					'help'        => $dimensions,
					'page'        => 'media',
				],
				'font_optimization_section' => [
					'title' => __( 'Fonts', 'rocket' ),
					'type'  => 'fields_container',
					'help'  => $fonts,
					'page'  => 'media',
				],
			]
		);

		/**
		 * Add more content to the 'cache_webp' setting field.
		 *
		 * @since  3.4
		 *
		 * @param array $cache_webp_field Data to be added to the setting field.
		 */

		$this->settings->add_settings_fields(
			[
				'lazyload'            => [
					'type'              => 'checkbox',
					'label'             => __( 'Enable for images', 'rocket' ),
					'section'           => 'lazyload_section',
					'page'              => 'media',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
					'container_class'   => [
						! empty( $disable_images_lazyload ) ? 'wpr-isDisabled' : '',
					],
					'input_attr'        => [
						'disabled' => ! empty( $disable_images_lazyload ) ? 1 : 0,
					],
					// translators: %1$s = “WP Rocket”, %2$s = a list of plugin names.
					'description'       => ! empty( $disable_images_lazyload ) ? sprintf( __( 'LazyLoad for images is currently activated in %2$s. If you want to use %1$s’s LazyLoad, disable this option in %2$s.', 'rocket' ), WP_ROCKET_PLUGIN_NAME, $disable_images_lazyload ) : '',
				],
				'lazyload_css_bg_img' => [
					'container_class'   => [
						$disable_css_bg_img_lazyload ? 'wpr-isDisabled' : '',
						'wpr-isParent',
					],
					'type'              => 'checkbox',
					'label'             => __( 'Enable for CSS background images', 'rocket' ),
					'section'           => 'lazyload_section',
					'page'              => 'media',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
					'input_attr'        => [
						'disabled' => $disable_css_bg_img_lazyload ? 1 : 0,
					],
				],
				'lazyload_iframes'    => [
					'container_class'   => [
						! empty( $disable_iframes_lazyload ) ? 'wpr-isDisabled' : '',
						'wpr-isParent',
					],
					'type'              => 'checkbox',
					'label'             => __( 'Enable for iframes and videos', 'rocket' ),
					'section'           => 'lazyload_section',
					'page'              => 'media',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
					'input_attr'        => [
						'disabled' => ! empty( $disable_iframes_lazyload ) ? 1 : 0,
					],
				],
				'lazyload_youtube'    => [
					'container_class'   => [
						! empty( $disable_youtube_lazyload ) ? 'wpr-isDisabled' : '',
						'wpr-field--children',
					],
					'type'              => 'checkbox',
					'label'             => __( 'Replace YouTube iframe with preview image', 'rocket' ),
					// translators: %1$s = “WP Rocket”, %2$s = a list of plugin or themes names.
					'description'       => ! empty( $disable_youtube_lazyload ) ? sprintf( __( 'Replace YouTube iframe with preview image is not compatible with %2$s.', 'rocket' ), WP_ROCKET_PLUGIN_NAME, $disable_youtube_lazyload ) : __( 'This can significantly improve your loading time if you have a lot of YouTube videos on a page.', 'rocket' ),
					'parent'            => 'lazyload_iframes',
					'section'           => 'lazyload_section',
					'page'              => 'media',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
					'input_attr'        => [
						'disabled' => ! empty( $disable_youtube_lazyload ) ? 1 : 0,
					],
				],
				'exclude_lazyload'    => [
					'container_class' => [
						'wpr-Delayjs',
					],
					'type'            => 'textarea',
					'label'           => __( 'Excluded images or iframes', 'rocket' ),
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description'     => sprintf( __( 'Specify keywords (e.g. image filename, CSS filename, CSS class, domain) from the image or iframe code to be excluded (one per line). %1$sMore info%2$s', 'rocket' ), '<a href="' . esc_url( $exclude_lazyload['url'] ) . '" data-beacon-article="' . esc_attr( $exclude_lazyload['id'] ) . '" target="_blank" rel="noopener noreferrer">', '</a>' ),
					'section'         => 'lazyload_section',
					'page'            => 'media',
					'default'         => [],
					'placeholder'     => "example-image.jpg\nslider-image\nbackground-image-style.css",
				],
				'image_dimensions'    => [
					'type'              => 'checkbox',
					'label'             => __( 'Add missing image dimensions', 'rocket' ),
					'section'           => 'dimensions_section',
					'page'              => 'media',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'auto_preload_fonts'  => [
					'type'              => 'checkbox',
					'label'             => __( 'Preload fonts', 'rocket' ),
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description'       => sprintf( __( 'Preload above-the-fold fonts to enhance layout stability and optimize text-based LCP elements. %1$sMore info%2$s', 'rocket' ), '<a href="' . esc_url( $fonts_preload['url'] ) . '" data-beacon-article="' . esc_attr( $fonts_preload['id'] ) . '" target="_blank" rel="noopener noreferrer">', '</a>' ),
					'section'           => 'font_optimization_section',
					'page'              => 'media',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'host_fonts_locally'  => [
					'type'              => 'checkbox',
					'label'             => __( 'Self-host Google Fonts', 'rocket' ),
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description'       => sprintf( __( 'Download and serve fonts directly from your server. Reduces connections to external servers and minimizes font shifts. %1$sMore info%2$s', 'rocket' ), '<a href="' . esc_url( $fonts['url'] ) . '" data-beacon-article="' . esc_attr( $fonts['id'] ) . '" target="_blank" rel="noopener noreferrer">', '</a>' ),
					'section'           => 'font_optimization_section',
					'page'              => 'media',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
			]
		);
	}

	/**
	 * Registers Preload section.
	 *
	 * @since 3.0
	 */
	private function preload_section() {
		$this->settings->add_page_section(
			'preload',
			[
				'title'            => __( 'Preload', 'rocket' ),
				'menu_description' => __( 'Generate cache files', 'rocket' ),
			]
		);

		$bot_beacon    = $this->beacon->get_suggest( 'bot' );
		$fonts_preload = $this->beacon->get_suggest( 'fonts_preload' );
		$preload_links = $this->beacon->get_suggest( 'preload_links' );
		$exclusions    = $this->beacon->get_suggest( 'preload_exclusions' );

		$this->settings->add_settings_sections(
			[
				'preload_section'       => [
					'title'       => __( 'Preload Cache', 'rocket' ),
					'type'        => 'fields_container',
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description' => __( 'When you enable preloading WP Rocket will automatically detect your sitemaps and save all URLs to the database. The plugin will make sure that your cache is always preloaded.', 'rocket' ),
					'help'        => [
						'id'  => $this->beacon->get_suggest( 'sitemap_preload' ),
						'url' => $bot_beacon['url'],
					],
					'page'        => 'preload',
				],
				'preload_links_section' => [
					'title'       => __( 'Preload Links', 'rocket' ),
					'type'        => 'fields_container',
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description' => sprintf( __( 'Link preloading improves the perceived load time by downloading a page when a user hovers over the link. %1$sMore info%2$s', 'rocket' ), '<a href="' . esc_url( $preload_links['url'] ) . '" data-beacon-article="' . esc_attr( $preload_links['id'] ) . '" target="_blank">', '</a>' ),
					'help'        => [
						'id'  => $preload_links['id'],
						'url' => $preload_links['url'],
					],
					'page'        => 'preload',
				],
			]
		);

		$this->settings->add_settings_fields(
			[
				'manual_preload'       => [
					'type'              => 'checkbox',
					'label'             => __( 'Activate Preloading', 'rocket' ),
					'section'           => 'preload_section',
					'page'              => 'preload',
					'default'           => 1,
					'sanitize_callback' => 'sanitize_checkbox',
					'container_class'   => [
						'wpr-isParent',
					],
				],
				'preload_excluded_uri' => [
					'type'              => 'textarea',
					'label'             => __( 'Exclude URLs', 'rocket' ),
					'container_class'   => [
						'wpr-field--children',
					],
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description'       => sprintf( __( 'Specify URLs to be excluded from the preload feature (one per line). %1$sMore info%2$s', 'rocket' ), '<a href="' . esc_url( $exclusions['url'] ) . '" data-beacon-article="' . esc_attr( $exclusions['id'] ) . '" target="_blank">', '</a>' ),
					'placeholder'       => '/author/(.*)',
					'helper'            => 'Use (.*) wildcards to address multiple URLs under a given path.',
					'parent'            => 'manual_preload',
					'section'           => 'preload_section',
					'page'              => 'preload',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
				],
				'preload_links'        => [
					'type'              => 'checkbox',
					'label'             => __( 'Enable link preloading', 'rocket' ),
					'section'           => 'preload_links_section',
					'page'              => 'preload',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
			]
		);
	}

	/**
	 * Registers Advanced Cache section.
	 *
	 * @since 3.0
	 */
	private function advanced_cache_section() {
		$this->settings->add_page_section(
			'advanced_cache',
			[
				'title'            => __( 'Advanced Rules', 'rocket' ),
				'menu_description' => __( 'Fine-tune cache rules', 'rocket' ),
			]
		);
		$ecommerce_beacon           = $this->beacon->get_suggest( 'ecommerce' );
		$cache_query_strings_beacon = $this->beacon->get_suggest( 'cache_query_strings' );
		$never_cache_beacon         = $this->beacon->get_suggest( 'exclude_cache' );
		$never_cache_cookie_beacon  = $this->beacon->get_suggest( 'exclude_cookie' );
		$exclude_user_agent_beacon  = $this->beacon->get_suggest( 'exclude_user_agent' );
		$always_purge_beacon        = $this->beacon->get_suggest( 'always_purge' );
		$cache_life_beacon          = $this->beacon->get_suggest( 'cache_lifespan' );
		$nonce_beacon               = $this->beacon->get_suggest( 'nonce' );

		$ecommerce_plugin = '';
		$reject_uri_desc  = __( 'Sensitive pages like custom login/logout URLs should be excluded from cache.', 'rocket' );

		if ( function_exists( 'WC' ) && function_exists( 'wc_get_page_id' ) ) {
			$ecommerce_plugin = _x( 'WooCommerce', 'plugin name', 'rocket' );
		} elseif ( function_exists( 'EDD' ) ) {
			$ecommerce_plugin = _x( 'Easy Digital Downloads', 'plugin name', 'rocket' );
		} elseif ( function_exists( 'it_exchange_get_page_type' ) && function_exists( 'it_exchange_get_page_url' ) ) {
			$ecommerce_plugin = _x( 'iThemes Exchange', 'plugin name', 'rocket' );
		} elseif ( defined( 'JIGOSHOP_VERSION' ) && function_exists( 'jigoshop_get_page_id' ) ) {
			$ecommerce_plugin = _x( 'Jigoshop', 'plugin name', 'rocket' );
		} elseif ( defined( 'WPSHOP_VERSION' ) && class_exists( 'wpshop_tools' ) && method_exists( 'wpshop_tools', 'get_page_id' ) ) { // @phpstan-ignore-line
			$ecommerce_plugin = _x( 'WP-Shop', 'plugin name', 'rocket' );
		}

		if ( ! empty( $ecommerce_plugin ) ) {
			$reject_uri_desc .= sprintf(
					// translators: %1$s = opening <a> tag, %2$s = plugin name, %3$s closing </a> tag.
					__( '<br>Cart, checkout and "my account" pages set in <strong>%1$s%2$s%3$s</strong> will be detected and never cached by default.', 'rocket' ),
					'<a href="' . esc_url( $ecommerce_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $ecommerce_beacon['id'] ) . '" target="_blank">',
					$ecommerce_plugin,
					'</a>'
			);
		}

		$this->settings->add_settings_sections(
			[
				'cache_lifespan'               => [
					'title'       => __( 'Cache Lifespan', 'rocket' ),
					'type'        => 'fields_container',
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description' => sprintf( __( 'Cache files older than the specified lifespan will be deleted.<br>Enable %1$spreloading%2$s for the cache to be rebuilt automatically after lifespan expiration.', 'rocket' ), '<a href="#preload">', '</a>' ),
					'help'        => [
						'url' => $cache_life_beacon['url'],
						'id'  => $this->beacon->get_suggest( 'cache_lifespan_section' ),
					],
					'page'        => 'advanced_cache',
				],
				'cache_reject_uri_section'     => [
					'title'       => __( 'Never Cache URL(s)', 'rocket' ),
					'type'        => 'fields_container',
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description' => $reject_uri_desc,
					'help'        => $never_cache_beacon,
					'page'        => 'advanced_cache',
				],
				'cache_reject_cookies_section' => [
					'title' => __( 'Never Cache Cookies', 'rocket' ),
					'type'  => 'fields_container',
					'page'  => 'advanced_cache',
					'help'  => $never_cache_cookie_beacon,
				],
				'cache_reject_ua_section'      => [
					'title' => __( 'Never Cache User Agent(s)', 'rocket' ),
					'type'  => 'fields_container',
					'help'  => $exclude_user_agent_beacon,
					'page'  => 'advanced_cache',
				],
				'cache_purge_pages_section'    => [
					'title' => __( 'Always Purge URL(s)', 'rocket' ),
					'type'  => 'fields_container',
					'help'  => $always_purge_beacon,
					'page'  => 'advanced_cache',
				],
				'cache_query_strings_section'  => [
					'title'       => __( 'Cache Query String(s)', 'rocket' ),
					'type'        => 'fields_container',
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description' => sprintf( __( '%1$sCache for query strings%2$s enables you to force caching for specific GET parameters.', 'rocket' ), '<a href="' . esc_url( $cache_query_strings_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $cache_query_strings_beacon['id'] ) . '" target="_blank">', '</a>' ),
					'help'        => $cache_query_strings_beacon,
					'page'        => 'advanced_cache',
				],
			]
		);

		$this->settings->add_settings_fields(
			[
				'purge_cron_interval'  => [
					'type'              => 'cache_lifespan',
					'label'             => __( 'Specify time after which the global cache is cleared<br>(0 = unlimited )', 'rocket' ),
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description'       => sprintf( __( 'Reduce lifespan to 10 hours or less if you notice issues that seem to appear periodically. %1$sWhy?%2$s', 'rocket' ), '<a href="' . esc_url( $nonce_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $nonce_beacon['id'] ) . '" target="_blank">', '</a>' ),
					'section'           => 'cache_lifespan',
					'page'              => 'advanced_cache',
					'default'           => 10,
					'sanitize_callback' => 'sanitize_cache_lifespan',
					'choices'           => [
						'HOUR_IN_SECONDS' => __( 'Hours', 'rocket' ),
						'DAY_IN_SECONDS'  => __( 'Days', 'rocket' ),
					],
				],
				'cache_reject_uri'     => [
					'type'              => 'textarea',
					'description'       => __( 'Specify URLs of pages or posts that should never be cached (one per line)', 'rocket' ),
					'helper'            => __( 'The domain part of the URL will be stripped automatically.<br>Use (.*) wildcards to address multiple URLs under a given path.', 'rocket' ),
					'placeholder'       => '/example/(.*)',
					'section'           => 'cache_reject_uri_section',
					'page'              => 'advanced_cache',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
				],
				'cache_reject_cookies' => [
					'type'              => 'textarea',
					'description'       => __( 'Specify full or partial IDs of cookies that, when set in the visitor\'s browser, should prevent a page from getting cached (one per line)', 'rocket' ),
					'section'           => 'cache_reject_cookies_section',
					'page'              => 'advanced_cache',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
				],
				'cache_reject_ua'      => [
					'type'              => 'textarea',
					'description'       => __( 'Specify user agent strings that should never see cached pages (one per line)', 'rocket' ),
					'helper'            => __( 'Use (.*) wildcards to detect parts of UA strings.', 'rocket' ),
					'placeholder'       => '(.*)Mobile(.*)Safari(.*)',
					'section'           => 'cache_reject_ua_section',
					'page'              => 'advanced_cache',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
				],
				'cache_purge_pages'    => [
					'type'              => 'textarea',
					'description'       => __( 'Specify URLs you always want purged from cache whenever you update any post or page (one per line)', 'rocket' ),
					'helper'            => __( 'The domain part of the URL will be stripped automatically.<br>Use (.*) wildcards to address multiple URLs under a given path.', 'rocket' ),
					'section'           => 'cache_purge_pages_section',
					'page'              => 'advanced_cache',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
				],
				'cache_query_strings'  => [
					'type'              => 'textarea',
					'description'       => __( 'Specify query strings for caching (one per line)', 'rocket' ),
					'section'           => 'cache_query_strings_section',
					'page'              => 'advanced_cache',
					'default'           => [],
					'sanitize_callback' => 'sanitize_textarea',
				],
			]
		);
	}

	/**
	 * Registers Database section.
	 *
	 * @since 3.0
	 */
	private function database_section() {
		$total = [];

		foreach ( array_keys( $this->optimize->get_options() ) as $key ) {
			$total[ $key ] = $this->optimize->count_cleanup_items( $key );
		}

		$this->settings->add_page_section(
			'database',
			[
				'title'            => __( 'Database', 'rocket' ),
				'menu_description' => __( 'Optimize, reduce bloat', 'rocket' ),
			]
		);

		$this->settings->add_settings_sections(
			[
				'post_cleanup_section'       => [
					'title'       => __( 'Post Cleanup', 'rocket' ),
					'type'        => 'fields_container',
					'description' => __( 'Post revisions and drafts will be permanently deleted. Do not use this option if you need to retain revisions or drafts.', 'rocket' ),
					'help'        => $this->beacon->get_suggest( 'db_optimization' ),
					'page'        => 'database',
				],
				'comments_cleanup_section'   => [
					'title'       => __( 'Comments Cleanup', 'rocket' ),
					'type'        => 'fields_container',
					'description' => __( 'Spam and trashed comments will be permanently deleted.', 'rocket' ),
					'page'        => 'database',
				],
				'transients_cleanup_section' => [
					'title'       => __( 'Transients Cleanup', 'rocket' ),
					'type'        => 'fields_container',
					'description' => __( 'Transients are temporary options; they are safe to remove. They will be automatically regenerated as your plugins require them.', 'rocket' ),
					'page'        => 'database',
				],
				'database_cleanup_section'   => [
					'title'       => __( 'Database Cleanup', 'rocket' ),
					'type'        => 'fields_container',
					'description' => __( 'Reduces overhead of database tables', 'rocket' ),
					'page'        => 'database',
				],
				'schedule_cleanup_section'   => [
					'title' => __( 'Automatic Cleanup', 'rocket' ),
					'type'  => 'fields_container',
					'page'  => 'database',
				],
			]
		);

		$this->settings->add_settings_fields(
			[
				'database_revisions'          => [
					'type'              => 'checkbox',
					'label'             => __( 'Revisions', 'rocket' ),
					// translators: %s is the number of revisions found in the database. It's a formatted number, don't use %d.
					'description'       => sprintf( _n( '%s revision in your database.', '%s revisions in your database.', $total['database_revisions'], 'rocket' ), number_format_i18n( $total['database_revisions'] ) ),
					'section'           => 'post_cleanup_section',
					'page'              => 'database',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'database_auto_drafts'        => [
					'type'              => 'checkbox',
					'label'             => __( 'Auto Drafts', 'rocket' ),
					// translators: %s is the number of revisions found in the database. It's a formatted number, don't use %d.
					'description'       => sprintf( _n( '%s draft in your database.', '%s drafts in your database.', $total['database_auto_drafts'], 'rocket' ), number_format_i18n( $total['database_auto_drafts'] ) ),
					'section'           => 'post_cleanup_section',
					'page'              => 'database',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'database_trashed_posts'      => [
					'type'              => 'checkbox',
					'label'             => __( 'Trashed Posts', 'rocket' ),
					// translators: %s is the number of revisions found in the database. It's a formatted number, don't use %d.
					'description'       => sprintf( _n( '%s trashed post in your database.', '%s trashed posts in your database.', $total['database_trashed_posts'], 'rocket' ), $total['database_trashed_posts'] ),
					'section'           => 'post_cleanup_section',
					'page'              => 'database',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'database_spam_comments'      => [
					'type'              => 'checkbox',
					'label'             => __( 'Spam Comments', 'rocket' ),
					// translators: %s is the number of revisions found in the database. It's a formatted number, don't use %d.
					'description'       => sprintf( _n( '%s spam comment in your database.', '%s spam comments in your database.', $total['database_spam_comments'], 'rocket' ), number_format_i18n( $total['database_spam_comments'] ) ),
					'section'           => 'comments_cleanup_section',
					'page'              => 'database',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'database_trashed_comments'   => [
					'type'              => 'checkbox',
					'label'             => __( 'Trashed Comments', 'rocket' ),
					// translators: %s is the number of revisions found in the database. It's a formatted number, don't use %d.
					'description'       => sprintf( _n( '%s trashed comment in your database.', '%s trashed comments in your database.', $total['database_trashed_comments'], 'rocket' ), number_format_i18n( $total['database_trashed_comments'] ) ),
					'section'           => 'comments_cleanup_section',
					'page'              => 'database',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'database_all_transients'     => [
					'type'              => 'checkbox',
					'label'             => __( 'All transients', 'rocket' ),
					// translators: %s is the number of revisions found in the database. It's a formatted number, don't use %d.
					'description'       => sprintf( _n( '%s transient in your database.', '%s transients in your database.', $total['database_all_transients'], 'rocket' ), number_format_i18n( $total['database_all_transients'] ) ),
					'section'           => 'transients_cleanup_section',
					'page'              => 'database',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'database_optimize_tables'    => [
					'type'              => 'checkbox',
					'label'             => __( 'Optimize Tables', 'rocket' ),
					// translators: %s is the number of revisions found in the database. It's a formatted number, don't use %d.
					'description'       => sprintf( _n( '%s table to optimize in your database.', '%s tables to optimize in your database.', $total['database_optimize_tables'], 'rocket' ), number_format_i18n( $total['database_optimize_tables'] ) ),
					'section'           => 'database_cleanup_section',
					'page'              => 'database',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'schedule_automatic_cleanup'  => [
					'container_class'   => [
						'wpr-isParent',
					],
					'type'              => 'checkbox',
					'label'             => __( 'Schedule Automatic Cleanup', 'rocket' ),
					'description'       => '',
					'section'           => 'schedule_cleanup_section',
					'page'              => 'database',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'automatic_cleanup_frequency' => [
					'container_class'   => [
						'wpr-field--children',
					],
					'type'              => 'select',
					'label'             => __( 'Frequency', 'rocket' ),
					'description'       => '',
					'parent'            => 'schedule_automatic_cleanup',
					'section'           => 'schedule_cleanup_section',
					'page'              => 'database',
					'default'           => 'daily',
					'sanitize_callback' => 'sanitize_text_field',
					'choices'           => [
						'daily'   => __( 'Daily', 'rocket' ),
						'weekly'  => __( 'Weekly', 'rocket' ),
						'monthly' => __( 'Monthly', 'rocket' ),
					],
				],
			]
		);
	}

	/**
	 * Registers CDN section
	 *
	 * @since 3.0
	 */
	private function cdn_section() {
		$this->settings->add_page_section(
			'page_cdn',
			[
				'title'            => __( 'CDN', 'rocket' ),
				'menu_description' => __( 'Integrate your CDN', 'rocket' ),
			]
		);

		$cdn_beacon         = $this->beacon->get_suggest( 'cdn' );
		$cdn_exclude_beacon = $this->beacon->get_suggest( 'exclude_cdn' );

		$this->settings->add_settings_sections(
			[
				'cdn_section'         => [
					'title'       => __( 'CDN', 'rocket' ),
					'type'        => 'fields_container',
					'description' => __( 'All URLs of static files (CSS, JS, images) will be rewritten to the CNAME(s) you provide.', 'rocket' ) . '<br><em>' . sprintf(
						// translators: %1$s = opening link tag, %2$s = closing link tag.
						__( 'Not required for services like Cloudflare and Sucuri. Please see our available %1$sAdd-ons%2$s.', 'rocket' ),
						'<a href="#addons">',
						'</a>'
					) . '</em>',
					'help'        => [
						'id'  => $this->beacon->get_suggest( 'cdn_section' ),
						'url' => $cdn_beacon['url'],
					],
					'page'        => 'page_cdn',
				],
				'cnames_section'      => [
					'type' => 'nocontainer',
					'page' => 'page_cdn',
				],
				'exclude_cdn_section' => [
					'title' => __( 'Exclude files from CDN', 'rocket' ),
					'type'  => 'fields_container',
					'help'  => [
						'id'  => $cdn_exclude_beacon['id'],
						'url' => $cdn_exclude_beacon['url'],
					],
					'page'  => 'page_cdn',
				],
			]
		);

		$maybe_display_cdn_helper = '';

		/**
		 * Filters the addons names requiring the helper message.
		 *
		 * @param array $addons Array of addons.
		 */
		$addons = wpm_apply_filters_typed( 'array', 'rocket_cdn_helper_addons', [] );

		$addons = array_unique( $addons );

		if ( ! empty( $addons ) ) {
			$maybe_display_cdn_helper = wp_sprintf(
				// translators: %1$s = opening em tag, %2$l = list of add-on name(s), %3$s = closing em tag.
				_n(
					'%1$s%2$l Add-on%3$s is currently enabled. Configuration of the CDN settings is not required for %2$l to work on your site.',
					'%1$s%2$l Add-ons%3$s are currently enabled. Configuration of the CDN settings is not required for %2$l to work on your site.',
					count( $addons ),
					'rocket'
				),
				'<em>',
				$addons,
				'</em>'
			) . '<br>';
		}

		$this->settings->add_settings_fields(
			/**
			 * Filters the fields for the CDN section.
			 *
			 * @since  3.5
			 * @author Remy Perona
			 *
			 * @param array $cdn_settings_fields Data to be added to the CDN section.
			 */
			apply_filters(
				'rocket_cdn_settings_fields',
				[
					'cdn'              => [
						'type'              => 'checkbox',
						'label'             => __( 'Enable Content Delivery Network', 'rocket' ),
						'helper'            => $maybe_display_cdn_helper,
						'section'           => 'cdn_section',
						'page'              => 'page_cdn',
						'default'           => 0,
						'sanitize_callback' => 'sanitize_checkbox',
					],
					'cdn_cnames'       => [
						'type'        => 'cnames',
						'label'       => __( 'CDN CNAME(s)', 'rocket' ),
						'description' => __( 'Specify the CNAME(s) below', 'rocket' ),
						'default'     => [],
						'section'     => 'cnames_section',
						'page'        => 'page_cdn',
					],
					'cdn_reject_files' => [
						'type'              => 'textarea',
						'description'       => __( 'Specify URL(s) of files that should not get served via CDN (one per line).', 'rocket' ),
						'helper'            => __( 'The domain part of the URL will be stripped automatically.<br>Use (.*) wildcards to exclude all files of a given file type located at a specific path.', 'rocket' ),
						'placeholder'       => '/wp-content/plugins/some-plugins/(.*).css',
						'section'           => 'exclude_cdn_section',
						'page'              => 'page_cdn',
						'default'           => [],
						'sanitize_callback' => 'sanitize_textarea',
					],
				]
			)
		);
	}

	/**
	 * Registers Heartbeat section.
	 *
	 * @since  3.2
	 */
	private function heartbeat_section() {
		$heartbeat_beacon = $this->beacon->get_suggest( 'heartbeat_settings' );

		$this->settings->add_page_section(
			'heartbeat',
			[
				'title'            => __( 'Heartbeat', 'rocket' ),
				'menu_description' => __( 'Control WordPress Heartbeat API', 'rocket' ),
			]
		);

		$this->settings->add_settings_sections(
			[
				'heartbeat_section'  => [
					'title'       => __( 'Heartbeat', 'rocket' ),
					'description' => __( 'Reducing or disabling the Heartbeat API’s activity can help save some of your server’s resources.', 'rocket' ),
					'type'        => 'fields_container',
					'page'        => 'heartbeat',
					'help'        => $heartbeat_beacon,
				],
				'heartbeat_settings' => [
					'title'       => __( 'Reduce or disable Heartbeat activity', 'rocket' ),
					'description' => __( 'Reducing activity will change Heartbeat frequency from one hit each minute to one hit every 2 minutes.', 'rocket' ) . '<br/>' . __( 'Disabling Heartbeat entirely may break plugins and themes using this API.', 'rocket' ),
					'type'        => 'fields_container',
					'page'        => 'heartbeat',
				],
			]
		);

		$fields_default = [
			'type'              => 'select',
			'page'              => 'heartbeat',
			'section'           => 'heartbeat_settings',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'reduce_periodicity',
			'choices'           => [
				''                   => __( 'Do not limit', 'rocket' ),
				'reduce_periodicity' => __( 'Reduce activity', 'rocket' ),
				'disable'            => __( 'Disable', 'rocket' ),
			],
		];

		$this->settings->add_settings_fields(
			[
				'control_heartbeat'         => [
					'type'              => 'checkbox',
					'label'             => __( 'Control Heartbeat', 'rocket' ),
					'page'              => 'heartbeat',
					'section'           => 'heartbeat_section',
					'sanitize_callback' => 'sanitize_checkbox',
					'default'           => 0,
				],
				'heartbeat_admin_behavior'  => array_merge(
					$fields_default,
					[
						'label'       => __( 'Behavior in backend', 'rocket' ),
						'description' => '',
					]
				),
				'heartbeat_editor_behavior' => array_merge(
					$fields_default,
					[
						'label' => __( 'Behavior in post editor', 'rocket' ),
					]
				),
				'heartbeat_site_behavior'   => array_merge(
					$fields_default,
					[
						'label' => __( 'Behavior in frontend', 'rocket' ),
					]
				),
			]
		);
	}

	/**
	 * Registers Add-ons section.
	 *
	 * @since 3.0
	 */
	private function addons_section() {
		$webp_beacon       = $this->beacon->get_suggest( 'webp' );
		$user_cache_beacon = $this->beacon->get_suggest( 'user_cache' );

		$this->settings->add_page_section(
			'addons',
			[
				'title'            => __( 'Add-ons', 'rocket' ),
				'menu_description' => __( 'Add more features', 'rocket' ),
			]
		);

		$this->settings->add_settings_sections(
			[
				'one_click' => [
					'title'       => __( 'One-click Rocket Add-ons', 'rocket' ),
					'description' => __( 'One-Click Add-ons are features extending available options without configuration needed. Switch the option "on" to enable from this screen.', 'rocket' ),
					'type'        => 'addons_container',
					'page'        => 'addons',
				],
			]
		);

		$this->settings->add_settings_sections(
			[
				'addons' => [
					'title'       => __( 'Rocket Add-ons', 'rocket' ),
					'description' => __( 'Rocket Add-ons are complementary features extending available options.', 'rocket' ),
					'type'        => 'addons_container',
					'page'        => 'addons',
				],
			]
		);

		$this->settings->add_settings_fields(
			[
				'cache_logged_user' => [
					'type'              => 'one_click_addon',
					'label'             => __( 'User Cache', 'rocket' ),
					'logo'              => [
						'url'    => WP_ROCKET_ASSETS_IMG_URL . 'icon-user-cache.svg',
						'width'  => 152,
						'height' => 135,
					],
					'title'             => __( 'If you need to create a dedicated set of cache files for each logged-in WordPress user, you must activate this add-on.', 'rocket' ),
					// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
					'description'       => sprintf( __( 'User cache is great when you have user-specific or restricted content on your website.<br>%1$sLearn more%2$s', 'rocket' ), '<a href="' . esc_url( $user_cache_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $user_cache_beacon['id'] ) . '" target="_blank">', '</a>' ),
					'section'           => 'one_click',
					'page'              => 'addons',
					'settings_page'     => 'user_cache',
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
			]
		);

		$default_cf_settings = [
			'do_cloudflare' => [
				'type'              => 'rocket_addon',
				'label'             => __( 'Cloudflare', 'rocket' ),
				'logo'              => [
					'url'    => rocket_get_constant( 'WP_ROCKET_ASSETS_IMG_URL', '' ) . 'logo-cloudflare2.svg',
					'width'  => 153,
					'height' => 51,
				],
				'title'             => __( 'Integrate your Cloudflare account with this add-on.', 'rocket' ),
				'description'       => __( 'Provide your account email, global API key, and domain to use options such as clearing the Cloudflare cache and enabling optimal settings with WP Rocket.', 'rocket' ),
				'helper'            => sprintf(
				// translators: %1$s = opening span tag, %2$s = closing span tag.
				__( '%1$sPlanning on using Automatic Platform Optimization (APO)?%2$s Just activate the official Cloudflare plugin and configure it. WP Rocket will automatically enable compatibility.', 'rocket' ),
					'<span class="wpr-helper-title">',
					'</span>'
				),
				'section'           => 'addons',
				'page'              => 'addons',
				'settings_page'     => 'cloudflare',
				'default'           => 0,
				'sanitize_callback' => 'sanitize_checkbox',
			],
		];

		/**
		 * Filters the Cloudflare Addon field values
		 *
		 * @since 3.14
		 *
		 * @param array $cf_settings Array of values to populate the field.
		 */
		$cf_settings = (array) apply_filters( 'rocket_cloudflare_field_settings', $default_cf_settings );
		$cf_settings = wp_parse_args( $cf_settings, $default_cf_settings );

		$this->settings->add_settings_fields( $cf_settings );

		/**
		 * Allow to display the "Varnish" tab in the settings page
		 *
		 * @since 2.7
		 *
		 * @param bool $display true will display the "Varnish" tab.
		*/
		if ( apply_filters( 'rocket_display_varnish_options_tab', true ) ) {
			$varnish_beacon = $this->beacon->get_suggest( 'varnish' );

			$this->settings->add_settings_fields(
				/**
				 * Filters the Varnish field settings data
				 *
				 * @since 3.0
				 * @author Remy Perona
				 *
				 * @param array $settings Field settings data.
				 */
				apply_filters(
					'rocket_varnish_field_settings',
					[
						'varnish_auto_purge' => [
							'type'              => 'one_click_addon',
							'label'             => __( 'Varnish', 'rocket' ),
							'logo'              => [
								'url'    => WP_ROCKET_ASSETS_IMG_URL . 'logo-varnish.svg',
								'width'  => 152,
								'height' => 135,
							],
							'title'             => __( 'If Varnish runs on your server, you must activate this add-on.', 'rocket' ),
							// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
							'description'       => sprintf( __( 'Varnish cache will be purged each time WP Rocket clears its cache to ensure content is always up-to-date.<br>%1$sLearn more%2$s', 'rocket' ), '<a href="' . esc_url( $varnish_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $varnish_beacon['id'] ) . '" target="_blank">', '</a>' ),
							'section'           => 'one_click',
							'page'              => 'addons',
							'settings_page'     => 'varnish',
							'default'           => 0,
							'sanitize_callback' => 'sanitize_checkbox',
						],
					]
				)
			);
		}

		$webp_beacon = $this->beacon->get_suggest( 'webp' );

		if ( rocket_valid_key() && ! \Imagify_Partner::has_imagify_api_key() ) {
			$imagify_link = '<a href="#imagify">';
		} else {
			$imagify_link = '<a href="https://wordpress.org/plugins/imagify/" target="_blank" rel="noopener noreferrer">';
		}

		$this->settings->add_settings_fields(
			[
				'cache_webp' =>
				/**
				 * Add more content to the 'cache_webp' setting field.
				 *
				 * @since  3.10 moved to add-on section
				 * @since  3.4
				 *
				 * @param array $cache_webp_field Data to be added to the setting field.
				 */
					apply_filters(
						'rocket_cache_webp_setting_field',
						[
							'type'              => 'one_click_addon',
							'label'             => __( 'WebP Compatibility', 'rocket' ),
							'logo'              => [
								'url'    => WP_ROCKET_ASSETS_IMG_URL . 'logo-webp.svg',
								'width'  => 152,
								'height' => 135,
							],
							'title'             => __( 'Improve browser compatibility for WebP images.', 'rocket' ),
							// translators: %1$s = opening <a> tag, %2$s = closing </a> tag.
							'description'       => sprintf(
							// translators: %1$s and %3$s = opening <a> tag, %2$s = closing </a> tag.
								__( 'Enable this option if you would like WP Rocket to serve WebP images to compatible browsers. Please note that WP Rocket cannot create WebP images for you. To create WebP images we recommend %1$sImagify%2$s. %3$sMore info%2$s', 'rocket' ),
								$imagify_link,
								'</a>',
								'<a href="' . esc_url( $webp_beacon['url'] ) . '" data-beacon-article="' . esc_attr( $webp_beacon['id'] ) . '" target="_blank" rel="noopener noreferrer">'
							),
							'section'           => 'one_click',
							'page'              => 'addons',
							'settings_page'     => 'webp',
							'default'           => 0,
							'sanitize_callback' => 'sanitize_checkbox',
							'container_class'   => [
								'wpr-webp-addon',
							],
						]
						),
			]
		);

		if ( defined( 'WP_ROCKET_SUCURI_API_KEY_HIDDEN' ) && WP_ROCKET_SUCURI_API_KEY_HIDDEN ) {
			// No need to display the dedicated tab if there is nothing to display on it.
			$description   = __( 'Clear the Sucuri cache when WP Rocket’s cache is cleared.', 'rocket' );
			$settings_page = false;
		} else {
			$description   = __( 'Provide your API key to clear the Sucuri cache when WP Rocket’s cache is cleared.', 'rocket' );
			$settings_page = 'sucuri';
		}

		$this->settings->add_settings_fields(
			[
				'sucury_waf_cache_sync' => [
					'type'              => 'rocket_addon',
					'label'             => __( 'Sucuri', 'rocket' ),
					'logo'              => [
						'url'    => WP_ROCKET_ASSETS_IMG_URL . 'logo-sucuri.png',
						'width'  => 152,
						'height' => 56,
					],
					'title'             => __( 'Synchronize Sucuri cache with this add-on.', 'rocket' ),
					'description'       => $description,
					'section'           => 'addons',
					'page'              => 'addons',
					'settings_page'     => $settings_page,
					'default'           => 0,
					'sanitize_callback' => 'sanitize_checkbox',
				],
			]
		);
	}

	/**
	 * Registers Cloudflare section.
	 *
	 * @since 3.0
	 */
	private function cloudflare_section() {
		$this->settings->add_page_section(
			'cloudflare',
			[
				'title'            => __( 'Cloudflare', 'rocket' ),
				'menu_description' => '',
				'class'            => [
					'wpr-subMenuItem',
					'wpr-addonSubMenuItem',
				],
			]
		);

		$beacon_cf_credentials     = $this->beacon->get_suggest( 'cloudflare_credentials' );
		$beacon_cf_settings        = $this->beacon->get_suggest( 'cloudflare_settings' );
		$beacon_cf_credentials_api = $this->beacon->get_suggest( 'cloudflare_credentials_api' );

		$this->settings->add_settings_sections(
			[
				'cloudflare_credentials' => [
					'type'  => 'fields_container',
					'title' => __( 'Cloudflare credentials', 'rocket' ),
					'help'  => [
						'id'  => $beacon_cf_credentials['id'],
						'url' => $beacon_cf_credentials['url'],
					],
					'page'  => 'cloudflare',
				],
				'cloudflare_settings'    => [
					'type'  => 'fields_container',
					'title' => __( 'Cloudflare settings', 'rocket' ),
					'help'  => [
						'id'  => $beacon_cf_settings['id'],
						'url' => $beacon_cf_settings['url'],
					],
					'page'  => 'cloudflare',
				],
			]
		);

		if ( ! defined( 'WP_ROCKET_CF_API_KEY_HIDDEN' ) || ! WP_ROCKET_CF_API_KEY_HIDDEN ) {
			$this->settings->add_settings_fields(
				[
					'cloudflare_api_key_mask' => [
						'label'       => _x( 'Global API key:', 'Cloudflare', 'rocket' ),
						'description' => sprintf( '<a href="%1$s" target="_blank">%2$s</a>', esc_url( $beacon_cf_credentials_api['url'] ), _x( 'Find your API key', 'Cloudflare', 'rocket' ) ),
						'default'     => '',
						'section'     => 'cloudflare_credentials',
						'page'        => 'cloudflare',
					],
				]
			);
		}

		$this->settings->add_settings_fields(
			[
				'cloudflare_email'            => [
					'label'           => _x( 'Account email', 'Cloudflare', 'rocket' ),
					'default'         => '',
					'container_class' => [
						'wpr-field--split',
					],
					'section'         => 'cloudflare_credentials',
					'page'            => 'cloudflare',
				],
				'cloudflare_zone_id_mask'     => [
					'label'           => _x( 'Zone ID', 'Cloudflare', 'rocket' ),
					'default'         => '',
					'container_class' => [
						'wpr-field--split',
					],
					'section'         => 'cloudflare_credentials',
					'page'            => 'cloudflare',
				],
				'cloudflare_devmode'          => [
					'type'              => 'sliding_checkbox',
					'label'             => __( 'Development mode', 'rocket' ),
					// translators: %1$s = link opening tag, %2$s = link closing tag.
					'description'       => sprintf( __( 'Temporarily activate development mode on your website. This setting will automatically turn off after 3 hours. %1$sLearn more%2$s', 'rocket' ), '<a href="https://support.cloudflare.com/hc/en-us/articles/200168246" target="_blank">', '</a>' ),
					'default'           => 0,
					'section'           => 'cloudflare_settings',
					'page'              => 'cloudflare',
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'cloudflare_auto_settings'    => [
					'type'              => 'sliding_checkbox',
					'label'             => __( 'Optimal settings', 'rocket' ),
					'description'       => __( 'Automatically enhances your Cloudflare configuration for speed, performance grade and compatibility.', 'rocket' ),
					'default'           => 0,
					'section'           => 'cloudflare_settings',
					'page'              => 'cloudflare',
					'sanitize_callback' => 'sanitize_checkbox',
				],
				'cloudflare_protocol_rewrite' => [
					'type'              => 'sliding_checkbox',
					'label'             => __( 'Relative protocol', 'rocket' ),
					'description'       => __( 'Should only be used with Cloudflare\'s flexible SSL feature. URLs of static files (CSS, JS, images) will be rewritten to use // instead of http:// or https://.', 'rocket' ),
					'default'           => 0,
					'section'           => 'cloudflare_settings',
					'page'              => 'cloudflare',
					'sanitize_callback' => 'sanitize_checkbox',
				],
			]
		);
	}

	/**
	 * Registers Sucuri cache section.
	 *
	 * @since  3.2
	 */
	private function sucuri_section() {
		if ( defined( 'WP_ROCKET_SUCURI_API_KEY_HIDDEN' ) && WP_ROCKET_SUCURI_API_KEY_HIDDEN ) {
			return;
		}

		$sucuri_beacon = $this->beacon->get_suggest( 'sucuri_credentials' );

		$this->settings->add_page_section(
			'sucuri',
			[
				'title'            => __( 'Sucuri', 'rocket' ),
				'menu_description' => '',
				'class'            => [
					'wpr-subMenuItem',
					'wpr-addonSubMenuItem',
				],
			]
		);

		$this->settings->add_settings_sections(
			[
				'sucuri_credentials' => [
					'type'  => 'fields_container',
					'title' => __( 'Sucuri credentials', 'rocket' ),
					'page'  => 'sucuri',
					'help'  => [
						'id'  => $sucuri_beacon['id'],
						'url' => $sucuri_beacon['url'],
					],
				],
			]
		);

		$this->settings->add_settings_fields(
			[
				'sucury_waf_api_key' => [
					'label'       => _x( 'Firewall API key (for plugin), must be in format {32 characters}/{32 characters}:', 'Sucuri', 'rocket' ),
					'description' => sprintf( '<a href="%1$s" target="_blank">%2$s</a>', 'https://kb.sucuri.net/firewall/Performance/clearing-cache', _x( 'Find your API key', 'Sucuri', 'rocket' ) ),
					'default'     => '',
					'section'     => 'sucuri_credentials',
					'page'        => 'sucuri',
				],
			]
		);
	}

	/**
	 * Sets hidden fields.
	 *
	 * @since 3.0
	 */
	private function hidden_fields() {

		$hidden_fields = [
			'consumer_key',
			'consumer_email',
			'secret_key',
			'license',
			'secret_cache_key',
			'minify_css_key',
			'minify_js_key',
			'version',
			'previous_version',
			'cloudflare_old_settings',
			'cache_ssl',
			'minify_google_fonts',
			'emoji',
			'remove_unused_css',
			'async_css',
			'cache_mobile',
			'do_caching_mobile_files',
			'minify_concatenate_css',
			'cloudflare_api_key',
			'cloudflare_zone_id',
			'dns_prefetch',
		];

		$this->settings->add_hidden_settings_fields(
			/**
			 * Filters the hidden settings fields
			 *
			 * @since 3.5
			 * @author Remy Perona
			 *
			 * @param array $hidden_settings_fields An array of hidden settings fields ID
			 */
			apply_filters(
				'rocket_hidden_settings_fields',
				$hidden_fields
			)
		);
	}

	/**
	 * Sanitize and format a list.
	 *
	 * @since 3.5.5
	 *
	 * @param  array  $list     A list of strings.
	 * @param  string $tag_name Name of the HTML tag that will wrap each element of the list.
	 * @return array
	 */
	private function sanitize_and_format_list( array $list, $tag_name = 'strong' ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.listFound
		if ( empty( $list ) ) {
			return [];
		}

		$list = array_filter( $list );

		if ( empty( $list ) ) {
			return [];
		}

		$list = array_unique( $list );

		if ( empty( $tag_name ) ) {
			return $list;
		}

		$format = "<$tag_name>%s</$tag_name>";

		return array_map( 'sprintf', array_fill( 0, count( $list ), $format ), $list );
	}

	/**
	 * Checks if combine JS option should be disabled
	 *
	 * @since 3.9
	 *
	 * @return bool
	 */
	private function disable_combine_js(): bool {
		if ( (bool) get_rocket_option( 'delay_js', 0 ) ) {
			return true;
		}

		return ! (bool) get_rocket_option( 'minify_js', 0 );
	}

	/**
	 * Render radio options sub fields.
	 *
	 * @since 3.10
	 *
	 * @param array $sub_fields    Array of fields to display.
	 */
	public function display_radio_options_sub_fields( $sub_fields ) {
		$sub_fields = $this->settings->set_radio_buttons_sub_fields_value( $sub_fields );
		$this->render->render_fields( $sub_fields );
	}

	/**
	 * Render mobile cache option.
	 *
	 * @return void
	 */
	public function display_mobile_cache_option(): void {
		if ( (bool) $this->options->get( 'cache_mobile', 0 ) ) {
			return;
		}

		$data = $this->beacon->get_suggest( 'mobile_cache' );
		echo $this->generate( 'settings/mobile-cache', $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic content is properly escaped in the view.
	}

	/**
	 * Callback method for the AJAX request to mobile cache.
	 *
	 * @return void
	 */
	public function enable_mobile_cache(): void {
		check_ajax_referer( 'rocket-ajax', 'nonce', true );

		if ( ! current_user_can( 'rocket_manage_options' ) ) {
			wp_send_json_error();
			return; // @phpstan-ignore-line
		}

		$this->options->set( 'cache_mobile', 1 );
		$this->options->set( 'do_caching_mobile_files', 1 );
		update_option( rocket_get_constant( 'WP_ROCKET_SLUG', 'wp_rocket_settings' ), $this->options->get_options() );

		wp_send_json_success();
	}

	/**
	 * Enable Separate cache files option on upgrade.
	 *
	 * @return void
	 */
	public function enable_separate_cache_files_mobile(): void {
		if ( ! (bool) $this->options->get( 'cache_mobile', 0 ) ) {
			return;
		}

		if ( (bool) $this->options->get( 'do_caching_mobile_files', 0 ) ) {
			return;
		}

		$this->options->set( 'do_caching_mobile_files', 1 );
		update_option( rocket_get_constant( 'WP_ROCKET_SLUG', 'wp_rocket_settings' ), $this->options->get_options() );
	}

	/**
	 * Display an update notice when the plugin is updated.
	 *
	 * @return void
	 */
	public function display_update_notice() {
		if ( ! current_user_can( 'rocket_manage_options' ) ) {
			return;
		}

		if ( 'settings_page_wprocket' !== get_current_screen()->id ) {
			return;
		}

		$boxes = get_user_meta( get_current_user_id(), 'rocket_boxes', true );

		if ( in_array( 'rocket_update_notice', (array) $boxes, true ) ) {
			return;
		}

		$previous_version = $this->options->get( 'previous_version' );

		// Bail-out if previous version is greater than or equal to 3.19.
		if ( version_compare( $previous_version, '3.19', '>=' ) ) {
			return;
		}

		$preconnect_content = $this->beacon->get_suggest( 'preconnect_domains' );

		rocket_notice_html(
			[
				'status'         => 'info',
				'dismissible'    => '',
				'message'        => sprintf(
						// translators: %1$s: opening strong tag, %2$s: closing strong tag, %3$s: opening a tag, %4$s: closing a tag.
						__( '%1$sWP Rocket:%2$s the plugin has been updated to the 3.19 version. New feature: %3$sPreconnect to external domains%4$s. Check out our documentation to learn more about it.', 'rocket' ),
						'<strong>',
						'</strong>',
						'<a href="' . esc_url( $preconnect_content['url'] ) . '" data-beacon-article="' . esc_attr( $preconnect_content['id'] ) . '" target="_blank" rel="noopener noreferrer">',
						'</a>'
				),
				'dismiss_button' => 'rocket_update_notice',
			]
		);
	}
}
