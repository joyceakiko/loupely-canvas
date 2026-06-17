/**
 * Loupely Canvas - settings nav scroll spy
 *
 * Highlights the sticky nav link for the section currently in view, and on
 * click. Self-contained: reads the DOM, needs no values from PHP. The jump
 * itself is a plain anchor link, so it works with the script absent too.
 */
( function () {
    "use strict";

    var nav = document.querySelector( ".lc-settings-nav" );
    if ( ! nav ) {
        return;
    }

    var links = Array.prototype.slice.call( nav.querySelectorAll( 'a[href^="#"]' ) );
    if ( ! links.length ) {
        return;
    }

    var sections = [];
    links.forEach( function ( a ) {
        var id = a.getAttribute( "href" ).slice( 1 );
        var el = document.getElementById( id );
        if ( el ) {
            sections.push( el );
        }
    } );

    function setActive( id ) {
        links.forEach( function ( a ) {
            a.classList.toggle( "is-active", a.getAttribute( "href" ) === "#" + id );
        } );
    }

    links.forEach( function ( a ) {
        a.addEventListener( "click", function () {
            setActive( a.getAttribute( "href" ).slice( 1 ) );
        } );
    } );

    if ( "IntersectionObserver" in window ) {
        var observer = new IntersectionObserver( function ( entries ) {
            entries.forEach( function ( entry ) {
                if ( entry.isIntersecting ) {
                    setActive( entry.target.id );
                }
            } );
        }, { rootMargin: "-40% 0px -55% 0px", threshold: 0 } );

        sections.forEach( function ( el ) {
            observer.observe( el );
        } );
    }
} )();
