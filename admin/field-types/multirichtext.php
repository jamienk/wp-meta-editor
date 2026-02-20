<?php
defined( 'ABSPATH' ) || exit;

wpme_register_field_type( 'multirichtext', [
    'label' => 'Multi Rich Text',

    'enqueue' => function (): void {
        // Relies on richtext.php having already enqueued wp_editor + the shared modal.
        // If richtext type is not registered, do it ourselves.
        wp_enqueue_editor();
        wp_enqueue_script( 'editor' );
    },

    'render_cell' => function ( string $key, string $raw ): string {
        $k = esc_attr( $key );

        $items = [];
        if ( $raw ) {
            $decoded = json_decode( urldecode( $raw ), true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                $items = $decoded;
            }
        }

        $html  = '<div class="wpme-multirichtext-cell" data-key="' . $k . '">';
        $html .= '<div class="wpme-multirichtext-items">';

        foreach ( $items as $i => $item ) {
            $html .= wpme_multirichtext_item_html(
                $item['output-name']   ?? '',
                $item['output-text']   ?? '',
                (int) ( $item['output-number'] ?? 0 )
            );
        }

        $html .= '</div>'; // .wpme-multirichtext-items

        // Hidden input — holds the serialized JSON for the save loop
        $html .= '<input type="hidden"'
            . ' class="wpme-field wpme-multirichtext-value"'
            . ' data-key="' . $k . '"'
            . ' value="' . esc_attr( $raw ) . '">';

        $html .= '<button type="button" class="button button-small wpme-multirichtext-add" style="margin-top:6px;">+ Add Item</button>';
        $html .= '</div>'; // .wpme-multirichtext-cell

        return $html;
    },
] );

/**
 * Render a single multi-richtext item card.
 * Called from PHP on load, and echoed as a JS template string in admin.js.
 */
function wpme_multirichtext_item_html( string $name, string $text, int $number ): string {
    $preview = wp_strip_all_tags( $text );
    $preview = mb_strlen( $preview ) > 60 ? mb_substr( $preview, 0, 60 ) . '…' : ( $preview ?: '(empty)' );

    return
        '<div class="wpme-multirichtext-item">' .
            '<div class="wpme-multirichtext-item-header">' .
                '<input type="text"' .
                    ' class="wpme-multirichtext-name"' .
                    ' placeholder="Name (e.g. Patents)"' .
                    ' value="' . esc_attr( $name ) . '">' .
                '<div class="wpme-multirichtext-item-body">' .
					'<span class="wpme-richtext-preview">' . esc_html( $preview ) . '</span>' .
					'<textarea class="wpme-multirichtext-text" style="display:none;">' . esc_textarea( $text ) . '</textarea>' .
					'<button type="button" class="button button-small wpme-multirichtext-open" style="margin-top:4px;">Edit&hellip;</button>' .
				'</div>' .
                '<input type="number"' .
                    ' class="wpme-multirichtext-number"' .
                    ' placeholder="#"' .
                    ' value="' . esc_attr( (string) $number ) . '"' .
                    ' style="width:60px;">' .
                '<button type="button" class="button button-small wpme-multirichtext-delete">✕</button>' .
            '</div>' .
            
        '</div>';
}