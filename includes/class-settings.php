<?php
/**
 * Settings Class for Simple API Translator.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAT_Settings {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add Settings page to the admin menu.
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Simple API Translator Settings', 'simple-api-translator' ),
			__( 'Simple API Translator', 'simple-api-translator' ),
			'manage_options',
			'simple-api-translator',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings, sections, and fields.
	 */
	public function register_settings() {
		register_setting( 'sat_settings_group', 'sat_translation_engine', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'openai',
		) );

		register_setting( 'sat_settings_group', 'sat_openai_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );

		register_setting( 'sat_settings_group', 'sat_deepl_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );

		add_settings_section(
			'sat_general_section',
			__( 'Translation Engine Configuration', 'simple-api-translator' ),
			array( $this, 'section_callback' ),
			'simple-api-translator'
		);

		add_settings_field(
			'sat_translation_engine',
			__( 'Translation Engine', 'simple-api-translator' ),
			array( $this, 'engine_field_callback' ),
			'simple-api-translator',
			'sat_general_section'
		);

		add_settings_field(
			'sat_openai_api_key',
			__( 'OpenAI API Key', 'simple-api-translator' ),
			array( $this, 'api_key_field_callback' ),
			'simple-api-translator',
			'sat_general_section'
		);

		add_settings_field(
			'sat_deepl_api_key',
			__( 'DeepL API Key', 'simple-api-translator' ),
			array( $this, 'deepl_key_field_callback' ),
			'simple-api-translator',
			'sat_general_section'
		);
	}

	/**
	 * Section description callback.
	 */
	public function section_callback() {
		echo '<p>' . esc_html__( 'Configure your preferred translation engine and enter the corresponding API key.', 'simple-api-translator' ) . '</p>';
	}

	/**
	 * Render the translation engine selector.
	 */
	public function engine_field_callback() {
		$engine = get_option( 'sat_translation_engine', 'openai' );
		?>
		<select name="sat_translation_engine" id="sat_translation_engine">
			<option value="openai" <?php selected( $engine, 'openai' ); ?>><?php esc_html_e( 'OpenAI (GPT-4o-mini)', 'simple-api-translator' ); ?></option>
			<option value="deepl" <?php selected( $engine, 'deepl' ); ?>><?php esc_html_e( 'DeepL Translator', 'simple-api-translator' ); ?></option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Select the service you want to use for translating content.', 'simple-api-translator' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the API key input field.
	 */
	public function api_key_field_callback() {
		$api_key = get_option( 'sat_openai_api_key', '' );
		?>
		<input type="password" 
		       name="sat_openai_api_key" 
		       id="sat_openai_api_key" 
		       value="<?php echo esc_attr( $api_key ); ?>" 
		       class="regular-text" 
		       placeholder="sk-proj-..." 
		       style="font-family: monospace;" />
		<p class="description">
			<?php esc_html_e( 'Enter your secret OpenAI API key.', 'simple-api-translator' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the DeepL API key input field.
	 */
	public function deepl_key_field_callback() {
		$deepl_key = get_option( 'sat_deepl_api_key', '' );
		?>
		<input type="password" 
		       name="sat_deepl_api_key" 
		       id="sat_deepl_api_key" 
		       value="<?php echo esc_attr( $deepl_key ); ?>" 
		       class="regular-text" 
		       placeholder="e.g. 12345678-abcd-efgh-ijkl-1234567890ab:fx" 
		       style="font-family: monospace;" />
		<p class="description">
			<?php esc_html_e( 'Enter your DeepL API key (works with both DeepL API Free and Pro keys).', 'simple-api-translator' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the settings page wrapper.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin-top: 15px; max-width: 800px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<form action="options.php" method="post">
					<?php
					settings_fields( 'sat_settings_group' );
					do_settings_sections( 'simple-api-translator' );
					submit_button();
					?>
				</form>
			</div>
		</div>
		<?php
	}
}
