/* Taxi Peninsula — Customizer: accessible show/hide + reorder list for home sections. */
(function ($) {
	'use strict';
	function sync(list) {
		var value = list.find('.tp-sortable__item').map(function () {
			var item = $(this);
			var on = item.find('input[type=checkbox]').prop('checked');
			item.toggleClass('is-hidden', !on);
			return (on ? '' : '-') + item.data('key');
		}).get().join(',');
		list.siblings('.tp-sortable-value').val(value).trigger('change');
	}
	$(document).on('change', '.tp-sortable input[type=checkbox]', function () {
		sync($(this).closest('.tp-sortable'));
	});
	$(document).on('click', '.tp-sortable .tp-up, .tp-sortable .tp-down', function () {
		var btn = $(this);
		var item = btn.closest('.tp-sortable__item');
		if (btn.hasClass('tp-up')) { item.prev().before(item); } else { item.next().after(item); }
		btn.focus();
		sync(item.closest('.tp-sortable'));
		if (wp.a11y && wp.a11y.speak) {
			wp.a11y.speak(item.find('label').text().trim() + ': ' + (item.index() + 1));
		}
	});
	$(function () {
		$('.tp-sortable').each(function () {
			$(this).find('.tp-sortable__item').each(function () {
				$(this).toggleClass('is-hidden', !$(this).find('input[type=checkbox]').prop('checked'));
			});
		});
	});
})(jQuery);
