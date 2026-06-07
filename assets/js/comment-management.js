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

	const closeMenus = ( except = null ) => {
		document.querySelectorAll( '.cm-menu-toggle[aria-expanded="true"]' )
			.forEach( ( toggle ) => {
				if ( toggle === except ) {
					return;
				}

				toggle.setAttribute( 'aria-expanded', 'false' );
				const menu = document.getElementById(
					toggle.getAttribute( 'aria-controls' )
				);

				if ( menu ) {
					menu.hidden = true;
				}
			} );
	};

	const getCommentRoot = ( controls ) => {
		const commentId = controls.dataset.commentId;
		const commentNode = document.getElementById( `comment-${ commentId }` );

		return (
			commentNode?.closest( '.wpd-comment, .wc-comment, .comment' ) ||
			commentNode ||
			controls.closest(
				`#wc-comment-${ commentId }, [data-comment-id="${ commentId }"].comment, .wc-comment, .comment`
			) ||
			controls.parentElement
		);
	};

	const getCommentsRegion = ( element ) => (
		element.closest( '#wpdcom, #comments, .comments-area' )
	);

	const refreshCommentsRegion = async ( controls ) => {
		const currentRegion = getCommentsRegion( controls );

		if ( ! currentRegion || currentRegion.id === 'wpdcom' ) {
			window.location.reload();
			return;
		}

		try {
			const formValues = Array.from(
				currentRegion.querySelectorAll( 'input, textarea, select' )
			).map( ( field ) => ( {
				key: field.name || field.id,
				type: field.type,
				value: field.value,
				checked: field.checked,
			} ) ).filter( ( field ) => field.key );
			const response = await fetch( window.location.href, {
				credentials: 'same-origin',
				cache: 'no-store',
				headers: {
					'X-Requested-With': 'XMLHttpRequest',
				},
			} );

			if ( ! response.ok ) {
				throw new Error( config.strings.requestFailed );
			}

			const documentCopy = new DOMParser().parseFromString(
				await response.text(),
				'text/html'
			);
			const selector = currentRegion.id
				? `#${ CSS.escape( currentRegion.id ) }`
				: '.comments-area';
			const replacement = documentCopy.querySelector( selector );

			if ( ! replacement ) {
				throw new Error( config.strings.requestFailed );
			}

			currentRegion.replaceWith( replacement );

			formValues.forEach( ( savedField ) => {
				const field = Array.from(
					replacement.querySelectorAll( 'input, textarea, select' )
				).find( ( candidate ) => (
					candidate.name === savedField.key ||
					candidate.id === savedField.key
				) );

				if ( ! field ) {
					return;
				}

				if ( savedField.type === 'checkbox' || savedField.type === 'radio' ) {
					field.checked = savedField.checked;
				} else {
					field.value = savedField.value;
				}
			} );

			if (
				! replacement.querySelector( '.comment, .wpd-comment' ) &&
				replacement.previousElementSibling?.tagName === 'HR'
			) {
				replacement.previousElementSibling.remove();
			}
		} catch ( error ) {
			window.location.reload();
		}
	};

	const updateStatusBadge = ( controls, status, label ) => {
		const badge = controls.querySelector( '.cm-status-badge' );
		const commentRoot = getCommentRoot( controls );

		badge.dataset.commentStatus = status;
		badge.textContent = label;
		badge.hidden = ! label;
		commentRoot?.classList.toggle( 'cm-comment-moderated', Boolean( label ) );
		controls.querySelectorAll( '.cm-actions button' ).forEach( ( button ) => {
			button.disabled = Boolean( label );
		} );
	};

	const renderHistory = ( controls, history ) => {
		const panel = controls.querySelector( '.cm-history' );
		panel.replaceChildren();
		panel.hidden = false;

		const heading = document.createElement( 'strong' );
		heading.className = 'cm-history-title';
		heading.textContent = config.strings.historyTitle;
		panel.append( heading );

		if ( ! history.length ) {
			const empty = document.createElement( 'p' );
			empty.textContent = config.strings.historyEmpty;
			panel.append( empty );
			return;
		}

		const list = document.createElement( 'ol' );
		list.className = 'cm-history-list';

		history.forEach( ( revision ) => {
			const item = document.createElement( 'li' );
			const metadata = document.createElement( 'div' );
			const content = document.createElement( 'pre' );
			const restore = document.createElement( 'button' );

			metadata.className = 'cm-history-meta';
			metadata.textContent = `${ revision.date } · ${ revision.user }`;
			content.className = 'cm-history-content';
			content.textContent = revision.content;
			restore.type = 'button';
			restore.className = 'cm-restore';
			restore.dataset.revisionId = revision.id;
			restore.textContent = config.strings.restore;

			item.append( metadata, content, restore );
			list.append( item );
		} );

		panel.append( list );
	};

	let undoTimer = null;

	const showUndo = ( controls, message, reference ) => {
		let notice = document.querySelector( '.cm-undo-notice' );

		if ( ! notice ) {
			notice = document.createElement( 'div' );
			notice.className = 'cm-undo-notice';
			notice.setAttribute( 'role', 'status' );
			document.body.append( notice );
		}

		notice.replaceChildren();

		const text = document.createElement( 'span' );
		const button = document.createElement( 'button' );
		text.textContent = message;
		button.type = 'button';
		button.textContent = config.strings.undo;
		button.addEventListener( 'click', async () => {
			window.clearTimeout( undoTimer );
			button.disabled = true;

			try {
				const payload = await request(
					controls,
					'undo',
					null,
					reference
				);
				updateStatusBadge(
					controls,
					payload.data.status,
					payload.data.statusLabel
				);
				setStatus( controls, payload.data.message );
				notice.remove();
				await refreshCommentsRegion( controls );
			} catch ( error ) {
				setStatus(
					controls,
					error instanceof Error ? error.message : config.strings.requestFailed,
					true
				);
			}
		} );
		notice.append( text, button );

		window.clearTimeout( undoTimer );
		undoTimer = window.setTimeout( async () => {
			text.textContent = config.strings.undoExpired;
			button.remove();
			await refreshCommentsRegion( controls );
			notice.remove();
		}, 10000 );
	};

	const matchCommentTextareaStyle = ( textarea ) => {
		const source = Array.from(
			document.querySelectorAll(
				'#commentform textarea[name="comment"], form.comment-form textarea[name="comment"], .wpd-form textarea, textarea[name="comment"]'
			)
		).find( ( candidate ) => (
			candidate !== textarea &&
			! candidate.closest( '[data-comment-management-controls]' )
		) );

		if ( ! source ) {
			return;
		}

		const sourceStyle = window.getComputedStyle( source );
		const properties = [
			'appearance',
			'background-color',
			'background-image',
			'background-position',
			'background-repeat',
			'background-size',
			'border-bottom-color',
			'border-bottom-style',
			'border-bottom-width',
			'border-left-color',
			'border-left-style',
			'border-left-width',
			'border-right-color',
			'border-right-style',
			'border-right-width',
			'border-top-color',
			'border-top-style',
			'border-top-width',
			'border-radius',
			'box-shadow',
			'color',
			'font-family',
			'font-size',
			'font-style',
			'font-weight',
			'letter-spacing',
			'line-height',
			'outline',
			'padding-bottom',
			'padding-left',
			'padding-right',
			'padding-top',
			'resize',
			'text-align',
			'text-indent',
			'text-transform',
			'transition',
		];

		properties.forEach( ( property ) => {
			textarea.style.setProperty(
				property,
				sourceStyle.getPropertyValue( property )
			);
		} );

		const sourceHeight = Number.parseFloat( sourceStyle.height );

		if ( Number.isFinite( sourceHeight ) && sourceHeight > 0 ) {
			textarea.style.minHeight = sourceStyle.height;
		}
	};

	const request = async (
		controls,
		operation,
		content = null,
		reference = null
	) => {
		const formData = new FormData();
		formData.append( 'action', config.action );
		formData.append( 'nonce', config.nonce );
		formData.append( 'commentId', controls.dataset.commentId );
		formData.append( 'operation', operation );
		formData.append( 'renderer', controls.dataset.renderer || 'default' );

		if ( content !== null ) {
			formData.append( 'content', content );
		}

		if ( reference !== null ) {
			formData.append( 'reference', reference );
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

			return payload;
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
			closeMenus();
			return;
		}

		if ( target.closest( '.cm-menu-toggle' ) ) {
			const toggle = target.closest( '.cm-menu-toggle' );
			const menu = document.getElementById(
				toggle.getAttribute( 'aria-controls' )
			);
			const opening = toggle.getAttribute( 'aria-expanded' ) !== 'true';

			closeMenus( toggle );
			toggle.setAttribute( 'aria-expanded', String( opening ) );
			menu.hidden = ! opening;

			if ( opening ) {
				menu.querySelector( '[role="menuitem"]' )?.focus();
			}
			return;
		}

		if ( target.closest( '.cm-actions' ) ) {
			closeMenus();
		}

		if ( target.matches( '[data-operation="edit"]' ) ) {
			const editor = controls.querySelector( '.cm-editor' );
			const textarea = controls.querySelector( '.cm-editor-content' );
			textarea.value = controls.dataset.content || '';
			matchCommentTextareaStyle( textarea );
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
			request(
				controls,
				'edit',
				controls.querySelector( '.cm-editor-content' ).value
			).then( ( payload ) => {
				const commentNode = document.getElementById(
					`comment-${ controls.dataset.commentId }`
				);
				const contentElement =
					commentNode?.querySelector( '.wpd-comment-text, .cm-managed-content' ) ||
					controls.previousElementSibling;
				contentElement.innerHTML = payload.data.contentHtml;
				controls.dataset.content = payload.data.contentRaw;
				controls.querySelector( '.cm-editor' ).hidden = true;
				setStatus( controls, payload.data.message );
			} ).catch( ( error ) => {
				setStatus( controls, error.message, true );
			} );
			return;
		}

		if ( target.matches( '[data-operation="history"]' ) ) {
			request( controls, 'history' ).then( ( payload ) => {
				renderHistory( controls, payload.data.history );
				setStatus( controls, '' );
			} ).catch( ( error ) => {
				setStatus( controls, error.message, true );
			} );
			return;
		}

		if ( target.matches( '.cm-restore' ) ) {
			request(
				controls,
				'restore_revision',
				null,
				target.dataset.revisionId
			).then( ( payload ) => {
				const contentElement =
					getCommentRoot( controls )?.querySelector(
						'.wpd-comment-text, .cm-managed-content'
					) ||
					controls.previousElementSibling;
				contentElement.innerHTML = payload.data.contentHtml;
				controls.dataset.content = payload.data.contentRaw;
				controls.querySelector( '.cm-history' ).hidden = true;
				setStatus( controls, payload.data.message );
			} ).catch( ( error ) => {
				setStatus( controls, error.message, true );
			} );
			return;
		}

		const operation = target.dataset.operation;

		if ( ! operation || operation === 'edit' ) {
			return;
		}

		if ( operation === 'delete' && ! window.confirm( config.strings.confirmDelete ) ) {
			return;
		}

		request( controls, operation ).then( async ( payload ) => {
			if ( operation === 'delete' ) {
				await refreshCommentsRegion( controls );
				return;
			}

			updateStatusBadge(
				controls,
				payload.data.status,
				payload.data.statusLabel
			);
			setStatus( controls, payload.data.message );
			showUndo(
				controls,
				payload.data.message,
				payload.data.undoReference
			);
		} ).catch( ( error ) => {
			setStatus( controls, error.message, true );
		} );
	} );

	document.addEventListener( 'keydown', ( event ) => {
		const target = event.target;

		if ( ! ( target instanceof HTMLElement ) ) {
			return;
		}

		if ( event.key === 'Escape' ) {
			const openToggle = document.querySelector(
				'.cm-menu-toggle[aria-expanded="true"]'
			);
			closeMenus();
			openToggle?.focus();
			return;
		}

		const menu = target.closest( '.cm-actions[role="menu"]' );

		if (
			! menu ||
			! [ 'ArrowDown', 'ArrowUp', 'Home', 'End' ].includes( event.key )
		) {
			return;
		}

		const items = Array.from(
			menu.querySelectorAll( '[role="menuitem"]:not(:disabled)' )
		);

		if ( ! items.length ) {
			return;
		}

		event.preventDefault();

		const currentIndex = items.indexOf( target );
		let nextIndex;

		if ( event.key === 'Home' ) {
			nextIndex = 0;
		} else if ( event.key === 'End' ) {
			nextIndex = items.length - 1;
		} else if ( event.key === 'ArrowDown' ) {
			nextIndex = currentIndex < 0
				? 0
				: ( currentIndex + 1 ) % items.length;
		} else {
			nextIndex = currentIndex <= 0
				? items.length - 1
				: currentIndex - 1;
		}

		items[ nextIndex ].focus();
	} );
}() );
