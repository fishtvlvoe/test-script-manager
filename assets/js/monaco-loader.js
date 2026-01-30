/**
 * Test Script Manager - Monaco Editor Loader
 *
 * Loads Monaco Editor via CDN and provides initialization functions.
 * Uses AMD loader pattern from jsDelivr CDN.
 *
 * @package TestScriptManager
 */

(function() {
	'use strict';

	// State tracking
	var isMonacoLoaded = false;
	var isMonacoLoading = false;
	var loadCallbacks = [];

	/**
	 * Configure Monaco CDN paths.
	 */
	function configureMonaco() {
		if (typeof require === 'undefined' || typeof require.config === 'undefined') {
			console.error('TSM: Monaco loader.js not loaded');
			return false;
		}

		require.config({
			paths: {
				'vs': tsmMonaco.cdnPath
			}
		});

		return true;
	}

	/**
	 * Load Monaco Editor asynchronously.
	 *
	 * @param {Function} callback Called when Monaco is ready.
	 */
	function loadMonaco(callback) {
		// Already loaded
		if (isMonacoLoaded && typeof monaco !== 'undefined') {
			if (callback) callback();
			return;
		}

		// Add to callback queue
		if (callback) {
			loadCallbacks.push(callback);
		}

		// Already loading, just wait
		if (isMonacoLoading) {
			return;
		}

		isMonacoLoading = true;

		// Configure CDN paths
		if (!configureMonaco()) {
			isMonacoLoading = false;
			return;
		}

		// Load Monaco
		require(['vs/editor/editor.main'], function() {
			isMonacoLoaded = true;
			isMonacoLoading = false;

			// Fire all callbacks
			loadCallbacks.forEach(function(cb) {
				try {
					cb();
				} catch (e) {
					console.error('TSM: Monaco callback error:', e);
				}
			});
			loadCallbacks = [];
		});
	}

	/**
	 * Initialize a Monaco Editor instance.
	 *
	 * @param {string|HTMLElement} container Container ID or element.
	 * @param {Object} options Editor options.
	 * @return {Object|null} Editor instance or null if not ready.
	 */
	function initMonaco(container, options) {
		if (!isMonacoLoaded || typeof monaco === 'undefined') {
			console.error('TSM: Monaco not loaded. Call loadMonaco() first.');
			return null;
		}

		// Get container element
		var el = typeof container === 'string'
			? document.getElementById(container)
			: container;

		if (!el) {
			console.error('TSM: Editor container not found:', container);
			return null;
		}

		// Default options
		var defaultOptions = {
			value: '<?php\n\n// Your test script here\n',
			language: 'php',
			theme: tsmMonaco.defaultTheme || 'vs-dark',
			automaticLayout: true,
			minimap: { enabled: false },
			fontSize: 14,
			lineNumbers: 'on',
			roundedSelection: false,
			scrollBeyondLastLine: false,
			cursorStyle: 'line',
			wordWrap: 'on',
			tabSize: 4,
			insertSpaces: false
		};

		// Merge options
		var finalOptions = Object.assign({}, defaultOptions, options || {});

		// Create editor
		return monaco.editor.create(el, finalOptions);
	}

	/**
	 * Check if Monaco is loaded and ready.
	 *
	 * @return {boolean} True if Monaco is ready.
	 */
	function isReady() {
		return isMonacoLoaded && typeof monaco !== 'undefined';
	}

	/**
	 * Set editor theme.
	 *
	 * @param {string} theme Theme name ('vs', 'vs-dark', 'hc-black').
	 */
	function setTheme(theme) {
		if (isReady()) {
			monaco.editor.setTheme(theme);
		}
	}

	// Expose API globally
	window.tsmMonacoLoader = {
		load: loadMonaco,
		init: initMonaco,
		isReady: isReady,
		setTheme: setTheme
	};

	// Alias for convenience
	window.tsmInitMonaco = initMonaco;
	window.tsmLoadMonaco = loadMonaco;

})();
