<?php
defined( 'ABSPATH' ) || exit;

/**
 * Sync updated meta field values back into the LazyBlocks block comment
 * inside post_content, so LazyBlocks doesn't overwrite our meta on next save.
 *
 * LazyBlocks stores block data as:
 *   <!-- wp:lazyblock/block-name {"field1":"val1","field2":"val2"} -->
 *   <!-- /wp:lazyblock/block-name -->
 *
 * Post type name → block name: underscores become hyphens.
 *   project_lead → lazyblock/project-lead
 *   projects      → lazyblock/projects
 *
 * @param int   $post_id  The post being saved.
 * @param array $fields   Associative array of meta_key => new_value (already saved to postmeta).
 */
function wpme_sync_lazyblock( int $post_id, array $fields ): void {
    //update_option( 'wpme_debug_content', 'sync called for ' . $post_id );
    
    if ( empty( $fields ) ) return;

    $post = get_post( $post_id );
    if ( ! $post ) return;


	$block_map = [
		'projects'      => 'lazyblock/project',
		'project_leads' => 'lazyblock/project-lead',
	];
	
	$block_name = $block_map[ $post->post_type ] ?? 'lazyblock/' . str_replace( '_', '-', $post->post_type );
    //$block_name = 'lazyblock/' . str_replace( '_', '-', $post->post_type );

    $content = $post->post_content;
    
    if ( empty( $content ) ) {
		// Generate blockId the same way LazyBlocks does — 6 random alphanumeric chars
		$block_id       = substr( str_shuffle( 'abcdefghijklmnopqrstuvwxyz0123456789' ), 0, 6 );
		$unique_class   = $block_name . '-' . $block_id;
		$unique_class   = str_replace( 'lazyblock/', 'lazyblock-', $unique_class );
		$attrs          = array_merge( [
			'blockId'          => $block_id,
			'blockUniqueClass' => $unique_class,
		], $fields );
		$json    = wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$content = '<!-- wp:' . $block_name . ' ' . $json . ' /-->';
		wp_update_post( [ 'ID' => $post_id, 'post_content' => $content ] );
		return;
	}

    // Match the opening block comment, e.g.:
    //   <!-- wp:lazyblock/project-lead {"foo":"bar"} -->
    // or with no attributes:
    //   <!-- wp:lazyblock/project-lead -->
    $escaped = preg_quote( $block_name, '/' );
    $pattern = '/<!--\s*wp:' . $escaped . '(\s+(\{.*?\}))?\s*\/-->/s';

    if ( ! preg_match( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) return;

    // Decode existing attributes (may be empty)
    $existing_json  = isset( $matches[2][0] ) ? $matches[2][0] : '';
    $existing_attrs = [];
    if ( $existing_json !== '' ) {
        $decoded = json_decode( $existing_json, true );
        if ( json_last_error() === JSON_ERROR_NONE ) {
            $existing_attrs = $decoded;
        }
    }

    // Merge: our updated fields win over existing attrs.
    // Cast numeric-looking strings carefully — LazyBlocks stores image IDs as ints.
    $merged = $existing_attrs;
    foreach ( $fields as $key => $value ) {
        // If the existing value for this key was an int/float, preserve that type.
        if ( isset( $existing_attrs[ $key ] ) && is_int( $existing_attrs[ $key ] ) && ctype_digit( (string) $value ) ) {
            $merged[ $key ] = (int) $value;
        } elseif ( isset( $existing_attrs[ $key ] ) && is_float( $existing_attrs[ $key ] ) && is_numeric( $value ) ) {
            $merged[ $key ] = (float) $value;
        } else {
            $merged[ $key ] = $value;
        }
    }

	// Preserve LazyBlocks internal attrs — never overwrite these
	foreach ( [ 'blockId', 'blockUniqueClass' ] as $lb_key ) {
		if ( isset( $existing_attrs[ $lb_key ] ) ) {
			$merged[ $lb_key ] = $existing_attrs[ $lb_key ];
		}
	}

    $new_json    = wp_json_encode( $merged, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    $new_comment = '<!-- wp:' . $block_name . ' ' . $new_json . ' /-->';

    // Replace the old opening comment with the new one
    $full_match  = $matches[0][0]; // the full matched string
    $content     = str_replace( $full_match, $new_comment, $content );


    wp_update_post( [
        'ID'           => $post_id,
        'post_content' => $content,
    ] );
}

/**
 * Build a full LazyBlocks block comment from scratch when post_content is empty.
 */
function wpme_build_lazyblock_comment( string $block_name, array $fields ): string {
    $json = wp_json_encode( $fields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    return '<!-- wp:' . $block_name . ' ' . $json . ' -->' . "\n" . '<!-- /wp:' . $block_name . ' -->';
}
