<?php
defined( 'ABSPATH' ) || exit;

wpme_register_field_type( 'projecttags', [
    'label' => 'Project Tags',

    'render_cell' => function ( string $key, string $raw ): string {
        $k = esc_attr( $key );

        $all_tags = [
            'Sustainable Materials',
            'Smart Textiles',
            'Circular Textiles',
            'Advanced Materials',
        ];

        // Parse currently selected tags from JSON
        $selected = [];
        if ( $raw ) {
            $decoded = json_decode( urldecode($raw), true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                foreach ( $decoded as $item ) {
                    if ( isset( $item['one-tag'] ) ) {
                        $selected[] = $item['one-tag'];
                    }
                }
            }
        }

        $html  = '<div class="wpme-projecttags-cell" data-key="' . $k . '">';

        foreach ( $all_tags as $tag ) {
            $checked = in_array( $tag, $selected, true ) ? ' checked' : '';
            $html   .= '<label class="wpme-projecttag-label">'
                . '<input type="checkbox" class="wpme-projecttag-cb" value="' . esc_attr( $tag ) . '"' . $checked . '>'
                . ' <span>' . esc_html( $tag )
                . '</span></label>';
        }

        // Hidden input holds the JSON — updated by JS on checkbox change
        $html .= '<input type="hidden"'
            . ' class="wpme-field wpme-projecttags-value"'
            . ' data-key="' . $k . '"'
            . ' value="' . esc_attr( $raw ) . '">';

        $html .= '</div>';
        return $html;
    },
] );