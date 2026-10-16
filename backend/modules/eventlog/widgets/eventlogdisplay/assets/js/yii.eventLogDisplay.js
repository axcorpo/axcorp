(function ($, window, document, undefined) {
	/**
	 * Constants
	 * @constant {String} PLUGIN_NAME
	 * @constant {String} PLUGIN_VERSION
	 * @constant {String} DATA_KEY
	 * @constant {Object} DEFAULTS
	 */
	var PLUGIN_NAME = 'yiiEventLogDisplay',
		PLUGIN_VERSION = '0.0.1',
		EVENT_NS = '.' + PLUGIN_NAME,
		DATA_KEY = 'plugin_' + PLUGIN_NAME,
		DEFAULTS = {
			targets: [],
			highlight: true,
			highlightClass: 'warning',

			onInit: function () {},
			onDestroy: function () {}
		};

	/**
	 * Plugin
	 *
	 * @param element
	 * @param options
	 * @param metadata
	 * @constructor
	 */
	var Plugin = function (element, options, metadata) {
		// Exit if DOM element does not exist
		if (!element) {
			console.error('[' + PLUGIN_NAME + ']: DOM element is missing');
			return;
		}
		// Set the DOM element
		this.element = element;
		// Set options
		this.options = $.extend({}, DEFAULTS, options, metadata);
		// Initialization
		this.init();
	};

	/**
	 * Initialization
	 */
	Plugin.prototype.init = function () {
		// Cache elements
		this._cacheElements();
		// Bind events
		this._bindEvents();
		// Highlight
		if (this.options.highlight) {
			this.highlightDifferences();
		}
		// Hook event
		this._hook('onInit');
	};

	/**
	 * Caches DOM Elements.
	 *
	 * @private
	 */
	Plugin.prototype._cacheElements = function () {
		this.$window = $(window);
		this.$document = $(document);
		this.$html = $('html');
		this.$body = this.$html.children('body');
		this.$element = $(this.element);

		this.options.targets = !(this.options.targets instanceof Array) ?
			this.options.targets.split() :
			this.options.targets;
		this.$targets = this.$element.find(this.options.targets.join(','));
	};

	/**
	 * Binds Events.
	 *
	 * @private
	 */
	Plugin.prototype._bindEvents = function () {

	};

	/**
	 * Highlights the differences between all targets.
	 */
	Plugin.prototype.highlightDifferences = function () {
		var me = this;

		this.$targets.each(function (targetIndex, target) {
			var $target = $(target),
				$nextTarget = me.$targets.eq(targetIndex + 1);

			if (!$nextTarget.length) {
				return;
			}

			$target.find('tr').each(function (rowIndex, row) {
				var $row = $(row),
					$nextTargetRow = $nextTarget.find('tr').eq(rowIndex);

				if ($row.html() !== $nextTargetRow.html()) {
					$row.addClass(me.options.highlightClass);
					$nextTargetRow.addClass(me.options.highlightClass);
				}
			});
		});
	};

	/**
	 * Hooks callbacks.
	 *
	 * @access private
	 * @param [arguments]
	 */
	Plugin.prototype._hook = function () {
		var args = Array.prototype.slice.call(arguments),
			hookName = args.shift(),
			eventName = hookName.split(/(?=[A-Z])/)[1].toLowerCase() + EVENT_NS;

		if (typeof this.options[hookName] !== 'undefined') {
			// Callback
			this.options[hookName].apply(this.element, args);
			// Create a new event
			var event = $.Event(eventName, {
				target: this.element
			});
			// Trigger the event
			this.$element.trigger(event, args);
		}
	};

	/**
	 * Gets or sets a property.
	 *
	 * @access public
	 * @param {String} key
	 * @param {String} val
	 */
	Plugin.prototype.option = function (key, val) {
		if (val) {
			this.options[key] = val;
		} else {
			return this.options[key];
		}
	};

	/**
	 * Destroys the plugin instance.
	 *
	 * @public
	 */
	Plugin.prototype.destroy = function () {
		this._hook('onDestroy');
		this.$document.off(EVENT_NS);
		this.$element.off(EVENT_NS);
		this.$element.removeData(DATA_KEY);
	};

	/**
	 * Plugin definition
	 * @function external "jQuery.fn".timeslots
	 */
	$.fn[PLUGIN_NAME] = function (options) {
		var args = arguments;

		if (!options || typeof options === 'object') {
			return this.each(function () {
				if (!$.data(this, DATA_KEY)) {
					var metadata = $(this).data();
					$.data(this, DATA_KEY, new Plugin(this, options, metadata));
				}
			});
		} else if (typeof args[0] === 'string') {
			var methodName = args[0].replace('_', ''),
				returnVal;

			this.each(function () {
				var instance = $.data(this, DATA_KEY);

				if (instance && typeof instance[methodName] === 'function') {
					returnVal = instance[methodName].apply(instance, Array.prototype.slice.call(args, 1));
				} else {
					throw new Error('Could not call method "' + methodName + '" on jQuery.fn.' + PLUGIN_NAME);
				}
			});

			return (typeof returnVal !== 'undefined') ? returnVal : this;
		}
	};

	/**
	 * Expose global
	 */
	this[PLUGIN_NAME] = Plugin;

})(jQuery, window, document);
