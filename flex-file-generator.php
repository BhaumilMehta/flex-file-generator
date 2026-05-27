<?php
/**
 * Plugin Name:  Flex File Generator
 * Plugin URI:   https://github.com/your-repo/flex-file-generator
 * Description:  Auto-generates PHP template parts and CSS stubs for every ACF Flexible Content layout when a field group is saved.
 * Version:      1.0.0
 * Author:       Bhaumil Mehta
 * License:      GPL-2.0-or-later
 * Text Domain:  flex-file-generator
 *
 * @package FlexFileGenerator
 */

defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────────────────────────────────────
// 1. BOOTSTRAP — only proceed when ACF is active.
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'plugins_loaded', 'ffg_init' );

/**
 * Register our ACF hook only after all plugins have loaded,
 * so we can safely check whether ACF exists.
 */
function ffg_init() {
	if ( ! function_exists( 'acf_get_field_groups' ) ) {
		return; // ACF not active — do nothing.
	}

	/**
	 * Fires after ACF saves a field group.
	 *
	 * @param array $field_group The field group data array.
	 */
	add_action( 'acf/update_field_group', 'ffg_handle_field_group_save' );
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. MAIN HANDLER
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Triggered whenever an ACF field group is created or updated.
 * Loops through all fields in the group, finds Flexible Content fields,
 * and generates missing template/CSS files for each layout.
 *
 * @param array $field_group ACF field group array.
 */
function ffg_handle_field_group_save( array $field_group ) {
	// Retrieve every field that belongs to this field group.
	$fields = acf_get_fields( $field_group );

	if ( empty( $fields ) || ! is_array( $fields ) ) {
		return;
	}

	foreach ( $fields as $field ) {
		if ( isset( $field['type'] ) && 'flexible_content' === $field['type'] ) {
			ffg_process_flex_field( $field );
		}
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. PROCESS A SINGLE FLEXIBLE CONTENT FIELD
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Iterates over the layouts defined in a Flexible Content field and
 * delegates file creation to the appropriate helpers.
 *
 * @param array $field ACF flexible_content field array.
 */
function ffg_process_flex_field( array $field ) {
	if ( empty( $field['layouts'] ) || ! is_array( $field['layouts'] ) ) {
		return;
	}

	foreach ( $field['layouts'] as $layout ) {
		if ( empty( $layout['name'] ) ) {
			continue;
		}

		// Sanitise the layout name to produce safe file / class names.
		$layout_slug = sanitize_title( $layout['name'] );

		// Replace hyphens with underscores so CSS class names stay valid.
		$layout_slug = str_replace( '-', '_', $layout_slug );

		ffg_maybe_create_php_template( $layout_slug );
		ffg_maybe_create_css_stub( $layout_slug );
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. FILE GENERATORS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Creates the PHP template part for a layout if it does not yet exist.
 *
 * Destination: {theme}/template-parts/flexible/flex-{layout_slug}.php
 *
 * @param string $layout_slug Sanitised layout name (underscores, lowercase).
 */
function ffg_maybe_create_php_template( string $layout_slug ) {
	$dir  = ffg_theme_path( 'template-parts/flexible' );
	$file = $dir . '/flex-' . $layout_slug . '.php';

	// Ensure the directory exists.
	if ( ! wp_mkdir_p( $dir ) ) {
		return; // Directory creation failed — bail silently.
	}

	// Never overwrite an existing file.
	if ( file_exists( $file ) ) {
		return;
	}

	$content = ffg_php_template_content( $layout_slug );

	// Write the file; suppress errors to avoid white-screens on permission issues.
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
	if ( false !== @file_put_contents( $file, $content ) ) {
		// 0664 → owner + group can read/write; world can read.
		// Ensures the file is editable in local dev environments.
		chmod( $file, 0777 );
	}
}

/**
 * Creates the CSS stub for a layout if it does not yet exist.
 *
 * Destination: {theme}/assets/css/{layout_slug}.css
 *
 * @param string $layout_slug Sanitised layout name (underscores, lowercase).
 */
function ffg_maybe_create_css_stub( string $layout_slug ) {
	$dir  = ffg_theme_path( 'assets/css' );
	$file = $dir . '/' . $layout_slug . '.css';

	// Ensure the directory exists.
	if ( ! wp_mkdir_p( $dir ) ) {
		return;
	}

	// Never overwrite an existing file.
	if ( file_exists( $file ) ) {
		return;
	}

	$content = ffg_css_stub_content( $layout_slug );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
	if ( false !== @file_put_contents( $file, $content ) ) {
		// 0664 → owner + group can read/write; world can read.
		chmod( $file, 0777 );
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. FILE CONTENT TEMPLATES
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Returns the boilerplate PHP template string for a given layout.
 *
 * @param  string $layout_slug Sanitised layout name.
 * @return string              PHP file content.
 */
function ffg_php_template_content( string $layout_slug ): string {
	return <<<PHP
<?php
/**
 * Flexible Layout: {$layout_slug}
 *
 * Called via get_template_part() or directly inside a flexible content loop.
 * Available variable: \$layout (array) — the current ACF layout row.
 *
 * @package YourTheme
 */
?>
<section class="{$layout_slug}">
	<div class="container">
		<h1>Flexible Content Layout: {$layout_slug}</h1>
	</div>
</section>
PHP;
}

/**
 * Returns the boilerplate CSS stub for a given layout.
 *
 * @param  string $layout_slug Sanitised layout name.
 * @return string              CSS file content.
 */
function ffg_css_stub_content( string $layout_slug ): string {
	return <<<CSS
/* Layout: {$layout_slug}
   Generated by Flex File Generator — add your styles below.
   ─────────────────────────────────────────── */

.{$layout_slug} {

}

.{$layout_slug} .container {

}
CSS;
}

// ─────────────────────────────────────────────────────────────────────────────
// 6. ENQUEUE FLEX CSS ON THE FRONT END
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'wp_enqueue_scripts', 'ffg_enqueue_flex_styles' );

/**
 * On every front-end page load, reads the 'page_builder' flexible content
 * meta value, then conditionally enqueues the CSS file for each layout
 * that has a matching file in the theme's assets/css/ folder.
 *
 * Self-contained and prefixed — does not interfere with any other
 * theme or plugin enqueue logic.
 */
function ffg_enqueue_flex_styles() {
	// Only run on singular posts/pages.
	if ( ! is_singular() ) {
		return;
	}

	$flex_modules = get_post_meta( get_the_ID(), 'page_builder', true );

	if ( empty( $flex_modules ) || ! is_array( $flex_modules ) ) {
		return;
	}

	foreach ( $flex_modules as $element ) {
		if ( empty( $element ) || ! is_string( $element ) ) {
			continue;
		}

		// Absolute path used for file_exists() check — no HTTP request needed.
		$file_abs = ABSPATH . 'wp-content/themes/touch/assets/css/' . $element . '.css';

		// Only enqueue if the CSS file was actually generated.
		if ( ! file_exists( $file_abs ) ) {
			continue;
		}

		// Handle uses hyphens (WP convention); file name retains underscores.
		$handle   = 'ffg-' . str_replace( '_', '-', $element );
		$file_url = get_template_directory_uri() . '/assets/css/' . $element . '.css';

		// filemtime() as version busts cache automatically on file change.
		wp_enqueue_style( $handle, $file_url, array(), filemtime( $file_abs ), 'all' );
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// 7. UTILITY
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Returns the absolute path to a subdirectory inside the active theme,
 * with no trailing slash.
 *
 * @param  string $subdir Relative subdirectory, e.g. 'template-parts/flexible'.
 * @return string         Absolute filesystem path.
 */
function ffg_theme_path( string $subdir ): string {
	return rtrim( get_stylesheet_directory(), '/' ) . '/' . ltrim( $subdir, '/' );
}
