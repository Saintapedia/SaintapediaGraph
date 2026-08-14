/**
 * SaintapediaGraph — render .saintapedia-graph nodes with bundled Mermaid 10.
 *
 * Diagram source is base64 in data-mermaid (survives MediaWiki HTML tidy).
 */
( function () {
	'use strict';

	var mermaidMod = require( './lib/mermaid.min.js' );
	var mermaid = ( mermaidMod && mermaidMod.default ) ? mermaidMod.default : mermaidMod;

	if ( !mermaid || typeof mermaid.initialize !== 'function' ) {
		mw.log.error( '[SaintapediaGraph] Mermaid module did not load correctly', mermaidMod );
		return;
	}

	var ready = false;
	var renderSeq = 0;

	function ensureReady() {
		if ( ready ) {
			return;
		}
		// Global defaults only. Per-diagram theme comes from %%{init}%% in
		// the Mermaid source (data-mermaid).
		mermaid.initialize( {
			startOnLoad: false,
			theme: 'default',
			securityLevel: 'antiscript',
			flowchart: {
				htmlLabels: false,
				useMaxWidth: true
			}
		} );
		ready = true;
	}

	/**
	 * @param {string} b64
	 * @return {string}
	 */
	function decodeSource( b64 ) {
		if ( !b64 ) {
			return '';
		}
		try {
			// atob → binary string → UTF-8 via TextDecoder (available in all MW-supported browsers)
			var bin = atob( b64 );
			var bytes = new Uint8Array( bin.length );
			for ( var i = 0; i < bin.length; i++ ) {
				bytes[ i ] = bin.charCodeAt( i );
			}
			return new TextDecoder( 'utf-8' ).decode( bytes ).trim();
		} catch ( e ) {
			mw.log.warn( '[SaintapediaGraph] base64 decode failed', e );
			return '';
		}
	}

	/**
	 * @param {Element} el
	 * @return {string}
	 */
	function getSource( el ) {
		var b64 = el.getAttribute( 'data-mermaid' ) || '';
		if ( b64 ) {
			return decodeSource( b64 );
		}
		// Legacy fallback for pre-0.2 markup only.
		var dataNode = el.querySelector( 'script.saintapedia-graph-data' );
		if ( dataNode ) {
			return ( dataNode.textContent || '' ).trim();
		}
		return '';
	}

	/**
	 * @param {Element} el
	 * @return {Promise}
	 */
	function renderOne( el ) {
		ensureReady();

		var source = getSource( el );
		if ( !source ) {
			return Promise.reject( new Error( 'Empty diagram source' ) );
		}

		renderSeq += 1;
		var renderId = 'sg-mmd-' + renderSeq + '-' +
			( el.id || 'x' ).replace( /[^A-Za-z0-9_-]/g, '' ).slice( 0, 40 );

		return mermaid.render( renderId, source ).then( function ( result ) {
			var svg = ( result && result.svg ) ? result.svg : result;
			if ( typeof svg !== 'string' || svg === '' ) {
				throw new Error( 'Mermaid returned empty SVG' );
			}

			el.innerHTML = svg;
			if ( result && typeof result.bindFunctions === 'function' ) {
				result.bindFunctions( el );
			}
			// Drop the large base64 payload after success (keeps DOM light).
			el.removeAttribute( 'data-mermaid' );
			el.setAttribute( 'data-processed', '1' );
			el.classList.remove( 'saintapedia-graph-error' );
			el.removeAttribute( 'data-render-failed' );
		} );
	}

	/**
	 * @param {Element} el
	 * @param {*} err
	 */
	function markError( el, err ) {
		var msg = ( err && err.message ) ? err.message : String( err || 'render failed' );
		if ( err && err.str ) {
			msg = msg + ' — ' + err.str;
		}
		mw.log.warn( '[SaintapediaGraph] ' + msg, err );

		var source = getSource( el );
		el.classList.add( 'saintapedia-graph-error' );
		el.setAttribute( 'data-render-failed', '1' );
		el.removeAttribute( 'data-render-pending' );
		el.textContent = 'Diagram failed to render: ' + msg +
			( source ? ( '\n\n' + source ) : '' );

		try {
			el.setAttribute( 'title', mw.msg( 'saintapediagraph-error-render' ) );
		} catch ( e ) {
			// ignore
		}
	}

	function renderAll() {
		var nodes = document.querySelectorAll(
			'.saintapedia-graph:not([data-processed]):not([data-render-pending])'
		);
		if ( !nodes.length ) {
			return;
		}

		var list = Array.prototype.slice.call( nodes );
		list.forEach( function ( el ) {
			el.setAttribute( 'data-render-pending', '1' );
		} );

		var chain = Promise.resolve();
		list.forEach( function ( el ) {
			chain = chain.then( function () {
				return renderOne( el ).catch( function ( err ) {
					markError( el, err );
				} ).then( function () {
					el.removeAttribute( 'data-render-pending' );
				} );
			} );
		} );
		return chain;
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', renderAll );
	} else {
		renderAll();
	}

	mw.hook( 'wikipage.content' ).add( function ( $content ) {
		if ( $content && $content.find ) {
			$content.find( '.saintapedia-graph[data-render-failed]' )
				.removeAttr( 'data-render-failed' )
				.removeAttr( 'data-processed' );
		}
		renderAll();
	} );
}() );
