<?php

if ( ! class_exists( 'US_Regenerate_Thumbnails' ) ) {
	/**
	 * Class for regenerate thumbnails in background.
	 */
	class US_Regenerate_Thumbnails extends WP_Background_Process {

		/**
		 * Prefix for action.
		 *
		 * @var string
		 */
		protected $prefix = 'us';

		/**
		 *  Action for request.
		 *
		 * @var string
		 */
		protected $action = 'regenerate_thumbnails_background_process';

		/**
		 * @return self Returns an instance of the current class.
		 */
		public static function instance() {
			static $instance;
			if ( ! isset( $instance ) ) {
				$instance = new self;
			}

			return $instance;
		}

		/**
		 * Get option name for statistics.
		 *
		 * @return string
		 */
		private function get_stats_key() {
			return $this->identifier . '_data';
		}

		/**
		 * Set data for statistics.
		 *
		 * @param integer $count The all count
		 * @param array $sizes The sizes
		 * @return self
		 */
		public function set_stats_data( $count ) {
			update_option(
				$this->get_stats_key(), array(
				'created' => date( 'U' ),
				'count_tasks' => (int) $count,
			)
			);

			return $this;
		}

		/**
		 * Get data for statistics.
		 *
		 * @return array
		 */
		public function get_stats_data() {
			if ( $stats_data = get_option( $this->get_stats_key() ) ) {
				if ( ! empty( $stats_data['count_tasks'] ) ) {
					$batch = $this->get_batch();
					$outstanding_tasks = count( $batch->data );
					$stats_data['completed_tasks'] = 0;

					if ( $stats_data['count_tasks'] > $outstanding_tasks AND $outstanding_tasks ) {
						$stats_data['completed_tasks'] = $stats_data['count_tasks'] - $outstanding_tasks;
					} elseif ( $stats_data['count_tasks'] != $outstanding_tasks ) {
						$stats_data['completed_tasks'] = $stats_data['count_tasks'];
					}
				}

				return $stats_data;
			}

			return array();
		}

		/**
		 * Task
		 *
		 * @param mixed $attachment_id Queue item to iterate over
		 * @return mixed
		 */
		protected function task( $item_id ) {

			// Get image path
			$image_path = function_exists( 'wp_get_original_image_path' )
				? wp_get_original_image_path( ( int ) $item_id )
				: get_attached_file( (int) $item_id );

			// Complete actions if there is no image or for a placeholder
			if (
				empty( $image_path )
				OR strpos( $image_path, 'woocommerce-placeholder' ) !== FALSE
				OR strpos( $image_path, 'us-placeholder' ) !== FALSE
			) {
				return FALSE;
			}

			$dirname = pathinfo( $image_path, PATHINFO_DIRNAME ) . DIRECTORY_SEPARATOR;
			$image_metadata = wp_get_attachment_metadata( $item_id );
			$image_metadata_sizes = $used_image_thumbnails = $thumbnail_sizes = array();

			// Get all thumbnail sizes
			foreach ( array_keys( us_get_image_sizes_list() ) as $size_name ) {
				$thumbnail_sizes[] = us_get_image_size_params( $size_name );
			}

			$changed = FALSE;

			// Regenerate thumbnails
			foreach ( $thumbnail_sizes as $thumbnail_size ) {
				$crop = ! empty( $thumbnail_size['crop'] );

				// Obtaining real thumbnail sizes
				// Note: Size `full` has no width or height
				$width = (int) $thumbnail_size['width'];
				$height = (int) $thumbnail_size['height'];

				if ( ! $width OR ! $height ) {
					continue;
				}

				// Create new thumbnail
				$img_editor = wp_get_image_editor( $image_path );
				if ( ! is_wp_error( $img_editor ) ) {
					$img_editor->resize( $width, $height, $crop );
					$img_editor_size = $img_editor->get_size();
					$img_fullpath_filename = $img_editor->generate_filename();
					$img_filename = wp_basename( $img_fullpath_filename );

					$used_image_thumbnails[] = $img_filename;

					if ( ! file_exists( $img_fullpath_filename ) ) {
						$img_editor->save( $img_fullpath_filename );

						// Thumbnail size name
						$img_size_name = 'us_' . $img_editor_size['width'] . '_' . $img_editor_size['height'];
						if ( $crop ) {
							$img_size_name .= '_crop';
						}

						// Add size to metadata
						$image_metadata_sizes[ $img_size_name ] = array(
							'file' => $img_filename,
							'width' => $img_editor_size['width'],
							'height' => $img_editor_size['height'],
							'mime-type' => wp_get_image_mime( $img_fullpath_filename ),
						);
						$changed = TRUE;
					}
				}
			}

			// Delete unused image thumbnails
			if ( us_get_option( 'delete_unused_images', FALSE ) ) {
				$existing_sizes = apply_filters(
					'intermediate_image_sizes_advanced',
					wp_get_registered_image_subsizes(),
					$image_metadata,
					$item_id
				);

				foreach ( us_arr_path( $image_metadata, 'sizes', array() ) as $size_name => $size_data ) {
					if (
						! isset( $existing_sizes[ $size_name ] )
						// Prevent deletion of original images.
						AND wp_basename( $image_metadata['file'] ) !== $size_data['file']
					) {
						unset( $image_metadata['sizes'][ $size_name ] );
						unlink( $dirname . $size_data['file'] );
						$changed = TRUE;
					}
				}
			}

			// Update attachment metadata
			if ( $changed ) {
				$image_metadata['sizes'] = array_merge( $image_metadata['sizes'], $image_metadata_sizes );
				wp_update_attachment_metadata( $item_id, $image_metadata );
			}

			return FALSE;
		}

		/**
		 * Complete
		 */
		protected function complete() {
			parent::complete();
			delete_option( $this->get_stats_key() );
		}


	}

	// Init regenerate thumbnails.
	US_Regenerate_Thumbnails::instance();

	// For debugging url: http://host/wp-admin/?us_dev_debug=regenerate_thumbnails_process
	if (
		defined( 'US_DEV' )
		AND isset( $_GET['us_dev_debug'] )
		AND $_GET['us_dev_debug'] === 'regenerate_thumbnails_process'
	) {
		var_dump( US_Regenerate_Thumbnails::instance()->get_stats_data() );
		exit;
	}
}

if ( ! function_exists( 'us_after_ajax_save_regenerate_thumbnails_process' ) ) {
	/**
	 * Checks for size updates and, if necessary, start regenerate thumbnails
	 */
	function us_after_ajax_save_regenerate_thumbnails_process() {

		$post_usof_options = us_get_HTTP_POST_json( 'usof_options' );
		if ( empty( $post_usof_options ) ) {
			return;
		}

		if (
			isset( $post_usof_options['img_size'] )
			OR ( isset( $post_usof_options['delete_unused_images'] ) AND $post_usof_options['delete_unused_images'] )
		) {
			global $wpdb;

			$us_regenerate_thumbnails = US_Regenerate_Thumbnails::instance();

			// Get all image IDs
			$images_query = "
				SELECT
					ID AS image_id
				FROM {$wpdb->posts}
				WHERE 1=1
					AND (
						post_mime_type = 'image/jpeg'
						OR post_mime_type = 'image/gif'
						OR post_mime_type = 'image/png'
						OR post_mime_type = 'image/webp'
					)
					AND post_type = 'attachment'
					AND post_status = 'inherit'
			";

			$results = $wpdb->get_results( $images_query );

			// Add tasks and run background process.
			if ( ! empty( $results ) ) {
				foreach ( $results as $image ) {
					$us_regenerate_thumbnails->push_to_queue( $image->image_id );
				}
				$us_regenerate_thumbnails->save()->dispatch();
				$us_regenerate_thumbnails->set_stats_data( count( $results ) );
			}
		}
	}

	if ( apply_filters( 'us_regenerate_thumbnails_is_enabled', TRUE ) ) {
		add_action( 'usof_after_ajax_save', 'us_after_ajax_save_regenerate_thumbnails_process', 100 );
	}
}
