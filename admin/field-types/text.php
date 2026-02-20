<?php
defined( 'ABSPATH' ) || exit;

wpme_register_field_type( 'text', [
    'label' => 'Text',

    'render_cell' => function ( string $key, string $raw ): string {
        // URL-decode detection happens in JS (data-raw attribute).
        // We output the raw value in data-raw; textarea starts empty and JS fills it.
        $k = esc_attr( $key );
        $r = esc_attr( $raw );
        $v = esc_textarea( $raw );
        return
            '<textarea' .
            ' class="wpme-field wpme-meta-field"' .
            ' data-key="'  . $k . '"' .
            ' data-raw="'  . $r . '"' .
            ' rows="2"' .
            '>' . $v . '</textarea>';
    },
] );
