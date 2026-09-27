/* Taxi Peninsula — front-end behaviour (no dependencies). */
(function () {
	'use strict';

	var root = document.documentElement;
	var tpI18n = window.tpI18n || {};

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

	/* Dark mode toggle (follows the device setting until the visitor chooses). */
	var themeBtn = document.querySelector('[data-theme-toggle]');
	if (themeBtn) {
		var mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
		var isDark = function () {
			var t = root.getAttribute('data-theme');
			return t ? t === 'dark' : !!(mq && mq.matches);
		};
		var syncTheme = function () {
			var dark = isDark();
			themeBtn.setAttribute('aria-pressed', dark ? 'true' : 'false');
			var meta = document.querySelector('meta[name="theme-color"]');
			if (meta) { meta.setAttribute('content', dark ? '#0c1322' : '#0b2a5b'); }
		};
		themeBtn.addEventListener('click', function () {
			var next = isDark() ? 'light' : 'dark';
			root.setAttribute('data-theme', next);
			store('tp-theme', next);
			syncTheme();
		});
		if (mq && mq.addEventListener) { mq.addEventListener('change', syncTheme); }
		syncTheme();
	}

	/* Announcements: scheduled show/hide (works on cached pages) and dismiss. */
	document.querySelectorAll('[data-notice]').forEach(function (bar) {
		var id = bar.getAttribute('data-notice');
		var now = Date.now() / 1000;
		var start = +bar.getAttribute('data-start') || 0;
		var end = +bar.getAttribute('data-end') || 0;
		var dismissed = false;
		try { dismissed = localStorage.getItem('tp-notice-' + id) === '1'; } catch (e) {}
		bar.hidden = dismissed || (start && start > now) || (end && end <= now);
		var close = bar.querySelector('[data-notice-close]');
		if (close) {
			close.addEventListener('click', function () {
				bar.hidden = true;
				store('tp-notice-' + id, '1');
				var main = document.getElementById('main');
				if (main) { main.focus({ preventScroll: true }); }
			});
		}
	});

	/* Time-of-day hero message, in the business's timezone (pages may be cached). */
	var moment = document.querySelector('[data-hero-moment]');
	if (moment) {
		try {
			var moments = JSON.parse(moment.getAttribute('data-moments') || '[]');
			var tz = moment.getAttribute('data-tz') || undefined;
			var parts = new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: tz }).format(new Date());
			var hhmm = parts.replace(/[^0-9:]/g, '').slice(0, 5);
			var msg = '';
			moments.some(function (m) {
				var inWin = m[0] <= m[1] ? (hhmm >= m[0] && hhmm <= m[1]) : (hhmm >= m[0] || hhmm <= m[1]);
				if (inWin) { msg = m[2]; }
				return inWin;
			});
			var span = moment.querySelector('span');
			if (msg && span) { span.textContent = msg; moment.hidden = false; } else if (!msg) { moment.hidden = true; }
		} catch (e) { /* keep the server-rendered message */ }
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

	/* "Do you cover my suburb?" */
	function lev(a, b) {
		var m = a.length, n = b.length, d = [], i, j;
		for (i = 0; i <= m; i++) { d[i] = [i]; }
		for (j = 0; j <= n; j++) { d[0][j] = j; }
		for (i = 1; i <= m; i++) {
			for (j = 1; j <= n; j++) {
				d[i][j] = Math.min(d[i - 1][j] + 1, d[i][j - 1] + 1, d[i - 1][j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
			}
		}
		return d[m][n];
	}
	document.querySelectorAll('.suburb-check').forEach(function (box) {
		var suburbs = [];
		try { suburbs = JSON.parse(box.getAttribute('data-suburbs') || '[]'); } catch (e) {}
		var form = box.querySelector('form');
		var input = box.querySelector('input');
		var out = box.querySelector('.suburb-check__result');
		function el(tag, cls, text) {
			var n = document.createElement(tag);
			if (cls) { n.className = cls; }
			if (text) { n.textContent = text; }
			return n;
		}
		function link(href, text, cls) {
			var a = el('a', cls, text);
			a.href = href;
			return a;
		}
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var q = input.value.trim().toLowerCase().replace(/\s+/g, ' ');
			if (!q) { input.focus(); return; }
			var hit = null, near = null, best = 99;
			suburbs.forEach(function (s) {
				if (hit) { return; }
				if (s.aliases.indexOf(q) > -1) { hit = s; return; }
				s.aliases.forEach(function (a) {
					if (/^\d{4}$/.test(a)) { return; }
					var dist = lev(q, a);
					if (dist < best) { best = dist; near = s; }
				});
			});
			if (!hit && near && best <= (q.length > 6 ? 2 : 1)) { hit = near; }
			out.innerHTML = '';
			out.hidden = false;
			out.className = 'suburb-check__result ' + (hit ? 'is-yes' : 'is-no');
			if (hit) {
				out.appendChild(el('p', 'suburb-check__answer', (window.tpI18n && tpI18n.yes ? tpI18n.yes : 'Yes — we cover %s.').replace('%s', hit.name)));
				var actions = el('p', 'suburb-check__actions');
				actions.appendChild(link(hit.book, (tpI18n.book || 'Book a pick-up in %s').replace('%s', hit.name), 'btn btn--accent'));
				if (hit.url) { actions.appendChild(link(hit.url, (tpI18n.about || 'Wheelchair taxis in %s').replace('%s', hit.name), 'link-arrow')); }
				out.appendChild(actions);
			} else {
				out.appendChild(el('p', 'suburb-check__answer', (tpI18n.no || '%s is not on our regular list, but we often travel further on request.').replace('%s', input.value.trim())));
				var call = el('p', 'suburb-check__actions');
				call.appendChild(link(box.getAttribute('data-phone-href'), (tpI18n.call || 'Call %s to check').replace('%s', box.getAttribute('data-phone')), 'btn btn--primary'));
				out.appendChild(call);
			}
		});
	});

	/* Instant FAQ search. */
	document.querySelectorAll('[data-faq]').forEach(function (faq) {
		var search = faq.querySelector('[data-faq-search]');
		if (!search) { return; }
		var count = faq.querySelector('[data-faq-count]');
		var empty = faq.querySelector('[data-faq-empty]');
		var items = faq.querySelectorAll('.faq__item');
		var topics = faq.querySelector('.faq__topics');
		var run = debounce(function () {
			var words = search.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
			var shown = 0;
			items.forEach(function (item) {
				var text = item.textContent.toLowerCase();
				var ok = words.every(function (w) { return text.indexOf(w) > -1; });
				item.hidden = !ok;
				if (ok) { shown++; }
				if (words.length && ok) { item.open = true; } else if (!words.length) { item.open = false; }
			});
			faq.querySelectorAll('[data-faq-section]').forEach(function (sec) {
				sec.hidden = !sec.querySelector('.faq__item:not([hidden])');
			});
			if (topics) { topics.hidden = words.length > 0; }
			if (empty) { empty.hidden = shown > 0; }
			if (count) { count.textContent = words.length ? (tpI18n.faqCount || '%d matching questions').replace('%d', shown) : ''; }
		}, 150);
		search.addEventListener('input', run);
	});

	/* Accessible photo viewer (native <dialog>: focus trap, Esc to close). */
	var lightbox = null;
	function openLightbox(items, start, trigger) {
		if (!window.HTMLDialogElement) { window.open(items[start].full, '_blank'); return; }
		if (!lightbox) {
			lightbox = document.createElement('dialog');
			lightbox.className = 'lightbox';
			lightbox.setAttribute('aria-label', tpI18n.of ? '' : 'Photo');
			lightbox.innerHTML = '<div class="lightbox__inner"><figure class="lightbox__figure"><img class="lightbox__img" alt=""><figcaption class="lightbox__caption"></figcaption></figure>' +
				'<p class="lightbox__count" aria-live="polite"></p>' +
				'<button type="button" class="lightbox__btn lightbox__prev"></button><button type="button" class="lightbox__btn lightbox__next"></button>' +
				'<button type="button" class="lightbox__btn lightbox__close"></button></div>';
			document.body.appendChild(lightbox);
			lightbox.querySelector('.lightbox__prev').setAttribute('aria-label', tpI18n.prev || 'Previous photo');
			lightbox.querySelector('.lightbox__next').setAttribute('aria-label', tpI18n.next || 'Next photo');
			lightbox.querySelector('.lightbox__close').setAttribute('aria-label', tpI18n.close || 'Close');
			lightbox.querySelector('.lightbox__prev').innerHTML = '&#8249;';
			lightbox.querySelector('.lightbox__next').innerHTML = '&#8250;';
			lightbox.querySelector('.lightbox__close').innerHTML = '&times;';
			lightbox.addEventListener('click', function (e) { if (e.target === lightbox) { lightbox.close(); } });
			lightbox.addEventListener('close', function () { if (lightbox._trigger) { lightbox._trigger.focus(); } });
			lightbox.querySelector('.lightbox__close').addEventListener('click', function () { lightbox.close(); });
			lightbox.querySelector('.lightbox__prev').addEventListener('click', function () { lightbox._show(lightbox._i - 1); });
			lightbox.querySelector('.lightbox__next').addEventListener('click', function () { lightbox._show(lightbox._i + 1); });
			lightbox.addEventListener('keydown', function (e) {
				if (e.key === 'ArrowLeft') { lightbox._show(lightbox._i - 1); }
				if (e.key === 'ArrowRight') { lightbox._show(lightbox._i + 1); }
			});
		}
		lightbox._items = items;
		lightbox._trigger = trigger;
		lightbox._show = function (i) {
			var n = lightbox._items.length;
			lightbox._i = (i + n) % n;
			var it = lightbox._items[lightbox._i];
			var img = lightbox.querySelector('.lightbox__img');
			img.src = it.full;
			img.alt = it.alt || '';
			lightbox.querySelector('.lightbox__caption').textContent = it.caption || '';
			lightbox.querySelector('.lightbox__count').textContent = n > 1 ? (tpI18n.of || 'Photo %1$d of %2$d').replace('%1$d', lightbox._i + 1).replace('%2$d', n) : '';
			lightbox.querySelectorAll('.lightbox__prev, .lightbox__next').forEach(function (b) { b.hidden = n < 2; });
		};
		lightbox._show(start);
		lightbox.showModal();
		lightbox.querySelector('.lightbox__close').focus();
	}
	document.querySelectorAll('[data-gallery]').forEach(function (g) {
		var items = [];
		try { items = JSON.parse(g.getAttribute('data-gallery')); } catch (e) {}
		if (!items.length) { return; }
		g.querySelectorAll('[data-gallery-open]').forEach(function (btn) {
			btn.addEventListener('click', function () { openLightbox(items, +btn.getAttribute('data-gallery-open') || 0, btn); });
		});
	});

	/* Click-to-load video (nothing loads from YouTube/Vimeo until asked). */
	document.querySelectorAll('[data-video-embed]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var iframe = document.createElement('iframe');
			iframe.src = btn.getAttribute('data-video-embed');
			iframe.title = btn.getAttribute('data-video-title') || 'Video';
			iframe.allow = 'autoplay; fullscreen; picture-in-picture';
			iframe.allowFullscreen = true;
			iframe.className = 'video-iframe';
			btn.replaceWith(iframe);
			iframe.focus();
		});
	});

	/* "Read more" for long review text. */
	document.querySelectorAll('[data-clamp]').forEach(function (el) {
		if (el.scrollHeight <= el.clientHeight + 2) { return; }
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'link-button';
		btn.setAttribute('aria-expanded', 'false');
		btn.textContent = tpI18n.more || 'Read more';
		btn.addEventListener('click', function () {
			var open = el.classList.toggle('is-expanded');
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
			btn.textContent = open ? (tpI18n.less || 'Show less') : (tpI18n.more || 'Read more');
		});
		el.insertAdjacentElement('afterend', btn);
	});

	/* Arriving from the hero quick-book bar: go to the form and focus the first empty field. */
	var qbForm = document.querySelector('form[data-from-quickbook]');
	if (qbForm && !document.querySelector('[data-focus]')) {
		var firstEmpty = Array.prototype.find.call(qbForm.querySelectorAll('input[required], select[required]'), function (el) {
			return el.offsetParent !== null && el.type !== 'checkbox' && !el.value;
		});
		if (firstEmpty) {
			firstEmpty.scrollIntoView({ block: 'center' });
			firstEmpty.focus({ preventScroll: true });
		}
	}

	/* Live "Open now / Closed" status in the business's timezone. */
	function shortTime(hhmm) {
		var p = hhmm.split(':'), h = +p[0] % 24, m = +p[1];
		return ((h % 12) || 12) + (m ? ':' + (m < 10 ? '0' : '') + m : '') + (h < 12 ? 'am' : 'pm');
	}
	document.querySelectorAll('[data-hours]').forEach(function (el) {
		var sched, days;
		try { sched = JSON.parse(el.getAttribute('data-hours')); days = JSON.parse(el.getAttribute('data-days')); } catch (e) { return; }
		var tz = el.getAttribute('data-tz') || undefined;
		var fmt = new Intl.DateTimeFormat('en-GB', { weekday: 'short', hour: '2-digit', minute: '2-digit', hour12: false, timeZone: tz });
		var parts = {};
		fmt.formatToParts(new Date()).forEach(function (p) { parts[p.type] = p.value; });
		var dow = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].indexOf(parts.weekday) + 1;
		var time = (parts.hour === '24' ? '00' : parts.hour) + ':' + parts.minute;
		var today = sched[dow], open = !!(today && time >= today[0] && time < today[1]), text;
		if (open) {
			text = (tpI18n.openUntil || 'Open now · phones answered until %s').replace('%s', shortTime(today[1]));
		} else {
			text = tpI18n.closed || 'Closed now';
			for (var i = 0; i < 8; i++) {
				var d = ((dow - 1 + i) % 7) + 1, r = sched[d];
				if (!r || (i === 0 && time >= r[0])) { continue; }
				var when = i === 0 ? (tpI18n.openAt || 'we open at %s').replace('%s', shortTime(r[0]))
					: i === 1 ? (tpI18n.openTomorrow || 'we open tomorrow at %s').replace('%s', shortTime(r[0]))
					: (tpI18n.openDay || 'we open %1$s at %2$s').replace('%1$s', days[d % 7]).replace('%2$s', shortTime(r[0]));
				text = (tpI18n.closedWhen || 'Closed now · %s').replace('%s', when);
				break;
			}
		}
		el.classList.toggle('is-open', open);
		el.classList.toggle('is-closed', !open);
		var t = el.querySelector('.hours-status__text');
		if (t) { t.textContent = text; }
		document.querySelectorAll('[data-hours-closed-note]').forEach(function (n) { n.hidden = open; });
	});

	/* Move focus to server-rendered errors / success so screen readers announce them. */
	var focusTarget = document.querySelector('[data-focus]');
	if (focusTarget) {
		focusTarget.focus({ preventScroll: true });
		focusTarget.scrollIntoView({ block: 'center' });
	}
})();
