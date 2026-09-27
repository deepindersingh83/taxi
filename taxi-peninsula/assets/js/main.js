/* Taxi Peninsula — front-end behaviour (no dependencies). */
(function () {
	'use strict';

	var root = document.documentElement;

	function store(key, value) {
		try {
			if (value === null) { localStorage.removeItem(key); } else { localStorage.setItem(key, value); }
		} catch (e) { /* storage unavailable — preference lasts for this page only */ }
	}

	/* Text size buttons. */
	var sizeButtons = document.querySelectorAll('[data-text-size]');
	function applySize(size) {
		if (size === 'md') { root.removeAttribute('data-text-size'); } else { root.setAttribute('data-text-size', size); }
		sizeButtons.forEach(function (b) {
			b.setAttribute('aria-pressed', b.getAttribute('data-text-size') === size ? 'true' : 'false');
		});
	}
	applySize(root.getAttribute('data-text-size') || 'md');
	sizeButtons.forEach(function (btn) {
		btn.addEventListener('click', function () {
			var size = btn.getAttribute('data-text-size');
			applySize(size);
			store('tp-text-size', size === 'md' ? null : size);
		});
	});

	/* High contrast toggle. */
	var contrast = document.querySelector('[data-contrast-toggle]');
	if (contrast) {
		contrast.setAttribute('aria-pressed', root.getAttribute('data-contrast') === 'high' ? 'true' : 'false');
		contrast.addEventListener('click', function () {
			var on = root.getAttribute('data-contrast') !== 'high';
			if (on) { root.setAttribute('data-contrast', 'high'); } else { root.removeAttribute('data-contrast'); }
			contrast.setAttribute('aria-pressed', on ? 'true' : 'false');
			store('tp-contrast', on ? 'high' : null);
		});
	}

	/* Mobile navigation. */
	var toggle = document.querySelector('.nav-toggle');
	var nav = document.getElementById('primary-nav');
	if (toggle && nav) {
		function setOpen(open) {
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			nav.classList.toggle('is-open', open);
		}
		toggle.addEventListener('click', function () {
			setOpen(toggle.getAttribute('aria-expanded') !== 'true');
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && nav.classList.contains('is-open')) {
				setOpen(false);
				toggle.focus();
			}
		});
		nav.addEventListener('click', function (e) {
			if (e.target.closest('a')) { setOpen(false); }
		});
	}

	/* Show/hide dependent fields (e.g. return trip time). */
	document.querySelectorAll('[data-toggle-target]').forEach(function (input) {
		var target = document.querySelector(input.getAttribute('data-toggle-target'));
		if (!target) { return; }
		function sync() { target.hidden = !input.checked; }
		input.addEventListener('change', sync);
		sync();
	});

	/* Payment method panels (account details, online deposit note). */
	var payRadios = document.querySelectorAll('input[name="payment"]');
	if (payRadios.length) {
		var syncPay = function () {
			var chosen = document.querySelector('input[name="payment"]:checked');
			var value = chosen ? chosen.value : '';
			document.querySelectorAll('[data-payment-panel]').forEach(function (panel) {
				panel.hidden = panel.getAttribute('data-payment-panel') !== value;
			});
		};
		payRadios.forEach(function (r) { r.addEventListener('change', syncPay); });
		syncPay();
	}

	/* Sedan = no wheelchair; keep the numbers sensible. */
	var form = document.querySelector('form.booking-form');
	if (form) {
		var wheelchairs = form.querySelector('[name="wheelchairs"]');
		form.querySelectorAll('[name="vehicle"]').forEach(function (radio) {
			radio.addEventListener('change', function () {
				if (!wheelchairs) { return; }
				if (radio.value === 'sedan') { wheelchairs.value = 0; } else if (+wheelchairs.value === 0) { wheelchairs.value = 1; }
			});
		});

		/* Friendly client-side check before the server validates again. */
		form.addEventListener('submit', function (e) {
			var firstInvalid = null;
			form.querySelectorAll('[required]').forEach(function (el) {
				if (el.closest('[hidden]')) { return; }
				var ok = el.type === 'checkbox' ? el.checked : el.value.trim() !== '' && el.checkValidity();
				el.setAttribute('aria-invalid', ok ? 'false' : 'true');
				if (!ok && !firstInvalid) { firstInvalid = el; }
			});
			if (firstInvalid) {
				e.preventDefault();
				firstInvalid.focus();
				return;
			}
			var submit = form.querySelector('[type="submit"]');
			if (submit) { submit.disabled = true; submit.setAttribute('aria-busy', 'true'); }
		});
	}

	/* ------------------------------------------------------------------
	 * Address suggestions (accessible combobox, ARIA 1.2 pattern) and
	 * fare estimate. Config comes from window.tpBooking (set by PHP).
	 * ------------------------------------------------------------------ */
	var cfg = window.tpBooking || {};

	function debounce(fn, ms) {
		var t;
		return function () {
			var args = arguments, self = this;
			clearTimeout(t);
			t = setTimeout(function () { fn.apply(self, args); }, ms);
		};
	}

	function uuid() {
		if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
			var r = Math.random() * 16 | 0;
			return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
		});
	}

	var live = null;
	function announce(msg) {
		if (!live) {
			live = document.createElement('div');
			live.className = 'screen-reader-text';
			live.setAttribute('aria-live', 'polite');
			document.body.appendChild(live);
		}
		live.textContent = '';
		setTimeout(function () { live.textContent = msg; }, 50);
	}

	function initCombobox(input) {
		var session = uuid();
		var listId = input.id + '-list';
		var wrap = document.createElement('div');
		wrap.className = 'combo';
		input.parentNode.insertBefore(wrap, input);
		wrap.appendChild(input);

		var list = document.createElement('ul');
		list.id = listId;
		list.className = 'combo__list';
		list.setAttribute('role', 'listbox');
		list.setAttribute('aria-label', input.labels && input.labels[0] ? input.labels[0].textContent.replace('*', '').trim() : '');
		list.hidden = true;
		wrap.appendChild(list);

		input.setAttribute('role', 'combobox');
		input.setAttribute('aria-autocomplete', 'list');
		input.setAttribute('aria-expanded', 'false');
		input.setAttribute('aria-controls', listId);
		input.setAttribute('autocomplete', 'off');

		var items = [], active = -1, lastQuery = '';

		function close() {
			list.hidden = true;
			input.setAttribute('aria-expanded', 'false');
			input.removeAttribute('aria-activedescendant');
			active = -1;
		}

		function highlight(i) {
			var opts = list.querySelectorAll('[role="option"]');
			opts.forEach(function (o, n) { o.setAttribute('aria-selected', n === i ? 'true' : 'false'); });
			active = i;
			if (i >= 0 && opts[i]) {
				input.setAttribute('aria-activedescendant', opts[i].id);
				opts[i].scrollIntoView({ block: 'nearest' });
			} else {
				input.removeAttribute('aria-activedescendant');
			}
		}

		function choose(i) {
			if (!items[i]) { return; }
			input.value = items[i].text;
			close();
			session = uuid(); // A selection ends the billing session.
			input.dispatchEvent(new Event('change', { bubbles: true }));
		}

		function render() {
			list.innerHTML = '';
			items.forEach(function (it, i) {
				var li = document.createElement('li');
				li.id = listId + '-' + i;
				li.setAttribute('role', 'option');
				li.setAttribute('aria-selected', 'false');
				li.className = 'combo__option';
				var main = document.createElement('span');
				main.className = 'combo__main';
				main.textContent = it.main || it.text;
				li.appendChild(main);
				if (it.secondary) {
					var sec = document.createElement('span');
					sec.className = 'combo__secondary';
					sec.textContent = it.secondary;
					li.appendChild(sec);
				}
				li.addEventListener('mousedown', function (e) { e.preventDefault(); choose(i); });
				list.appendChild(li);
			});
			if (items.length) {
				var credit = document.createElement('li');
				credit.className = 'combo__credit';
				credit.setAttribute('role', 'presentation');
				credit.textContent = (cfg.i18n && cfg.i18n.powered) || '';
				list.appendChild(credit);
			}
			list.hidden = !items.length;
			input.setAttribute('aria-expanded', items.length ? 'true' : 'false');
			active = -1;
			announce(items.length ? cfg.i18n.results.replace('%d', items.length) : cfg.i18n.noResults);
		}

		var search = debounce(function () {
			var q = input.value.trim();
			if (q.length < 3 || q === lastQuery) {
				if (q.length < 3) { items = []; close(); }
				return;
			}
			lastQuery = q;
			fetch(cfg.placesUrl + (cfg.placesUrl.indexOf('?') > -1 ? '&' : '?') + 'q=' + encodeURIComponent(q) + '&session=' + session.replace(/-/g, ''), { credentials: 'same-origin' })
				.then(function (r) { return r.ok ? r.json() : { suggestions: [] }; })
				.then(function (data) {
					if (input.value.trim() !== q) { return; }
					items = data.suggestions || [];
					render();
				})
				.catch(function () { items = []; close(); });
		}, 250);

		input.addEventListener('input', search);
		input.addEventListener('keydown', function (e) {
			if (list.hidden) {
				if (e.key === 'ArrowDown' && items.length) { list.hidden = false; input.setAttribute('aria-expanded', 'true'); highlight(0); e.preventDefault(); }
				return;
			}
			if (e.key === 'ArrowDown') { highlight(Math.min(active + 1, items.length - 1)); e.preventDefault(); }
			else if (e.key === 'ArrowUp') { highlight(Math.max(active - 1, 0)); e.preventDefault(); }
			else if (e.key === 'Enter' && active >= 0) { choose(active); e.preventDefault(); }
			else if (e.key === 'Escape') { close(); e.preventDefault(); }
		});
		input.addEventListener('blur', function () { setTimeout(close, 100); });
	}

	if (cfg.places && cfg.placesUrl && window.fetch) {
		document.querySelectorAll('input[data-places]').forEach(initCombobox);
	}

	var fareBox = document.querySelector('[data-fare-estimate]');
	if (cfg.fare && fareBox && window.fetch) {
		var fForm = fareBox.closest('form');
		var fields = ['pickup', 'dropoff', 'date', 'time'].map(function (n) { return fForm.querySelector('[name="' + n + '"]'); });
		var lastKey = '';
		var update = debounce(function () {
			var v = fields.map(function (f) { return f ? f.value.trim() : ''; });
			if (!v[0] || !v[1] || v[0].length < 5 || v[1].length < 5) { fareBox.hidden = true; return; }
			var key = v.join('|');
			if (key === lastKey) { return; }
			lastKey = key;
			fareBox.hidden = false;
			fareBox.textContent = cfg.i18n.estimating;
			var url = cfg.estimateUrl + (cfg.estimateUrl.indexOf('?') > -1 ? '&' : '?') +
				'from=' + encodeURIComponent(v[0]) + '&to=' + encodeURIComponent(v[1]) + '&date=' + encodeURIComponent(v[2]) + '&time=' + encodeURIComponent(v[3]);
			fetch(url, { credentials: 'same-origin' })
				.then(function (r) { return r.ok ? r.json() : { ok: false }; })
				.then(function (d) {
					if (lastKey !== key) { return; }
					fareBox.innerHTML = '';
					if (!d.ok) { fareBox.textContent = cfg.i18n.noEstimate; return; }
					var strong = document.createElement('strong');
					strong.textContent = cfg.i18n.estimate + ': ' + d.text;
					var small = document.createElement('span');
					small.className = 'fare-estimate__detail';
					small.textContent = d.detail;
					fareBox.appendChild(strong);
					fareBox.appendChild(small);
					var note = document.createElement('p');
					note.className = 'field__hint';
					note.textContent = fareBox.getAttribute('data-note') || '';
					if (note.textContent) { fareBox.appendChild(note); }
				})
				.catch(function () { fareBox.textContent = cfg.i18n.noEstimate; });
		}, 600);
		fields.forEach(function (f) { if (f) { f.addEventListener('change', update); f.addEventListener('blur', update); } });
		update();
	}

	/* Cookie consent + Google Analytics (loaded only after "Accept"). */
	var consentCfg = window.tpConsent;
	if (consentCfg && consentCfg.ga) {
		var banner = document.getElementById('tp-consent');
		var getChoice = function () { try { return localStorage.getItem('tp-consent'); } catch (e) { return null; } };
		var loadGa = function () {
			if (window.tpGaLoaded) { return; }
			window.tpGaLoaded = true;
			window.dataLayer = window.dataLayer || [];
			window.gtag = function () { window.dataLayer.push(arguments); };
			window.gtag('js', new Date());
			window.gtag('config', consentCfg.ga, { anonymize_ip: true });
			var s = document.createElement('script');
			s.async = true;
			s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(consentCfg.ga);
			document.head.appendChild(s);
		};
		var choice = getChoice();
		if (!consentCfg.required || choice === 'granted') {
			loadGa();
		} else if (!choice && banner) {
			banner.hidden = false;
		}
		if (banner) {
			banner.querySelectorAll('[data-consent]').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var value = btn.getAttribute('data-consent');
					store('tp-consent', value);
					banner.hidden = true;
					if (value === 'granted') {
						loadGa();
					} else if (window.tpGaLoaded) {
						// Analytics already ran this visit; a reload stops it and clears its cookies.
						document.cookie.split(';').forEach(function (c) {
							var name = c.split('=')[0].trim();
							if (/^_ga/.test(name)) { document.cookie = name + '=; Max-Age=0; path=/; domain=.' + location.hostname.replace(/^www\./, ''); }
						});
						location.reload();
					}
				});
			});
			document.querySelectorAll('[data-consent-open]').forEach(function (link) {
				link.addEventListener('click', function () {
					banner.hidden = false;
					var first = banner.querySelector('[data-consent]');
					if (first) { first.focus(); }
				});
			});
		}
	}

	/* Move focus to server-rendered errors / success so screen readers announce them. */
	var focusTarget = document.querySelector('[data-focus]');
	if (focusTarget) {
		focusTarget.focus({ preventScroll: true });
		focusTarget.scrollIntoView({ block: 'center' });
	}
})();
