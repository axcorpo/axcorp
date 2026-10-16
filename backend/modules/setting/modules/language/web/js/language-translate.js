/**
 * Language Translate App.
 */
(function ($, window, document, undefined) {
	this.languageTranslateApp = {
		/**
		 * Initialization.
		 */
		init: function () {
			this._cacheElements();
			this._bindEvents();
		},

		/**
		 * Caches DOM elements.
		 *
		 * @private
		 */
		_cacheElements: function () {
			this.$document = $(document);
			this.$body = $(document.body);
			this.$table = this.$body.find('.data-table');
		},

		/**
		 * Binds events.
		 *
		 * @private
		 */
		_bindEvents: function () {
			this.$table.on('draw.dt', this._onDataTableDraw.bind(this));
			this.$table.on('click', '.control-source', this._onSourceControlClick.bind(this));
			this.$table.on('click', 'div.control-translation', this._onTranslationControlClick.bind(this));
		},

		/**
		 * Handles translation form control click event.
		 *
		 * @param e
		 * @private
		 */
		_onDataTableDraw: function (e) {
			this.$table.find('div.control-translation').each(function (index, item) {
				var $item = $(item),
					$textarea = $item.closest('td').children('textarea.control-translation');

				if ($item.tinymce()) {
					$item.tinymce().remove();
				}
			});
		},

		/**
		 * Handles source form control click event.
		 *
		 * @param e
		 * @private
		 */
		_onSourceControlClick: function (e) {
			var $target = $(e.target),
				$row = $target.closest('tr'),
				$col = $row.children('.translation-column');
			// Copy the value from source to translation
			$col.children('.control-translation').html($target.html());
			$col.children('textarea').val($target.html()).trigger('change');
		},

		/**
		 * Handles translation form control click event.
		 *
		 * @param e
		 * @private
		 */
		_onTranslationControlClick: function (e) {
			var $target = $(e.currentTarget),
				$textarea = $target.closest('td').children('textarea');

			if (!$target.tinymce()) {
				$target.tinymce({
					mode: 'none',
					menubar: false,
					statusbar: false,
					inline: true,
					auto_focus: $target.attr('id'),
					plugins: 'paste textcolor colorpicker',
					toolbar1: 'undo redo | bold italic forecolor backcolor | removeformat',
					forced_root_block: '',
					paste_as_text: true,
					entity_encoding: 'raw',
					setup: function (editor) {
						editor.on('change', function () {console.log('here');
							$textarea.val(editor.getContent()).trigger('change');
						});
					}
				});
			}
		}
	};
})(jQuery, window, document);

/**
 * Document Ready
 */
$(document).ready(function () {
	languageTranslateApp.init();
});
