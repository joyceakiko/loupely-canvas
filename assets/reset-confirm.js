/**
 * Loupely Canvas - reset confirm dialog
 *
 * Wires the reset buttons on the settings screen to a confirm dialog. Each
 * button names the form it submits and carries its own title, body, and confirm
 * label. The wipe button stays disabled until at least one content checkbox is
 * ticked, and the dialog lists the ticked items so the user sees exactly what
 * goes. The dialog itself stays locked until the typed word matches the
 * confirmation word, then it writes that word into the target form's hidden
 * field and submits it. The word comes from PHP (lcReset.phrase), so the dialog
 * and the server check for the same word.
 */
( function () {
	"use strict";

	var data = window.lcReset || {};
	var phrase = typeof data.phrase === "string" ? data.phrase : "Understood";
	var selectedLabel = typeof data.selectedLabel === "string" ? data.selectedLabel : "";
	var backup = data.backup && typeof data.backup === "object" ? data.backup : null;

	var dialog = document.getElementById( "lc-reset-dialog" );
	if ( ! dialog ) {
		return;
	}

	var titleEl   = dialog.querySelector( ".lc-reset-dialog__title" );
	var bodyEl    = dialog.querySelector( ".lc-reset-dialog__body" );
	var listEl    = dialog.querySelector( ".lc-reset-dialog__list" );
	var backupEl  = dialog.querySelector( ".lc-reset-dialog__backup" );
	var input     = dialog.querySelector( ".lc-reset-dialog__input" );
	var confirmEl = dialog.querySelector( ".lc-reset-dialog__confirm" );
	var triggers  = document.querySelectorAll( "[data-lc-reset-form]" );
	var cancels   = dialog.querySelectorAll( "[data-lc-reset-cancel]" );

	var targetFormId = "";
	var lastTrigger  = null;

	function checkedBoxes( form ) {
		if ( ! form ) {
			return [];
		}
		return Array.prototype.slice.call(
			form.querySelectorAll( 'input[name="lc_reset_types[]"]:checked' )
		);
	}

	function hasProSelected( form ) {
		return checkedBoxes( form ).some( function ( box ) {
			return box.getAttribute( "data-pro" ) === "1";
		} );
	}

	function fillBackup( form ) {
		if ( ! backupEl ) {
			return;
		}
		backupEl.textContent = "";
		if ( ! backup || ! hasProSelected( form ) ) {
			backupEl.hidden = true;
			return;
		}
		backupEl.appendChild( document.createTextNode( backup.text || "" ) );
		if ( backup.pro && backup.url ) {
			backupEl.appendChild( document.createTextNode( " " ) );
			var link = document.createElement( "a" );
			link.href = backup.url;
			link.target = "_blank";
			link.rel = "noopener noreferrer";
			link.textContent = backup.linkText || backup.url;
			backupEl.appendChild( link );
		}
		backupEl.hidden = false;
	}

	function fillList( form ) {
		listEl.innerHTML = "";
		var boxes = checkedBoxes( form );
		if ( ! boxes.length ) {
			listEl.hidden = true;
			return;
		}
		boxes.forEach( function ( box ) {
			var li = document.createElement( "li" );
			li.textContent = box.getAttribute( "data-label" ) || box.value;
			listEl.appendChild( li );
		} );
		listEl.hidden = false;
	}

	function open( trigger ) {
		targetFormId = trigger.getAttribute( "data-lc-reset-form" ) || "";
		lastTrigger  = trigger;

		var form = document.getElementById( targetFormId );
		var hasBoxes = checkedBoxes( form ).length > 0;

		titleEl.textContent   = trigger.getAttribute( "data-lc-reset-title" ) || "";
		bodyEl.textContent    = trigger.getAttribute( "data-lc-reset-body" ) || "";
		confirmEl.textContent = trigger.getAttribute( "data-lc-reset-confirm-label" ) || "";

		if ( hasBoxes && selectedLabel ) {
			bodyEl.textContent = trigger.getAttribute( "data-lc-reset-body" ) + " " + selectedLabel;
		}
		fillList( form );
		fillBackup( form );

		input.value = "";
		setLocked( true );
		dialog.hidden = false;
		input.focus();
	}

	function close() {
		dialog.hidden = true;
		input.value = "";
		setLocked( true );
		if ( lastTrigger ) {
			lastTrigger.focus();
		}
	}

	function setLocked( locked ) {
		confirmEl.disabled = locked;
	}

	function matches() {
		return input.value.trim() === phrase;
	}

	function submit() {
		if ( ! matches() ) {
			return;
		}
		var form = document.getElementById( targetFormId );
		if ( ! form ) {
			return;
		}
		var field = form.querySelector( 'input[name="lc_reset_confirm"]' );
		if ( field ) {
			field.value = phrase;
		}
		form.submit();
	}

	Array.prototype.forEach.call( triggers, function ( trigger ) {
		trigger.addEventListener( "click", function () {
			if ( trigger.disabled ) {
				return;
			}
			open( trigger );
		} );
	} );

	Array.prototype.forEach.call( cancels, function ( el ) {
		el.addEventListener( "click", close );
	} );

	input.addEventListener( "input", function () {
		setLocked( ! matches() );
	} );

	input.addEventListener( "keydown", function ( e ) {
		if ( e.key === "Enter" && matches() ) {
			e.preventDefault();
			submit();
		}
	} );

	confirmEl.addEventListener( "click", submit );

	document.addEventListener( "keydown", function ( e ) {
		if ( e.key === "Escape" && ! dialog.hidden ) {
			close();
		}
	} );

	// The wipe button runs only when at least one content checkbox is ticked.
	var wipeForm = document.getElementById( "lc-reset-everything-form" );
	var wipeBtn  = document.querySelector(
		'[data-lc-reset-form="lc-reset-everything-form"]'
	);
	if ( wipeForm && wipeBtn ) {
		var boxes = wipeForm.querySelectorAll( 'input[name="lc_reset_types[]"]' );
		var refresh = function () {
			wipeBtn.disabled = checkedBoxes( wipeForm ).length === 0;
		};
		Array.prototype.forEach.call( boxes, function ( box ) {
			box.addEventListener( "change", refresh );
		} );
		refresh();
	}
} )();
