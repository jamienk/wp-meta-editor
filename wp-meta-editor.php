<?php
/**
 * Plugin Name: WP Meta Editor
 * Description: Spreadsheet-style meta field editor for any post type.
 * Version: 1.1.7
 * Author: Jamie @ CA
 */

defined( 'ABSPATH' ) || exit;

/* 
if ( isset( $_GET['wpme_debug'] ) ) {
    echo '<pre>' . esc_html( get_option( 'wpme_debug_content', '(empty)' ) ) . '</pre>';
    die();
}
use this, but putting this somewhere
update_option( 'wpme_debug_content', $content );
```
Then in your browser go to:
```
/wp-admin/admin.php?page=wp-meta-editor&wpme_debug=1
 */

define( 'WPME_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPME_URL',  plugin_dir_url( __FILE__ ) );

require_once WPME_PATH . 'admin/field-types.php';
require_once WPME_PATH . 'admin/ajax-handlers.php';
require_once WPME_PATH . 'admin/lazyblocks.php';

add_action( 'admin_menu', function () {
    add_menu_page(
        'Meta Editor',
        'Meta Editor',
        'manage_options',
        'wp-meta-editor',
        'wpme_render_admin_page',
        'dashicons-editor-table',
        80
    );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( $hook !== 'toplevel_page_wp-meta-editor' ) return;

    wp_enqueue_style(  'wpme-admin', WPME_URL . 'assets/admin.css', [], '1.0.0' );
    wp_enqueue_script( 'wpme-admin', WPME_URL . 'assets/admin.js',  [], '1.0.0', true );

    wp_localize_script( 'wpme-admin', 'wpme', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'wpme_nonce' ),
    ] );

    wpme_enqueue_field_types();
} );

function wpme_render_admin_page() {
    $tab = isset( $_GET['wpme_tab'] ) ? sanitize_key( $_GET['wpme_tab'] ) : 'config';

    if ( $tab === 'table' && isset( $_GET['post_type'] ) ) {
        require WPME_PATH . 'admin/table-page.php';
    } else {
        require WPME_PATH . 'admin/admin-page.php';
    }
}
