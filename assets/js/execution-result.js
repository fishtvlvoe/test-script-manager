/**
 * Execution Result Page JavaScript
 *
 * Provides interactivity for the execution result page:
 * - JSON viewer with collapsible sections
 * - Copy output to clipboard
 *
 * @package TestScriptManager
 */
(function() {
	'use strict';

	/**
	 * Initialize JSON viewers on page load.
	 *
	 * Parses JSON data from data-json attribute and renders
	 * a formatted, collapsible tree view.
	 */
	function initJsonViewers() {
		var viewers = document.querySelectorAll('.tsm-json-viewer');

		viewers.forEach(function(container) {
			var jsonStr = container.getAttribute('data-json');

			if (!jsonStr) {
				return;
			}

			try {
				var data = JSON.parse(jsonStr);
				var html = renderJsonValue(data, 0);

				container.innerHTML = '<pre>' + html + '</pre>';

				// Add click handlers for collapsible sections
				container.querySelectorAll('.json-toggle').forEach(function(toggle) {
					toggle.addEventListener('click', function() {
						this.classList.toggle('collapsed');
					});
				});

			} catch (e) {
				// Not valid JSON, show as text
				var pre = document.createElement('pre');
				pre.textContent = jsonStr;
				pre.style.color = '#d4d4d4';
				pre.style.margin = '0';
				container.appendChild(pre);
			}
		});
	}

	/**
	 * Render a JSON value with syntax highlighting.
	 *
	 * @param {*} value     Value to render.
	 * @param {number} depth Current nesting depth.
	 * @return {string} HTML string.
	 */
	function renderJsonValue(value, depth) {
		var indent = '  '.repeat(depth);
		var nextIndent = '  '.repeat(depth + 1);

		if (value === null) {
			return '<span class="json-null">null</span>';
		}

		if (typeof value === 'boolean') {
			return '<span class="json-boolean">' + value + '</span>';
		}

		if (typeof value === 'number') {
			return '<span class="json-number">' + value + '</span>';
		}

		if (typeof value === 'string') {
			return '<span class="json-string">"' + escapeHtml(value) + '"</span>';
		}

		if (Array.isArray(value)) {
			if (value.length === 0) {
				return '[]';
			}

			var items = value.map(function(item, index) {
				var comma = index < value.length - 1 ? ',' : '';
				return nextIndent + renderJsonValue(item, depth + 1) + comma;
			});

			return '<span class="json-toggle"></span><span class="json-content">[\n' + items.join('\n') + '\n' + indent + ']</span>';
		}

		if (typeof value === 'object') {
			var keys = Object.keys(value);
			if (keys.length === 0) {
				return '{}';
			}

			var entries = keys.map(function(key, index) {
				var comma = index < keys.length - 1 ? ',' : '';
				return nextIndent + '<span class="json-key">"' + escapeHtml(key) + '"</span>: ' + renderJsonValue(value[key], depth + 1) + comma;
			});

			return '<span class="json-toggle"></span><span class="json-content">{\n' + entries.join('\n') + '\n' + indent + '}</span>';
		}

		return String(value);
	}

	/**
	 * Escape HTML special characters.
	 *
	 * @param {string} str String to escape.
	 * @return {string} Escaped string.
	 */
	function escapeHtml(str) {
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return String(str).replace(/[&<>"']/g, function(m) {
			return map[m];
		});
	}

	/**
	 * Initialize copy to clipboard button.
	 */
	function initCopyButton() {
		var copyBtn = document.querySelector('.tsm-copy-output');
		if (!copyBtn) {
			return;
		}

		copyBtn.addEventListener('click', function() {
			var output = document.querySelector('.tsm-output-content');
			var jsonViewer = document.querySelector('.tsm-json-viewer pre');
			var htmlOutput = document.querySelector('.tsm-html-output');

			var text = '';
			if (output) {
				text = output.textContent;
			} else if (jsonViewer) {
				text = jsonViewer.textContent;
			} else if (htmlOutput) {
				text = htmlOutput.textContent;
			}

			if (!text) {
				return;
			}

			// Use Clipboard API if available
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function() {
					showCopyFeedback(copyBtn, true);
				}).catch(function() {
					fallbackCopy(text, copyBtn);
				});
			} else {
				fallbackCopy(text, copyBtn);
			}
		});
	}

	/**
	 * Fallback copy method using textarea.
	 *
	 * @param {string} text    Text to copy.
	 * @param {Element} button Button element for feedback.
	 */
	function fallbackCopy(text, button) {
		var textarea = document.createElement('textarea');
		textarea.value = text;
		textarea.style.position = 'fixed';
		textarea.style.opacity = '0';
		document.body.appendChild(textarea);
		textarea.select();

		try {
			document.execCommand('copy');
			showCopyFeedback(button, true);
		} catch (err) {
			showCopyFeedback(button, false);
		}

		document.body.removeChild(textarea);
	}

	/**
	 * Show copy feedback on button.
	 *
	 * @param {Element} button  Button element.
	 * @param {boolean} success Whether copy succeeded.
	 */
	function showCopyFeedback(button, success) {
		var originalText = button.textContent;
		button.textContent = success ? 'Copied!' : 'Failed';

		setTimeout(function() {
			button.textContent = originalText;
		}, 2000);
	}

	/**
	 * Initialize all functionality on DOM ready.
	 */
	function init() {
		initJsonViewers();
		initCopyButton();
	}

	// Initialize on DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
