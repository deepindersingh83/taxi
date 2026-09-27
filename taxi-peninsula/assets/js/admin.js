/* Taxi Peninsula — admin media pickers (driver photo, fleet gallery). */
(function ($) {
	'use strict';
	$(document).on('click', '[data-tp-media-pick]', function (e) {
		e.preventDefault();
		var box = $(this).closest('[data-tp-media]');
		var multiple = box.data('multiple') === 1 || box.data('multiple') === '1';
		var input = box.find('input[type=hidden]');
		var frame = wp.media({ title: $(this).text(), multiple: multiple ? 'add' : false, library: { type: 'image' } });
		frame.on('open', function () {
			var sel = frame.state().get('selection');
			(input.val() || '').split(',').filter(Boolean).forEach(function (id) {
				var a = wp.media.attachment(id); a.fetch(); sel.add(a);
			});
		});
		frame.on('select', function () {
			var items = frame.state().get('selection').toJSON();
			input.val(items.map(function (i) { return i.id; }).join(','));
			var prev = box.find('.tp-media__preview').empty();
			items.forEach(function (i) {
				var src = (i.sizes && i.sizes.thumbnail ? i.sizes.thumbnail.url : i.url);
				$('<img>', { src: src, alt: '', css: { width: 72, height: 72, objectFit: 'cover', borderRadius: 6, marginRight: 4 } }).appendTo(prev);
			});
		});
		frame.open();
	});
	$(document).on('click', '[data-tp-media-clear]', function (e) {
		e.preventDefault();
		var box = $(this).closest('[data-tp-media]');
		box.find('input[type=hidden]').val('');
		box.find('.tp-media__preview').empty();
	});
})(jQuery);
