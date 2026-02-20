<?php
defined( 'ABSPATH' ) || exit;

$post_type   = sanitize_key( $_GET['post_type'] ?? '' );
$all_configs = wpme_get_all_configs();
$config      = $all_configs[ $post_type ] ?? [ 'keys' => [], 'field_types' => [] ];
$meta_keys   = $config['keys'];
$field_types = $config['field_types'];

$config_url = admin_url( 'admin.php?page=wp-meta-editor' );

if ( ! $post_type ) {
    echo '<div class="wrap"><p>No post type specified. <a href="' . esc_url( $config_url ) . '">Go to configuration.</a></p></div>';
    return;
}

if ( empty( $meta_keys ) ) {
    echo '<div class="wrap"><p>No meta fields configured for <strong>' . esc_html( $post_type ) . '</strong>. <a href="' . esc_url( $config_url ) . '">Configure now.</a></p></div>';
    return;
}

$posts = get_posts( [
    'post_type'      => $post_type,
    'post_status'    => [ 'publish', 'draft' ],
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
] );

$pt_obj   = get_post_type_object( $post_type );
$pt_label = $pt_obj->label ?? $post_type;
?>

<div class="wrap wpme-wrap">
    <h1>
        Meta Editor &mdash; <?php echo esc_html( $pt_label ); ?>
        <a href="<?php echo esc_url( $config_url ); ?>" class="page-title-action">&#8592; Config</a>
    </h1>

	<button class="button button-primary wpme-new-post-btn" data-post-type="<?php echo esc_attr( $post_type ); ?>">
		+ New <?php echo esc_html( $pt_obj->labels->singular_name ); ?>
	</button>



    <p class="wpme-table-info">
        Showing <strong><?php echo count( $posts ); ?></strong> post(s) &mdash;
        meta fields: <em><?php echo esc_html( implode( ', ', $meta_keys ) ); ?></em>
    </p>

    <?php if ( empty( $posts ) ) : ?>
        <p>No published or draft posts found for this post type.</p>
    <?php else : ?>

	<div class='mpme-wrapper'>
    <table class="wpme-table widefat" data-post-type="<?php echo esc_attr( $post_type ); ?>">
        <thead>
            <tr>
                <th class="wpme-col-id">ID</th>
                <th class="wpme-col-title">Title</th>
                <th class="wpme-col-status">Status</th>
                <?php foreach ( $meta_keys as $key ) : ?>
                    <th><?php echo esc_html( $key ); ?></th>
                <?php endforeach; ?>
                <th class="wpme-col-save">Save</th>
            </tr>
        </thead>
        <tbody id="wpme-tbody">
        <?php foreach ( $posts as $post ) :
            $edit_url = get_edit_post_link( $post->ID );
        ?>
            <tr class="wpme-row" data-post-id="<?php echo esc_attr( $post->ID ); ?>">

                <td class="wpme-col-id">
                    <a href="<?php echo esc_url( $edit_url ); ?>" target="_blank" title="Edit in WordPress"><?php echo esc_html( $post->ID ); ?><br>
                    Edit</a>
                </td>

                <!-- Title -->
                <td class="wpme-col-title">
                    <textarea
                        class="wpme-field wpme-core-field"
                        data-key="post_title"
                        rows="2"
                    ><?php echo esc_textarea( $post->post_title ); ?></textarea>
                </td>

                <!-- Status -->
                <td class="wpme-col-status">
                    <select class="wpme-status-select wpme-core-field" data-key="post_status">
                        <option value="publish" <?php selected( $post->post_status, 'publish' ); ?>>Published</option>
                        <option value="draft"   <?php selected( $post->post_status, 'draft' ); ?>>Draft</option>
                    </select>
                </td>

                <!-- Meta fields: rendered by field type system -->
                <?php foreach ( $meta_keys as $key ) :
                    $raw     = get_post_meta( $post->ID, $key, true );
                    $type_id = $field_types[ $key ] ?? 'text';
                ?>
                    <td><?php echo wpme_render_cell( $key, (string) $raw, $type_id ); ?></td>
                <?php endforeach; ?>

                <td class="wpme-col-save">
                    <button class="button wpme-save-btn" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                        Save
                    </button>
                    <span class="wpme-save-status"></span>
                    
                    <button class="button wpme-delete-btn" data-post-id="<?php echo esc_attr( $post->ID ); ?>">Delete</button>
                    
                    
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    </div>

    <?php endif; ?>
</div>
