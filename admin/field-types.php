<?php
defined( 'ABSPATH' ) || exit;

// ── Registry ──────────────────────────────────────────────────────────────────

$_wpme_field_types = [];

/**
 * Register a field type.
 *
 * @param string $id   Unique slug, e.g. 'text', 'image'
 * @param array  $def {
 *   string   $label       Display name shown in dropdown
 *   callable $render_cell fn( $key, $raw ) → HTML string for the table cell
 *   callable $enqueue     Optional. Called once on admin_enqueue_scripts.
 * }
 */
function wpme_register_field_type( string $id, array $def ): void {
    global $_wpme_field_types;
    $_wpme_field_types[ $id ] = $def;
}

/** Return all registered types. */
function wpme_get_field_types(): array {
    global $_wpme_field_types;
    return $_wpme_field_types;
}

/** Return a single type def, falling back to 'text'. */
function wpme_get_field_type( string $id ): array {
    global $_wpme_field_types;
    return $_wpme_field_types[ $id ] ?? $_wpme_field_types['text'] ?? [];
}

/** Render a table cell for the given key, raw value, and type id. */
function wpme_render_cell( string $key, string $raw, string $type_id ): string {
    $def = wpme_get_field_type( $type_id );
    if ( empty( $def['render_cell'] ) ) return esc_html( $raw );
    return call_user_func( $def['render_cell'], $key, $raw );
}

/** Call enqueue() on every registered type that has one. */
function wpme_enqueue_field_types(): void {
    foreach ( wpme_get_field_types() as $def ) {
        if ( ! empty( $def['enqueue'] ) ) {
            call_user_func( $def['enqueue'] );
        }
    }
}

// ── Normalise config shape ────────────────────────────────────────────────────
// Old shape: wpme_configs[ post_type ] = [ 'key1', 'key2' ]  (flat array)
// New shape: wpme_configs[ post_type ] = [ 'keys' => [...], 'field_types' => [...] ]

function wpme_normalize_config( $raw_config ): array {
    if ( ! is_array( $raw_config ) ) return [ 'keys' => [], 'field_types' => [] ];

    // Already new shape
    if ( isset( $raw_config['keys'] ) ) {
        return array_merge( [ 'keys' => [], 'field_types' => [] ], $raw_config );
    }

    // Old flat shape — migrate silently, default everything to 'text'
    $keys = array_values( array_filter( $raw_config, 'is_string' ) );
    return [
        'keys'        => $keys,
        'field_types' => array_fill_keys( $keys, 'text' ),
    ];
}

function wpme_get_all_configs(): array {
    $raw = get_option( 'wpme_configs', [] );
    $out = [];
    foreach ( $raw as $pt => $cfg ) {
        $out[ $pt ] = wpme_normalize_config( $cfg );
    }
    return $out;
}

// ── Load built-in types ───────────────────────────────────────────────────────

require_once __DIR__ . '/field-types/text.php';
require_once __DIR__ . '/field-types/image.php';
require_once __DIR__ . '/field-types/richtext.php';
require_once __DIR__ . '/field-types/projecttags.php';
require_once __DIR__ . '/field-types/projectlead.php';
require_once __DIR__ . '/field-types/multirichtext.php';
require_once __DIR__ . '/field-types/progress.php';
require_once __DIR__ . '/field-types/status.php';

