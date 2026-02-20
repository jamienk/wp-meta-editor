<?php
defined( 'ABSPATH' ) || exit;

wpme_register_field_type( 'progress', [
    'label' => 'Progress',

    'render_cell' => function ( string $key, string $raw ): string {
        $k = esc_attr( $key );

		$progressterms = ['Initiation', 'Development', 'Validation', 'Complete'];
				
        $select  = '<select'
            . ' class="wpme-field wpme-projectlead-select"'
            . ' data-key="' . $k . '"'
            . '>';
        $select .= '<option value="">— None —</option>';

        foreach ( $progressterms as $pterm) {
            
            $selected = selected( (string) $pterm, $raw, false );
            $select  .= '<option value="' . esc_attr( $pterm ) . '" ' . $selected . '>'
                . esc_html( $pterm ?: '' ) 
                . '</option>';
        }

        $select .= '</select>';
        return $select;
    },
] );