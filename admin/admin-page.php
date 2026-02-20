<?php
defined( 'ABSPATH' ) || exit;

// ── Save config ───────────────────────────────────────────────────────────────
if ( isset( $_POST['wpme_save_config'] ) && check_admin_referer( 'wpme_save_config' ) ) {
    $post_type   = sanitize_key( $_POST['wpme_post_type'] ?? '' );
    
    $ordered  = array_map( 'sanitize_text_field', (array) ( $_POST['wpme_key_order']  ?? [] ) );
	$checked  = array_map( 'sanitize_text_field', (array) ( $_POST['wpme_meta_keys']  ?? [] ) );
	$chosen_keys = array_values( array_filter( $ordered, fn( $k ) => in_array( $k, $checked, true ) ) );
    //$chosen_keys = array_map( 'sanitize_text_field', (array) ( $_POST['wpme_meta_keys'] ?? [] ) );

    $raw_types   = (array) ( $_POST['wpme_field_types'] ?? [] );
    $valid_types = array_keys( wpme_get_field_types() );
    $field_types = [];
    foreach ( $chosen_keys as $k ) {
        $t = sanitize_key( $raw_types[ $k ] ?? 'text' );
        $field_types[ $k ] = in_array( $t, $valid_types, true ) ? $t : 'text';
    }

    $all_configs = wpme_get_all_configs();
    $all_configs[ $post_type ] = [
        'keys'        => $chosen_keys,
        'field_types' => $field_types,
    ];
    update_option( 'wpme_configs', $all_configs );

    echo '<div class="notice notice-success"><p>Configuration saved.</p></div>';
}

// ── Data ──────────────────────────────────────────────────────────────────────
$all_configs = wpme_get_all_configs();
$field_types = wpme_get_field_types();

//$post_types = get_post_types( [], 'objects' );
// only for projects and project leads for now...
$post_types = array_filter(
    get_post_types( [], 'objects' ),
    fn( $pt ) => in_array( $pt->name, [ 'project_leads', 'projects' ], true )
);
$exclude    = [ 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation' ];
$post_types = array_filter( $post_types, fn( $pt ) => ! in_array( $pt->name, $exclude ) );

$selected_pt  = sanitize_key( $_GET['wpme_configure'] ?? array_key_first( $post_types ) ?? '' );
$show_private = isset( $_GET['wpme_show_private'] );

$meta_keys = [];
if ( $selected_pt ) {
    global $wpdb;
    $meta_keys = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT pm.meta_key
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE p.post_type = %s
         ORDER BY pm.meta_key ASC",
        $selected_pt
    ) );
    if ( ! $show_private ) {
        $meta_keys = array_values( array_filter( $meta_keys, fn( $k ) => $k[0] !== '_' ) );
    }
}

$saved_cfg   = $all_configs[ $selected_pt ] ?? [ 'keys' => [], 'field_types' => [] ];
$saved_keys  = $saved_cfg['keys'];
$saved_types = $saved_cfg['field_types'];

// Show saved keys first in their saved order, then any remaining keys alphabetically
$meta_keys = array_merge(
    array_filter( $saved_keys, fn( $k ) => in_array( $k, $meta_keys, true ) ),
    array_filter( $meta_keys, fn( $k ) => ! in_array( $k, $saved_keys, true ) )
);
$meta_keys = array_values( $meta_keys );
?>

<div class="wrap wpme-wrap">
    <h1>Configuration</h1>

    <?php if ( ! empty( $all_configs ) ) : ?>
        <div class="wpme-saved-configs">
            
            <ul>
            <?php foreach ( $all_configs as $pt => $cfg ) :
                $label = $post_types[ $pt ]->label ?? $pt;
                $url   = add_query_arg( [ 'page' => 'wp-meta-editor', 'wpme_tab' => 'table', 'post_type' => $pt ], admin_url( 'admin.php' ) );
                $summary = [];
                foreach ( $cfg['keys'] as $k ) {
                    $t = $cfg['field_types'][ $k ] ?? 'text';
                    $summary[] = $k . ' <em>(' . esc_html( $field_types[ $t ]['label'] ?? $t ) . ')</em>';
                }
            ?>
                <li>
                    <strong><?php echo esc_html( $label ); ?></strong> 
                    <!-- &mdash;
                    <?php echo implode( ', ', $summary ); ?>
                    &nbsp;
                    -->
                    <a href="<?php echo esc_url( $url ); ?>" class="button button-small">Open Table &rarr;</a>
                </li>
            <?php endforeach; ?>
            </ul>
        </div>
        <hr>
    <?php endif; ?>

    <div class="wpme-config-layout">

        <div class="wpme-pt-list">
            <!-- <h3>Post Types</h3> -->
            <ul>
            <?php foreach ( $post_types as $pt ) :
                $active = $pt->name === $selected_pt;
                $url    = add_query_arg( [ 'page' => 'wp-meta-editor', 'wpme_configure' => $pt->name ], admin_url( 'admin.php' ) );
            ?>
                <li>
                    <a href="<?php echo esc_url( $url ); ?>" class="<?php echo $active ? 'wpme-active-pt' : ''; ?>">
                        <?php echo esc_html( $pt->label ); ?>
                        <!-- <span class="wpme-pt-slug"><?php echo esc_html( $pt->name ); ?></span> -->
                    </a>
                </li>
            <?php endforeach; ?>
            </ul>
        </div>

        <div class="wpme-key-config">
            <?php if ( $selected_pt ) : ?>
                <h3>Fields for: <strong><?php echo esc_html( $post_types[ $selected_pt ]->label ?? $selected_pt ); ?></strong></h3>

                <!-- 
				<p>
                    <a href="<?php echo esc_url( add_query_arg( [ 'wpme_show_private' => $show_private ? null : '1' ] ) ); ?>">
                        <?php echo $show_private ? '&#x1F441; Hide private fields' : '&#x1F441; Show private fields (underscore-prefixed)'; ?>
                    </a>
                </p>
 				-->

                <?php if ( empty( $meta_keys ) ) : ?>
                    <p><em>No meta keys found for this post type<?php echo $show_private ? '' : ' (public keys only)'; ?>.</em></p>
                <?php else : ?>
                <form method="post">
                    <?php wp_nonce_field( 'wpme_save_config' ); ?>
                    <input type="hidden" name="wpme_save_config" value="1">
                    <input type="hidden" name="wpme_post_type" value="<?php echo esc_attr( $selected_pt ); ?>">

                    <table class="wpme-config-table widefat">
                        <thead>
                            <tr>
                            	<th> </td>
                                <th style="width:32px;"></th>
                                <th>Field</th>
                                <th style="width:160px;">Field Type</th>
                            </tr>
                        </thead>
                        <tbody id="wpme-config-tbody">
                        <?php foreach ( $meta_keys as $key ) :
                            $checked      = in_array( $key, $saved_keys, true );
                            $current_type = $saved_types[ $key ] ?? 'text';
                        ?>
                            <tr class="wpme-config-row <?php echo $checked ? 'wpme-config-checked' : ''; ?>">
                            	<td class="wpme-drag-handle" title="Drag to reorder">
                            		⠿
                            		 <input type="hidden" name="wpme_key_order[]" value="<?php echo esc_attr( $key ); ?>">
                            	</td>
                                <td>
                                    <input type="checkbox"
                                           name="wpme_meta_keys[]"
                                           value="<?php echo esc_attr( $key ); ?>"
                                           class="wpme-key-checkbox"
                                           <?php checked( $checked ); ?>>
                                </td>
                                <td>
                                    <code><?php echo esc_html( $key ); ?></code>
                                </td>
                                <td>
                                    <select name="wpme_field_types[<?php echo esc_attr( $key ); ?>]"
                                            class="wpme-type-select"
                                            <?php echo ! $checked ? 'disabled' : ''; ?>>
                                        <?php foreach ( $field_types as $type_id => $type_def ) : ?>
                                            <option value="<?php echo esc_attr( $type_id ); ?>"
                                                <?php selected( $current_type, $type_id ); ?>>
                                                <?php echo esc_html( $type_def['label'] ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">Save Configuration</button>
                        <?php if ( ! empty( $saved_keys ) ) :
                            $table_url = add_query_arg( [ 'page' => 'wp-meta-editor', 'wpme_tab' => 'table', 'post_type' => $selected_pt ], admin_url( 'admin.php' ) );
                        ?>
                            <a href="<?php echo esc_url( $table_url ); ?>" class="button button-secondary" style="margin-left:8px;">Open Table &rarr;</a>
                        <?php endif; ?>
                    </p>
                </form>

                <script>
                // Enable/disable the type dropdown based on checkbox state
                document.querySelectorAll('.wpme-key-checkbox').forEach(function(cb) {
                    cb.addEventListener('change', function() {
                        const row    = cb.closest('.wpme-config-row');
                        const select = row.querySelector('.wpme-type-select');
                        select.disabled = ! cb.checked;
                        row.classList.toggle('wpme-config-checked', cb.checked);
                    });
                });
                </script>

                <?php endif; ?>
            <?php else : ?>
                <p>Select a post type on the left.</p>
            <?php endif; ?>
        </div>

    </div>
</div>
