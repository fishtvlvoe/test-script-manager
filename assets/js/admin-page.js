/**
 * Test Script Manager - Admin Page JavaScript
 *
 * Handles script list loading, creation form, Monaco editor, and search functionality.
 *
 * @package TestScriptManager
 */

(function($) {
	'use strict';

	let scripts = [];
	let createEditor = null;
	let editEditor = null;
	let currentEditId = null;
	let isLoadingScript = false;
	let currentScriptSlug = null;

	// Auto-save configuration
	var autoSaveTimeout = null;
	var AUTOSAVE_DELAY = 3000; // 3 seconds

	// Theme configuration - get saved theme or default to vs-dark
	var currentTheme = localStorage.getItem('tsm-editor-theme') || 'vs-dark';

	// Load scripts on page load
	$(document).ready(function() {
		loadScripts();
		initEventHandlers();
		initMonacoEditor();
		initThemeToggle();
	});

	/**
	 * Initialize theme toggle functionality.
	 */
	function initThemeToggle() {
		var toggleBtn = document.getElementById('tsm-theme-toggle');
		if (!toggleBtn) return;

		// Set initial label based on current theme
		updateThemeLabel(currentTheme);

		toggleBtn.addEventListener('click', function() {
			// Toggle between vs-dark and vs (light)
			var newTheme = currentTheme === 'vs-dark' ? 'vs' : 'vs-dark';

			// Apply to all Monaco editors globally
			if (typeof monaco !== 'undefined' && monaco.editor) {
				monaco.editor.setTheme(newTheme);
			}

			// Save preference to localStorage
			localStorage.setItem('tsm-editor-theme', newTheme);
			currentTheme = newTheme;

			// Update button label
			updateThemeLabel(newTheme);

			console.log('TSM: Theme changed to', newTheme);
		});
	}

	/**
	 * Update theme toggle button label.
	 *
	 * @param {string} theme Current theme name.
	 */
	function updateThemeLabel(theme) {
		var themeLabel = document.getElementById('tsm-theme-label');
		if (themeLabel) {
			// Button shows what clicking will switch TO
			themeLabel.textContent = theme === 'vs-dark' ? 'Light Theme' : 'Dark Theme';
		}
	}

	/**
	 * Initialize event handlers.
	 */
	function initEventHandlers() {
		// New script button
		$('#tsm-new-script').on('click', showCreateForm);

		// Cancel create
		$('#tsm-cancel-create').on('click', hideCreateForm);

		// Save script
		$('#tsm-save-script').on('click', saveScript);

		// Search
		$('#tsm-search').on('keyup', debounce(searchScripts, 300));

		// Auto-generate slug from name
		$('#tsm-script-name').on('keyup', function() {
			const name = $(this).val();
			const slug = name.toLowerCase()
				.replace(/[^a-z0-9]+/g, '-')
				.replace(/^-+|-+$/g, '');
			$('#tsm-script-slug').val(slug);
		});

		// Script list item click - edit mode
		$('#tsm-script-list').on('click', '.tsm-script-item', function() {
			const scriptId = $(this).data('id');
			openEditMode(scriptId);
		});

		// Back to list
		$('#tsm-back-to-list').on('click', hideEditSection);

		// Update script
		$('#tsm-update-script').on('click', updateScript);

		// Execute script
		$('#tsm-execute-script').on('click', executeCurrentScript);
	}

	/**
	 * Load scripts from REST API.
	 */
	function loadScripts() {
		$.ajax({
			url: tsmAdmin.restUrl + '/scripts',
			method: 'GET',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', tsmAdmin.nonce);
			},
			success: function(response) {
				scripts = response.scripts || [];
				renderScriptList(scripts);
			},
			error: function() {
				$('#tsm-script-list').html('<div class="tsm-loading">Failed to load scripts</div>');
			}
		});
	}

	/**
	 * Render script list.
	 *
	 * @param {Array} scriptList Array of script objects.
	 */
	function renderScriptList(scriptList) {
		const $list = $('#tsm-script-list');

		if (scriptList.length === 0) {
			$list.html('<div class="tsm-loading">No scripts yet. Create one to get started!</div>');
			return;
		}

		let html = '';
		scriptList.forEach(function(script) {
			const lastExecuted = script.last_executed_at
				? new Date(script.last_executed_at).toLocaleDateString()
				: 'Never';

			html += '<div class="tsm-script-item" data-id="' + script.id + '">';
			html += '<div class="tsm-script-name">' + escapeHtml(script.name) + '</div>';
			html += '<div class="tsm-script-meta">';
			html += script.language + ' &bull; Last run: ' + lastExecuted;
			html += '</div>';
			html += '</div>';
		});

		$list.html(html);
	}

	/**
	 * Show create form.
	 */
	function showCreateForm() {
		$('#tsm-welcome').hide();
		$('#tsm-create-form').show();
		$('#tsm-script-name').focus();

		// Initialize Monaco Editor if not already initialized
		if (!createEditor) {
			initMonacoEditor();
		}
	}

	/**
	 * Hide create form.
	 */
	function hideCreateForm() {
		$('#tsm-create-form').hide();
		$('#tsm-welcome').show();
		clearForm();
	}

	/**
	 * Save script via REST API.
	 */
	function saveScript() {
		const name = $('#tsm-script-name').val().trim();
		const slug = $('#tsm-script-slug').val().trim();
		const code = getEditorCode().trim();

		if (!name || !slug || !code) {
			showMessage('Please fill in all required fields.', 'error');
			return;
		}

		// Disable button during save
		$('#tsm-save-script').prop('disabled', true).text('Saving...');

		$.ajax({
			url: tsmAdmin.restUrl + '/scripts',
			method: 'POST',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', tsmAdmin.nonce);
			},
			data: {
				name: name,
				slug: slug,
				code: code,
				language: 'php'
			},
			success: function(response) {
				showMessage('Script created successfully!', 'success');
				clearForm();
				loadScripts();

				// Hide form after 1 second
				setTimeout(hideCreateForm, 1000);
			},
			error: function(xhr) {
				const message = xhr.responseJSON && xhr.responseJSON.error
					? xhr.responseJSON.error
					: 'Failed to create script';
				showMessage(message, 'error');
			},
			complete: function() {
				$('#tsm-save-script').prop('disabled', false).text('Save Script');
			}
		});
	}

	/**
	 * Search scripts by keyword.
	 */
	function searchScripts() {
		const keyword = $('#tsm-search').val().trim().toLowerCase();

		if (!keyword) {
			renderScriptList(scripts);
			return;
		}

		const filtered = scripts.filter(function(script) {
			return script.name.toLowerCase().indexOf(keyword) !== -1 ||
			       (script.code && script.code.toLowerCase().indexOf(keyword) !== -1);
		});

		renderScriptList(filtered);
	}

	/**
	 * Show message.
	 *
	 * @param {string} text    Message text.
	 * @param {string} type    Message type ('success' or 'error').
	 */
	function showMessage(text, type) {
		const $message = $('#tsm-message');
		$message
			.removeClass('success error')
			.addClass(type)
			.text(text)
			.show();
	}

	/**
	 * Clear form fields.
	 */
	function clearForm() {
		$('#tsm-script-name, #tsm-script-slug').val('');
		clearEditor();
		$('#tsm-message').hide();
	}

	/**
	 * Debounce function.
	 *
	 * @param {Function} func Function to debounce.
	 * @param {number}   wait Wait time in milliseconds.
	 * @return {Function} Debounced function.
	 */
	function debounce(func, wait) {
		let timeout;
		return function() {
			const context = this;
			const args = arguments;
			clearTimeout(timeout);
			timeout = setTimeout(function() {
				func.apply(context, args);
			}, wait);
		};
	}

	/**
	 * Escape HTML entities.
	 *
	 * @param {string} text Text to escape.
	 * @return {string} Escaped text.
	 */
	function escapeHtml(text) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return text.replace(/[&<>"']/g, function(m) {
			return map[m];
		});
	}

	/**
	 * Initialize Monaco Editor for create form.
	 */
	function initMonacoEditor() {
		// Add loading state
		$('#monaco-editor-create').addClass('loading').text('Loading editor...');

		// Load Monaco
		if (typeof tsmLoadMonaco === 'function') {
			tsmLoadMonaco(function() {
				createMonacoInstance();
			});
		} else {
			console.error('TSM: Monaco loader not available');
			$('#monaco-editor-create').text('Editor failed to load');
		}
	}

	/**
	 * Create Monaco editor instance for create form.
	 */
	function createMonacoInstance() {
		const container = document.getElementById('monaco-editor-create');
		if (!container) {
			console.error('TSM: Create editor container not found');
			return;
		}

		// Clear loading state
		$(container).removeClass('loading').text('');

		// Create editor with saved theme
		createEditor = tsmInitMonaco(container, {
			value: '<?php\n\n// Your test script here\n',
			language: 'php',
			theme: currentTheme
		});

		if (createEditor) {
			// Sync editor value to hidden input before form submission
			createEditor.onDidChangeModelContent(function() {
				$('#tsm-script-code').val(createEditor.getValue());
			});

			// Set initial value
			$('#tsm-script-code').val(createEditor.getValue());

			console.log('TSM: Monaco editor initialized');
		}
	}

	/**
	 * Get code from editor.
	 *
	 * @return {string} Code from Monaco editor or empty string.
	 */
	function getEditorCode() {
		if (createEditor) {
			return createEditor.getValue();
		}
		return $('#tsm-script-code').val() || '';
	}

	/**
	 * Clear editor content.
	 */
	function clearEditor() {
		if (createEditor) {
			createEditor.setValue('<?php\n\n// Your test script here\n');
		}
		$('#tsm-script-code').val('');
	}

	/**
	 * Schedule auto-save after debounce delay.
	 */
	function scheduleAutoSave() {
		clearTimeout(autoSaveTimeout);
		updateSaveIndicator('unsaved');

		autoSaveTimeout = setTimeout(function() {
			if (currentEditId) {
				performAutoSave(currentEditId);
			}
		}, AUTOSAVE_DELAY);
	}

	/**
	 * Perform auto-save via REST API.
	 *
	 * @param {number} scriptId Script ID to save.
	 */
	function performAutoSave(scriptId) {
		if (!editEditor) return;

		var content = editEditor.getValue();

		$.ajax({
			url: tsmAdmin.restUrl + '/scripts/' + scriptId,
			method: 'PUT',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', tsmAdmin.nonce);
			},
			contentType: 'application/json',
			data: JSON.stringify({
				code: content
			}),
			success: function(response) {
				if (response.success) {
					updateSaveIndicator('saved');
					// Update local cache
					var index = scripts.findIndex(function(s) { return s.id == scriptId; });
					if (index !== -1) {
						scripts[index].code = content;
					}
				} else {
					updateSaveIndicator('error', response.error);
				}
			},
			error: function(xhr) {
				var message = xhr.responseJSON && xhr.responseJSON.error
					? xhr.responseJSON.error
					: 'Auto-save failed';
				updateSaveIndicator('error', message);
			}
		});
	}

	/**
	 * Update save indicator UI.
	 *
	 * @param {string} status Status: 'saved', 'unsaved', 'error'.
	 * @param {string} message Optional error message.
	 */
	function updateSaveIndicator(status, message) {
		var indicator = document.getElementById('tsm-save-indicator');
		if (!indicator) return;

		if (status === 'saved') {
			indicator.textContent = 'Saved at ' + new Date().toLocaleTimeString();
			indicator.className = 'tsm-save-indicator saved';
		} else if (status === 'unsaved') {
			indicator.textContent = 'Unsaved changes';
			indicator.className = 'tsm-save-indicator unsaved';
		} else if (status === 'error') {
			indicator.textContent = 'Error: ' + (message || 'Save failed');
			indicator.className = 'tsm-save-indicator error';
		}
	}

	/**
	 * Open edit mode for a script.
	 *
	 * @param {number} scriptId Script ID.
	 */
	function openEditMode(scriptId) {
		// Hide other sections
		$('#tsm-welcome, #tsm-create-form').hide();
		$('#tsm-edit-section').show();

		// Mark active in list
		$('.tsm-script-item').removeClass('active');
		$('.tsm-script-item[data-id="' + scriptId + '"]').addClass('active');

		// Find script in cache
		const script = scripts.find(s => s.id == scriptId);
		if (script) {
			$('#tsm-edit-title').text('Edit: ' + script.name);
		}

		// Store current edit ID
		currentEditId = scriptId;
		$('#tsm-edit-script-id').val(scriptId);

		// Load script content from API
		loadScriptForEdit(scriptId);
	}

	/**
	 * Load script content for editing.
	 *
	 * @param {number} scriptId Script ID.
	 */
	function loadScriptForEdit(scriptId) {
		// Show loading in editor container
		const container = document.getElementById('monaco-editor-edit');
		if (!container) return;

		// Set loading flag to prevent auto-save trigger
		isLoadingScript = true;

		$.ajax({
			url: tsmAdmin.restUrl + '/scripts/' + scriptId,
			method: 'GET',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', tsmAdmin.nonce);
			},
			success: function(response) {
				if (response.script) {
					// Store script slug for execute function
					currentScriptSlug = response.script.slug || null;
					$('#tsm-current-script-slug').val(currentScriptSlug);
					createOrUpdateEditEditor(response.script.code || '<?php\n');
				}
			},
			error: function(xhr) {
				if (xhr.status === 404) {
					showEditMessage('Script not found. It may have been deleted.', 'error');
					hideEditSection();
				} else {
					showEditMessage('Failed to load script', 'error');
				}
			},
			complete: function() {
				isLoadingScript = false;
			}
		});
	}

	/**
	 * Create or update edit editor with content.
	 *
	 * @param {string} code Code content.
	 */
	function createOrUpdateEditEditor(code) {
		const container = document.getElementById('monaco-editor-edit');
		if (!container) return;

		// If editor exists, just update value
		if (editEditor) {
			isLoadingScript = true;
			editEditor.setValue(code);
			isLoadingScript = false;
			return;
		}

		// Wait for Monaco to be ready
		if (!tsmMonacoLoader.isReady()) {
			$(container).addClass('loading').text('Loading editor...');
			tsmLoadMonaco(function() {
				createEditEditorInstance(container, code);
			});
		} else {
			createEditEditorInstance(container, code);
		}
	}

	/**
	 * Create edit editor instance.
	 *
	 * @param {HTMLElement} container Container element.
	 * @param {string} code Code content.
	 */
	function createEditEditorInstance(container, code) {
		$(container).removeClass('loading').text('');

		// Create editor with saved theme
		editEditor = tsmInitMonaco(container, {
			value: code,
			language: 'php',
			theme: currentTheme
		});

		if (editEditor) {
			// Connect change event for auto-save
			editEditor.onDidChangeModelContent(function(e) {
				// Skip if loading content programmatically
				if (isLoadingScript || e.isFlush) return;
				scheduleAutoSave();
			});

			// Add keyboard shortcuts
			addEditorShortcuts(editEditor);

			console.log('TSM: Edit Monaco editor initialized');
		}
	}

	/**
	 * Hide edit section and show welcome.
	 */
	function hideEditSection() {
		// Cancel pending auto-save
		clearTimeout(autoSaveTimeout);

		$('#tsm-edit-section').hide();
		$('#tsm-welcome').show();
		$('#tsm-edit-message').hide();
		$('#tsm-save-indicator').text('').removeClass('saved unsaved error');
		$('.tsm-script-item').removeClass('active');
		currentEditId = null;
		currentScriptSlug = null;
	}

	/**
	 * Update script via REST API.
	 */
	function updateScript() {
		if (!currentEditId || !editEditor) {
			showEditMessage('No script selected', 'error');
			return;
		}

		const code = editEditor.getValue();

		// Disable button during save
		$('#tsm-update-script').prop('disabled', true).text('Saving...');

		$.ajax({
			url: tsmAdmin.restUrl + '/scripts/' + currentEditId,
			method: 'PUT',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', tsmAdmin.nonce);
			},
			data: {
				code: code
			},
			success: function(response) {
				showEditMessage('Script updated successfully!', 'success');

				// Update local cache
				const index = scripts.findIndex(s => s.id == currentEditId);
				if (index !== -1) {
					scripts[index].code = code;
				}
			},
			error: function(xhr) {
				const message = xhr.responseJSON && xhr.responseJSON.error
					? xhr.responseJSON.error
					: 'Failed to update script';
				showEditMessage(message, 'error');
			},
			complete: function() {
				$('#tsm-update-script').prop('disabled', false).text('Update Script');
			}
		});
	}

	/**
	 * Show message in edit section.
	 *
	 * @param {string} text Message text.
	 * @param {string} type Message type ('success' or 'error').
	 */
	function showEditMessage(text, type) {
		const $message = $('#tsm-edit-message');
		$message
			.removeClass('success error')
			.addClass(type)
			.text(text)
			.show();

		// Auto-hide after 3 seconds
		setTimeout(function() {
			$message.fadeOut();
		}, 3000);
	}

	/**
	 * Add keyboard shortcuts to Monaco editor.
	 *
	 * @param {Object} editor Monaco editor instance.
	 */
	function addEditorShortcuts(editor) {
		if (!editor || typeof monaco === 'undefined') return;

		// Ctrl+S / Cmd+S - Save immediately
		editor.addAction({
			id: 'tsm-save-script',
			label: 'Save Script',
			keybindings: [monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS],
			contextMenuGroupId: 'navigation',
			contextMenuOrder: 1.5,
			run: function() {
				// Cancel pending auto-save
				clearTimeout(autoSaveTimeout);
				// Save immediately
				if (currentEditId) {
					performAutoSave(currentEditId);
				}
			}
		});

		// Ctrl+Enter / Cmd+Enter - Execute script
		editor.addAction({
			id: 'tsm-execute-script',
			label: 'Execute Script',
			keybindings: [monaco.KeyMod.CtrlCmd | monaco.KeyCode.Enter],
			contextMenuGroupId: 'navigation',
			contextMenuOrder: 1.6,
			run: function() {
				executeCurrentScript();
			}
		});
	}

	/**
	 * Execute current script via API and open result page.
	 */
	function executeCurrentScript() {
		if (!currentEditId) {
			showEditMessage('Please save the script first', 'error');
			return;
		}

		// First, save any pending changes
		clearTimeout(autoSaveTimeout);
		if (editEditor) {
			// Synchronous save before execute
			var content = editEditor.getValue();
			$.ajax({
				url: tsmAdmin.restUrl + '/scripts/' + currentEditId,
				method: 'PUT',
				async: false, // Wait for save to complete
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', tsmAdmin.nonce);
				},
				contentType: 'application/json',
				data: JSON.stringify({ code: content })
			});
		}

		// Disable execute button during execution
		var btn = $('#tsm-execute-script');
		var originalText = btn.text();
		btn.prop('disabled', true).text('Executing...');

		// Call execute API
		$.ajax({
			url: tsmAdmin.restUrl + '/scripts/' + currentEditId + '/execute',
			method: 'POST',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', tsmAdmin.nonce);
			},
			data: {
				timeout: 30
			},
			success: function(response) {
				if (response.success && response.result && response.result.execution_id) {
					// Open result page in new tab
					var resultUrl = tsmAdmin.adminUrl + '?page=tsm-result&execution_id=' + response.result.execution_id;
					window.open(resultUrl, '_blank');
				} else {
					var error = response.error || 'Execution failed';
					showEditMessage(error, 'error');
				}
			},
			error: function(xhr) {
				var msg = xhr.responseJSON && xhr.responseJSON.error
					? xhr.responseJSON.error
					: 'Execution failed';
				showEditMessage(msg, 'error');
			},
			complete: function() {
				btn.prop('disabled', false).text(originalText);
			}
		});
	}

})(jQuery);
