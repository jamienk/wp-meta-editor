<?php
defined( 'ABSPATH' ) || exit;

wpme_register_field_type( 'image', [
    'label' => 'Image',

    'enqueue' => function (): void {
        wp_enqueue_media(); // loads wp.media uploader
    },

    'render_cell' => function ( string $key, string $raw ): string {
        $k = esc_attr( $key );

        // Try to pull thumbnail URL from the stored JSON blob
        $thumb = '';
        if ( $raw ) {
            $decoded = json_decode( $raw, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $thumb = $decoded['sizes']['thumbnail']['url']
                      ?? $decoded['sizes']['medium']['url']
                      ?? $decoded['url']
                      ?? '';
            }
        }

        $thumb_html = $thumb
            ? '<img src="' . esc_url( $thumb ) . '" class="wpme-img-preview" alt="">'
            : '<span class="wpme-img-placeholder">No image</span>';

        // Hidden input carries the JSON value — picked up by the save loop
        return
            '<div class="wpme-image-cell" data-key="' . $k . '">' .
                '<div class="wpme-img-thumb">' . $thumb_html . '</div>' .
                '<input type="hidden"' .
                    ' class="wpme-field wpme-image-value"' .
                    ' data-key="' . $k . '"' .
                    ' value="' . esc_attr( $raw ) . '">' .
                '<div class="wpme-img-actions">' .
                    '<button type="button" class="button button-small wpme-img-choose">Choose Image</button>' .
                    ( $raw ? ' <button type="button" class="button button-small wpme-img-remove">Remove</button>' : '' ) .
                '</div>' .
            '</div>';
    },
] );
