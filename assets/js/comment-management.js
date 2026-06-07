( function () {
	'use strict';

	const config = window.CommentManagement;

	if ( ! config ) {
		return;
	}

	const setBusy = ( controls, busy ) => {
		controls.querySelectorAll( 'button, textarea' ).forEach( ( element ) => {
			element.disabled = busy;
		} );
	};

	const setStatus = ( controls, message, isError = false ) => {
		const status = controls.querySelector( '.cm-status' );
		status.textContent = message;
		status.classList.toggle( 'cm-status-error', isError );
	};

	const request = async ( controls, operation, content = null ) => {
		const formData = new FormData();
		formData.append( 'action', config.action );
		formData.append( 'nonce', config.nonce );
		formData.append( 'commentId', controls.dataset.commentId );
		formData.append( 'operation', operation );
		formData.append( 'renderer', controls.dataset.renderer || 'default' );

		if ( content !== null ) {
			formData.append( 'content', content );
		}

		if ( operation === 'delete' ) {
			formData.append( 'confirmation', 'DELETE' );
		}

		setBusy( controls, true );
		setStatus(
			controls,
			operation === 'edit' ? config.strings.saving : config.strings.working
		);

		try {
			const response = await fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData,
				headers: {
					'X-Requested-With': 'XMLHttpRequest',
				},
			} );
			const payload = await response.json();

			if ( ! response.ok || ! payload.success ) {
				throw new Error( payload.data?.message || config.strings.requestFailed );
			}

			if ( payload.data.action === 'edit' ) {
				const commentNode = document.getElementById( `comment-${ controls.dataset.commentId }` );
				const contentElement =
					commentNode?.querySelector( '.wpd-comment-text, .cm-managed-content' ) ||
					controls.previousElementSibling;
				contentElement.innerHTML = payload.data.contentHtml;
				controls.dataset.content = content;
				controls.querySelector( '.cm-editor' ).hidden = true;
				setStatus( controls, payload.data.message );
				return;
			}

			const commentId = controls.dataset.commentId;
			const commentNode = document.getElementById( `comment-${ commentId }` );
			const commentRoot =
				commentNode?.closest( '.wpd-comment, .wc-comment, .comment' ) ||
				commentNode ||
				controls.closest( `#wc-comment-${ commentId }, [data-comment-id="${ commentId }"].comment, .wc-comment, .comment` ) ||
				controls.parentElement;

			commentRoot.remove();
			controls.remove();
		} catch ( error ) {
			setStatus(
				controls,
				error instanceof Error ? error.message : config.strings.requestFailed,
				true
			);
		} finally {
			setBusy( controls, false );
		}
	};

	document.addEventListener( 'click', ( event ) => {
		const target = event.target;

		if ( ! ( target instanceof HTMLElement ) ) {
			return;
		}

		const controls = target.closest( '[data-comment-management-controls]' );

		if ( ! controls ) {
			return;
		}

		if ( target.matches( '[data-operation="edit"]' ) ) {
			const editor = controls.querySelector( '.cm-editor' );
			const textarea = controls.querySelector( '.cm-editor-content' );
			textarea.value = controls.dataset.content || '';
			editor.hidden = false;
			textarea.focus();
			return;
		}

		if ( target.matches( '.cm-cancel' ) ) {
			controls.querySelector( '.cm-editor' ).hidden = true;
			setStatus( controls, '' );
			return;
		}

		if ( target.matches( '.cm-save' ) ) {
			request( controls, 'edit', controls.querySelector( '.cm-editor-content' ).value );
			return;
		}

		const operation = target.dataset.operation;

		if ( ! operation || operation === 'edit' ) {
			return;
		}

		if ( operation === 'delete' && ! window.confirm( config.strings.confirmDelete ) ) {
			return;
		}

		request( controls, operation );
	} );
}() );
