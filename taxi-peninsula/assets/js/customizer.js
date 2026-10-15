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
	// Drag and drop (mouse); ▲ ▼ buttons remain for keyboard and touch.
	var dragging = null;
	$(document).on('dragstart', '.tp-sortable__item', function (e) {
		dragging = this;
		$(this).addClass('is-dragging');
		e.originalEvent.dataTransfer.effectAllowed = 'move';
		e.originalEvent.dataTransfer.setData('text/plain', $(this).data('key'));
	});
	$(document).on('dragover', '.tp-sortable__item', function (e) {
		if (!dragging || dragging === this) { return; }
		e.preventDefault();
		$('.tp-sortable__item').removeClass('is-over');
		$(this).addClass('is-over');
	});
	$(document).on('drop', '.tp-sortable__item', function (e) {
		if (!dragging || dragging === this) { return; }
		e.preventDefault();
		var after = $(dragging).index() < $(this).index();
		if (after) { $(this).after(dragging); } else { $(this).before(dragging); }
		sync($(this).closest('.tp-sortable'));
	});
	$(document).on('dragend', '.tp-sortable__item', function () {
		$('.tp-sortable__item').removeClass('is-dragging is-over');
		dragging = null;
	});
	$(document).on('click', '.tp-sortable-reset', function () {
		var list = $(this).closest('li, .customize-control').find('.tp-sortable');
		$(this).data('default').split(',').forEach(function (item) {
			var key = item.replace(/^-/, '');
			var li = list.find('.tp-sortable__item[data-key="' + key + '"]');
			li.find('input[type=checkbox]').prop('checked', item.charAt(0) !== '-');
			list.append(li);
		});
		sync(list);
		if (wp.a11y && wp.a11y.speak) { wp.a11y.speak($(this).text()); }
	});

	$(function () {
		$('.tp-sortable').each(function () {
			$(this).find('.tp-sortable__item').each(function () {
				$(this).toggleClass('is-hidden', !$(this).find('input[type=checkbox]').prop('checked'));
			});
		});
	});
})(jQuery);
