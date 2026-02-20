<?php
defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_wpme_save_meta', function () {
    check_ajax_referer( 'wpme_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied.', 403 );
    }

    $post_id = intval( $_POST['post_id'] ?? 0 );

    if ( ! $post_id || ! get_post( $post_id ) ) {
        wp_send_json_error( 'Invalid post ID.' );
    }

    $post_type   = get_post_type( $post_id );
    $all_configs = get_option( 'wpme_configs', [] );
    $all_configs = wpme_get_all_configs();
	$config = $all_configs[ $post_type ] ?? [ 'keys' => [], 'field_types' => [] ];
	$allowed = $config['keys'];

    $updated_post   = [];
    $updated_meta   = [];
    $skipped        = [];

    // ── Core post fields ─────────────────────────────────────────────────────

    $post_data = [ 'ID' => $post_id ];
    $dirty     = false;

    if ( isset( $_POST['post_title'] ) ) {
        $post_data['post_title'] = sanitize_text_field( wp_unslash( $_POST['post_title'] ) );
        $dirty = true;
        $updated_post[] = 'post_title';
    }

    if ( isset( $_POST['post_status'] ) ) {
        $allowed_statuses = [ 'publish', 'draft' ];
        $status = sanitize_key( $_POST['post_status'] );
        if ( in_array( $status, $allowed_statuses, true ) ) {
            $post_data['post_status'] = $status;
            $dirty = true;
            $updated_post[] = 'post_status';
        }
    }

    if ( $dirty ) {
        $result = wp_update_post( $post_data, true );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( 'Failed to update post: ' . $result->get_error_message() );
        }
    }

    // ── Meta fields ───────────────────────────────────────────────────────────

    $fields = $_POST['fields'] ?? [];

    if ( is_array( $fields ) ) {
        foreach ( $fields as $key => $value ) {
            $key = sanitize_text_field( $key );

            if ( ! in_array( $key, $allowed, true ) ) {
                $skipped[] = $key;
                continue;
            }

            // Value arrives as the human-readable (decoded) string from JS.
            // If the original was URL-encoded, JS re-encoded it before sending —
            // so we just store whatever arrives here directly.
            $value     = wp_unslash( $value );
			$type      = $config['field_types'][ $key ] ?? 'text';
			$value     = in_array( $type, [ 'richtext', 'multirichtext' ], true )
				? wp_kses_post( $value )
				: sanitize_textarea_field( $value );
			update_post_meta( $post_id, $key, $value );
            $updated_meta[] = $key;
        }
        if ( ! empty( $updated_meta ) ) {
			wpme_sync_lazyblock( $post_id, array_combine(
				$updated_meta,
				array_map( fn( $k ) => get_post_meta( $post_id, $k, true ), $updated_meta )
			) );
		}
    }

    wp_send_json_success( [
        'post_id'      => $post_id,
        'updated_post' => $updated_post,
        'updated_meta' => $updated_meta,
        'skipped'      => $skipped,
    ] );
} );



add_action( 'wp_ajax_wpme_insert_post', function () {
    check_ajax_referer( 'wpme_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied.', 403 );
    }

    $post_type = sanitize_key( $_POST['post_type'] ?? '' );
    $pt_obj    = get_post_type_object( $post_type );
    if ( ! $pt_obj ) {
        wp_send_json_error( 'Invalid post type.' );
    }

    $title   = 'New ' . $pt_obj->labels->singular_name;
    $post_id = wp_insert_post( [
        'post_type'   => $post_type,
        'post_status' => 'draft',
        'post_title'  => $title,
    ] );

    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( $post_id->get_error_message() );
    }

    // Return everything the JS needs to build the new row
    wp_send_json_success( [
        'post_id'   => $post_id,
        'title'     => $title,
        'edit_url'  => get_edit_post_link( $post_id, 'raw' ),
    ] );
} );



add_action( 'wp_ajax_wpme_delete_post', function () {
    check_ajax_referer( 'wpme_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied.', 403 );
    }
    $post_id = intval( $_POST['post_id'] ?? 0 );
    if ( ! $post_id ) wp_send_json_error( 'Invalid post ID.' );
    
    wp_trash_post( $post_id );
    wp_send_json_success( [ 'post_id' => $post_id ] );
} );




add_action( 'wp_ajax_wpme_get_image_sizes', function () {
    check_ajax_referer( 'wpme_nonce', 'nonce' );
    $id   = intval( $_POST['id'] ?? 0 );
    $meta = wp_get_attachment_metadata( $id );
    $base_url = dirname( wp_get_attachment_url( $id ) ) . '/';
    $att  = get_post( $id );

    $sizes = [];
    foreach ( $meta['sizes'] as $size_name => $size_data ) {
        $sizes[ $size_name ] = [
            'url'         => $base_url . $size_data['file'],
            'width'       => $size_data['width'],
            'height'      => $size_data['height'],
            'orientation' => $size_data['height'] > $size_data['width'] ? 'portrait' : 'landscape',
        ];
    }
    // Add full size
    $sizes['full'] = [
        'url'         => wp_get_attachment_url( $id ),
        'width'       => $meta['width'],
        'height'      => $meta['height'],
        'orientation' => $meta['height'] > $meta['width'] ? 'portrait' : 'landscape',
    ];

    wp_send_json_success( [
        'sizes'       => $sizes,
        'alt'         => get_post_meta( $id, '_wp_attachment_image_alt', true ),
        'title'       => $att->post_title ?? '',
        'caption'     => $att->post_excerpt ?? '',
        'description' => $att->post_content ?? '',
        'url'         => wp_get_attachment_url( $id ),
        'link'        => get_permalink( $id ),
    ] );
} );