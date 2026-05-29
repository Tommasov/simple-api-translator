<?php
/**
 * Metabox Interface Class for Simple API Translator.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAT_Metabox {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_sat_translate_content', array( $this, 'ajax_translate_content' ) );
	}

	/**
	 * Register the metabox on Posts and Pages.
	 */
	public function register_metabox() {
		$screens = array( 'post', 'page' );
		foreach ( $screens as $screen ) {
			add_meta_box(
				'sat_translation_metabox',
				__( 'Simple API Translator', 'simple-api-translator' ),
				array( $this, 'render_metabox' ),
				$screen,
				'side',
				'high'
			);
		}
	}

	/**
	 * Render the metabox UI HTML.
	 */
	/**
	 * Render the metabox UI HTML.
	 */
	public function render_metabox( $post ) {
		// Detect WPML languages and if this is a translation post.
		$languages = array();
		$is_wpml_active = false;
		$is_translation = false;
		$source_language_name = '';
		$target_language_name = '';
		$original_post_id = null;

		$wpml_details = $this->get_wpml_details( $post->ID );

		if ( $wpml_details['is_wpml_active'] ) {
			$is_wpml_active = true;
			$wpml_languages = icl_get_languages( 'skip_missing=0' );

			if ( $wpml_details['is_translation'] ) {
				$is_translation = true;
				$original_post_id = $wpml_details['original_post_id'];
				
				// Find source language name.
				$source_lang_code = $wpml_details['source_lang'];
				if ( isset( $wpml_languages[ $source_lang_code ]['translated_name'] ) ) {
					$source_language_name = $wpml_languages[ $source_lang_code ]['translated_name'];
				} else {
					$source_language_name = strtoupper( $source_lang_code );
				}

				// Find target language name.
				$current_lang_code = $wpml_details['current_lang'];
				if ( isset( $wpml_languages[ $current_lang_code ]['translated_name'] ) ) {
					$target_language_name = $wpml_languages[ $current_lang_code ]['translated_name'];
				} else {
					$target_language_name = strtoupper( $current_lang_code );
				}
			} else {
				// It's the original post, populate other languages for standard manual translating.
				$current_lang = $wpml_details['current_lang'];
				if ( ! empty( $wpml_languages ) ) {
					foreach ( $wpml_languages as $lang ) {
						if ( $lang['language_code'] !== $current_lang ) {
							$languages[ $lang['translated_name'] ] = $lang['translated_name'];
						}
					}
				}
			}
		}

		// Fallback standard languages.
		if ( ! $is_translation && empty( $languages ) ) {
			$languages = array(
				'English'    => __( 'English', 'simple-api-translator' ),
				'Italiano'   => __( 'Italian', 'simple-api-translator' ),
				'Español'    => __( 'Spanish', 'simple-api-translator' ),
				'Français'   => __( 'French', 'simple-api-translator' ),
				'Deutsch'    => __( 'German', 'simple-api-translator' ),
				'Português'  => __( 'Portuguese', 'simple-api-translator' ),
				'Nederlands' => __( 'Dutch', 'simple-api-translator' ),
			);
		}

		wp_nonce_field( 'sat_translate_nonce_action', 'sat_translate_nonce' );
		?>
		<div class="sat-metabox-container" style="padding: 5px 0;">
			<?php if ( $is_translation ) : ?>
				<div style="background: #f0f6fc; border-left: 4px solid #72aee6; padding: 10px; margin-bottom: 15px; border-radius: 2px;">
					<p style="margin: 0 0 5px 0; font-size: 13px;">
						<strong><?php esc_html_e( 'Translation Import Mode:', 'simple-api-translator' ); ?></strong>
					</p>
					<p style="margin: 0; font-size: 12px; color: #3c434a;">
						<?php printf( 
							esc_html__( 'Original content (%s) will be translated and applied directly to this %s version.', 'simple-api-translator' ), 
							'<strong>' . esc_html( $source_language_name ) . '</strong>', 
							'<strong>' . esc_html( $target_language_name ) . '</strong>' 
						); ?>
					</p>
				</div>
				<input type="hidden" id="sat_target_language" value="<?php echo esc_attr( $target_language_name ); ?>" />
			<?php else : ?>
				<p>
					<label for="sat_target_language"><strong><?php esc_html_e( 'Target Language:', 'simple-api-translator' ); ?></strong></label>
					<select id="sat_target_language" class="widefat" style="margin-top: 5px;">
						<?php foreach ( $languages as $key => $name ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>

				<?php if ( $is_wpml_active ) : ?>
					<p style="font-size: 11px; color: #666; margin-top: -5px;">
						<em><?php esc_html_e( 'Detected WPML languages.', 'simple-api-translator' ); ?></em>
					</p>
				<?php endif; ?>
			<?php endif; ?>

			<button type="button" id="sat_translate_btn" class="button button-primary widefat" style="text-align: center; justify-content: center; display: inline-flex; align-items: center; gap: 6px; margin-top: 10px; min-height: 30px; height: auto; line-height: 1; padding: 6px 12px;">
				<span class="dashicons dashicons-translation" style="font-size: 18px; width: 18px; height: 18px; line-height: 1; margin: 0; display: inline-block;"></span>
				<span style="line-height: 1; display: inline-block; margin-top: 1px;"><?php esc_html_e( 'Translate Content', 'simple-api-translator' ); ?></span>
			</button>

			<div id="sat_loader_wrapper" style="display: none; text-align: center; margin-top: 15px;">
				<span class="spinner is-active" style="float: none; margin: 0 auto;"></span>
				<p style="font-size: 12px; color: #666; margin-top: 5px;">
					<?php
					$engine = get_option( 'sat_translation_engine', 'openai' );
					if ( 'deepl' === $engine ) {
						esc_html_e( 'Translating content with DeepL...', 'simple-api-translator' );
					} else {
						esc_html_e( 'Translating content with OpenAI...', 'simple-api-translator' );
					}
					?>
				</p>
			</div>

			<div id="sat_result_wrapper" style="display: none; margin-top: 15px;">
				<label for="sat_translated_content"><strong><?php esc_html_e( 'Translated Result:', 'simple-api-translator' ); ?></strong></label>
				<textarea id="sat_translated_content" class="widefat" rows="8" style="margin-top: 5px; font-family: monospace; font-size: 12px;"></textarea>
				
				<div style="display: flex; gap: 5px; margin-top: 8px;">
					<button type="button" id="sat_copy_btn" class="button button-secondary" style="flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 4px; min-height: 30px; height: auto; line-height: 1; padding: 6px 8px;">
						<span class="dashicons dashicons-clipboard" style="font-size: 16px; width: 16px; height: 16px; line-height: 1; margin: 0; display: inline-block;"></span> <span style="line-height: 1; display: inline-block; margin-top: 1px;"><?php esc_html_e( 'Copy', 'simple-api-translator' ); ?></span>
					</button>
					<button type="button" id="sat_overwrite_btn" class="button button-secondary" style="flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 4px; min-height: 30px; height: auto; line-height: 1; padding: 6px 8px;">
						<span class="dashicons dashicons-editor-paste-text" style="font-size: 16px; width: 16px; height: 16px; line-height: 1; margin: 0; display: inline-block;"></span> <span style="line-height: 1; display: inline-block; margin-top: 1px;"><?php esc_html_e( 'Apply to Editor', 'simple-api-translator' ); ?></span>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue styles and javascript scripts for metabox.
	 */
	public function enqueue_assets( $hook ) {
		global $post;
		
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		if ( ! in_array( get_post_type( $post ), array( 'post', 'page' ), true ) ) {
			return;
		}

		wp_enqueue_script(
			'sat-metabox-script',
			SAT_PLUGIN_URL . 'assets/metabox.js',
			array( 'jquery' ),
			SAT_VERSION,
			true
		);

		wp_localize_script( 'sat-metabox-script', 'sat_ajax_obj', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'post_id'  => $post->ID,
			'nonce'    => wp_create_nonce( 'sat_translate_nonce_action' ),
			'alerts'   => array(
				'empty_content' => __( 'There is no content in the editor to translate.', 'simple-api-translator' ),
				'success_copy'  => __( 'Copied to clipboard!', 'simple-api-translator' ),
				'applied'       => __( 'Applied to the editor successfully!', 'simple-api-translator' ),
			)
		) );
	}

	/**
	 * Get WPML language and translation details for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array Array with translation details.
	 */
	private function get_wpml_details( $post_id ) {
		$details = array(
			'is_wpml_active'   => false,
			'is_translation'   => false,
			'current_lang'     => '',
			'source_lang'      => '',
			'original_post_id' => null,
		);

		if ( ! function_exists( 'icl_get_languages' ) ) {
			return $details;
		}

		$details['is_wpml_active'] = true;

		// Method 1: Using wpml_post_language_details filter.
		$language_details = apply_filters( 'wpml_post_language_details', null, $post_id );
		if ( ! empty( $language_details ) ) {
			if ( is_object( $language_details ) ) {
				$details['current_lang'] = isset( $language_details->language_code ) ? $language_details->language_code : '';
				$details['original_post_id'] = isset( $language_details->original_post_id ) ? (int) $language_details->original_post_id : null;
			} elseif ( is_array( $language_details ) ) {
				$details['current_lang'] = isset( $language_details['language_code'] ) ? $language_details['language_code'] : '';
				$details['original_post_id'] = isset( $language_details['original_post_id'] ) ? (int) $language_details['original_post_id'] : null;
			}
		}

		// Method 2: Fallback to wpml_element_language_details if empty.
		if ( empty( $details['current_lang'] ) ) {
			$post_type = get_post_type( $post_id );
			$element_details = apply_filters( 'wpml_element_language_details', null, array(
				'element_id'   => $post_id,
				'element_type' => 'post_' . $post_type,
			) );

			if ( ! empty( $element_details ) ) {
				if ( is_object( $element_details ) ) {
					$details['current_lang'] = isset( $element_details->language_code ) ? $element_details->language_code : '';
					$details['source_lang'] = isset( $element_details->source_language_code ) ? $element_details->source_language_code : '';
				} elseif ( is_array( $element_details ) ) {
					$details['current_lang'] = isset( $element_details['language_code'] ) ? $element_details['language_code'] : '';
					$details['source_lang'] = isset( $element_details['source_language_code'] ) ? $element_details['source_language_code'] : '';
				}
			}
		}

		// Method 3: Get original post ID using wpml_original_element_id filter.
		if ( empty( $details['original_post_id'] ) ) {
			$post_type = get_post_type( $post_id );
			$orig_id = apply_filters( 'wpml_original_element_id', null, $post_id, 'post_' . $post_type );
			if ( ! empty( $orig_id ) ) {
				$details['original_post_id'] = (int) $orig_id;
			}
		}

		// Determine if it is a translation.
		if ( ! empty( $details['original_post_id'] ) && $details['original_post_id'] !== (int) $post_id ) {
			$details['is_translation'] = true;
		} elseif ( ! empty( $details['source_lang'] ) ) {
			$details['is_translation'] = true;
			// If we have source language but no original post ID, try to find it.
			if ( empty( $details['original_post_id'] ) ) {
				$post_type = get_post_type( $post_id );
				$orig_id = apply_filters( 'wpml_original_element_id', null, $post_id, 'post_' . $post_type );
				if ( ! empty( $orig_id ) ) {
					$details['original_post_id'] = (int) $orig_id;
				}
			}
		}

		// If it's a translation, get source language if we don't have it.
		if ( $details['is_translation'] && empty( $details['source_lang'] ) && ! empty( $details['original_post_id'] ) ) {
			$orig_details = apply_filters( 'wpml_post_language_details', null, $details['original_post_id'] );
			if ( ! empty( $orig_details ) ) {
				if ( is_object( $orig_details ) ) {
					$details['source_lang'] = isset( $orig_details->language_code ) ? $orig_details->language_code : '';
				} elseif ( is_array( $orig_details ) ) {
					$details['source_lang'] = isset( $orig_details['language_code'] ) ? $orig_details['language_code'] : '';
				}
			}
		}

		return $details;
	}

	/**
	 * AJAX translation handler.
	 */
	public function ajax_translate_content() {
		check_ajax_referer( 'sat_translate_nonce_action', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'simple-api-translator' ) ) );
		}

		$post_id         = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$content         = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$target_language = isset( $_POST['target_language'] ) ? sanitize_text_field( wp_unslash( $_POST['target_language'] ) ) : '';

		// If WPML is active, detect if this is a translation post.
		if ( $post_id > 0 ) {
			$wpml_details = $this->get_wpml_details( $post_id );
			if ( $wpml_details['is_translation'] && ! empty( $wpml_details['original_post_id'] ) ) {
				$original_post = get_post( $wpml_details['original_post_id'] );
				if ( $original_post ) {
					$content = $original_post->post_content;
					
					// Override target language to match this post's actual language.
					$current_lang_code = $wpml_details['current_lang'];
					$wpml_languages = icl_get_languages( 'skip_missing=0' );
					if ( isset( $wpml_languages[ $current_lang_code ]['translated_name'] ) ) {
						$target_language = $wpml_languages[ $current_lang_code ]['translated_name'];
					} else {
						$target_language = strtoupper( $current_lang_code );
					}
				}
			}
		}

		if ( empty( $content ) ) {
			wp_send_json_error( array( 'message' => __( 'Content to translate is empty.', 'simple-api-translator' ) ) );
		}

		if ( empty( $target_language ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select a target language.', 'simple-api-translator' ) ) );
		}

		$translation = SAT_Translator::translate( $content, $target_language );

		if ( is_wp_error( $translation ) ) {
			wp_send_json_error( array( 'message' => $translation->get_error_message() ) );
		}

		wp_send_json_success( array( 'translation' => $translation ) );
	}
}
