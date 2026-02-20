<?php
defined( 'ABSPATH' ) || exit;

wpme_register_field_type( 'richtext', [
    'label' => 'Rich Text',

    'enqueue' => function (): void {
        // Ensure TinyMCE assets are available
        wp_enqueue_editor();
        wp_enqueue_script( 'editor' );

        // Print the shared modal + wp_editor instance once, in the footer
        add_action( 'admin_footer', function (): void {
            // Guard: only print once even if called multiple times
            static $printed = false;
            if ( $printed ) return;
            $printed = true;
            ?>
            <div id="wpme-richtext-modal" style="display:none;">
                <div id="wpme-richtext-backdrop"></div>
                <div id="wpme-richtext-dialog">
                    <div id="wpme-richtext-dialog-header">
                        <span id="wpme-richtext-dialog-title">Edit Field</span>
                        <button type="button" id="wpme-richtext-done" class="button button-primary">Done</button>
                        <button type="button" id="wpme-richtext-cancel" class="button">Cancel</button>
                    </div>
                    <div id="wpme-richtext-editor-wrap">
                        <?php
                        wp_editor( '', 'wpme_richtext_editor', [
                            'media_buttons' => false,
                            'textarea_rows' => 18,
                            'teeny'         => false,
                            'tinymce'       => true,
                            'quicktags'     => true,
                        ] );
                        ?>
                    </div>
                </div>
            </div>

            <style>
                #wpme-richtext-backdrop {
                    position: fixed;
                    inset: 0;
                    background: rgba(0,0,0,0.55);
                    z-index: 99998;
                }
                #wpme-richtext-dialog {
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    z-index: 99999;
                    background: #fff;
                    border-radius: 4px;
                    box-shadow: 0 8px 40px rgba(0,0,0,0.3);
                    width: 780px;
                    max-width: 95vw;
                    max-height: 90vh;
                    display: flex;
                    flex-direction: column;
                }
                #wpme-richtext-dialog-header {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 12px 16px;
                    border-bottom: 1px solid #ddd;
                    background: #f9f9f9;
                    border-radius: 4px 4px 0 0;
                    flex-shrink: 0;
                }
                #wpme-richtext-dialog-title {
                    flex: 1;
                    font-weight: 600;
                    font-size: 14px;
                    color: #1d2327;
                }
                #wpme-richtext-editor-wrap {
                    padding: 16px;
                    overflow-y: auto;
                    flex: 1;
                }
                #wpme-richtext-editor-wrap .wp-editor-container {
                    border: 1px solid #ddd;
                }
            </style>

            <script>
            ( function() {

                var modal       = document.getElementById( 'wpme-richtext-modal' );
                var backdrop    = document.getElementById( 'wpme-richtext-backdrop' );
                var doneBtn     = document.getElementById( 'wpme-richtext-done' );
                var cancelBtn   = document.getElementById( 'wpme-richtext-cancel' );
                var titleEl     = document.getElementById( 'wpme-richtext-dialog-title' );
                var activeInput = null; // the hidden input we're editing

                function getEditorContent() {
                    // Try TinyMCE first, fall back to textarea
                    if ( window.tinymce ) {
                        var ed = tinymce.get( 'wpme_richtext_editor' );
                        if ( ed && ! ed.isHidden() ) {
                            return ed.getContent();
                        }
                    }
                    return document.getElementById( 'wpme_richtext_editor' ).value;
                }

                function setEditorContent( html ) {
                    if ( window.tinymce ) {
                        var ed = tinymce.get( 'wpme_richtext_editor' );
                        if ( ed && ! ed.isHidden() ) {
                            ed.setContent( html );
                            return;
                        }
                    }
                    document.getElementById( 'wpme_richtext_editor' ).value = html;
                }

                function openModal( hiddenInput, fieldKey ) {
                    activeInput = hiddenInput;
                    titleEl.textContent = 'Edit: ' + fieldKey;
                    setEditorContent( hiddenInput.value );
                    modal.style.display = 'block';
                }
                window.wpmeOpenRichTextModal = openModal;

                function closeModal( save ) {
					if ( save && activeInput ) {
						activeInput.value = getEditorContent();
						// Only update preview for real DOM elements (not multirichtext proxy objects)
						if ( typeof activeInput.closest === 'function' ) {
							var cell    = activeInput.closest( '.wpme-richtext-cell' );
							var preview = cell ? cell.querySelector( '.wpme-richtext-preview' ) : null;
							if ( preview ) {
								var text = getEditorContent().replace( /<[^>]+>/g, '' );
								preview.textContent = text.length > 80 ? text.slice( 0, 80 ) + '…' : text;
							}
						}
					}
					modal.style.display = 'none';
					activeInput = null;
				}

                doneBtn.addEventListener(   'click', function() { closeModal( true );  } );
                cancelBtn.addEventListener( 'click', function() { closeModal( false ); } );
                backdrop.addEventListener(  'click', function() { closeModal( false ); } );

                // Keyboard: Escape to cancel
                document.addEventListener( 'keydown', function( e ) {
                    if ( e.key === 'Escape' && modal.style.display === 'block' ) {
                        closeModal( false );
                    }
                } );

                // Delegated open — listens for clicks on any .wpme-richtext-open button
                document.addEventListener( 'click', function( e ) {
                    var btn = e.target.closest( '.wpme-richtext-open' );
                    if ( ! btn ) return;
                    var cell  = btn.closest( '.wpme-richtext-cell' );
                    var input = cell.querySelector( '.wpme-richtext-value' );
                    openModal( input, input.dataset.key );
                } );

            } )();
            </script>
            <?php
        }, 99 );
    },

    'render_cell' => function ( string $key, string $raw ): string {
        $k       = esc_attr( $key );
        $escaped = esc_attr( $raw );

        // Strip tags for the preview snippet
        $preview = wp_strip_all_tags( $raw );
        $preview = mb_strlen( $preview ) > 80
            ? mb_substr( $preview, 0, 80 ) . '…'
            : $preview;

        return
            '<div class="wpme-richtext-cell">' .
                '<span class="wpme-richtext-preview">' . esc_html( $preview ?: '(empty)' ) . '</span>' .
                '<input type="hidden"' .
                    ' class="wpme-field wpme-richtext-value"' .
                    ' data-key="' . $k . '"' .
                    ' value="' . $escaped . '">' .
                '<div style="margin-top:5px;">' .
                    '<button type="button" class="button button-small wpme-richtext-open">Edit&hellip;</button>' .
                '</div>' .
            '</div>';
    },
] );