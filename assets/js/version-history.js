/**
 * Test Script Manager - Version History JavaScript
 *
 * Handles version history display and Monaco Diff Editor for comparison.
 *
 * @package TestScriptManager
 */

(function($) {
	'use strict';

	// Version history state
	var currentVersions = [];
	var diffEditor = null;
	var sideBySideMode = true;
	var currentScriptId = null;

	/**
	 * Initialize version history module.
	 */
	window.tsmVersionHistory = {
		init: function(scriptId) {
			currentScriptId = scriptId;
			loadVersionHistory(scriptId);
		},

		refresh: function() {
			if (currentScriptId) {
				loadVersionHistory(currentScriptId);
			}
		},

		destroy: function() {
			if (diffEditor) {
				diffEditor.dispose();
				diffEditor = null;
			}
			currentVersions = [];
			currentScriptId = null;
		}
	};

	/**
	 * Load version history from API.
	 *
	 * @param {number} scriptId Script ID.
	 */
	function loadVersionHistory(scriptId) {
		var $list = $('#tsm-version-list');
		$list.html('<div class="tsm-loading">Loading versions...</div>');

		$.ajax({
			url: tsmAdmin.restUrl + '/scripts/' + scriptId + '/versions',
			method: 'GET',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', tsmAdmin.nonce);
			},
			data: { limit: 50 },
			success: function(response) {
				if (response.success) {
					currentVersions = response.versions || [];
					renderVersionList(currentVersions);
				} else {
					$list.html('<div class="tsm-no-versions">Failed to load versions.</div>');
				}
			},
			error: function() {
				$list.html('<div class="tsm-no-versions">Failed to load versions.</div>');
			}
		});
	}

	/**
	 * Render version history list.
	 *
	 * @param {Array} versions Array of version objects.
	 */
	function renderVersionList(versions) {
		var $list = $('#tsm-version-list');

		if (versions.length === 0) {
			$list.html('<div class="tsm-no-versions">No version history yet.</div>');
			return;
		}

		var html = '';
		versions.forEach(function(version, index) {
			var timeStr = formatVersionTime(version.created_at);
			var author = version.author_name || 'Unknown';

			html += '<div class="tsm-version-item" data-version-id="' + version.id + '">';
			html += '<div class="tsm-version-info">';
			html += '<span class="tsm-version-time">' + escapeHtml(timeStr) + '</span>';
			html += '<span class="tsm-version-author">' + escapeHtml(author) + '</span>';
			html += '</div>';
			html += '<div class="tsm-version-actions">';
			html += '<button type="button" class="button tsm-compare-btn" data-version-id="' + version.id + '" title="Compare with current">';
			html += '<span class="dashicons dashicons-image-flip-horizontal"></span>';
			html += '</button>';
			html += '<button type="button" class="button tsm-restore-btn" data-version-id="' + version.id + '" title="Restore this version">';
			html += '<span class="dashicons dashicons-backup"></span>';
			html += '</button>';
			html += '</div>';
			html += '</div>';
		});

		$list.html(html);

		// Bind action buttons
		$list.find('.tsm-compare-btn').on('click', function(e) {
			e.stopPropagation();
			var versionId = $(this).data('version-id');
			openDiffView(versionId, 'current');
		});

		$list.find('.tsm-restore-btn').on('click', function(e) {
			e.stopPropagation();
			var versionId = $(this).data('version-id');
			restoreVersion(versionId);
		});
	}

	/**
	 * Format version timestamp for display.
	 *
	 * @param {string} dateStr ISO date string.
	 * @return {string} Formatted time string.
	 */
	function formatVersionTime(dateStr) {
		var date = new Date(dateStr);
		var now = new Date();

		// If today, show time only
		if (date.toDateString() === now.toDateString()) {
			return 'Today ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
		}

		// If yesterday
		var yesterday = new Date(now);
		yesterday.setDate(yesterday.getDate() - 1);
		if (date.toDateString() === yesterday.toDateString()) {
			return 'Yesterday ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
		}

		// Otherwise show date and time
		return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
	}

	/**
	 * Open diff view to compare versions.
	 *
	 * @param {number|string} v1 Version 1 ID.
	 * @param {number|string} v2 Version 2 ID or 'current'.
	 */
	function openDiffView(v1, v2) {
		// Show diff modal
		var $modal = $('#tsm-diff-modal');
		if ($modal.length === 0) {
			createDiffModal();
			$modal = $('#tsm-diff-modal');
		}

		$modal.show();
		$('#tsm-diff-container').html('<div class="tsm-loading">Loading comparison...</div>');

		// Load comparison data
		$.ajax({
			url: tsmAdmin.restUrl + '/scripts/' + currentScriptId + '/versions/compare',
			method: 'GET',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', tsmAdmin.nonce);
			},
			data: { v1: v1, v2: v2 },
			success: function(response) {
				if (response.success) {
					renderDiff(response.v1, response.v2);
				} else {
					$('#tsm-diff-container').html('<div class="tsm-error">Failed to load comparison.</div>');
				}
			},
			error: function() {
				$('#tsm-diff-container').html('<div class="tsm-error">Failed to load comparison.</div>');
			}
		});
	}

	/**
	 * Create diff modal HTML.
	 */
	function createDiffModal() {
		var modalHtml = '<div id="tsm-diff-modal" class="tsm-modal">' +
			'<div class="tsm-modal-content">' +
			'<div class="tsm-modal-header">' +
			'<h3>Version Comparison</h3>' +
			'<div class="tsm-modal-actions">' +
			'<button type="button" class="button" id="tsm-diff-toggle-mode">Toggle View</button>' +
			'<button type="button" class="button" id="tsm-diff-close">&times;</button>' +
			'</div>' +
			'</div>' +
			'<div class="tsm-modal-body">' +
			'<div id="tsm-diff-labels"></div>' +
			'<div id="tsm-diff-container"></div>' +
			'</div>' +
			'</div>' +
			'</div>';

		$('body').append(modalHtml);

		// Bind close button
		$('#tsm-diff-close').on('click', closeDiffModal);

		// Bind toggle button
		$('#tsm-diff-toggle-mode').on('click', toggleDiffMode);

		// Close on backdrop click
		$('#tsm-diff-modal').on('click', function(e) {
			if (e.target === this) {
				closeDiffModal();
			}
		});

		// Close on ESC
		$(document).on('keydown.tsmDiff', function(e) {
			if (e.key === 'Escape') {
				closeDiffModal();
			}
		});

		// Load saved preference
		var savedMode = localStorage.getItem('tsm_diff_mode');
		if (savedMode) {
			sideBySideMode = savedMode === 'side-by-side';
		}
	}

	/**
	 * Close diff modal.
	 */
	function closeDiffModal() {
		$('#tsm-diff-modal').hide();
		if (diffEditor) {
			diffEditor.dispose();
			diffEditor = null;
		}
	}

	/**
	 * Render diff using Monaco Diff Editor.
	 *
	 * @param {Object} v1 Version 1 data (code, label, time).
	 * @param {Object} v2 Version 2 data (code, label, time).
	 */
	function renderDiff(v1, v2) {
		var container = document.getElementById('tsm-diff-container');
		container.innerHTML = '';

		// Show labels
		var labelsHtml = '<div class="tsm-diff-label-left">' + escapeHtml(v1.label) +
			' <span class="tsm-diff-time">(' + formatVersionTime(v1.time) + ')</span></div>' +
			'<div class="tsm-diff-label-right">' + escapeHtml(v2.label) +
			' <span class="tsm-diff-time">(' + formatVersionTime(v2.time) + ')</span></div>';
		$('#tsm-diff-labels').html(labelsHtml);

		// Wait for Monaco to be ready
		if (typeof monaco === 'undefined' || !monaco.editor) {
			container.innerHTML = '<div class="tsm-error">Monaco Editor not loaded.</div>';
			return;
		}

		// Get current theme from localStorage
		var currentTheme = localStorage.getItem('tsm-editor-theme') || 'vs-dark';

		// Create diff editor
		diffEditor = monaco.editor.createDiffEditor(container, {
			enableSplitViewResizing: true,
			renderSideBySide: sideBySideMode,
			readOnly: true,
			originalEditable: false,
			ignoreTrimWhitespace: false,
			automaticLayout: true,
			theme: currentTheme,
		});

		// Set models
		diffEditor.setModel({
			original: monaco.editor.createModel(v1.code, 'php'),
			modified: monaco.editor.createModel(v2.code, 'php'),
		});
	}

	/**
	 * Toggle diff view mode (side-by-side vs inline).
	 */
	function toggleDiffMode() {
		sideBySideMode = !sideBySideMode;

		if (diffEditor) {
			diffEditor.updateOptions({
				renderSideBySide: sideBySideMode
			});
		}

		// Save preference
		localStorage.setItem('tsm_diff_mode', sideBySideMode ? 'side-by-side' : 'inline');

		// Update button text
		$('#tsm-diff-toggle-mode').text(sideBySideMode ? 'Inline View' : 'Side-by-Side');
	}

	/**
	 * Restore script to a specific version.
	 *
	 * @param {number} versionId Version ID to restore.
	 */
	function restoreVersion(versionId) {
		if (!confirm('Are you sure you want to restore this version? Current code will be saved as a new version.')) {
			return;
		}

		$.ajax({
			url: tsmAdmin.restUrl + '/scripts/' + currentScriptId + '/versions/' + versionId + '/restore',
			method: 'POST',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', tsmAdmin.nonce);
			},
			success: function(response) {
				if (response.success) {
					// Update editor with restored code
					if (window.tsmUpdateEditorCode && response.code) {
						window.tsmUpdateEditorCode(response.code);
					}

					// Show success message
					showNotice('success', 'Version restored successfully.');

					// Refresh version list
					loadVersionHistory(currentScriptId);
				} else {
					showNotice('error', response.error || 'Failed to restore version.');
				}
			},
			error: function(xhr) {
				var msg = xhr.responseJSON && xhr.responseJSON.error
					? xhr.responseJSON.error
					: 'Failed to restore version.';
				showNotice('error', msg);
			}
		});
	}

	/**
	 * Show inline notice (reuse from admin-page.js pattern).
	 *
	 * @param {string} type    Notice type ('success' or 'error').
	 * @param {string} message Notice message.
	 */
	function showNotice(type, message) {
		var noticeContainer = document.getElementById('tsm-notices');
		if (!noticeContainer) {
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

		setTimeout(function() {
			notice.style.transition = 'opacity 0.3s';
			notice.style.opacity = '0';
			setTimeout(function() { notice.remove(); }, 300);
		}, 5000);

		notice.querySelector('.notice-dismiss').addEventListener('click', function() {
			notice.remove();
		});
	}

	/**
	 * Escape HTML entities.
	 *
	 * @param {string} text Text to escape.
	 * @return {string} Escaped text.
	 */
	function escapeHtml(text) {
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String(text).replace(/[&<>"']/g, function(m) {
			return map[m];
		});
	}

})(jQuery);
