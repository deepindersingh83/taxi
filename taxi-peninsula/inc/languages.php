<?php
/**
 * Languages: Vietnamese, Chinese, Greek, Italian and Arabic.
 *
 * - Visitors choose a language with the switcher in the header (or are
 *   offered one when their browser prefers it). The choice is kept in a
 *   cookie; ?lang=vi works on any URL.
 * - The theme's own text — menus, the booking form, booking status, emails
 *   and SMS to customers, My Account and the driver screen — comes from
 *   /languages/taxi-peninsula-{locale}.mo.
 * - Page text you write yourself is translated by making a translated copy
 *   of the page (Pages → edit → Languages box). Visitors are sent to the copy
 *   in their language automatically.
 * - Arabic switches the layout to right-to-left.
 * - Customer emails and SMS go out in the language the booking was made in.
 * - [tp_auslan] shows an Auslan video and National Relay Service details.
 *
 * wp-admin always stays in the site language.
 *
 * @package TaxiPeninsula
 */

defined( 'ABSPATH' ) || exit;

/**
 * Supported languages: code => [locale, native name, English name, direction, hreflang].
 */
function tp_languages() {
	return array(
		'en' => array( 'en_AU', 'English', 'English', 'ltr', 'en-AU' ),
		'vi' => array( 'vi', 'Tiếng Việt', 'Vietnamese', 'ltr', 'vi' ),
		'zh' => array( 'zh_CN', '简体中文', 'Chinese (Simplified)', 'ltr', 'zh-Hans' ),
		'el' => array( 'el', 'Ελληνικά', 'Greek', 'ltr', 'el' ),
		'it' => array( 'it_IT', 'Italiano', 'Italian', 'ltr', 'it' ),
		'ar' => array( 'ar', 'العربية', 'Arabic', 'rtl', 'ar' ),
	);
}

/**
 * "View this site in …?" prompts, written in each language.
 */
function tp_language_prompts() {
	return array(
		'vi' => array( 'Xem trang web bằng tiếng Việt?', 'Xem bằng tiếng Việt', 'Không, cảm ơn' ),
		'zh' => array( '要用中文浏览本网站吗？', '切换到中文', '不用了，谢谢' ),
		'el' => array( 'Θέλετε να δείτε τον ιστότοπο στα Ελληνικά;', 'Ναι, στα Ελληνικά', 'Όχι, ευχαριστώ' ),
		'it' => array( 'Vuoi vedere il sito in italiano?', 'Sì, in italiano', 'No, grazie' ),
		'ar' => array( 'هل تريد عرض الموقع باللغة العربية؟', 'نعم، بالعربية', 'لا، شكرًا' ),
	);
}

/**
 * Languages switched on in the Customizer (English is always on).
 *
 * @return array code => language row.
 */
function tp_enabled_languages() {
	static $enabled = null;
	if ( null === $enabled ) {
		$enabled = array();
		foreach ( tp_languages() as $code => $row ) {
			if ( 'en' === $code || get_theme_mod( 'tp_lang_' . $code, true ) ) {
				$enabled[ $code ] = $row;
			}
		}
	}
	return $enabled;
}

/**
 * Is this a request we translate? (Not wp-admin screens; form handlers yes.)
 */
function tp_lang_applies() {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return false;
	}
	if ( is_admin() ) {
		$script = basename( (string) ( $_SERVER['SCRIPT_NAME'] ?? '' ) );
		return in_array( $script, array( 'admin-post.php', 'admin-ajax.php' ), true );
	}
	return true;
}

/**
 * The visitor's language for this request. Pass a code to switch.
 *
 * @param string|null $set Language code.
 * @return string
 */
function tp_lang( $set = null ) {
	static $lang = null;
	if ( null !== $set ) {
		$lang = isset( tp_languages()[ $set ] ) ? $set : 'en';
		return $lang;
	}
	if ( null === $lang ) {
		$lang = 'en';
		if ( tp_lang_applies() ) {
			$enabled = tp_enabled_languages();
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- language choice only.
			$query  = isset( $_GET['lang'] ) ? sanitize_key( wp_unslash( $_GET['lang'] ) ) : '';
			$posted = isset( $_POST['tp_lang'] ) ? sanitize_key( wp_unslash( $_POST['tp_lang'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$cookie = isset( $_COOKIE['tp_lang'] ) ? sanitize_key( wp_unslash( $_COOKIE['tp_lang'] ) ) : '';
			// phpcs:enable
			foreach ( array( $query, $posted, $cookie ) as $candidate ) {
				if ( $candidate && isset( $enabled[ $candidate ] ) ) {
					$lang = $candidate;
					break;
				}
			}
		}
	}
	return $lang;
}

/**
 * Locale for a language code.
 */
function tp_lang_locale( $code = null ) {
	$code = $code ?: tp_lang();
	return tp_languages()[ $code ][0] ?? 'en_AU';
}

function tp_lang_is_rtl( $code = null ) {
	$code = $code ?: tp_lang();
	return 'rtl' === ( tp_languages()[ $code ][3] ?? 'ltr' );
}

// WordPress asks for the locale many times; answer with the visitor's language.
$tp_locale_filter = static function ( $locale ) {
	return 'en' === tp_lang() ? $locale : tp_lang_locale();
};
add_filter( 'locale', $tp_locale_filter, 99 );
add_filter( 'determine_locale', $tp_locale_filter, 99 );
unset( $tp_locale_filter );

/**
 * Reload translations after the language changes mid-request (e.g. a
 * translated page was opened directly).
 *
 * @param string $code Language code.
 */
function tp_switch_lang( $code ) {
	if ( $code === tp_lang() ) {
		return;
	}
	tp_lang( $code );
	unload_textdomain( 'taxi-peninsula' );
	load_theme_textdomain( 'taxi-peninsula', TP_DIR . '/languages' );
	tp_lang_load_core();
}

/**
 * Core strings, month names and text direction (core language packs are
 * used when installed under wp-content/languages).
 */
function tp_lang_load_core() {
	$locale = determine_locale();
	if ( 'en' !== tp_lang() && file_exists( WP_LANG_DIR . '/' . $locale . '.mo' ) ) {
		unload_textdomain( 'default' );
		load_default_textdomain( $locale );
	}
	if ( 'en' !== tp_lang() || is_rtl() ) {
		$GLOBALS['wp_locale'] = new WP_Locale(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_locale']->text_direction = tp_lang_is_rtl() ? 'rtl' : 'ltr';
	}
}
add_action( 'after_setup_theme', 'tp_lang_load_core', 20 );

// Remember the choice.
add_action(
	'init',
	static function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! tp_lang_applies() || ! isset( $_GET['lang'] ) || headers_sent() ) {
			return;
		}
		$code = sanitize_key( wp_unslash( $_GET['lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( tp_enabled_languages()[ $code ] ) ) {
			tp_lang_set_cookie( $code );
		}
	}
);

function tp_lang_set_cookie( $code ) {
	if ( headers_sent() ) {
		return;
	}
	setcookie(
		'tp_lang',
		$code,
		array(
			'expires'  => time() + YEAR_IN_SECONDS,
			'path'     => COOKIEPATH ?: '/',
			'domain'   => COOKIE_DOMAIN ?: '',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);
	$_COOKIE['tp_lang'] = $code;
}

add_filter(
	'body_class',
	static function ( $classes ) {
		$classes[] = 'lang-' . tp_lang();
		if ( tp_lang_is_rtl() ) {
			$classes[] = 'rtl';
		}
		return $classes;
	}
);

// Arabic: swap in the mirrored stylesheet.
add_action(
	'wp_enqueue_scripts',
	static function () {
		// Generated with rtlcss: npx rtlcss assets/css/main.css assets/css/main-rtl.css (same for patterns.css).
		foreach ( array( 'tp-main', 'tp-patterns' ) as $handle ) {
			wp_style_add_data( $handle, 'rtl', 'replace' );
		}
	},
	20
);

/* -------------------------------------------------------------------------
 * Translated text you enter in the Customizer (hero, hours…).
 * ---------------------------------------------------------------------- */

/**
 * Customizer texts that can have a version per language.
 */
function tp_lang_text_keys() {
	return array(
		'hero_eyebrow' => array( __( 'Small heading', 'taxi-peninsula' ), 'text' ),
		'hero_title'   => array( __( 'Headline', 'taxi-peninsula' ), 'text' ),
		'hero_text'    => array( __( 'Intro text', 'taxi-peninsula' ), 'textarea' ),
		'hours'        => array( __( 'Opening hours', 'taxi-peninsula' ), 'text' ),
		'location'     => array( __( 'Location', 'taxi-peninsula' ), 'text' ),
	);
}

/**
 * Translated override for a Customizer text, or null.
 */
function tp_lang_opt( $key ) {
	$lang = tp_lang();
	if ( 'en' === $lang || ! isset( tp_lang_text_keys()[ $key ] ) ) {
		return null;
	}
	$value = get_theme_mod( 'tp_' . $key . '_' . $lang, '' );
	return '' === $value ? null : $value;
}

add_action(
	'customize_register',
	static function ( WP_Customize_Manager $wp_customize ) {
		$wp_customize->add_section(
			'tp_languages',
			array(
				'title'       => __( 'Languages', 'taxi-peninsula' ),
				'panel'       => 'tp_panel',
				'description' => __( 'Visitors can switch the website to these languages. Menus, the booking form, booking pages, emails and texts are translated. For your own page text, open the page and use the Languages box to make a translated copy.', 'taxi-peninsula' ),
			)
		);
		foreach ( tp_languages() as $code => $row ) {
			if ( 'en' === $code ) {
				continue;
			}
			$wp_customize->add_setting( 'tp_lang_' . $code, array( 'default' => true, 'sanitize_callback' => 'tp_sanitize_checkbox' ) );
			$wp_customize->add_control(
				'tp_lang_' . $code,
				array(
					'section' => 'tp_languages',
					/* translators: 1: language name in English, 2: native name */
					'label'   => sprintf( __( 'Offer %1$s (%2$s)', 'taxi-peninsula' ), $row[2], $row[1] ),
					'type'    => 'checkbox',
				)
			);
		}
		$wp_customize->add_setting( 'tp_tis_note', array( 'default' => true, 'sanitize_callback' => 'tp_sanitize_checkbox' ) );
		$wp_customize->add_control(
			'tp_tis_note',
			array(
				'section'     => 'tp_languages',
				'label'       => __( 'Show the free interpreter (TIS National 131 450) and National Relay Service details', 'taxi-peninsula' ),
				'description' => __( 'Shown in the footer and on pages that are not translated yet. Register your business with TIS National so their interpreters can call you.', 'taxi-peninsula' ),
				'type'        => 'checkbox',
			)
		);
		$wp_customize->add_setting( 'tp_auslan_url', array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
		$wp_customize->add_control(
			'tp_auslan_url',
			array(
				'section'     => 'tp_languages',
				'label'       => __( 'Auslan video (YouTube or Vimeo link)', 'taxi-peninsula' ),
				'description' => __( 'A short video in Auslan explaining how to book. Shown with [tp_auslan] (it is on the Accessibility Statement page) and linked from the language menu.', 'taxi-peninsula' ),
				'type'        => 'url',
			)
		);
		$wp_customize->add_setting( 'tp_auslan_transcript', array( 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ) );
		$wp_customize->add_control(
			'tp_auslan_transcript',
			array(
				'section' => 'tp_languages',
				'label'   => __( 'Auslan video: English transcript', 'taxi-peninsula' ),
				'type'    => 'textarea',
			)
		);

		$wp_customize->add_section(
			'tp_lang_texts',
			array(
				'title'       => __( 'Translated home page text', 'taxi-peninsula' ),
				'panel'       => 'tp_panel',
				'description' => __( 'If you changed the hero text or opening hours, add the translated versions here. Leave a box empty to use the theme’s built-in translation of the original wording.', 'taxi-peninsula' ),
			)
		);
		foreach ( tp_languages() as $code => $row ) {
			if ( 'en' === $code ) {
				continue;
			}
			foreach ( tp_lang_text_keys() as $key => $field ) {
				$wp_customize->add_setting( 'tp_' . $key . '_' . $code, array( 'default' => '', 'sanitize_callback' => 'textarea' === $field[1] ? 'sanitize_textarea_field' : 'sanitize_text_field' ) );
				$wp_customize->add_control(
					'tp_' . $key . '_' . $code,
					array(
						'section' => 'tp_lang_texts',
						'label'   => $row[1] . ' — ' . $field[0],
						'type'    => $field[1],
					)
				);
			}
		}
	},
	20
);

/* -------------------------------------------------------------------------
 * Translated copies of pages.
 * ---------------------------------------------------------------------- */

/**
 * The page a translation was made from (or the page itself).
 */
function tp_translation_source( $post_id ) {
	$source = (int) get_post_meta( $post_id, '_tp_translation_of', true );
	return $source ?: (int) $post_id;
}

/**
 * All language versions of a page: code => post ID (English = the source).
 *
 * @param int  $post_id   Any version.
 * @param bool $published Only published versions.
 * @return array
 */
function tp_translations( $post_id, $published = true ) {
	static $cache = array();
	$source = tp_translation_source( $post_id );
	$key    = $source . ( $published ? 'p' : 'a' );
	if ( ! isset( $cache[ $key ] ) ) {
		$list = array( 'en' => $source );
		$ids  = get_posts(
			array(
				'post_type'   => get_post_type( $source ) ?: 'page',
				'post_status' => $published ? 'publish' : array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'numberposts' => 20,
				'fields'      => 'ids',
				'meta_key'    => '_tp_translation_of', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => $source, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		foreach ( $ids as $id ) {
			$code = get_post_meta( $id, '_tp_lang', true );
			if ( $code && isset( tp_languages()[ $code ] ) ) {
				$list[ $code ] = (int) $id;
			}
		}
		$cache[ $key ] = $list;
	}
	return $cache[ $key ];
}

function tp_post_lang( $post_id ) {
	return get_post_meta( $post_id, '_tp_lang', true ) ?: 'en';
}

// Links to pages point at the visitor's language version when there is one.
add_filter(
	'page_link',
	static function ( $link, $post_id ) {
		static $busy = false;
		if ( $busy || 'en' === tp_lang() || ! tp_lang_applies() ) {
			return $link;
		}
		$versions = tp_translations( $post_id );
		$target   = $versions[ tp_lang() ] ?? 0;
		if ( $target && (int) $target !== (int) $post_id ) {
			$busy = true;
			$link = get_permalink( $target );
			$busy = false;
		}
		return $link;
	},
	10,
	2
);

// Opening a page: follow the visitor's language, or adopt the page's.
add_action(
	'template_redirect',
	static function () {
		if ( ! is_singular( 'page' ) ) {
			return;
		}
		$post_id   = get_queried_object_id();
		$page_lang = tp_post_lang( $post_id );
		$explicit  = isset( $_GET['lang'] ) && isset( tp_enabled_languages()[ sanitize_key( wp_unslash( $_GET['lang'] ) ) ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$versions  = tp_translations( $post_id );

		if ( $page_lang !== tp_lang() ) {
			if ( ! $explicit && 'en' !== $page_lang && isset( tp_enabled_languages()[ $page_lang ] ) ) {
				// e.g. a Vietnamese page opened from Google: show the site in Vietnamese.
				tp_switch_lang( $page_lang );
				tp_lang_set_cookie( $page_lang );
				return;
			}
			$target = $versions[ tp_lang() ] ?? ( 'en' !== $page_lang ? $versions['en'] : 0 );
			if ( $target && (int) $target !== (int) $post_id ) {
				wp_safe_redirect( get_permalink( $target ), 302 );
				exit;
			}
		}
	},
	1
);

/**
 * A short notice on pages whose own text is not in the visitor's language.
 */
add_filter(
	'the_content',
	static function ( $content ) {
		if ( 'en' === tp_lang() || ! is_singular() || ! in_the_loop() || ! is_main_query() || '' === trim( wp_strip_all_tags( $content ) ) ) {
			return $content;
		}
		$post_id = get_the_ID();
		if ( tp_post_lang( $post_id ) === tp_lang() || ! in_array( get_post_type( $post_id ), array( 'page', 'post', 'tp_service', 'tp_area' ), true ) ) {
			return $content;
		}
		ob_start();
		?>
		<div class="lang-note" role="note">
			<p><strong><?php esc_html_e( 'This page is in English.', 'taxi-peninsula' ); ?></strong>
				<?php esc_html_e( 'The booking form and your booking details are in your language.', 'taxi-peninsula' ); ?></p>
			<?php tp_the_interpreter_note(); ?>
		</div>
		<?php
		return ob_get_clean() . $content;
	},
	1
);

// Keep translated copies out of the English blog, search and archives.
add_action(
	'pre_get_posts',
	static function ( WP_Query $q ) {
		if ( is_admin() || ! $q->is_main_query() || $q->is_singular() ) {
			return;
		}
		$meta   = (array) $q->get( 'meta_query' );
		$meta[] = 'en' === tp_lang()
			? array( 'key' => '_tp_lang', 'compare' => 'NOT EXISTS' )
			: array(
				'relation' => 'OR',
				array( 'key' => '_tp_lang', 'compare' => 'NOT EXISTS' ),
				array( 'key' => '_tp_lang', 'value' => tp_lang() ),
			);
		$q->set( 'meta_query', $meta );
	}
);

// hreflang for pages that have translations.
add_action(
	'wp_head',
	static function () {
		if ( ! is_singular( 'page' ) ) {
			return;
		}
		$versions = tp_translations( get_queried_object_id() );
		if ( count( $versions ) < 2 ) {
			return;
		}
		$langs = tp_languages();
		foreach ( $versions as $code => $id ) {
			printf( "<link rel=\"alternate\" hreflang=\"%s\" href=\"%s\">\n", esc_attr( $langs[ $code ][4] ), esc_url( get_permalink( $id ) ) );
		}
		printf( "<link rel=\"alternate\" hreflang=\"x-default\" href=\"%s\">\n", esc_url( get_permalink( $versions['en'] ) ) );
	},
	2
);

/* Editing: the Languages box on pages. */

add_action(
	'add_meta_boxes_page',
	static function () {
		add_meta_box( 'tp_languages', __( 'Languages', 'taxi-peninsula' ), 'tp_render_languages_box', 'page', 'side', 'default' );
	}
);

function tp_render_languages_box( WP_Post $post ) {
	$langs = tp_languages();
	$code  = tp_post_lang( $post->ID );
	wp_nonce_field( 'tp_page_lang', '_tp_lang_nonce' );
	if ( 'en' !== $code ) {
		$source = tp_translation_source( $post->ID );
		echo '<p>';
		/* translators: 1: language, 2: page title */
		printf( esc_html__( 'This is the %1$s version of “%2$s”.', 'taxi-peninsula' ), esc_html( $langs[ $code ][1] ), '<a href="' . esc_url( get_edit_post_link( $source ) ?: '#' ) . '">' . esc_html( get_the_title( $source ) ) . '</a>' );
		echo '</p><p class="description">' . esc_html__( 'Visitors who choose this language are sent here automatically once it is published.', 'taxi-peninsula' ) . '</p>';
		return;
	}
	$versions = tp_translations( $post->ID, false );
	if ( 'auto-draft' === $post->post_status ) {
		echo '<p class="description">' . esc_html__( 'Save the page first, then you can add translated copies.', 'taxi-peninsula' ) . '</p>';
		return;
	}
	echo '<p class="description">' . esc_html__( 'Make a copy of this page for each language and translate the text in the editor. Menus and links switch to the right copy by themselves.', 'taxi-peninsula' ) . '</p><ul class="tp-lang-list">';
	foreach ( tp_enabled_languages() as $lc => $row ) {
		if ( 'en' === $lc ) {
			continue;
		}
		echo '<li><span lang="' . esc_attr( $row[4] ) . '">' . esc_html( $row[1] ) . '</span> ';
		if ( isset( $versions[ $lc ] ) ) {
			$status = get_post_status( $versions[ $lc ] );
			echo '<a href="' . esc_url( get_edit_post_link( $versions[ $lc ] ) ) . '">' . esc_html( 'publish' === $status ? __( 'Edit', 'taxi-peninsula' ) : __( 'Edit draft', 'taxi-peninsula' ) ) . '</a>';
		} else {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=tp_translate&post=' . $post->ID . '&lang=' . $lc ), 'tp_translate_' . $post->ID );
			echo '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Add translation', 'taxi-peninsula' ) . '</a>';
		}
		echo '</li>';
	}
	echo '</ul>';
}

add_action(
	'admin_post_tp_translate',
	static function () {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$code    = isset( $_GET['lang'] ) ? sanitize_key( wp_unslash( $_GET['lang'] ) ) : '';
		check_admin_referer( 'tp_translate_' . $post_id );
		$post = get_post( $post_id );
		if ( ! $post || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'publish_pages' ) || ! isset( tp_languages()[ $code ] ) || 'en' === $code ) {
			wp_die( '', 403 );
		}
		$existing = tp_translations( $post_id, false );
		if ( isset( $existing[ $code ] ) ) {
			wp_safe_redirect( get_edit_post_link( $existing[ $code ], 'raw' ) );
			exit;
		}
		$name = tp_languages()[ $code ][1];
		$new  = wp_insert_post(
			array(
				'post_type'    => $post->post_type,
				'post_status'  => 'draft',
				'post_title'   => $post->post_title . ' (' . $name . ')',
				'post_name'    => $post->post_name . '-' . $code,
				'post_content' => $post->post_content,
				'post_excerpt' => $post->post_excerpt,
				'post_parent'  => $post->post_parent,
				'menu_order'   => $post->menu_order,
				'meta_input'   => array(
					'_tp_lang'           => $code,
					'_tp_translation_of' => $post_id,
					'_wp_page_template'  => get_post_meta( $post_id, '_wp_page_template', true ),
				),
			),
			true
		);
		if ( is_wp_error( $new ) ) {
			wp_die( esc_html( $new->get_error_message() ) );
		}
		$thumb = get_post_thumbnail_id( $post_id );
		if ( $thumb ) {
			set_post_thumbnail( $new, $thumb );
		}
		wp_safe_redirect( get_edit_post_link( $new, 'raw' ) );
		exit;
	}
);

// "Languages" column on Pages.
add_filter(
	'manage_page_posts_columns',
	static function ( $cols ) {
		$cols['tp_lang'] = __( 'Languages', 'taxi-peninsula' );
		return $cols;
	}
);
add_action(
	'manage_page_posts_custom_column',
	static function ( $col, $post_id ) {
		if ( 'tp_lang' !== $col ) {
			return;
		}
		$code = tp_post_lang( $post_id );
		if ( 'en' !== $code ) {
			echo '<span class="tp-lang-chip">' . esc_html( strtoupper( $code ) ) . '</span>';
			return;
		}
		$versions = array_keys( tp_translations( $post_id, false ) );
		echo esc_html( strtoupper( implode( ' · ', $versions ) ) );
	},
	10,
	2
);

/* -------------------------------------------------------------------------
 * Emails and texts to customers in the booking's language.
 * ---------------------------------------------------------------------- */

// Remember which language a booking was made in.
add_action(
	'added_post_meta',
	static function ( $meta_id, $object_id, $meta_key ) {
		if ( '_tp_reference' === $meta_key && 'en' !== tp_lang() && tp_lang_applies() && 'tp_booking' === get_post_type( $object_id ) ) {
			update_post_meta( $object_id, '_tp_lang', tp_lang() );
		}
	},
	10,
	3
);

/**
 * Run $callback with translations switched to another language.
 *
 * @param string   $code     Language code.
 * @param callable $callback Work to do.
 * @return mixed
 */
function tp_in_lang( $code, callable $callback ) {
	$was = tp_lang();
	if ( ! $code || $code === $was || ! isset( tp_languages()[ $code ] ) ) {
		return $callback();
	}
	tp_switch_lang( $code );
	try {
		return $callback();
	} finally {
		tp_switch_lang( $was );
	}
}

/**
 * Customer emails and SMS: in the language the booking was made in, even
 * when staff or a driver trigger them.
 */
function tp_in_booking_lang( $post_id, callable $callback ) {
	return tp_in_lang( get_post_meta( $post_id, '_tp_lang', true ) ?: 'en', $callback );
}

/**
 * Office emails, booking history and the activity log: always in the site
 * language, even when a customer's action triggers them.
 */
function tp_in_site_lang( callable $callback ) {
	return tp_in_lang( 'en', $callback );
}

/* -------------------------------------------------------------------------
 * Front end: switcher, browser-language suggestion, interpreter note.
 * ---------------------------------------------------------------------- */

/**
 * URL of the current page in another language.
 */
function tp_lang_url( $code ) {
	if ( is_singular( 'page' ) ) {
		$versions = tp_translations( get_queried_object_id() );
		if ( isset( $versions[ $code ] ) ) {
			return add_query_arg( 'lang', $code, get_permalink( $versions[ $code ] ) );
		}
		if ( 'en' !== tp_post_lang( get_queried_object_id() ) ) {
			return add_query_arg( 'lang', $code, get_permalink( $versions['en'] ) );
		}
	}
	// A missing page has no other-language version: switch language on the home page instead.
	$path = is_404() ? '/' : ( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/' );
	$url  = remove_query_arg( array( 'lang', 'tp_form', 'saved', 'rated', 'privacy_request' ), home_url( $path ) );
	return add_query_arg( 'lang', $code, $url );
}

function tp_auslan_url() {
	if ( ! get_theme_mod( 'tp_auslan_url', '' ) ) {
		return '';
	}
	$pages = (array) get_option( 'tp_pages', array() );
	$id    = (int) ( $pages['accessibility'] ?? 0 );
	return $id && 'publish' === get_post_status( $id ) ? get_permalink( $id ) . '#auslan' : '';
}

/**
 * Language menu.
 *
 * @param string $context header|footer.
 */
function tp_the_language_switcher( $context = 'header' ) {
	$enabled = tp_enabled_languages();
	if ( count( $enabled ) < 2 ) {
		return;
	}
	$current = tp_lang();
	$auslan  = tp_auslan_url();
	if ( 'footer' === $context ) {
		echo '<nav class="lang-links" aria-label="' . esc_attr__( 'Languages', 'taxi-peninsula' ) . '"><ul>';
		foreach ( $enabled as $code => $row ) {
			printf(
				'<li><a href="%1$s" lang="%2$s" hreflang="%2$s" data-lang-choice%3$s>%4$s</a></li>',
				esc_url( tp_lang_url( $code ) ),
				esc_attr( $row[4] ),
				$code === $current ? ' aria-current="true"' : '',
				esc_html( $row[1] )
			);
		}
		if ( $auslan ) {
			printf( '<li><a href="%s">%s</a></li>', esc_url( $auslan ), esc_html__( 'Auslan', 'taxi-peninsula' ) );
		}
		echo '</ul></nav>';
		return;
	}
	?>
	<details class="lang-switch" data-lang-switch>
		<summary class="lang-switch__button" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: current language */ __( 'Language: %s. Change language', 'taxi-peninsula' ), $enabled[ $current ][1] ) ); ?>">
			<?php tp_the_icon( 'globe' ); ?>
			<span class="lang-switch__current" lang="<?php echo esc_attr( $enabled[ $current ][4] ); ?>"><?php echo esc_html( $enabled[ $current ][1] ); ?></span>
			<span class="lang-switch__code" aria-hidden="true"><?php echo esc_html( strtoupper( $current ) ); ?></span>
		</summary>
		<ul class="lang-switch__menu">
			<?php foreach ( $enabled as $code => $row ) : ?>
				<li>
					<a href="<?php echo esc_url( tp_lang_url( $code ) ); ?>" lang="<?php echo esc_attr( $row[4] ); ?>" hreflang="<?php echo esc_attr( $row[4] ); ?>" data-lang-choice<?php echo $code === $current ? ' aria-current="true"' : ''; ?>>
						<span class="lang-switch__native"><?php echo esc_html( $row[1] ); ?></span>
						<?php if ( 'en' !== $code ) : ?>
							<span class="lang-switch__en" lang="en"><?php echo esc_html( $row[2] ); ?></span>
						<?php endif; ?>
					</a>
				</li>
			<?php endforeach; ?>
			<?php if ( $auslan ) : ?>
				<li><a href="<?php echo esc_url( $auslan ); ?>"><span class="lang-switch__native"><?php esc_html_e( 'Auslan', 'taxi-peninsula' ); ?></span> <span class="lang-switch__en"><?php esc_html_e( 'video', 'taxi-peninsula' ); ?></span></a></li>
			<?php endif; ?>
		</ul>
	</details>
	<?php
}

/**
 * "Need an interpreter?" line (TIS National and the National Relay Service).
 */
function tp_the_interpreter_note( $class = 'interpreter-note' ) {
	if ( ! get_theme_mod( 'tp_tis_note', true ) ) {
		return;
	}
	echo '<p class="' . esc_attr( $class ) . '">';
	tp_the_icon( 'phone' );
	echo ' <span>';
	printf(
		/* translators: 1: business name, 2: phone number */
		esc_html__( 'Need an interpreter? Call TIS National on 131 450 and ask them to call %1$s on %2$s.', 'taxi-peninsula' ),
		esc_html( get_bloginfo( 'name' ) ),
		'<a href="' . esc_url( tp_phone_href() ) . '">' . esc_html( tp_opt( 'phone_display' ) ) . '</a>'
	);
	echo ' ';
	esc_html_e( 'Deaf, hard of hearing or speech impaired? Use the National Relay Service on 133 677.', 'taxi-peninsula' );
	echo '</span></p>';
}

// Offer the visitor's browser language once.
add_action(
	'wp_footer',
	static function () {
		$enabled = tp_enabled_languages();
		if ( count( $enabled ) < 2 || 'en' !== tp_lang() ) {
			return;
		}
		$offers = array();
		foreach ( tp_language_prompts() as $code => $p ) {
			if ( isset( $enabled[ $code ] ) ) {
				$offers[ $code ] = array(
					'q'    => $p[0],
					'yes'  => $p[1],
					'no'   => $p[2],
					'url'  => tp_lang_url( $code ),
					'lang' => $enabled[ $code ][4],
					'dir'  => $enabled[ $code ][3],
				);
			}
		}
		printf(
			'<div class="lang-suggest" hidden data-lang-suggest="%s" role="region" aria-label="%s"><p class="lang-suggest__q"></p><p class="lang-suggest__actions"><a class="btn btn--accent btn--sm lang-suggest__yes" data-lang-choice href="#"></a> <button type="button" class="btn btn--ghost btn--sm lang-suggest__no"></button></p></div>',
			esc_attr( wp_json_encode( $offers ) ),
			esc_attr__( 'Language', 'taxi-peninsula' )
		);
	},
	5
);

/* -------------------------------------------------------------------------
 * [tp_auslan] — Auslan video and National Relay Service.
 * ---------------------------------------------------------------------- */

add_shortcode(
	'tp_auslan',
	static function () {
		$url        = get_theme_mod( 'tp_auslan_url', '' );
		$remote     = $url && function_exists( 'tp_parse_video_url' ) ? tp_parse_video_url( $url ) : array( '', '' );
		$transcript = trim( (string) get_theme_mod( 'tp_auslan_transcript', '' ) );
		ob_start();
		?>
		<section class="tp-component auslan card" id="auslan" aria-labelledby="auslan-title">
			<h2 id="auslan-title" class="auslan__title"><?php esc_html_e( 'Auslan and the National Relay Service', 'taxi-peninsula' ); ?></h2>
			<?php if ( $remote[0] ) : ?>
				<?php
				$embed = 'youtube' === $remote[0]
					? 'https://www.youtube-nocookie.com/embed/' . $remote[1] . '?autoplay=1&rel=0&cc_load_policy=1'
					: 'https://player.vimeo.com/video/' . $remote[1] . '?autoplay=1&dnt=1';
				$thumb = ''; // Nothing loads from YouTube until play is pressed.
				?>
				<div class="video-frame auslan__video">
					<button type="button" class="video-facade" data-video-embed="<?php echo esc_url( $embed ); ?>" data-video-title="<?php esc_attr_e( 'How to book with us, in Auslan', 'taxi-peninsula' ); ?>">
						<span class="video-facade__art" aria-hidden="true"><?php echo tp_illustration( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
						<span class="video-facade__play"><?php tp_the_icon( 'play' ); ?></span>
						<span class="video-facade__label"><?php esc_html_e( 'Play: how to book with us, in Auslan', 'taxi-peninsula' ); ?></span>
					</button>
				</div>
				<?php if ( $transcript ) : ?>
					<details class="video-transcript">
						<summary><?php esc_html_e( 'Read the English transcript', 'taxi-peninsula' ); ?></summary>
						<div class="prose"><?php echo wp_kses_post( wpautop( esc_html( $transcript ) ) ); ?></div>
					</details>
				<?php endif; ?>
			<?php elseif ( current_user_can( 'edit_theme_options' ) ) : ?>
				<p class="tp-todo"><?php esc_html_e( '[Add your Auslan video link under Appearance → Customize → Taxi Peninsula → Languages. Only staff see this note.]', 'taxi-peninsula' ); ?></p>
			<?php endif; ?>
			<ul class="auslan__ways">
				<li><?php esc_html_e( 'Book online — the booking form has no time limit and works with screen readers.', 'taxi-peninsula' ); ?></li>
				<?php if ( tp_sms_href() ) : ?>
					<li>
						<?php
						printf(
							/* translators: %s: SMS link */
							esc_html__( 'Text us on %s — we reply by text.', 'taxi-peninsula' ),
							'<a href="' . esc_attr( tp_sms_href() ) . '">' . esc_html( tp_opt( 'phone_display' ) ) . '</a>'
						);
						?>
					</li>
				<?php endif; ?>
				<li>
					<?php
					printf(
						/* translators: %s: phone */
						esc_html__( 'National Relay Service: use the NRS app or nrschat.nrscall.gov.au, or call 133 677, and give our number %s.', 'taxi-peninsula' ),
						esc_html( tp_opt( 'phone_display' ) )
					);
					?>
				</li>
				<li><?php esc_html_e( 'Ask for SMS updates: we text you when your driver is on the way and when they arrive.', 'taxi-peninsula' ); ?></li>
			</ul>
		</section>
		<?php
		return ob_get_clean();
	}
);

/* -------------------------------------------------------------------------
 * Built-in page titles (Book a Taxi, My Account…) follow the language, as
 * long as you have not renamed them and there is no translated copy.
 * ---------------------------------------------------------------------- */

function tp_lang_page_titles() {
	return array(
		'Book a Taxi'                 => __( 'Book a Taxi', 'taxi-peninsula' ),
		'Manage My Booking'           => __( 'Manage My Booking', 'taxi-peninsula' ),
		'My Account'                  => __( 'My Account', 'taxi-peninsula' ),
		'Contact Us'                  => __( 'Contact Us', 'taxi-peninsula' ),
		'Our Fleet'                   => __( 'Our Fleet', 'taxi-peninsula' ),
		'FAQ'                         => __( 'FAQ', 'taxi-peninsula' ),
		'Driver Jobs'                 => __( 'Driver Jobs', 'taxi-peninsula' ),
		'Our Services'                => __( 'Our Services', 'taxi-peninsula' ),
		'About Us'                    => __( 'About Us', 'taxi-peninsula' ),
		'NDIS & Aged Care'            => __( 'NDIS & Aged Care', 'taxi-peninsula' ),
		'Privacy Policy'              => __( 'Privacy Policy', 'taxi-peninsula' ),
		'Accessibility Statement'     => __( 'Accessibility Statement', 'taxi-peninsula' ),
		'Terms & Cancellation Policy' => __( 'Terms & Cancellation Policy', 'taxi-peninsula' ),
		'Complaints & Feedback'       => __( 'Complaints & Feedback', 'taxi-peninsula' ),
		'Drive With Us'               => __( 'Drive With Us', 'taxi-peninsula' ),
		'Home'                        => __( 'Home', 'taxi-peninsula' ),
		'Blog'                        => __( 'Blog', 'taxi-peninsula' ),
	);
}

function tp_lang_translate_title( $title, $post_id = 0 ) {
	if ( 'en' === tp_lang() || is_admin() || ! $post_id || 'page' !== get_post_type( $post_id ) || 'en' !== tp_post_lang( $post_id ) ) {
		return $title;
	}
	$plain = html_entity_decode( wp_strip_all_tags( (string) $title ), ENT_QUOTES, 'UTF-8' );
	$map   = tp_lang_page_titles();
	return isset( $map[ $plain ] ) ? esc_html( $map[ $plain ] ) : $title;
}
add_filter( 'the_title', 'tp_lang_translate_title', 10, 2 );
add_filter( 'single_post_title', static function ( $title, $post ) {
	return tp_lang_translate_title( $title, $post->ID ?? 0 );
}, 10, 2 );
// Menu items that use the page title.
add_filter( 'nav_menu_item_title', static function ( $title, $item ) {
	return 'post_type' === $item->type && 'page' === $item->object ? tp_lang_translate_title( $title, (int) $item->object_id ) : $title;
}, 10, 2 );
