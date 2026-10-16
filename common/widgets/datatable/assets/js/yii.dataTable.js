(function ($, window, document, undefined) {
	/**
	 * Constants
	 * @constant {String} PLUGIN_NAME
	 * @constant {String} PLUGIN_VERSION
	 * @constant {String} DATA_KEY
	 * @constant {Object} DEFAULTS
	 */
	var PLUGIN_NAME = 'yiiDataTable',
		PLUGIN_VERSION = '0.0.1',
		EVENT_NS = '.' + PLUGIN_NAME,
		DATA_KEY = 'plugin_' + PLUGIN_NAME,
		DEFAULTS = {
			bulkMinCheck: 2,

			onInit: function () {},
			onDestroy: function () {},
			onCheckboxColumnChange: function (state) {}
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
		this.$table = $(this.element);
		// Custom properties
		this.isWebkit = ('WebkitAppearance' in document.documentElement.style);
		// Setup the scroll element (html, body)
		this.$scrollElement = this.$html;
		this.$scrollElement = this.$scrollElement.add(this.$body);
	};

	/**
	 * Binds Events.
	 *
	 * @private
	 */
	Plugin.prototype._bindEvents = function () {
		this.$table.on('preXhr.dt', this._onDataTablePreXhr.bind(this));
		this.$table.on('init.dt', this._onDataTableInit.bind(this));
		this.$table.on('draw.dt', this._onDataTableDraw.bind(this));
		this.$table.on('responsive-resize.dt', this._onDataTableResponsiveResize.bind(this));
		this.$table.on('column-visibility.dt', this._onDataTableColumnVisibility.bind(this));
		this.$table.on('column-reorder.dt', this._onDataTableColumnReorder.bind(this));
		this.$table.on('change' + EVENT_NS, '.field-column :input', this._onFieldColumnChange.bind(this));
		this.$table.on('click' + EVENT_NS, '.action-column [data-dt-operation]', this._onRecordOperationClick.bind(this));
		this.$document.on('click' + EVENT_NS, '[data-dt-bulk-operation]', this._onBulkOperationClick.bind(this));
		this.$document.on('change' + EVENT_NS, '.checkbox-column :checkbox', this._onCheckboxColumnChange.bind(this));
		this.$document.on('change' + EVENT_NS + ' input' + EVENT_NS, '.filter-column :input', this._onFilterColumnChange.bind(this));
		this.$document.on('click' + EVENT_NS, '.filter-column [data-dt-clear-filters]', this._onClearFiltersClick.bind(this));
		this.$document.on('change' + EVENT_NS + ' input' + EVENT_NS, '[data-dt-external-filter]', this._onExternalFilterChange.bind(this));
		// Plugin events
		this.$table.on('switchChange.bootstrapSwitch', '[data-krajee-bootstrapswitch]', this._onSwitchInputChange.bind(this));
		this.$document.on('dp.change', '.datetimepicker', this._onDateTimePickerChange.bind(this));
	};

	/**
	 * Gets the DataTable instance.
	 *
	 * @returns {*}
	 */
	Plugin.prototype.getDataTable = function () {
		return this.$table.DataTable();
	};

	/**
	 * Handles DataTable PreXhr event.
	 *
	 * @param e
	 * @param settings
	 * @param data
	 * @private
	 */
	Plugin.prototype._onDataTablePreXhr = function (e, settings, data) {
		// Cancel the previously existing XHR
		if (settings.jqXHR) {
			settings.jqXHR.abort();
		}
		// Add external filters to the XHR
		data.external_filters = this.$body.find('[data-dt-external-filter]').serializeArray();
		// Show the processing
		if (settings.oInit && settings.oInit.processing === true) {
			this.showProcessing(true);
		}
	};

	/**
	 * Handles DataTable Init event.
	 *
	 * @param e
	 * @param settings
	 * @param json
	 * @private
	 */
	Plugin.prototype._onDataTableInit = function (e, settings, json) {
		var me = this,
			$table = $(e.target),
			$tableWrapper = $table.closest('.dataTables_wrapper');
		// Execute this as the last one
		setTimeout(function () {
			var $thead, $filterColumns;
			// Identify the visible thead DOM element
			if ($tableWrapper.find('.dataTables_scroll').length) {
				$thead = $tableWrapper.find('.dataTables_scrollHead thead');
			} else {
				$thead = $table.children('thead');
			}
			// Find the filter columns from the table thead
			$filterColumns = $thead.find('.filters-row').children();
			// Loop through the aoPreSearchCols
			$.each(settings.aoPreSearchCols, function (index, searchCol) {
				// If the column has search value
				if (searchCol.sSearch) {
					$filterColumns.eq(index).find(':input').val(searchCol.sSearch);
				}
			});
			// Reinit thead plugins
			me.reinitPlugins($thead);
			// Trigger window resize to adjust the columns size
			me.$window.trigger('resize');
		}, 16);

		// Setup the auto reload interval
		if (settings.ajax && typeof settings.ajax.reloadInterval === 'number') {
			if (this.ajaxReloadInterval) {
				clearInterval(this.ajaxReloadInterval);
			}
			this.ajaxReloadInterval = setInterval(function () {
				var enableReloadInterval = true;
				if (typeof settings.ajax.enableReloadInterval !== 'undefined') {
					if (typeof settings.ajax.enableReloadInterval === 'function') {
						enableReloadInterval = settings.ajax.enableReloadInterval.call(me);
					} else {
						enableReloadInterval = settings.ajax.enableReloadInterval;
					}
				}
				if (enableReloadInterval) {
					me.getDataTable().ajax.reload();
				}
			}, settings.ajax.reloadInterval);
		}
	};

	/**
	 * Handles DataTable Draw event.
	 *
	 * @param e
	 * @private
	 */
	Plugin.prototype._onDataTableDraw = function (e) {
		var $target = $(e.target),
			$tableWrapper = $target.closest('.dataTables_wrapper'),
			$table = $tableWrapper.find('#' + this.$table.attr('id')),
			$thead = $tableWrapper.find('.dataTables_scroll').length ?
				$tableWrapper.find('.dataTables_scrollHead thead') :
				$table.children('thead'),
			$checkboxSelectAll = $thead.find('.checkbox-column :checkbox');
		// Preserve all rows selection if the select all checkbox is checked
		if ($checkboxSelectAll.length && $checkboxSelectAll.prop('checked')) {
			$checkboxSelectAll.trigger('change' + EVENT_NS);
		}
		// Reinit tbody plugins
		this.reinitPlugins($target.children('tbody'));
		// Toggle bulk control visibility
		this.toggleBulkControlVisibility();
	};

	/**
	 * Handles DataTable ResponsiveResize event.
	 *
	 * @param e
	 * @param datatable
	 * @param columns
	 * @private
	 */
	Plugin.prototype._onDataTableResponsiveResize = function (e, datatable, columns) {
		var me = this;
		// Execute this as the last one
		setTimeout(function () {
			var $filterColumns = me.$table.find('.filters-row').children('.filter-column');
			// Toggle filter columns visibility
			$.each(columns, function (index, state) {
				$filterColumns.eq(index).css('display', state ? '' : 'none');
			});
		}, 16);
	};

	/**
	 * Handles DataTable ColumnVisibility event.
	 *
	 * @param e
	 * @param settings
	 * @param column
	 * @param state
	 * @param recalc
	 * @private
	 */
	Plugin.prototype._onDataTableColumnVisibility = function (e, settings, column, state, recalc) {
		// Toggle filters row column visibility
		this.$table.find('.filters-row').children().eq(column).toggleClass('hidden', !state);
	};

	/**
	 * Handles DataTable ColumnReorder event.
	 *
	 * @param e
	 * @param settings
	 * @param details
	 * @private
	 */
	Plugin.prototype._onDataTableColumnReorder = function (e, settings, details) {
		// Reload DataTable to preserve the ajax.params() columns orders
		this.getDataTable().ajax.reload();
	};

	/**
	 * Handles DataTable FieldColumn visible control change event.
	 *
	 * @param e
	 * @private
	 */
	Plugin.prototype._onFieldColumnChange = function (e) {
		var $target = $(e.target),
			targetData = $target.data(),
			rowData = this.getDataTable().row($target.closest('tr').get(0)).data(),
			getValue = function () {
				var value = $target.val();
				// Set value as the checked property as number
				if ($target.is(':checkbox')) {
					value = +$target.prop('checked');
				}
				// Cast to custom types
				if (targetData.type === 'number') {
					value = +value;
				} else if (targetData.type === 'boolean') {
					value = !!value;
				}
				return value;
			},
			xhrData = {
				dt: this.$table.attr('id'),
				value: getValue(),
				key: rowData[targetData.key],
				attribute: targetData.attribute,
				params: targetData.params
			};
		// Make the AJAX call
		var xhr = $.ajax({
			url: targetData.url || window.location.path,
			method: targetData.method || 'POST',
			data: xhrData
		});
		// Call custom method on done if is set
		if (targetData.onDone && typeof this[targetData.onDone] === 'function') {
			xhr.done(this[targetData.onDone].bind(this));
		}
	};

	/**
	 * Handles DataTable CheckboxColumn change event.
	 *
	 * @param e
	 * @private
	 */
	Plugin.prototype._onCheckboxColumnChange = function (e) {
		var $target = $(e.currentTarget),
			$row = $target.closest('tr'),
			$tableWrapper = $target.closest('.dataTables_wrapper'),
			$table = $tableWrapper.find('#' + this.$table.attr('id')),
			$thead = $tableWrapper.find('.dataTables_scroll').length ?
				$tableWrapper.find('.dataTables_scrollHead thead') :
				$table.children('thead'),
			$checkAll = $thead.find('.checkbox-column :checkbox:enabled'),
			$bodyCheckboxes = $table.children('tbody').find('.checkbox-column :checkbox:enabled'),
			me = this;
		// Exit if the table is not of the current plugin instance
		if (!$table.is(this.$table)) {
			return;
		}
		// If the target is the check all control
		if ($target.is($checkAll)) {
			// Set all body checkboxes checked state
			$bodyCheckboxes.prop('checked', $checkAll.prop('checked'));
			// Loop through the body checkboxes
			$bodyCheckboxes.each(function (index, checkbox) {
				var $checkbox = $(checkbox);
				// Select / deselect the current row
				me.getDataTable().row($checkbox.closest('tr').get(0))[$checkbox.prop('checked') ? 'select' : 'deselect']();
			});
		} else {
			// Set the check all control checked state based on the number of the body selected checkboxes
			$checkAll.prop('checked', $bodyCheckboxes.length === $bodyCheckboxes.filter(':checked').length);
			// Select / deselect the current row
			this.getDataTable().row($row.get(0))[$target.prop('checked') ? 'select' : 'deselect']();
		}
		// Toggle bulk control visibility
		this.toggleBulkControlVisibility();
		this._hook('onCheckboxColumnChange', this.getDataTable().rows({selected: true}).count() >= this.options.bulkMinCheck);
	};

	/**
	 * Handles DataTable ActionColumn record operation click.
	 *
	 * @param e
	 * @private
	 */
	Plugin.prototype._onRecordOperationClick = function (e) {
		var me = this,
			$target = $(e.currentTarget),
			$row = $target.closest('tr'),
			dataTableRowData = this.getDataTable().row($row.index()).data(),
			targetData = $target.data(),
			xhrData = {
				dt_operation: targetData.dtOperation,
				params: targetData.dtParams,
				columns: {}
			},
			handleRequest = function () {
				me.showProcessing(true);
				// Ensure that columns is traversable
				if (typeof targetData.dtColumns === 'string') {
					targetData.dtColumns = targetData.dtColumns.split();
				}
				// Add row columns to the XHR data
				$.each(targetData.dtColumns, function (index, column) {
					xhrData.columns[column] = dataTableRowData[column];
				});
				// Make the XHR
				$.ajax({
					url: targetData.dtUrl || $target.attr('href') || window.location.href,
					method: targetData.dtMethod || 'POST',
					data: xhrData
				}).done(function (response) {
					me.redrawAndNotify(response);
				}).always(function () {
					me.showProcessing(false);
				});
			};
		// Check if operation should be confirmed
		if (targetData.dtConfirm) {
			// Show confirm dialog
			appDialog.confirm(targetData.dtConfirm, function (isConfirmed) {
				if (isConfirmed) {
					handleRequest();
				}
			});
		} else {
			handleRequest();
		}
		// Prevent default action
		e.stopImmediatePropagation();
		return false;
	};

	/**
	 * Handles DataTable records bulk operation click.
	 *
	 * @param e
	 * @private
	 */
	Plugin.prototype._onBulkOperationClick = function (e) {
		var me = this,
			$target = $(e.currentTarget),
			targetData = $target.data(),
			$relatedTable = this.$body.find(targetData.dtTable),
			$selectedCheckboxes = $relatedTable.children('tbody').find('.checkbox-column :checkbox:checked'),
			xhrData = {
				dt_bulk_operation: targetData.dtBulkOperation,
				params: targetData.dtParams,
				selection: $.map($selectedCheckboxes.serializeArray(), function (field) {
					return field.value;
				})
			},
			handleRequest = function () {
				me.showProcessing(true);
				// Exit if the selection is empty
				if (!xhrData.selection || !xhrData.selection.length) {
					return true;
				}
				// Make the XHR
				$.ajax({
					url: targetData.dtUrl || $target.attr('href') || window.location.href,
					method: targetData.dtMethod || 'POST',
					data: xhrData
				}).done(function (response) {
					me.redrawAndNotify(response, $relatedTable.DataTable());
				}).always(function () {
					me.showProcessing(false);
				});
			};
		// Prevent the XHR if the bulk operation name is not specified
		if (!targetData.dtBulkOperation) {
			return;
		}
		// Check if operation should be confirmed
		if (targetData.dtConfirm) {
			// Show confirm dialog
			appDialog.confirm(targetData.dtConfirm, function (isConfirmed) {
				if (isConfirmed) {
					handleRequest();
				}
			});
		} else {
			handleRequest();
		}
		// Prevent default action
		e.stopImmediatePropagation();
		return false;
	};

	/**
	 * Handles DataTable ActionColumn clear filters control click.
	 *
	 * @param e
	 * @private
	 */
	Plugin.prototype._onClearFiltersClick = function (e) {
		var $target = $(e.currentTarget),
			$tableWrapper = $target.closest('.dataTables_wrapper'),
			$table = $tableWrapper.find('#' + this.$table.attr('id'));
		// Exit if the table is not of the current plugin instance
		if (!$table.is(this.$table)) {
			return;
		}
		// Clear the DataTable columns search, then redraw
		this.getDataTable().columns().search('').draw();
		// Clear the individual column filer value
		if ($tableWrapper.find('.dataTables_scroll').length) {
			$tableWrapper.find('.dataTables_scrollHead .filters-row :input').val('').trigger('change.select2');
		} else {
			$table.children('thead').find('.filters-row :input').val('').trigger('change.select2');
		}
	};

	/**
	 * Handles DataTable filter column control change event.
	 *
	 * @param e
	 * @private
	 */
	Plugin.prototype._onFilterColumnChange = function (e) {
		var $target = $(e.target),
			$th = $target.closest('th'),
			$tableWrapper = $target.closest('.dataTables_wrapper'),
			$table = $tableWrapper.find('#' + this.$table.attr('id'));

		// Prevent "change" event for input DOM elements that can handle "input" event
		if ($target.is('input:not(:checkbox, :radio)') && e.type === 'change') {
			return;
		}

		// Apply column filter
		$table.DataTable()
			.column($th.index())
			.search($target.val())
			.draw();
	};

	/**
	 * Handles DataTable external filter control change event.
	 *
	 * @param e
	 * @private
	 */
	Plugin.prototype._onExternalFilterChange = function (e) {
		var me = this;

		if (this._externalFilterTimeout) {
			clearTimeout(this._externalFilterTimeout);
		}
		this._externalFilterTimeout = setTimeout(function () {
			var $target = $(e.target),
				targetData = $target.data(),
				$table = targetData.dtExternalFilter ? me.$body.find(targetData.dtExternalFilter) : me.$table;
			// Apply external filter by simply draw the table
			$table.DataTable().draw();
		}, e.type === 'input' ? 200 : 0);
	};

	/**
	 * Handles SwitchInput plugin change event.
	 *
	 * @param e
	 * @private
	 */
	Plugin.prototype._onSwitchInputChange = function (e) {
		// Trigger custom change event to make AJAX call
		$(e.target).trigger('change' + EVENT_NS);
	};

	/**
	 * Handles DateTimePicker plugin change event.
	 *
	 * @param e
	 * @private
	 */
	Plugin.prototype._onDateTimePickerChange = function (e) {
		// Trigger custom change event to make AJAX call
		$(e.target).find('.datetimepicker-input').trigger('input' + EVENT_NS);
	};

	/**
	 * Shows or hides the processing DOM element.
	 *
	 * @param state
	 */
	Plugin.prototype.showProcessing = function (state) {
		var $processingElement = this.$table.closest('.dataTables_wrapper').find('.dataTables_processing');

		$processingElement.toggle(!!state);
	};

	/**
	 * Reinitialize plugins.
	 *
	 * @param $container
	 */
	Plugin.prototype.reinitPlugins = function ($container) {
		// Fallback container to the current plugin element
		$container = $container || this.$table;
		var me = this,
			$selects2 = $container.find('[data-krajee-select2]'),
			$bootstrapSwitches = $container.find('[data-krajee-bootstrapSwitch]'),
			$numberControls = $container.find('[data-krajee-numbercontrol]'),
			$touchSpins = $container.find('[data-krajee-touchspin]'),
			$datetimepickers = $container.find('.datetimepicker');
		// Select2
		if ($.fn.select2 && $selects2.length) {
			$selects2.each(function (index, select2) {
				var $select2 = $(select2),
					data = $select2.data(),
					rowData = me.getDataTable().row($select2.closest('tr').get(0)).data(),
					options = window[$select2.attr('data-krajee-select2')];
				// Destroy existing plugin instance
				if ($select2.data('select2')) {
					$select2.select2('destroy');
				}
				if (rowData) {
					// Set a new ID
					$select2.attr('id', 'dt-row-select2-' + index);
					// Set the value
					$select2.val(rowData[data.attribute]).trigger('change.select2');
				}
				// Init the plugin
				$.when($select2.select2(options)).done(initS2Loading($select2.attr('id'), $select2.attr('data-s2-options')));
			});
		}
		// BootstrapSwitch
		if ($.fn.bootstrapSwitch && $bootstrapSwitches.length) {
			$bootstrapSwitches.each(function (index, bootstrapSwitch) {
				var $bootstrapSwitch = $(bootstrapSwitch),
					data = $bootstrapSwitch.data(),
					rowData = me.getDataTable().row($bootstrapSwitch.closest('tr').get(0)).data(),
					options = window[$bootstrapSwitch.attr('data-krajee-bootstrapswitch')];
				// Destroy existing plugin instance
				if ($bootstrapSwitch.data('bootstrapSwitch')) {
					$bootstrapSwitch.bootstrapSwitch('destroy');
				}
				if (rowData) {
					// Set a new ID
					$bootstrapSwitch.attr('id', 'dt-row-bootstrap-switch-' + index);
					// Set the state as boolean value
					options.state = !!rowData[data.attribute];
				}
				// Init the plugin
				$bootstrapSwitch.bootstrapSwitch(options);
			});
		}
		// NumberControl
		if ($.fn.numberControl && $numberControls.length) {
			$numberControls.each(function (index, numberControl) {
				var $numberControl = $(numberControl),
					$dispControl = $numberControl.parent().prev('[id*="-disp"]'),
					options = window[$numberControl.attr('data-krajee-numbercontrol')];
				// Destroy existing plugin instance
				if ($numberControl.data('numberControl')) {
					$numberControl.numberControl('destroy');
				}
				// Set a new ID
				options.displayId = 'number-control-' + index + '-disp';
				$dispControl.attr('id', options.displayId).addClass('number-control-disp');
				$numberControl.attr('id', 'number-control-' + index);
				// Init the plugin
				$numberControl.numberControl(options);
			});
		}
		// TouchSpin
		if ($.fn.TouchSpin && $touchSpins.length) {
			$touchSpins.each(function (index, touchSpin) {
				var $touchSpin = $(touchSpin),
					options = window[$touchSpin.attr('data-krajee-touchspin')];
				// Destroy existing plugin instance
				if ($touchSpin.data('TouchSpin')) {
					$touchSpin.TouchSpin('destroy');
				}
				// Set a new ID
				$touchSpin.attr('id', 'touchspin-' + index);
				// Init the plugin
				$touchSpin.TouchSpin(options);
			});
		}
		// DateTimePicker
		if ($.fn.datetimepicker && $datetimepickers.length) {
			$datetimepickers.each(function (index, datetimepicker) {
				var $datetimepicker = $(datetimepicker),
					$formControl = $datetimepicker.find('.datetimepicker-input');
				// Destroy existing plugin instance
				if ($datetimepicker.data('DateTimePicker')) {
					$datetimepicker.data('DateTimePicker').destroy();
				}
				// Init the plugin
				$datetimepicker.datetimepicker(window[$formControl.attr('data-datetimepicker-options')]);
			});
		}
	};

	/**
	 * Toggles bulk delete control visibility based on a state value.
	 *
	 * @param state
	 */
	Plugin.prototype.toggleBulkControlVisibility = function (state) {
		// If state is not set, check the number of the selected rows
		if (typeof state === 'undefined') {
			state = this.getDataTable().rows({selected: true}).count() < this.options.bulkMinCheck;
		}
		// Display the linked bulk delete button only if the selected rows match the bulkMinCheck option
		$('[data-dt-bulk-operation][data-dt-table="#' + this.$table.attr('id') + '"]').toggleClass('hidden', state);
	};

	/**
	 * Redraws DataTable and shows a notification after a certain operation.
	 *
	 * @param response
	 * @param dataTable
	 */
	Plugin.prototype.redrawAndNotify = function (response, dataTable) {
		dataTable = $.fn.dataTable.isDataTable(dataTable) ? dataTable : this.getDataTable();
		// If the operation was success
		if (response.success === true) {
			dataTable.draw();
		}
		// Show notification
		notify.show(response);
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
			eventName = '';

		if (hookName.substr(0, 2) === 'on') {
			eventName = hookName.slice(2).charAt(0).toLowerCase() + hookName.slice(3);
		} else {
			eventName = hookName.charAt(0).toLowerCase() + hookName.slice(1);
		}
		eventName += EVENT_NS;

		// Execute the callback
		if (typeof this.options[hookName] === 'function') {
			this.options[hookName].apply(this.element, args);
		}
		// Create a new event
		var event = $.Event(eventName, {
			target: this.element
		});
		// Trigger the event
		this.$table.trigger(event, args);
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
		this.$table.off(EVENT_NS);
		this.$table.removeData(DATA_KEY);
	};

	/**
	 * Plugin definition
	 * @function external "jQuery.fn".timeslots
	 */
	$.fn[PLUGIN_NAME] = function (options) {
		var args = arguments;

		if (!options || typeof options === "object") {
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
