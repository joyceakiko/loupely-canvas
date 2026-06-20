/**
 * Loupely - click to copy tokens
 *
 * Any element carrying the lc-token class becomes click to copy: clicking it (or
 * focusing it and pressing Enter or Space) copies its text and shows a brief
 * "Copied to clipboard" confirmation. Tokens added to the page later, such as a
 * field token built as the user types, are wired automatically through a
 * mutation observer, so a new token needs only the class and nothing else.
 *
 * Messages come from a localized object when present, so each product supplies
 * its own translated strings.
 */
( function () {
    'use strict';

    var L = window.lcTokenCopyL10n || {};
    var copiedMsg = L.copied || 'Copied to clipboard';
    var copyLabel = L.copy || 'Copy';

    // One-time stylesheet for the token affordance and the toast.
    var style = document.createElement( 'style' );
    style.textContent =
        '.lc-token{cursor:pointer;}' +
        '.lc-token:hover{border-color:#7a9e87;}' +
        '.lc-token:focus{outline:2px solid #5c7f68;outline-offset:1px;}' +
        '.lc-token-toast{position:fixed;left:50%;bottom:32px;transform:translateX(-50%) translateY(8px);' +
        'background:#1a2420;color:#f5f7f5;font-size:13px;line-height:1;padding:10px 16px;border-radius:6px;' +
        'box-shadow:0 6px 18px rgba(0,0,0,0.18);opacity:0;pointer-events:none;transition:opacity .15s ease,transform .15s ease;z-index:100000;}' +
        '.lc-token-toast.is-shown{opacity:1;transform:translateX(-50%) translateY(0);}';
    document.head.appendChild( style );

    // A single toast element, reused.
    var toast = document.createElement( 'div' );
    toast.className = 'lc-token-toast';
    toast.setAttribute( 'role', 'status' );
    toast.setAttribute( 'aria-live', 'polite' );
    document.body.appendChild( toast );

    var hideTimer = null;
    function showToast( msg ) {
        toast.textContent = msg;
        toast.classList.add( 'is-shown' );
        if ( hideTimer ) { clearTimeout( hideTimer ); }
        hideTimer = setTimeout( function () {
            toast.classList.remove( 'is-shown' );
        }, 1500 );
    }

    function copyText( text ) {
        if ( navigator.clipboard && navigator.clipboard.writeText ) {
            navigator.clipboard.writeText( text ).then( function () {
                showToast( copiedMsg );
            }, function () {
                fallbackCopy( text );
            } );
            return;
        }
        fallbackCopy( text );
    }

    function fallbackCopy( text ) {
        var ta = document.createElement( 'textarea' );
        ta.value = text;
        ta.setAttribute( 'readonly', '' );
        ta.style.position = 'fixed';
        ta.style.top = '-1000px';
        document.body.appendChild( ta );
        ta.select();
        try {
            document.execCommand( 'copy' );
            showToast( copiedMsg );
        } catch ( e ) {
            // Leave the user to copy manually if the browser blocks it.
        }
        document.body.removeChild( ta );
    }

    // Make one token element act like a button for assistive tech and keyboard.
    function decorate( el ) {
        if ( el.getAttribute( 'data-lc-token-ready' ) === '1' ) { return; }
        el.setAttribute( 'data-lc-token-ready', '1' );
        el.setAttribute( 'role', 'button' );
        el.setAttribute( 'tabindex', '0' );
        var token = el.textContent.trim();
        if ( token ) {
            el.setAttribute( 'title', copyLabel + ' ' + token );
            el.setAttribute( 'aria-label', copyLabel + ' ' + token );
        }
    }

    function decorateAll( root ) {
        ( root || document ).querySelectorAll( '.lc-token' ).forEach( decorate );
    }

    decorateAll( document );

    // Wire tokens added after load (live field tokens, for example).
    if ( window.MutationObserver ) {
        var obs = new MutationObserver( function ( mutations ) {
            mutations.forEach( function ( m ) {
                m.addedNodes.forEach( function ( node ) {
                    if ( node.nodeType !== 1 ) { return; }
                    if ( node.classList && node.classList.contains( 'lc-token' ) ) {
                        decorate( node );
                    }
                    if ( node.querySelectorAll ) {
                        decorateAll( node );
                    }
                } );
            } );
        } );
        obs.observe( document.body, { childList: true, subtree: true } );
    }

    function handleActivate( el ) {
        var token = el.textContent.trim();
        if ( token ) { copyText( token ); }
    }

    document.addEventListener( 'click', function ( e ) {
        var el = e.target.closest( '.lc-token' );
        if ( el ) { handleActivate( el ); }
    } );

    document.addEventListener( 'keydown', function ( e ) {
        if ( e.key !== 'Enter' && e.key !== ' ' && e.key !== 'Spacebar' ) { return; }
        var el = e.target.closest( '.lc-token' );
        if ( el ) {
            e.preventDefault();
            handleActivate( el );
        }
    } );

} () );
