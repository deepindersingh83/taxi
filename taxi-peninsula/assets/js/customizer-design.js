/* Taxi Peninsula — Customizer: colour schemes, live contrast warnings, slider values. */
(function (api) {
	'use strict';
	var cfg = window.tpDesign || { schemes: {}, i18n: {} };
	var KEYS = ['primary', 'accent', 'link', 'background', 'footer'];
	var TEXT = '#16213a';

	function rgb(hex) {
		hex = String(hex || '').replace('#', '');
		if (hex.length === 3) { hex = hex.replace(/(.)/g, '$1$1'); }
		return [0, 2, 4].map(function (i) { return parseInt(hex.substr(i, 2), 16) || 0; });
	}
	function lum(hex) {
		var c = rgb(hex).map(function (v) {
			v /= 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
		});
		return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
	}
	function contrast(a, b) {
		var x = lum(a), y = lum(b);
		return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
	}
	function best(bg, list) {
		return list.slice().sort(function (a, b) { return contrast(b, bg) - contrast(a, bg); })[0];
	}
	function fmt(s, v) { return String(s).replace('%s', v); }

	/* The colours in use: a picked colour, or the scheme's colour when left empty. */
	function colours() {
		var scheme = cfg.schemes[api('tp_d_color_scheme').get()] || cfg.schemes.navy;
		var c = {};
		KEYS.forEach(function (k) {
			var v = api('tp_d_color_' + k) ? api('tp_d_color_' + k).get() : '';
			c[k] = /^#([0-9a-f]{3}){1,2}$/i.test(v) ? v : scheme[k];
		});
		return c;
	}

	function check() {
		var c = colours();
		var i18n = cfg.i18n;
		var onAccent = contrast(c.primary, c.accent) >= 4.5 ? c.primary : best(c.accent, ['#000000', '#ffffff']);
		var footerText = best(c.footer, ['#dfe6f3', TEXT]);
		var rules = [
			['background', 'text', contrast(TEXT, c.background), 4.5],
			['link', 'link', contrast(c.link, c.background), 4.5],
			['primary', 'primary', contrast('#ffffff', c.primary), 4.5],
			['accent', 'accent', contrast(c.accent, c.primary), 3],
			['accent', 'onAccent', contrast(onAccent, c.accent), 4.5],
			['footer', 'footer', contrast(footerText, c.footer), 4.5]
		];
		KEYS.forEach(function (k) {
			var control = api.control('tp_d_color_' + k);
			if (!control) { return; }
			['text', 'link', 'primary', 'accent', 'onAccent', 'footer'].forEach(function (code) {
				control.notifications.remove('tp_contrast_' + code);
			});
		});
		rules.forEach(function (r) {
			var control = api.control('tp_d_color_' + r[0]);
			if (control && r[2] < r[3]) {
				control.notifications.add(new api.Notification('tp_contrast_' + r[1], {
					type: 'warning',
					message: fmt(i18n[r[1]], r[2].toFixed(1))
				}));
			}
		});
	}

	api.bind('ready', function () {
		/* Picking a scheme fills in the five colour pickers. */
		api('tp_d_color_scheme', function (setting) {
			setting.bind(function (key) {
				var scheme = cfg.schemes[key];
				if (!scheme) { return; }
				KEYS.forEach(function (k) {
					if (api('tp_d_color_' + k)) { api('tp_d_color_' + k).set(scheme[k]); }
				});
				check();
			});
		});
		KEYS.forEach(function (k) {
			api('tp_d_color_' + k, function (setting) { setting.bind(check); });
		});
		check();

		/* Show the current value beside each slider. */
		document.querySelectorAll('#customize-controls input[type="range"]').forEach(function (input) {
			var out = document.createElement('output');
			out.className = 'tp-range-value';
			out.style.cssText = 'display:inline-block;min-width:4em;margin-left:.5em;font-weight:600;';
			var pct = /base_size|overlay/.test(input.getAttribute('data-customize-setting-link') || '');
			var sync = function () { out.textContent = fmt(pct ? cfg.i18n.pct : cfg.i18n.px, input.value); };
			input.insertAdjacentElement('afterend', out);
			input.addEventListener('input', sync);
			sync();
		});
	});
})(wp.customize);
