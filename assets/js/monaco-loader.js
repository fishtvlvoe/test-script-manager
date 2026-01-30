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
	 * Curated list of common WordPress functions for autocomplete.
	 * Intentionally limited to ~35 most-used functions to avoid bloat.
	 */
	var wpFunctions = [
		// Options API
		{ name: 'get_option', description: 'Retrieves an option value', signature: 'get_option( $option, $default = false )' },
		{ name: 'update_option', description: 'Updates the value of an option', signature: 'update_option( $option, $value, $autoload = null )' },
		{ name: 'add_option', description: 'Adds a new option', signature: 'add_option( $option, $value = "", $deprecated = "", $autoload = "yes" )' },
		{ name: 'delete_option', description: 'Removes option from database', signature: 'delete_option( $option )' },
		// Post API
		{ name: 'get_post', description: 'Retrieves post data', signature: 'get_post( $post = null, $output = OBJECT, $filter = "raw" )' },
		{ name: 'get_posts', description: 'Retrieves an array of posts', signature: 'get_posts( $args = null )' },
		{ name: 'wp_insert_post', description: 'Insert or update a post', signature: 'wp_insert_post( $postarr, $wp_error = false, $fire_after_hooks = true )' },
		{ name: 'wp_update_post', description: 'Update a post', signature: 'wp_update_post( $postarr = array(), $wp_error = false, $fire_after_hooks = true )' },
		{ name: 'wp_delete_post', description: 'Trash or delete a post', signature: 'wp_delete_post( $postid = 0, $force_delete = false )' },
		{ name: 'get_post_meta', description: 'Retrieves a post meta field', signature: 'get_post_meta( $post_id, $key = "", $single = false )' },
		{ name: 'update_post_meta', description: 'Updates a post meta field', signature: 'update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = "" )' },
		// User API
		{ name: 'get_user_by', description: 'Retrieve user info by field', signature: 'get_user_by( $field, $value )' },
		{ name: 'get_current_user_id', description: 'Get the current user ID', signature: 'get_current_user_id()' },
		{ name: 'wp_get_current_user', description: 'Retrieve the current user object', signature: 'wp_get_current_user()' },
		{ name: 'get_user_meta', description: 'Retrieves user meta', signature: 'get_user_meta( $user_id, $key = "", $single = false )' },
		{ name: 'update_user_meta', description: 'Updates user meta', signature: 'update_user_meta( $user_id, $meta_key, $meta_value, $prev_value = "" )' },
		// Hooks API
		{ name: 'add_action', description: 'Hooks a function to a specific action', signature: 'add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 )' },
		{ name: 'add_filter', description: 'Hooks a function to a specific filter', signature: 'add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 )' },
		{ name: 'do_action', description: 'Calls the callback functions added to an action hook', signature: 'do_action( $hook_name, ...$arg )' },
		{ name: 'apply_filters', description: 'Calls the callback functions added to a filter hook', signature: 'apply_filters( $hook_name, $value, ...$args )' },
		{ name: 'remove_action', description: 'Removes a function from an action hook', signature: 'remove_action( $hook_name, $callback, $priority = 10 )' },
		{ name: 'remove_filter', description: 'Removes a function from a filter hook', signature: 'remove_filter( $hook_name, $callback, $priority = 10 )' },
		// Scripts/Styles
		{ name: 'wp_enqueue_script', description: 'Registers the script and enqueues it', signature: 'wp_enqueue_script( $handle, $src = "", $deps = array(), $ver = false, $args = array() )' },
		{ name: 'wp_enqueue_style', description: 'Registers the style and enqueues it', signature: 'wp_enqueue_style( $handle, $src = "", $deps = array(), $ver = false, $media = "all" )' },
		// Security/Escaping
		{ name: 'esc_html', description: 'Escaping for HTML blocks', signature: 'esc_html( $text )' },
		{ name: 'esc_attr', description: 'Escaping for HTML attributes', signature: 'esc_attr( $text )' },
		{ name: 'esc_url', description: 'Checks and cleans a URL', signature: 'esc_url( $url, $protocols = null, $_context = "display" )' },
		{ name: 'esc_sql', description: 'Escapes data for SQL queries', signature: 'esc_sql( $data )' },
		{ name: 'wp_nonce_field', description: 'Retrieve or display nonce hidden field', signature: 'wp_nonce_field( $action = -1, $name = "_wpnonce", $referer = true, $display = true )' },
		{ name: 'wp_verify_nonce', description: 'Verifies that a nonce is correct', signature: 'wp_verify_nonce( $nonce, $action = -1 )' },
		{ name: 'sanitize_text_field', description: 'Sanitizes a string', signature: 'sanitize_text_field( $str )' },
		{ name: 'absint', description: 'Convert a value to non-negative integer', signature: 'absint( $maybeint )' },
		// Database (wpdb)
		{ name: '$wpdb->get_results', description: 'Retrieve an entire result set from the database', signature: '$wpdb->get_results( $query, $output = OBJECT )' },
		{ name: '$wpdb->get_row', description: 'Retrieve one row from the database', signature: '$wpdb->get_row( $query, $output = OBJECT, $y = 0 )' },
		{ name: '$wpdb->get_var', description: 'Retrieve one variable from the database', signature: '$wpdb->get_var( $query = null, $x = 0, $y = 0 )' },
		{ name: '$wpdb->prepare', description: 'Prepares a SQL query for safe execution', signature: '$wpdb->prepare( $query, ...$args )' },
		{ name: '$wpdb->insert', description: 'Insert a row into a table', signature: '$wpdb->insert( $table, $data, $format = null )' },
		{ name: '$wpdb->update', description: 'Update a row in a table', signature: '$wpdb->update( $table, $data, $where, $format = null, $where_format = null )' },
		{ name: '$wpdb->delete', description: 'Delete a row from a table', signature: '$wpdb->delete( $table, $where, $where_format = null )' },
		{ name: '$wpdb->query', description: 'Perform a database query', signature: '$wpdb->query( $query )' }
	];

	/**
	 * Register WordPress function autocomplete provider.
	 * Called after Monaco is loaded.
	 */
	function registerWpCompletionProvider() {
		if (typeof monaco === 'undefined') return;

		monaco.languages.registerCompletionItemProvider('php', {
			provideCompletionItems: function(model, position) {
				var word = model.getWordUntilPosition(position);
				var range = {
					startLineNumber: position.lineNumber,
					endLineNumber: position.lineNumber,
					startColumn: word.startColumn,
					endColumn: word.endColumn
				};

				// Check for $wpdb-> prefix
				var textBeforeCursor = model.getValueInRange({
					startLineNumber: position.lineNumber,
					startColumn: 1,
					endLineNumber: position.lineNumber,
					endColumn: position.column
				});
				var hasWpdbPrefix = textBeforeCursor.match(/\$wpdb->$/);

				var suggestions = wpFunctions
					.filter(function(func) {
						// If user typed $wpdb->, only show wpdb methods
						if (hasWpdbPrefix) {
							return func.name.indexOf('$wpdb->') === 0;
						}
						// Otherwise show regular functions (not wpdb methods)
						return func.name.indexOf('$wpdb->') !== 0;
					})
					.map(function(func) {
						// For $wpdb-> methods, insert only the method name
						var insertText = func.name.indexOf('$wpdb->') === 0
							? func.name.replace('$wpdb->', '')
							: func.name;

						return {
							label: func.name,
							kind: monaco.languages.CompletionItemKind.Function,
							documentation: func.description,
							detail: func.signature,
							insertText: insertText,
							range: range
						};
					});

				return { suggestions: suggestions };
			}
		});
	}

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

			// Register WordPress function autocomplete provider
			registerWpCompletionProvider();

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
