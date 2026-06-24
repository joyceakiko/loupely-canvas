/**
 * Loupely Canvas - SEO editor
 *
 * Wires the per-page SEO section: the social image picker (Select and Clear),
 * the character counters under the title and description, and the button that
 * builds JSON-LD for the page. The button also lists which fields made it into
 * the schema and which were left out, above the code box. Everything it needs
 * comes from the section's data attributes and inputs, plus the labels handed
 * over in lcSeo, so it runs the same way in every editor that mounts the
 * section.
 */
( function () {
	'use strict';

	var L = ( typeof window.lcSeo === 'object' && window.lcSeo ) ? window.lcSeo : {};
	var LABELS = L.labels || {};

	function t( key, fallback ) {
		return ( typeof L[ key ] === 'string' && L[ key ] ) ? L[ key ] : fallback;
	}
	function label( key, fallback ) {
		return ( typeof LABELS[ key ] === 'string' && LABELS[ key ] ) ? LABELS[ key ] : fallback;
	}

	function wrapOf( el ) {
		return el ? el.closest( '.lc-seo' ) : null;
	}
	function val( wrap, selector ) {
		var node = wrap.querySelector( selector );
		return node ? String( node.value || '' ).trim() : '';
	}
	function data( wrap, key ) {
		return String( wrap.getAttribute( 'data-' + key ) || '' ).trim();
	}

	// The JSON-LD box may be upgraded to a CodeMirror editor, which keeps its own
	// document. Setting the textarea value alone would not show, so push through
	// the instance when one is attached (it mirrors itself back to the textarea).
	function setBoxValue( box, value ) {
		if ( box._lcCM && typeof box._lcCM.setValue === 'function' ) {
			box._lcCM.setValue( value );
		} else {
			box.value = value;
		}
	}

	// --- Media picker ---

	function targetInput( wrap, button ) {
		var byClass = wrap.querySelector( '.lc-seo-image' );
		if ( byClass ) {
			return byClass;
		}
		var id = button.getAttribute( 'data-target' );
		return id ? document.getElementById( id ) : null;
	}

	function openPicker( wrap, button ) {
		var input = targetInput( wrap, button );
		if ( ! input || typeof wp === 'undefined' || ! wp.media ) {
			return;
		}
		var frame = wp.media( { multiple: false } );
		frame.on( 'select', function () {
			var sel = frame.state().get( 'selection' ).first();
			if ( sel ) {
				input.value = sel.toJSON().url || '';
			}
		} );
		frame.open();
	}

	// --- Character counters ---

	function fieldForCounter( wrap, counter ) {
		var key = counter.getAttribute( 'data-for' );
		return wrap.querySelector( '.lc-seo-' + key );
	}

	function updateCounter( wrap, counter ) {
		var field = fieldForCounter( wrap, counter );
		if ( ! field ) {
			return;
		}
		var max = parseInt( counter.getAttribute( 'data-max' ), 10 ) || 0;
		var len = String( field.value || '' ).length;
		counter.textContent = len + ' / ' + max;
		var over = max > 0 && len > max;
		counter.classList.toggle( 'over', over );
		counter.title = over ? t( 'over', 'over recommended length' ) : ( len + ' ' + t( 'chars', 'characters' ) );
	}

	function initCounters( wrap ) {
		var counters = wrap.querySelectorAll( '.lc-seo-count' );
		Array.prototype.forEach.call( counters, function ( counter ) {
			updateCounter( wrap, counter );
			var field = fieldForCounter( wrap, counter );
			if ( field && ! field.getAttribute( 'data-lc-counted' ) ) {
				field.setAttribute( 'data-lc-counted', '1' );
				field.addEventListener( 'input', function () {
					updateCounter( wrap, counter );
				} );
			}
		} );
	}

	// --- Schema builder ---

	// The candidate fields for each type, in display order. Each entry is the
	// label key and the value used to decide whether the field is present.
	function fieldsForType( type, v ) {
		if ( type === 'Article' ) {
			return [
				[ 'title', v.name ],
				[ 'description', v.desc ],
				[ 'image', v.image ],
				[ 'published', v.published ],
				[ 'modified', v.modified ],
				[ 'author', v.author ],
				[ 'url', v.pageUrl ]
			];
		}
		if ( type === 'Organization' ) {
			return [ [ 'name', v.orgName ], [ 'siteUrl', v.homeUrl ], [ 'logo', v.image ], [ 'description', v.desc ] ];
		}
		if ( type === 'Product' ) {
			return [ [ 'name', v.name ], [ 'description', v.desc ], [ 'image', v.image ] ];
		}
		if ( type === 'LocalBusiness' ) {
			return [ [ 'name', v.name ], [ 'description', v.desc ], [ 'url', v.pageUrl ], [ 'image', v.image ] ];
		}
		// WebPage and anything else.
		return [ [ 'name', v.name ], [ 'description', v.desc ], [ 'image', v.image ], [ 'url', v.pageUrl ] ];
	}

	function readValues( wrap ) {
		var seoTitle = val( wrap, '.lc-seo-title' );
		var pageTitle = data( wrap, 'page-title' );
		var siteName = data( wrap, 'site-name' );
		var homeUrl = data( wrap, 'home-url' );
		return {
			seoTitle: seoTitle,
			desc: val( wrap, '.lc-seo-desc' ),
			image: val( wrap, '.lc-seo-image' ),
			pageTitle: pageTitle,
			siteName: siteName,
			homeUrl: homeUrl,
			pageUrl: data( wrap, 'page-url' ) || homeUrl,
			published: data( wrap, 'published' ),
			modified: data( wrap, 'modified' ),
			author: data( wrap, 'author' ),
			name: seoTitle || pageTitle || siteName,
			orgName: seoTitle || siteName
		};
	}

	function buildSchema( type, v ) {
		var s = { '@context': 'https://schema.org', '@type': type };

		if ( type === 'Article' ) {
			s.headline = v.name;
			if ( v.desc ) { s.description = v.desc; }
			if ( v.image ) { s.image = v.image; }
			if ( v.published ) { s.datePublished = v.published; }
			if ( v.modified ) { s.dateModified = v.modified; }
			if ( v.author ) { s.author = { '@type': 'Person', name: v.author }; }
			if ( v.pageUrl ) { s.mainEntityOfPage = v.pageUrl; }
			return s;
		}
		if ( type === 'Organization' ) {
			s.name = v.orgName;
			s.url = v.homeUrl;
			if ( v.image ) { s.logo = v.image; }
			if ( v.desc ) { s.description = v.desc; }
			return s;
		}
		if ( type === 'Product' ) {
			s.name = v.name;
			if ( v.desc ) { s.description = v.desc; }
			if ( v.image ) { s.image = v.image; }
			return s;
		}
		if ( type === 'LocalBusiness' ) {
			s.name = v.name;
			if ( v.desc ) { s.description = v.desc; }
			if ( v.pageUrl ) { s.url = v.pageUrl; }
			if ( v.image ) { s.image = v.image; }
			return s;
		}
		if ( type === 'FAQPage' ) {
			s.mainEntity = [ {
				'@type': 'Question',
				name: '',
				acceptedAnswer: { '@type': 'Answer', text: '' }
			} ];
			return s;
		}
		// WebPage and anything else.
		s.name = v.name;
		if ( v.desc ) { s.description = v.desc; }
		if ( v.image ) { s.image = v.image; }
		if ( v.pageUrl ) { s.url = v.pageUrl; }
		return s;
	}

	function renderSummary( panel, type, v ) {
		panel.innerHTML = '';
		panel.style.display = 'block';

		if ( type === 'FAQPage' ) {
			var note = document.createElement( 'p' );
			note.className = 'lc-seo-summary-note';
			note.textContent = t( 'faq', 'A blank question and answer was added. Fill it in below.' );
			panel.appendChild( note );
			return;
		}

		var fields = fieldsForType( type, v );
		var inc = [];
		var out = [];
		fields.forEach( function ( pair ) {
			var name = label( pair[ 0 ], pair[ 0 ] );
			if ( String( pair[ 1 ] || '' ).trim() !== '' ) {
				inc.push( name );
			} else {
				out.push( name );
			}
		} );

		panel.appendChild( summaryLine( 'in', t( 'included', 'Included' ), inc ) );
		if ( out.length ) {
			panel.appendChild( summaryLine( 'out', t( 'notIncluded', 'Not included' ), out ) );
		}
	}

	function summaryLine( kind, heading, items ) {
		var row = document.createElement( 'p' );
		row.className = 'lc-seo-summary-row lc-seo-summary-' + kind;
		var strong = document.createElement( 'strong' );
		strong.textContent = heading + ' (' + items.length + '): ';
		row.appendChild( strong );
		row.appendChild( document.createTextNode( items.length ? items.join( ', ' ) : '' ) );
		return row;
	}

	function generate( wrap ) {
		var box = wrap.querySelector( '.lc-seo-jsonld' );
		var panel = wrap.querySelector( '.lc-seo-summary' );
		if ( ! box ) {
			return;
		}
		var type = val( wrap, '.lc-seo-type' );

		if ( type === '' ) {
			setBoxValue( box, '' );
			if ( panel ) {
				panel.innerHTML = '';
				var msg = document.createElement( 'p' );
				msg.className = 'lc-seo-summary-note';
				msg.textContent = t( 'none', 'Select a schema type above to build the page schema.' );
				panel.appendChild( msg );
				panel.style.display = 'block';
			}
			return;
		}

		var v = readValues( wrap );
		if ( panel ) {
			renderSummary( panel, type, v );
		}
		setBoxValue( box, JSON.stringify( buildSchema( type, v ), null, 2 ) );
	}

	// --- Wiring ---

	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target.closest ) {
			return;
		}
		var pick = e.target.closest( '.lc-seo-pick-image' );
		if ( pick ) {
			e.preventDefault();
			openPicker( wrapOf( pick ), pick );
			return;
		}
		var clear = e.target.closest( '.lc-seo-clear-image' );
		if ( clear ) {
			e.preventDefault();
			var wrap = wrapOf( clear );
			var input = wrap ? targetInput( wrap, clear ) : null;
			if ( input ) { input.value = ''; }
			return;
		}
		var gen = e.target.closest( '.lc-seo-generate' );
		if ( gen ) {
			e.preventDefault();
			generate( wrapOf( gen ) );
		}
	} );

	function init() {
		var sections = document.querySelectorAll( '.lc-seo' );
		Array.prototype.forEach.call( sections, initCounters );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
