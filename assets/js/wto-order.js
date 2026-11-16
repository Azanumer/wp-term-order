/* Term Order — drag-and-drop reordering on the term list screen. */
(function ($) {
	'use strict';

	var $tbody = $('#the-list');
	if (!$tbody.length || typeof wtoData === 'undefined') {
		return;
	}

	var saving = false;

	$tbody.sortable({
		items: 'tr',
		handle: '.wto-drag-handle',
		placeholder: 'wto-sort-placeholder',
		axis: 'y',
		helper: function (e, row) {
			// Keep column widths while dragging.
			row.children().each(function () {
				$(this).width($(this).width());
			});
			return row;
		},
		update: function () {
			if (saving) {
				return;
			}
			saving = true;
			var order = [];
			$tbody.find('tr').each(function () {
				var id = $(this).attr('id') || '';
				var m = id.match(/^tag-(\d+)$/);
				if (m) {
					order.push(parseInt(m[1], 10));
				}
			});
			$.post(wtoData.ajaxUrl, {
				action: 'wto_save_order',
				nonce: wtoData.nonce,
				taxonomy: wtoData.taxonomy,
				order: order
			}).always(function () {
				saving = false;
			});
		}
	});

	// Add a drag handle to the Order column cells.
	$tbody.find('tr').each(function () {
		var $cell = $(this).find('td.wto_order, td.column-wto_order');
		if ($cell.length) {
			$cell.prepend('<span class="wto-drag-handle" title="Drag to reorder">&#9776;</span> ');
		}
	});
})(jQuery);
