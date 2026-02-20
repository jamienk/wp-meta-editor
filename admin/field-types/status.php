<?php
defined( 'ABSPATH' ) || exit;

wpme_register_field_type( 'status', [
    'label' => 'Status',

    'render_cell' => function ( string $key, string $raw ): string {
        $k = esc_attr( $key );

		$statusterms = ['In Progress', 'Market Ready'];
				
        $select  = '<select'
            . ' class="wpme-field wpme-projectlead-select"'
            . ' data-key="' . $k . '"'
            . '>';
        $select .= '<option value="">— None —</option>';

        foreach ( $statusterms as $sterm) {
            
            $selected = selected( (string) $sterm, $raw, false );
            $select  .= '<option value="' . esc_attr( $sterm ) . '" ' . $selected . '>'
                . esc_html( $sterm ?: '' ) 
                . '</option>';
        }

        $select .= '</select>';
        return $select;
    },
] );