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
	let categories = []; // Phase 7, Plan 04
	let bulkModeActive = false; // Phase 7, Plan 05

	// Auto-save configuration
	var autoSaveTimeout = null;
	var AUTOSAVE_DELAY = 3000; // 3 seconds

	// Theme configuration - get saved theme or default to vs-dark
	var currentTheme = localStorage.getItem('tsm-editor-theme') || 'vs-dark';

	// Expose function to update editor from version restore
	window.tsmUpdateEditorCode = function(code) {
		if (editEditor) {
			isLoadingScript = true;
			editEditor.setValue(code);
			isLoadingScript = false;
			updateSaveIndicator('saved');
		}
	};

	// Load scripts on page load
	$(document).ready(function() {
		loadScripts();
		loadCategories(); // Phase 7, Plan 04
		initEventHandlers();
		initBulkMode(); // Phase 7, Plan 05
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

		// Category filter (Phase 7, Plan 04)
		$('#tsm-category-select').on('change', filterByCategory);

		// Template modal (Phase 7, Plan 04)
		$('#tsm-new-from-template').on('click', openTemplateModal);
		$('#tsm-template-modal-close').on('click', closeTemplateModal);
		$('#tsm-template-modal-overlay').on('click', function(e) {
			if (e.target === this) closeTemplateModal();
		});

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

		// Background execute button
		$('#tsm-execute-background-btn').on('click', executeBackground);

		// Cancel buttons (event delegation for dynamically added buttons)
		$(document).on('click', '.tsm-cancel-btn', function(e) {
			e.stopPropagation(); // Prevent triggering execution item click
			var executionId = $(this).data('execution-id');
			if (executionId) {
				cancelExecution(executionId);
			}
		});

		// Click execution item to view result
		$(document).on('click', '.tsm-execution-item', function(e) {
			// Don't trigger if clicking cancel button
			if ($(e.target).hasClass('tsm-cancel-btn')) {
				return;
			}

			var executionId = $(this).data('execution-id');
			// Only open result for completed executions
			var $badge = $(this).find('.tsm-status-badge');
			var status = $badge.attr('class').match(/\b(success|error|fatal_error|cancelled)\b/);
			if (status && executionId) {
				var resultUrl = tsmAdmin.adminUrl + '?page=tsm-result&execution_id=' + executionId;
				window.open(resultUrl, '_blank');
			}
		});
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
	 * Load categories from REST API (Phase 7, Plan 04).
	 */
	function loadCategories() {
		$.ajax({
			url: tsmAdmin.restUrl + '/categories',
			method: 'GET',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', tsmAdmin.nonce);
			},
			success: function(response) {
				categories = response.categories || [];
				renderCategoryDropdown(categories);
			},
			error: function() {
				console.error('TSM: Failed to load categories');
			}
		});
	}

	/**
	 * Render category dropdown options (Phase 7, Plan 04).
	 *
	 * @param {Array} categoryList Array of category objects.
	 */
	function renderCategoryDropdown(categoryList) {
		var $select = $('#tsm-category-select');
		// Keep the "All Categories" option
		$select.find('option:not(:first)').remove();

		categoryList.forEach(function(category) {
			$select.append('<option value="' + category.id + '">' + escapeHtml(category.name) + '</option>');
		});
	}

	/**
	 * Filter scripts by selected category (Phase 7, Plan 04).
	 */
	function filterByCategory() {
		var categoryId = $('#tsm-category-select').val();
		var keyword = $('#tsm-search').val().trim().toLowerCase();

		if (!categoryId) {
			// Show all scripts (apply keyword filter if any)
			if (keyword) {
				searchScripts();
			} else {
				renderScriptList(scripts);
			}
			return;
		}

		// Fetch script IDs for this category
		$.ajax({
			url: tsmAdmin.restUrl + '/categories/' + categoryId + '/scripts',
			method: 'GET',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', tsmAdmin.nonce);
			},
			success: function(response) {
				var scriptIds = response.script_ids || [];

				// Filter local scripts array
				var filtered = scripts.filter(function(script) {
					var inCategory = scriptIds.indexOf(parseInt(script.id)) !== -1;
					if (!inCategory) return false;

					// Also apply keyword filter if any
					if (keyword) {
						return script.name.toLowerCase().indexOf(keyword) !== -1 ||
						       (script.code && script.code.toLowerCase().indexOf(keyword) !== -1);
					}
					return true;
				});

				renderScriptList(filtered);
			},
			error: function() {
				console.error('TSM: Failed to filter by category');
			}
		});
	}

	// ========================================
	// Phase 7, Plan 04: Template Functions
	// ========================================

	var templates = null; // Cache templates

	/**
	 * Open template selection modal (Phase 7, Plan 04).
	 */
	function openTemplateModal() {
		$('#tsm-template-modal-overlay').addClass('active');
		fetchTemplates();
	}

	/**
	 * Close template selection modal (Phase 7, Plan 04).
	 */
	function closeTemplateModal() {
		$('#tsm-template-modal-overlay').removeClass('active');
	}

	/**
	 * Fetch templates from REST API (Phase 7, Plan 04).
	 */
	function fetchTemplates() {
		var $list = $('#tsm-template-list');

		// Use cache if available
		if (templates !== null) {
			renderTemplateList(templates);
			return;
		}

		$list.html('<div class="tsm-loading">Loading templates...</div>');

		$.ajax({
			url: tsmAdmin.restUrl + '/templates',
			method: 'GET',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', tsmAdmin.nonce);
			},
			success: function(response) {
				templates = response.templates || [];
				renderTemplateList(templates);
			},
			error: function() {
				$list.html('<div class="tsm-error">Failed to load templates.</div>');
			}
		});
	}

	/**
	 * Render template list in modal (Phase 7, Plan 04).
	 *
	 * @param {Array} templateList Array of template objects.
	 */
	function renderTemplateList(templateList) {
		var $list = $('#tsm-template-list');

		if (templateList.length === 0) {
			$list.html('<div class="tsm-no-templates">No templates available.</div>');
			return;
		}

		var html = '';
		templateList.forEach(function(template) {
			html += '<div class="tsm-template-item" data-template-id="' + escapeHtml(template.id) + '">';
			html += '<div class="tsm-template-info">';
			html += '<h4>' + escapeHtml(template.name) + '</h4>';
			html += '<p>' + escapeHtml(template.description) + '</p>';
			html += '</div>';
			html += '<button type="button" class="button button-primary tsm-use-template">';
			html += '使用此模板';
			html += '</button>';
			html += '</div>';
		});

		$list.html(html);

		// Bind click handlers
		$list.find('.tsm-use-template').on('click', function() {
			var templateId = $(this).closest('.tsm-template-item').data('template-id');
			createFromTemplate(templateId);
		});
	}

	/**
	 * Create a new script from template (Phase 7, Plan 04).
	 *
	 * @param {string} templateId Template ID.
	 */
	function createFromTemplate(templateId) {
		var scriptName = prompt('請輸入腳本名稱:');
		if (!scriptName || !scriptName.trim()) {
			return;
		}

		scriptName = scriptName.trim();
		var $btn = $('.tsm-template-item[data-template-id="' + templateId + '"] .tsm-use-template');
		$btn.prop('disabled', true).text('Creating...');

		$.ajax({
			url: tsmAdmin.restUrl + '/templates/' + templateId + '/create',
			method: 'POST',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', tsmAdmin.nonce);
			},
			contentType: 'application/json',
			data: JSON.stringify({
				name: scriptName
			}),
			success: function(response) {
				if (response.success && response.script) {
					// Close modal
					closeTemplateModal();

					// Reload script list
					loadScripts();

					// Open the new script in edit mode after brief delay
					setTimeout(function() {
						openEditMode(response.script.id);
					}, 500);

					showNotice('success', 'Script created from template: ' + scriptName);
				} else {
					showNotice('error', response.error || 'Failed to create script from template');
				}
			},
			error: function(xhr) {
				var msg = xhr.responseJSON && xhr.responseJSON.error
					? xhr.responseJSON.error
					: 'Failed to create script from template';
				showNotice('error', msg);
			},
			complete: function() {
				$btn.prop('disabled', false).text('使用此模板');
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

		// Load execution history for this script
		loadExecutionHistory(scriptId);

		// Load script categories (Phase 7, Plan 04)
		loadScriptCategories(scriptId);

		// Initialize version history (Phase 6, Plan 02)
		if (typeof tsmVersionHistory !== 'undefined') {
			tsmVersionHistory.init(scriptId);
		}
	}

	/**
	 * Load categories for a script and render checkboxes (Phase 7, Plan 04).
	 *
	 * @param {number} scriptId Script ID.
	 */
	function loadScriptCategories(scriptId) {
		var $container = $('#tsm-category-checkboxes');
		$container.html('<span class="tsm-loading">Loading...</span>');

		// First ensure we have categories loaded
		if (categories.length === 0) {
			$.ajax({
				url: tsmAdmin.restUrl + '/categories',
				method: 'GET',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', tsmAdmin.nonce);
				},
				success: function(response) {
					categories = response.categories || [];
					fetchScriptCategoriesAndRender(scriptId, $container);
				},
				error: function() {
					$container.html('<span class="tsm-error">Failed to load categories</span>');
				}
			});
		} else {
			fetchScriptCategoriesAndRender(scriptId, $container);
		}
	}

	/**
	 * Fetch script's assigned categories and render checkboxes (Phase 7, Plan 04).
	 *
	 * @param {number} scriptId Script ID.
	 * @param {jQuery} $container Container element.
	 */
	function fetchScriptCategoriesAndRender(scriptId, $container) {
		$.ajax({
			url: tsmAdmin.restUrl + '/scripts/' + scriptId + '/categories',
			method: 'GET',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', tsmAdmin.nonce);
			},
			success: function(response) {
				var assignedIds = (response.categories || []).map(function(c) {
					return parseInt(c.id);
				});
				renderCategoryCheckboxes($container, assignedIds);
			},
			error: function() {
				// If 404, script has no categories - render empty checkboxes
				renderCategoryCheckboxes($container, []);
			}
		});
	}

	/**
	 * Render category checkboxes (Phase 7, Plan 04).
	 *
	 * @param {jQuery} $container Container element.
	 * @param {Array} assignedIds Array of assigned category IDs.
	 */
	function renderCategoryCheckboxes($container, assignedIds) {
		if (categories.length === 0) {
			$container.html('<span class="tsm-no-categories">No categories available.</span>');
			return;
		}

		var html = '';
		categories.forEach(function(category) {
			var checked = assignedIds.indexOf(parseInt(category.id)) !== -1 ? ' checked' : '';
			html += '<label class="tsm-category-checkbox">';
			html += '<input type="checkbox" name="tsm_categories[]" value="' + category.id + '"' + checked + '>';
			html += ' ' + escapeHtml(category.name);
			html += '</label>';
		});

		$container.html(html);

		// Add change handler for auto-save categories
		$container.find('input[type="checkbox"]').on('change', function() {
			saveScriptCategories(currentEditId);
		});
	}

	/**
	 * Save script categories (Phase 7, Plan 04).
	 *
	 * @param {number} scriptId Script ID.
	 */
	function saveScriptCategories(scriptId) {
		var selectedIds = [];
		$('#tsm-category-checkboxes input:checked').each(function() {
			selectedIds.push(parseInt($(this).val()));
		});

		$.ajax({
			url: tsmAdmin.restUrl + '/scripts/' + scriptId + '/categories',
			method: 'PUT',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', tsmAdmin.nonce);
			},
			contentType: 'application/json',
			data: JSON.stringify({
				category_ids: selectedIds
			}),
			success: function(response) {
				console.log('TSM: Categories saved');
			},
			error: function(xhr) {
				console.error('TSM: Failed to save categories', xhr.responseJSON);
			}
		});
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
		// Cleanup version history (Phase 6, Plan 02)
		if (typeof tsmVersionHistory !== 'undefined') {
			tsmVersionHistory.destroy();
		}

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
	 * Load execution history for the current script.
	 *
	 * @param {number} scriptId Script ID.
	 */
	function loadExecutionHistory(scriptId) {
		var $list = $('#tsm-execution-list');
		$list.html('<div class="tsm-loading">Loading...</div>');

		$.ajax({
			url: tsmAdmin.restUrl + '/executions',
			method: 'GET',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', tsmAdmin.nonce);
			},
			data: {
				script_id: scriptId,
				per_page: 10
			},
			success: function(response) {
				var executions = response.executions || [];
				if (executions.length === 0) {
					$list.html('<div class="tsm-no-executions">No executions yet.</div>');
					return;
				}

				var html = '';
				executions.forEach(function(execution) {
					html += renderExecutionItem(execution);
				});
				$list.html(html);
			},
			error: function() {
				$list.html('<div class="tsm-loading">Failed to load execution history.</div>');
			}
		});
	}

	/**
	 * Render a single execution history item.
	 *
	 * @param {Object} execution Execution object from API.
	 * @return {string} HTML string.
	 */
	function renderExecutionItem(execution) {
		// Mode icon: cloud for background, lightning for sync
		var modeIcon = execution.execution_mode === 'background'
			? '<span class="dashicons dashicons-cloud tsm-execution-mode-icon background" title="Background execution"></span>'
			: '<span class="dashicons dashicons-flash tsm-execution-mode-icon sync" title="Sync execution"></span>';

		// Status badge with retry count
		var statusText = execution.status;
		if (execution.status === 'retry' && execution.retry_count > 0) {
			statusText = 'Retry ' + execution.retry_count + '/3';
		}
		var statusBadge = '<span class="tsm-status-badge ' + execution.status + '">' + escapeHtml(statusText) + '</span>';

		// Cancel button for cancellable states (only for background executions)
		var isCancellable = ['pending', 'running', 'retry'].indexOf(execution.status) !== -1;
		var cancelBtn = isCancellable && execution.execution_mode === 'background'
			? '<button type="button" class="tsm-cancel-btn" data-execution-id="' + execution.id + '" title="Cancel execution">&times;</button>'
			: '';

		// Format time
		var timeStr = execution.executed_at
			? formatExecutionTime(execution.executed_at)
			: 'Pending';

		return '<div class="tsm-execution-item" data-execution-id="' + execution.id + '">' +
			'<div class="tsm-execution-item-left">' +
				modeIcon +
				'<span class="tsm-execution-time">' + timeStr + '</span>' +
			'</div>' +
			'<div class="tsm-execution-item-right">' +
				statusBadge +
				cancelBtn +
			'</div>' +
		'</div>';
	}

	/**
	 * Format execution time for display.
	 *
	 * @param {string} dateStr ISO date string.
	 * @return {string} Formatted time string.
	 */
	function formatExecutionTime(dateStr) {
		var date = new Date(dateStr);
		var now = new Date();
		var diff = now - date;

		// If today, show time only
		if (date.toDateString() === now.toDateString()) {
			return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
		}

		// If yesterday
		var yesterday = new Date(now);
		yesterday.setDate(yesterday.getDate() - 1);
		if (date.toDateString() === yesterday.toDateString()) {
			return 'Yesterday ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
		}

		// Otherwise show date
		return date.toLocaleDateString();
	}

	/**
	 * Execute script in background.
	 */
	function executeBackground() {
		if (!currentEditId) {
			showNotice('error', tsmAdmin.selectScriptFirst || 'Please select a script first.');
			return;
		}

		// Auto-save before execute (same as sync execute)
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

		var btn = document.getElementById('tsm-execute-background-btn');
		btn.disabled = true;
		btn.innerHTML = '<span class="dashicons dashicons-update spin"></span> ' + (tsmAdmin.scheduling || 'Scheduling...');

		$.ajax({
			url: tsmAdmin.restUrl + '/scripts/' + currentEditId + '/execute-background',
			method: 'POST',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', tsmAdmin.nonce);
			},
			success: function(response) {
				if (response.success) {
					// Show success message
					showNotice('success', tsmAdmin.backgroundScheduled || 'Script scheduled for background execution. You will be notified when complete.');

					// Refresh execution history
					loadExecutionHistory(currentEditId);
				} else {
					showNotice('error', response.error || 'Failed to schedule background execution.');
				}
			},
			error: function(xhr) {
				var msg = xhr.responseJSON && xhr.responseJSON.error
					? xhr.responseJSON.error
					: 'Network error. Please try again.';
				showNotice('error', msg);
			},
			complete: function() {
				btn.disabled = false;
				btn.innerHTML = '<span class="dashicons dashicons-cloud"></span> ' + (tsmAdmin.background || 'Background');
			}
		});
	}

	/**
	 * Cancel execution with confirmation.
	 *
	 * @param {number} executionId Execution ID to cancel.
	 */
	function cancelExecution(executionId) {
		// Confirmation dialog required per CONTEXT.md
		var confirmed = confirm(tsmAdmin.confirmCancel || 'Are you sure you want to cancel this execution?');
		if (!confirmed) {
			return;
		}

		$.ajax({
			url: tsmAdmin.restUrl + '/executions/' + executionId + '/cancel',
			method: 'POST',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', tsmAdmin.nonce);
			},
			success: function(response) {
				if (response.success) {
					showNotice('success', tsmAdmin.executionCancelled || 'Execution cancelled.');

					// Update the item in history without full reload
					var $item = $('.tsm-execution-item[data-execution-id="' + executionId + '"]');
					if ($item.length) {
						var $badge = $item.find('.tsm-status-badge');
						if ($badge.length) {
							$badge.removeClass('pending running retry').addClass('cancelled');
							$badge.text('cancelled');
						}
						var $cancelBtn = $item.find('.tsm-cancel-btn');
						if ($cancelBtn.length) {
							$cancelBtn.remove();
						}
					}
				} else {
					showNotice('error', response.error || 'Failed to cancel execution.');
				}
			},
			error: function(xhr) {
				var msg = xhr.responseJSON && xhr.responseJSON.error
					? xhr.responseJSON.error
					: 'Network error. Please try again.';
				showNotice('error', msg);
			}
		});
	}

	/**
	 * Show inline notice.
	 *
	 * @param {string} type    Notice type ('success' or 'error').
	 * @param {string} message Notice message.
	 */
	function showNotice(type, message) {
		// Check if there's an existing notice container
		var noticeContainer = document.getElementById('tsm-notices');
		if (!noticeContainer) {
			// Create notice container at top of main content area
			noticeContainer = document.createElement('div');
			noticeContainer.id = 'tsm-notices';
			var contentArea = document.querySelector('.tsm-main-content');
			if (contentArea) {
				contentArea.insertBefore(noticeContainer, contentArea.firstChild);
			}
		}

		var notice = document.createElement('div');
		notice.className = 'notice notice-' + type + ' is-dismissible';
		notice.innerHTML = '<p>' + escapeHtml(message) + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button>';

		noticeContainer.appendChild(notice);

		// Auto-dismiss after 5 seconds
		setTimeout(function() {
			notice.style.transition = 'opacity 0.3s';
			notice.style.opacity = '0';
			setTimeout(function() { notice.remove(); }, 300);
		}, 5000);

		// Manual dismiss
		notice.querySelector('.notice-dismiss').addEventListener('click', function() {
			notice.remove();
		});
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

	// ========================================
	// Phase 7, Plan 05: Bulk Operations
	// ========================================

	/**
	 * Initialize bulk mode functionality.
	 */
	function initBulkMode() {
		// Toggle bulk mode button
		$('#tsm-toggle-bulk-mode').on('click', toggleBulkMode);

		// Select all checkbox
		$('#tsm-select-all').on('change', function() {
			var isChecked = $(this).prop('checked');
			$('.tsm-script-checkbox:visible').prop('checked', isChecked);
			updateBulkSelectedCount();
		});

		// Individual checkbox change (event delegation)
		$(document).on('change', '.tsm-script-checkbox', function() {
			updateBulkSelectedCount();
			// Update select-all state
			var totalVisible = $('.tsm-script-checkbox:visible').length;
			var checkedVisible = $('.tsm-script-checkbox:visible:checked').length;
			$('#tsm-select-all').prop('checked', totalVisible > 0 && totalVisible === checkedVisible);
		});

		// Bulk action select change
		$('#tsm-bulk-action-select').on('change', function() {
			var action = $(this).val();
			if (action === 'set_category') {
				populateBulkCategorySelect();
				$('#tsm-bulk-category-select').show();
			} else {
				$('#tsm-bulk-category-select').hide();
			}
		});

		// Apply bulk action
		$('#tsm-apply-bulk').on('click', applyBulkAction);
	}

	/**
	 * Toggle bulk edit mode.
	 */
	function toggleBulkMode() {
		bulkModeActive = !bulkModeActive;

		var $bulkActions = $('#tsm-bulk-actions');
		var $toggleBtn = $('#tsm-toggle-bulk-mode');

		if (bulkModeActive) {
			$bulkActions.show();
			$toggleBtn.text(tsmAdmin.exitBulkEdit || 'Exit Bulk Edit');
			// Re-render script list to add checkboxes
			renderScriptListWithCheckboxes();
		} else {
			$bulkActions.hide();
			$toggleBtn.text(tsmAdmin.bulkEdit || 'Bulk Edit');
			// Re-render script list without checkboxes
			renderScriptList(scripts);
			// Reset selections
			$('#tsm-select-all').prop('checked', false);
			$('#tsm-bulk-action-select').val('');
			$('#tsm-bulk-category-select').hide();
			$('#tsm-apply-bulk').prop('disabled', true);
		}

		updateBulkSelectedCount();
	}

	/**
	 * Render script list with checkboxes for bulk mode.
	 */
	function renderScriptListWithCheckboxes() {
		const $list = $('#tsm-script-list');

		if (scripts.length === 0) {
			$list.html('<div class="tsm-loading">No scripts yet. Create one to get started!</div>');
			return;
		}

		let html = '';
		scripts.forEach(function(script) {
			const lastExecuted = script.last_executed_at
				? new Date(script.last_executed_at).toLocaleDateString()
				: 'Never';

			html += '<div class="tsm-script-item" data-id="' + script.id + '">';
			html += '<input type="checkbox" class="tsm-script-checkbox" data-script-id="' + script.id + '">';
			html += '<div class="tsm-script-info">';
			html += '<div class="tsm-script-name">' + escapeHtml(script.name) + '</div>';
			html += '<div class="tsm-script-meta">';
			html += script.language + ' &bull; Last run: ' + lastExecuted;
			html += '</div>';
			html += '</div>';
			html += '</div>';
		});

		$list.html(html);

		// In bulk mode, clicking item should NOT open edit mode
		// Only clicking on non-checkbox area should toggle checkbox
		$('#tsm-script-list').off('click', '.tsm-script-item');
		$('#tsm-script-list').on('click', '.tsm-script-item', function(e) {
			if (bulkModeActive) {
				// Toggle checkbox unless clicking the checkbox itself
				if (!$(e.target).is('.tsm-script-checkbox')) {
					var $checkbox = $(this).find('.tsm-script-checkbox');
					$checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
				}
			} else {
				const scriptId = $(this).data('id');
				openEditMode(scriptId);
			}
		});
	}

	/**
	 * Update selected count display.
	 */
	function updateBulkSelectedCount() {
		var count = $('.tsm-script-checkbox:checked').length;
		$('.tsm-selected-count').text(count + ' ' + (tsmAdmin.selected || 'selected'));
		$('#tsm-apply-bulk').prop('disabled', count === 0);
	}

	/**
	 * Populate bulk category select dropdown.
	 */
	function populateBulkCategorySelect() {
		var $select = $('#tsm-bulk-category-select');
		$select.empty();
		$select.append('<option value="">' + (tsmAdmin.setCategory || 'Select Category') + '</option>');

		categories.forEach(function(category) {
			$select.append('<option value="' + category.id + '">' + escapeHtml(category.name) + '</option>');
		});
	}

	/**
	 * Apply bulk action to selected scripts.
	 */
	function applyBulkAction() {
		var action = $('#tsm-bulk-action-select').val();
		if (!action) {
			showNotice('error', 'Please select an action.');
			return;
		}

		var scriptIds = [];
		$('.tsm-script-checkbox:checked').each(function() {
			scriptIds.push(parseInt($(this).data('script-id')));
		});

		if (scriptIds.length === 0) {
			showNotice('error', 'Please select at least one script.');
			return;
		}

		// Confirmation
		var confirmMsg;
		if (action === 'delete') {
			confirmMsg = (tsmAdmin.confirmBulkDelete || 'Delete %d scripts? This cannot be undone.').replace('%d', scriptIds.length);
		} else {
			confirmMsg = (tsmAdmin.confirmBulkCategory || 'Set category for %d scripts?').replace('%d', scriptIds.length);
		}

		if (!confirm(confirmMsg)) {
			return;
		}

		// Build payload
		var payload = {
			action: action,
			script_ids: scriptIds
		};

		if (action === 'set_category') {
			var categoryId = parseInt($('#tsm-bulk-category-select').val());
			if (!categoryId) {
				showNotice('error', 'Please select a category.');
				return;
			}
			payload.category_id = categoryId;
		}

		// Disable apply button
		$('#tsm-apply-bulk').prop('disabled', true).text('Processing...');

		$.ajax({
			url: tsmAdmin.restUrl + '/scripts/bulk',
			method: 'POST',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', tsmAdmin.nonce);
			},
			contentType: 'application/json',
			data: JSON.stringify(payload),
			success: function(response) {
				var msg = (tsmAdmin.bulkSuccess || 'Success: %d, Failed: %d')
					.replace('%d', response.success)
					.replace('%d', response.failed);
				showNotice('success', msg);

				// Refresh script list for delete action
				if (action === 'delete') {
					loadScripts();
				}

				// Reset bulk mode UI
				$('.tsm-script-checkbox').prop('checked', false);
				$('#tsm-select-all').prop('checked', false);
				$('#tsm-bulk-action-select').val('');
				$('#tsm-bulk-category-select').hide();
				updateBulkSelectedCount();
			},
			error: function(xhr) {
				var msg = xhr.responseJSON && xhr.responseJSON.error
					? xhr.responseJSON.error
					: 'Bulk operation failed';
				showNotice('error', msg);
			},
			complete: function() {
				$('#tsm-apply-bulk').prop('disabled', false).text(tsmAdmin.apply || 'Apply');
			}
		});
	}

})(jQuery);
