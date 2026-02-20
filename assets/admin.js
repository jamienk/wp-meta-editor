( function () {
    'use strict';

    // ── URL-encode helpers ────────────────────────────────────────────────────

    function isUrlEncoded( str ) {
        if ( typeof str !== 'string' || str === '' ) return false;
        try { return decodeURIComponent( str ) !== str; } catch ( e ) { return false; }
    }
    function safeDecode( str ) {
        try { return decodeURIComponent( str ); } catch ( e ) { return str; }
    }
    function safeEncode( str ) { return encodeURIComponent( str ); }
    
    
    
    
    // ── Column sorting ────────────────────────────────────────────────────────
	document.querySelectorAll( '.wpme-table th' ).forEach( function( th, colIndex ) {
		th.style.cursor = 'pointer';
		th.dataset.sortDir = 'asc';
	
		th.addEventListener( 'click', function() {
			const dir   = th.dataset.sortDir === 'asc' ? 1 : -1;
			const tbody = document.getElementById( 'wpme-tbody' );
			const rows  = Array.from( tbody.querySelectorAll( '.wpme-row' ) );
	
			rows.sort( function( a, b ) {
				const aVal = getCellText( a, colIndex );
				const bVal = getCellText( b, colIndex );
				// Natural sort: numeric if both look like numbers
				const aNum = parseFloat( aVal );
				const bNum = parseFloat( bVal );
				if ( ! isNaN( aNum ) && ! isNaN( bNum ) ) return ( aNum - bNum ) * dir;
				return aVal.localeCompare( bVal ) * dir;
			} );
	
			rows.forEach( function( row ) { tbody.appendChild( row ); } );
	
			// Toggle direction and update indicator
			th.dataset.sortDir = th.dataset.sortDir === 'asc' ? 'desc' : 'asc';
			document.querySelectorAll( '.wpme-table th' ).forEach( function( t ) {
				t.textContent = t.textContent.replace( / [▲▼]$/, '' );
			} );
			th.textContent += th.dataset.sortDir === 'asc' ? ' ▼' : ' ▲';
		} );
	} );
	
	function getCellText( row, colIndex ) {
		const cell = row.querySelectorAll( 'td' )[ colIndex ];
		if ( ! cell ) return '';
		// Prefer input/textarea/select value over text content
		const input = cell.querySelector( 'input[type="text"], textarea, select, input[type="number"]' );
		if ( input ) return input.value.trim();
		return cell.textContent.trim();
	}
    
    

    // ── Multi Rich Text helpers ───────────────────────────────────────────────

    /**
     * Serialize all items in a .wpme-multirichtext-cell into the hidden input.
     */
    function wpmeSerializeMultiRichText( cell ) {
        const items  = cell.querySelectorAll( '.wpme-multirichtext-item' );
        const result = [];
        items.forEach( function ( item ) {
            result.push( {
                'output-name':   item.querySelector( '.wpme-multirichtext-name'   ).value,
                'output-text':   item.querySelector( '.wpme-multirichtext-text'   ).value,
                'output-number': parseInt( item.querySelector( '.wpme-multirichtext-number' ).value, 10 ) || 0,
            } );
        } );
        cell.querySelector( '.wpme-multirichtext-value' ).value = JSON.stringify( result );
    }

    /**
     * Build a blank item card HTML string (mirrors wpme_multirichtext_item_html in PHP).
     */
    function wpmeBlankMultiRichTextItem() {
        return '<div class="wpme-multirichtext-item">' +
            '<div class="wpme-multirichtext-item-header">' +
                '<input type="text" class="wpme-multirichtext-name" placeholder="Name (e.g. Patents)" value="">' +
                '<input type="number" class="wpme-multirichtext-number" placeholder="#" value="" style="width:60px;">' +
                '<button type="button" class="button button-small wpme-multirichtext-delete">\u2715</button>' +
            '</div>' +
            '<div class="wpme-multirichtext-item-body">' +
                '<span class="wpme-richtext-preview">(empty)</span>' +
                '<textarea class="wpme-multirichtext-text" style="display:none;"></textarea>' +
                '<button type="button" class="button button-small wpme-multirichtext-open" style="margin-top:4px;">Edit&hellip;</button>' +
            '</div>' +
        '</div>';
    }

    // ── On load ───────────────────────────────────────────────────────────────

    document.addEventListener( 'DOMContentLoaded', function () {

        // Populate text meta fields (URL-decode if needed)
        document.querySelectorAll( '.wpme-meta-field' ).forEach( function ( field ) {
            const raw = field.dataset.raw || '';
            if ( isUrlEncoded( raw ) ) {
                field.value = safeDecode( raw );
                field.dataset.wasEncoded = 'true';
            } else {
                field.dataset.wasEncoded = 'false';
            }
        } );

        // ── Sortable config rows ──────────────────────────────────────────────
        if ( typeof jQuery !== 'undefined' && jQuery.fn.sortable ) {
            jQuery( '#wpme-config-tbody' ).sortable( {
                handle:               '.wpme-drag-handle',
                axis:                 'y',
                placeholder:          'wpme-config-row ui-sortable-placeholder',
                forcePlaceholderSize: true,
            } );
        }

        // ── All delegated click handling ──────────────────────────────────────

        document.addEventListener( 'click', function ( e ) {

            // ── Image: Choose ─────────────────────────────────────────────────
            const chooseBtn = e.target.closest( '.wpme-img-choose' );
            if ( chooseBtn ) {
                const cell    = chooseBtn.closest( '.wpme-image-cell' );
                const input   = cell.querySelector( '.wpme-image-value' );
                const thumb   = cell.querySelector( '.wpme-img-thumb' );
                const actions = cell.querySelector( '.wpme-img-actions' );

                if ( typeof wp === 'undefined' || ! wp.media ) {
                    alert( 'WordPress media library is not available.' );
                    return;
                }

                const frame = wp.media( {
                    title:    'Choose Image',
                    button:   { text: 'Use this image' },
                    library:  { type: 'image' },
                    multiple: false,
                } );

                frame.on( 'select', function () {
                    const attachment = frame.state().get( 'selection' ).first().toJSON();
                    const blob = {
                        alt:         attachment.alt         || '',
                        title:       attachment.title       || '',
                        caption:     attachment.caption     || '',
                        description: attachment.description || '',
                        id:          attachment.id,
                        link:        attachment.link        || '',
                        url:         attachment.url,
                        sizes:       {},
                    };
                    const sizes = attachment.sizes || {};
                    Object.keys( sizes ).forEach( function ( s ) {
                        const sz = sizes[ s ];
                        blob.sizes[ s ] = {
                            height:      sz.height,
                            width:       sz.width,
                            url:         sz.url,
                            orientation: sz.height > sz.width ? 'portrait' : 'landscape',
                        };
                    } );
                    input.value = JSON.stringify( blob );
                    const previewUrl = ( sizes.thumbnail || sizes.medium || { url: attachment.url } ).url;
                    thumb.innerHTML  = '<img src="' + previewUrl + '" class="wpme-img-preview" alt="">';
                    if ( ! actions.querySelector( '.wpme-img-remove' ) ) {
                        const removeBtn       = document.createElement( 'button' );
                        removeBtn.type        = 'button';
                        removeBtn.className   = 'button button-small wpme-img-remove';
                        removeBtn.textContent = 'Remove';
                        actions.appendChild( removeBtn );
                    }
                } );
                frame.open();
                return;
            }

            // ── Image: Remove ─────────────────────────────────────────────────
            const removeImgBtn = e.target.closest( '.wpme-img-remove' );
            if ( removeImgBtn ) {
                const cell  = removeImgBtn.closest( '.wpme-image-cell' );
                const input = cell.querySelector( '.wpme-image-value' );
                const thumb = cell.querySelector( '.wpme-img-thumb' );
                input.value     = '';
                thumb.innerHTML = '<span class="wpme-img-placeholder">No image</span>';
                removeImgBtn.remove();
                return;
            }

            // ── Project Tags: checkbox ────────────────────────────────────────
            const tagCb = e.target.closest( '.wpme-projecttag-cb' );
            if ( tagCb ) {
                const cell    = tagCb.closest( '.wpme-projecttags-cell' );
                const input   = cell.querySelector( '.wpme-projecttags-value' );
                const checked = Array.from( cell.querySelectorAll( '.wpme-projecttag-cb:checked' ) );
                input.value   = JSON.stringify( checked.map( function( cb ) {
                    return { 'one-tag': cb.value };
                } ) );
                return;
            }

            // ── Multi Rich Text: open editor ──────────────────────────────────
            const mrtOpenBtn = e.target.closest( '.wpme-multirichtext-open' );
            if ( mrtOpenBtn ) {
                
                //console.log('multirichtext open');
                
                const item    = mrtOpenBtn.closest( '.wpme-multirichtext-item' );
                const textarea = item.querySelector( '.wpme-multirichtext-text' );
                const cell    = mrtOpenBtn.closest( '.wpme-multirichtext-cell' );
                const key     = cell.dataset.key;

                if ( typeof window.wpmeOpenRichTextModal === 'function' ) {
                    // Pass a proxy object: openModal reads/writes .value
                    // and on Done we also update the preview and reserialize
                    const proxy = {
                        value: textarea.value,
                        _textarea: textarea,
                        _cell: cell,
                        _preview: item.querySelector( '.wpme-richtext-preview' ),
                    };

                    // Patch: after Done, modal writes to proxy.value —
                    // we need to sync back to textarea and reserialize.
                    // Override by wrapping with a MutationObserver on modal display:none
                    window._wpmeMRTProxy = proxy;
                    window.wpmeOpenRichTextModal( proxy, key );
                } else {
                    alert( 'Rich text editor not available. Make sure at least one field uses the Rich Text type.' );
                }
                return;
            }

            // ── Multi Rich Text: add item ─────────────────────────────────────
            const mrtAddBtn = e.target.closest( '.wpme-multirichtext-add' );
            if ( mrtAddBtn ) {
                const cell  = mrtAddBtn.closest( '.wpme-multirichtext-cell' );
                const items = cell.querySelector( '.wpme-multirichtext-items' );
                const tmp   = document.createElement( 'div' );
                tmp.innerHTML = wpmeBlankMultiRichTextItem();
                items.appendChild( tmp.firstElementChild );
                wpmeSerializeMultiRichText( cell );
                return;
            }

            // ── Multi Rich Text: delete item ──────────────────────────────────
            const mrtDeleteBtn = e.target.closest( '.wpme-multirichtext-delete' );
            if ( mrtDeleteBtn ) {
                const item = mrtDeleteBtn.closest( '.wpme-multirichtext-item' );
                const cell = mrtDeleteBtn.closest( '.wpme-multirichtext-cell' );
                if ( confirm( 'Delete this item?' ) ) {
                    item.remove();
                    wpmeSerializeMultiRichText( cell );
                }
                return;
            }
            
            
        	// ── Delete row ──────────────────────────────────────────────────────
            const deleteBtn = e.target.closest( '.wpme-delete-btn' );
				if ( deleteBtn ) {
					const postId = deleteBtn.dataset.postId;
					if ( ! confirm( 'Move this post to trash?' ) ) return;
					
					fetch( wpme.ajax_url, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: new URLSearchParams( { action: 'wpme_delete_post', nonce: wpme.nonce, post_id: postId } ).toString(),
					} )
						.then( res => res.json() )
						.then( function( data ) {
							if ( data.success ) {
								deleteBtn.closest( '.wpme-row' ).remove();
							} else {
								alert( 'Error: ' + data.data );
							}
						} );
					return;
				}
            
            

            // ── Save row ──────────────────────────────────────────────────────
            const saveBtn = e.target.closest( '.wpme-save-btn' );
            if ( saveBtn ) {
                const row      = saveBtn.closest( '.wpme-row' );
                const postId   = saveBtn.dataset.postId;
                const statusEl = row.querySelector( '.wpme-save-status' );

                // Serialize all multirichtext cells before saving
                row.querySelectorAll( '.wpme-multirichtext-cell' ).forEach( wpmeSerializeMultiRichText );

                saveBtn.disabled    = true;
                saveBtn.textContent = 'Saving\u2026';
                statusEl.textContent = '';
                statusEl.className   = 'wpme-save-status';

                const body = new URLSearchParams( {
                    action:  'wpme_save_meta',
                    nonce:   wpme.nonce,
                    post_id: postId,
                } );

                // Core fields
                row.querySelectorAll( '.wpme-core-field' ).forEach( function ( field ) {
                    body.set( field.dataset.key, field.value );
                } );

                // All meta fields — textarea, hidden inputs (image, richtext, multirichtext, projecttags, projectlead)
                row.querySelectorAll( '.wpme-field' ).forEach( function ( field ) {
                    if ( field.classList.contains( 'wpme-core-field' ) ) return;
                    if ( field.classList.contains( 'wpme-status-select' ) ) return;
                    const key        = field.dataset.key;
                    if ( ! key ) return;
                    const wasEncoded = field.dataset.wasEncoded === 'true';
                    const val        = wasEncoded ? safeEncode( field.value ) : field.value;
                    body.append( 'fields[' + key + ']', val );
                } );

                fetch( wpme.ajax_url, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body:    body.toString(),
                } )
                    .then( function ( res ) { return res.json(); } )
                    .then( function ( data ) {
                        if ( data.success ) {
                            statusEl.textContent = '\u2713 Saved';
                            statusEl.classList.add( 'wpme-saved' );
                            row.querySelectorAll( '.wpme-meta-field' ).forEach( function ( field ) {
                                field.dataset.raw = field.dataset.wasEncoded === 'true'
                                    ? safeEncode( field.value )
                                    : field.value;
                            } );
                        } else {
                            statusEl.textContent = '\u2717 ' + ( data.data || 'Error' );
                            statusEl.classList.add( 'wpme-error' );
                        }
                    } )
                    .catch( function () {
                        statusEl.textContent = '\u2717 Network error';
                        statusEl.classList.add( 'wpme-error' );
                    } )
                    .finally( function () {
                        saveBtn.disabled    = false;
                        saveBtn.textContent = 'Save';
                        setTimeout( function () {
                            statusEl.textContent = '';
                            statusEl.className   = 'wpme-save-status';
                        }, 3000 );
                    } );
                return;
            }

            // ── New post ──────────────────────────────────────────────────────
            const newBtn = e.target.closest( '.wpme-new-post-btn' );
            if ( newBtn ) {
                const postType = newBtn.dataset.postType;
                newBtn.disabled    = true;
                newBtn.textContent = 'Creating\u2026';

                const body = new URLSearchParams( {
                    action:    'wpme_insert_post',
                    nonce:     wpme.nonce,
                    post_type: postType,
                } );

                fetch( wpme.ajax_url, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body:    body.toString(),
                } )
                    .then( function( res ) { return res.json(); } )
                    .then( function ( data ) {
                        if ( ! data.success ) {
                            alert( 'Error: ' + data.data );
                            return;
                        }
                        const { post_id, title, edit_url } = data.data;
                        const tbody    = document.getElementById( 'wpme-tbody' );
                        const template = tbody.querySelector( '.wpme-row' );
                        const newRow   = template.cloneNode( true );

                        newRow.dataset.postId = post_id;
                        newRow.querySelector( '.wpme-col-id a' ).innerHTML = post_id + "<br>Edit";
                        newRow.querySelector( '.wpme-col-id a' ).href        = edit_url;
                        newRow.querySelector( '[data-key="post_title"]' ).value = title;
                        newRow.querySelector( '.wpme-status-select' ).value  = 'draft';

                        newRow.querySelectorAll( '.wpme-meta-field' ).forEach( function( f ) {
                            f.value              = '';
                            f.dataset.raw        = '';
                            f.dataset.wasEncoded = 'false';
                        } );
                        newRow.querySelectorAll( '.wpme-image-value' ).forEach( function( f ) { f.value = ''; } );
                        newRow.querySelectorAll( '.wpme-img-thumb' ).forEach( function( t ) {
                            t.innerHTML = '<span class="wpme-img-placeholder">No image</span>';
                        } );
                        newRow.querySelectorAll( '.wpme-img-remove' ).forEach( function( b ) { b.remove(); } );
                        newRow.querySelectorAll( '.wpme-multirichtext-items' ).forEach( function( c ) { c.innerHTML = ''; } );
                        newRow.querySelectorAll( '.wpme-multirichtext-value' ).forEach( function( f ) { f.value = '[]'; } );
                        newRow.querySelectorAll( '.wpme-richtext-value' ).forEach( function( f ) { f.value = ''; } );
                        newRow.querySelectorAll( '.wpme-richtext-preview' ).forEach( function( s ) { s.textContent = '(empty)'; } );

                        newRow.querySelector( '.wpme-save-btn' ).dataset.postId    = post_id;
                        newRow.querySelector( '.wpme-save-status' ).textContent    = '';

                        tbody.insertBefore( newRow, tbody.firstChild );
                    } )
                    .finally( function () {
                        newBtn.disabled    = false;
                        newBtn.textContent = '+ New ' + postType.replace( '_', ' ' );
                    } );
                return;
            }

        } ); // end delegated click

        // ── Multi Rich Text: sync textarea on name/number change ─────────────
        document.addEventListener( 'input', function ( e ) {
            const nameOrNum = e.target.closest( '.wpme-multirichtext-name, .wpme-multirichtext-number' );
            if ( nameOrNum ) {
                const cell = nameOrNum.closest( '.wpme-multirichtext-cell' );
                wpmeSerializeMultiRichText( cell );
            }
        } );

        // ── Multi Rich Text: sync proxy back from richtext modal ──────────────
        // The shared modal calls closeModal(true) which sets proxy.value.
        // We watch for the modal hiding and sync proxy back to the textarea.
        const mrtModal = document.getElementById( 'wpme-richtext-modal' );
        if ( mrtModal ) {
            const observer = new MutationObserver( function () {
                if ( mrtModal.style.display === 'none' && window._wpmeMRTProxy ) {
                    const proxy = window._wpmeMRTProxy;
                    proxy._textarea.value = proxy.value;

                    // Update preview
                    const text = proxy.value.replace( /<[^>]+>/g, '' );
                    proxy._preview.textContent = text.length > 60
                        ? text.slice( 0, 60 ) + '\u2026'
                        : ( text || '(empty)' );

                    wpmeSerializeMultiRichText( proxy._cell );
                    window._wpmeMRTProxy = null;
                }
            } );
            observer.observe( mrtModal, { attributes: true, attributeFilter: [ 'style' ] } );
        }

    } ); // end DOMContentLoaded

} )();