<?php
defined( 'ABSPATH' ) || exit;

wpme_register_field_type( 'projectlead', [
    'label' => 'Project Lead',

    'render_cell' => function ( string $key, string $raw ): string {
        $k = esc_attr( $key );

        // Parse existing value to get selected post_id
        $selected_id = '';
        if ( $raw ) {
            $decoded = json_decode( $raw, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $selected_id = (string) ( $decoded['post_id'] ?? '' );
            }
        }

        // Fetch all project_lead posts
        $leads = get_posts( [
            'post_type'      => 'project_leads',
            'post_status'    => [ 'publish', 'draft' ],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        $select  = '<select'
            . ' class="wpme-field wpme-projectlead-select"'
            . ' data-key="' . $k . '"'
            . ' data-posttype="project_leads"'
            . '>';
        $select .= '<option value="">— None —</option>';

        foreach ( $leads as $lead ) {
            $json     = wp_json_encode( [
                'post_type' => 'project_leads',
                'post_id'   => (string) $lead->ID,
            ] );
            $selected = selected( (string) $lead->ID, $selected_id, false );
            $select  .= '<option value="' . esc_attr( $json ) . '" ' . $selected . '>'
                . esc_html( $lead->post_title ?: '(no title)' ) 
                . '</option>';
        }

        $select .= '</select>';
        return $select;
    },
] );