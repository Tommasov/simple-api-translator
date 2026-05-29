<?php
/**
 * Translator Engine Class for Simple API Translator.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAT_Translator {

	/**
	 * Translate the given content into the target language.
	 *
	 * @param string $content         Content to translate.
	 * @param string $target_language Target language name or code.
	 * @return string|WP_Error        Translated content or WP_Error on failure.
	 */
	public static function translate( $content, $target_language ) {
		$engine = get_option( 'sat_translation_engine', 'openai' );

		if ( 'deepl' === $engine ) {
			return self::translate_with_deepl( $content, $target_language );
		}

		return self::translate_with_openai( $content, $target_language );
	}

	/**
	 * Translate using OpenAI.
	 */
	private static function translate_with_openai( $content, $target_language ) {
		$api_key = get_option( 'sat_openai_api_key', '' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_api_key', __( 'OpenAI API Key is missing. Please configure it in Settings -> Simple API Translator.', 'simple-api-translator' ) );
		}

		if ( empty( $content ) ) {
			return '';
		}

		$url = 'https://api.openai.com/v1/chat/completions';

		$system_prompt = "You are a professional, precise website content translation assistant.\n"
			. "Your task is to translate the provided text into the target language: \"{$target_language}\".\n"
			. "CRITICAL RULES:\n"
			. "1. You MUST keep all HTML tags (e.g., <div>, <p>, <strong>, <a href=\"...\">) completely intact and in their original positions. Do NOT translate tag names or attribute keys. Do NOT change the target of links (href). You may only translate attribute values like 'alt' or 'title' if they contain natural language text.\n"
			. "2. You MUST keep all WordPress shortcodes (e.g., [gallery], [contact-form-7 id=\"1\" title=\"Contact\"], [my_shortcode]...[/my_shortcode]) completely intact. Do NOT translate the shortcode tags, their names, or parameter keys/values.\n"
			. "3. You MUST keep all Gutenberg block comment wrappers (e.g., <!-- wp:paragraph -->, <!-- /wp:paragraph -->) completely intact. Do not modify or translate them.\n"
			. "4. Output ONLY the translated content. Do not wrap the output in markdown code blocks, and do not add any conversational introductions, explanations, or notes.";

		$body = array(
			'model'       => 'gpt-4o-mini',
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $system_prompt,
				),
				array(
					'role'    => 'user',
					'content' => $content,
				),
			),
			'temperature' => 0.3,
		);

		$response = wp_remote_post( $url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 60, // Translation might take longer for large content.
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code !== 200 ) {
			$error_data = json_decode( $response_body, true );
			$error_message = isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : __( 'Unknown OpenAI API error.', 'simple-api-translator' );
			return new WP_Error( 'openai_api_error', sprintf( __( 'OpenAI API Error (Code %d): %s', 'simple-api-translator' ), $response_code, $error_message ) );
		}

		$data = json_decode( $response_body, true );
		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid response format received from OpenAI API.', 'simple-api-translator' ) );
		}

		return trim( $data['choices'][0]['message']['content'] );
	}

	/**
	 * Translate using DeepL API.
	 */
	private static function translate_with_deepl( $content, $target_language ) {
		$api_key = get_option( 'sat_deepl_api_key', '' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_deepl_key', __( 'DeepL API Key is missing. Please configure it in Settings -> Simple API Translator.', 'simple-api-translator' ) );
		}

		if ( empty( $content ) ) {
			return '';
		}

		// Detect if using DeepL Free API or Pro API.
		// Free keys usually end with ":fx"
		$is_free = ( substr( $api_key, -3 ) === ':fx' );
		$url = $is_free ? 'https://api-free.deepl.com/v2/translate' : 'https://api.deepl.com/v2/translate';

		$target_lang_code = self::map_to_deepl_language( $target_language );

		$body = array(
			'text'        => array( $content ),
			'target_lang' => $target_lang_code,
			'tag_handling'=> 'html',
		);

		$response = wp_remote_post( $url, array(
			'headers' => array(
				'Authorization' => 'DeepL-Auth-Key ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 60,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code !== 200 ) {
			$error_data = json_decode( $response_body, true );
			$error_message = isset( $error_data['message'] ) ? $error_data['message'] : __( 'Unknown DeepL API error.', 'simple-api-translator' );
			return new WP_Error( 'deepl_api_error', sprintf( __( 'DeepL API Error (Code %d): %s', 'simple-api-translator' ), $response_code, $error_message ) );
		}

		$data = json_decode( $response_body, true );
		if ( ! isset( $data['translations'][0]['text'] ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid response format received from DeepL API.', 'simple-api-translator' ) );
		}

		return trim( $data['translations'][0]['text'] );
	}

	/**
	 * Map language name or custom code to DeepL-compliant ISO 2-letter uppercase target language code.
	 */
	private static function map_to_deepl_language( $language ) {
		$lang = trim( strtolower( $language ) );

		// If it's a 2-letter code, handle standard formats
		if ( strlen( $lang ) === 2 ) {
			if ( $lang === 'en' ) {
				return 'EN-US'; // DeepL requires EN-US or EN-GB for target languages
			}
			if ( $lang === 'pt' ) {
				return 'PT-PT'; // DeepL requires PT-PT or PT-BR for target languages
			}
			return strtoupper( $lang );
		}

		// Handle language name mappings
		$mappings = array(
			// English
			'english'    => 'EN-US',
			'inglese'    => 'EN-US',
			// Italian
			'italian'    => 'IT',
			'italiano'   => 'IT',
			// Spanish
			'spanish'    => 'ES',
			'español'    => 'ES',
			'spagnolo'   => 'ES',
			// French
			'french'     => 'FR',
			'français'   => 'FR',
			'francese'   => 'FR',
			// German
			'german'     => 'DE',
			'deutsch'    => 'DE',
			'tedesco'    => 'DE',
			// Portuguese
			'portuguese' => 'PT-PT',
			'português'  => 'PT-PT',
			'portoghese' => 'PT-PT',
			// Dutch
			'dutch'      => 'NL',
			'nederlands' => 'NL',
			'olandese'   => 'NL',
			// Romanian
			'romanian'   => 'RO',
			'rumeno'     => 'RO',
			'română'     => 'RO',
			'romana'     => 'RO',
			// Russian
			'russian'    => 'RU',
			'russo'      => 'RU',
			// Polish
			'polish'     => 'PL',
			'polacco'    => 'PL',
			// Bulgarian
			'bulgarian'  => 'BG',
			'bulgaro'    => 'BG',
			// Czech
			'czech'      => 'CS',
			'ceco'       => 'CS',
			// Danish
			'danish'     => 'DA',
			'danese'     => 'DA',
			// Greek
			'greek'      => 'EL',
			'greco'      => 'EL',
			// Estonian
			'estonian'   => 'ET',
			'estone'     => 'ET',
			// Finnish
			'finnish'    => 'FI',
			'finlandese' => 'FI',
			// Hungarian
			'hungarian'  => 'HU',
			'ungherese'  => 'HU',
			// Indonesian
			'indonesian' => 'ID',
			'indonesiano'=> 'ID',
			// Japanese
			'japanese'   => 'JA',
			'giapponese' => 'JA',
			// Korean
			'korean'     => 'KO',
			'coreano'    => 'KO',
			// Lithuanian
			'lithuanian' => 'LT',
			'lituano'    => 'LT',
			// Latvian
			'latvian'    => 'LV',
			'lettone'    => 'LV',
			// Norwegian
			'norwegian'  => 'NB',
			'norvegese'  => 'NB',
			// Slovak
			'slovak'     => 'SK',
			'slovacco'   => 'SK',
			// Slovenian
			'slovenian'  => 'SL',
			'sloveno'    => 'SL',
			// Swedish
			'swedish'    => 'SV',
			'svedese'    => 'SV',
			// Turkish
			'turkish'    => 'TR',
			'turco'      => 'TR',
			// Ukrainian
			'ukrainian'  => 'UK',
			'ucraino'    => 'UK',
			// Chinese
			'chinese'    => 'ZH',
			'cinese'     => 'ZH',
		);

		if ( isset( $mappings[ $lang ] ) ) {
			return $mappings[ $lang ];
		}

		// Fallback: capitalize if it's already an ISO code (e.g. EN-GB) or just uppercase the trimmed string
		return strtoupper( $language );
	}
}
