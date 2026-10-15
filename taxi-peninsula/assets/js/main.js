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
	document.querySelectorAll('[data-contrast-toggle]').forEach(function (contrast) {
		contrast.setAttribute('aria-pressed', root.getAttribute('data-contrast') === 'high' ? 'true' : 'false');
		contrast.addEventListener('click', function () {
			var on = root.getAttribute('data-contrast') !== 'high';
			if (on) { root.setAttribute('data-contrast', 'high'); } else { root.removeAttribute('data-contrast'); }
			contrast.setAttribute('aria-pressed', on ? 'true' : 'false');
			store('tp-contrast', on ? 'high' : null);
		});
	});

	/* Dark mode toggle (follows the device setting until the visitor chooses). */
	document.querySelectorAll('[data-theme-toggle]').forEach(function (themeBtn) {
		var mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
		var isDark = function () {
			var t = root.getAttribute('data-theme');
			return t ? t === 'dark' : !!(mq && mq.matches);
		};
		var syncTheme = function () {
			var dark = isDark();
			themeBtn.setAttribute('aria-pressed', dark ? 'true' : 'false');
			var meta = document.querySelector('meta[name="theme-color"]');
			if (meta) { meta.setAttribute('content', dark ? '#0c1322' : (meta.getAttribute('data-light') || '#0b2a5b')); }
		};
		themeBtn.addEventListener('click', function () {
			var next = isDark() ? 'light' : 'dark';
			root.setAttribute('data-theme', next);
			store('tp-theme', next);
			syncTheme();
		});
		if (mq && mq.addEventListener) { mq.addEventListener('change', syncTheme); }
		syncTheme();
	});

	/* Height of the sticky header, so sticky bars sit just below it. */
	var stickyHeader = document.querySelector('.site-header');
	var syncSticky = function () {
		var sticky = stickyHeader && getComputedStyle(stickyHeader).position === 'sticky';
		root.style.setProperty('--tp-sticky-top', (sticky ? stickyHeader.offsetHeight : 0) + 'px');
	};
	syncSticky();
	window.addEventListener('resize', syncSticky);

	/* Booking form: step progress and a live trip summary. */
	var bookForm = document.querySelector('[data-form-steps]') ? document.querySelector('[data-form-steps]').closest('form') : null;
	if (bookForm) {
		var stepsNav = bookForm.querySelector('[data-form-steps]');
		var stepItems = Array.prototype.slice.call(stepsNav.querySelectorAll('li[data-step]'));
		var panels = stepItems.map(function (li) { return bookForm.querySelector('[data-step-panel="' + li.getAttribute('data-step') + '"]'); });
		var bar = stepsNav.querySelector('.form-steps__bar span');
		var panelDone = function (panel) {
			if (!panel) { return false; }
			var req = panel.querySelectorAll('[required]');
			for (var i = 0; i < req.length; i++) {
				var el = req[i];
				if (el.closest('[hidden]')) { continue; }
				if (el.type === 'checkbox' ? !el.checked : (el.value.trim() === '' || !el.checkValidity())) { return false; }
			}
			return true;
		};
		var current = 0;
		var syncSteps = function () {
			stepItems.forEach(function (li, i) {
				li.classList.toggle('is-done', i < current && panelDone(panels[i]));
				li.classList.toggle('is-current', i === current);
				var link = li.querySelector('a');
				if (i === current) { link.setAttribute('aria-current', 'step'); } else { link.removeAttribute('aria-current'); }
			});
			if (bar) { bar.style.width = ((current + 1) / stepItems.length * 100) + '%'; }
		};
		bookForm.addEventListener('focusin', function (e) {
			var idx = panels.indexOf(e.target.closest('[data-step-panel]'));
			if (idx > -1 && idx !== current) { current = idx; syncSteps(); }
		});
		if ('IntersectionObserver' in window) {
			var stepIo = new IntersectionObserver(function (entries) {
				entries.forEach(function (en) {
					if (en.isIntersecting && !bookForm.contains(document.activeElement)) {
						current = panels.indexOf(en.target); syncSteps();
					}
				});
			}, { rootMargin: '-35% 0px -55% 0px' });
			panels.forEach(function (p) { if (p) { stepIo.observe(p); } });
		}
		bookForm.addEventListener('change', syncSteps);
		syncSteps();

		var summaries = document.querySelectorAll('[data-trip-summary]');
		var field = function (name) { return bookForm.querySelector('[name="' + name + '"]'); };
		var place = function (v) { v = (v || '').trim(); return v ? v.split(',')[0] : ''; };
		var fmtWhen = function () {
			var d = field('date') ? field('date').value : '';
			var t = field('time') ? field('time').value : '';
			if (!d) { return ''; }
			try {
				var dt = new Date(d + 'T' + (t || '00:00'));
				var out = dt.toLocaleDateString('en-AU', { weekday: 'short', day: 'numeric', month: 'short' });
				if (t) { out += ', ' + dt.toLocaleTimeString('en-AU', { hour: 'numeric', minute: '2-digit' }); }
				return out;
			} catch (e) { return d + ' ' + t; }
		};
		var syncSummary = function () {
			var from = place(field('pickup') && field('pickup').value);
			var to = place(field('dropoff') && field('dropoff').value);
			var veh = bookForm.querySelector('[name="vehicle"]:checked');
			var vehLabel = veh ? (veh.closest('label') || veh.parentNode).textContent.replace(/\s+/g, ' ').trim() : '';
			var pax = field('passengers') ? +field('passengers').value || 1 : 1;
			var wc = field('wheelchairs') ? +field('wheelchairs').value || 0 : 0;
			var people = pax + ' ' + (pax === 1 ? (tpI18n.passenger || 'passenger') : (tpI18n.passengers || 'passengers'));
			if (wc) { people += ' · ' + wc + ' ' + (wc === 1 ? (tpI18n.wheelchair || 'wheelchair') : (tpI18n.wheelchairs || 'wheelchairs')); }
			var fareBox = bookForm.querySelector('[data-fare-estimate]');
			var fare = fareBox && !fareBox.hidden && fareBox.querySelector('strong') ? fareBox.querySelector('strong').textContent.replace(/^[^$]*/, '') : '';
			var ret = field('return_trip') && field('return_trip').checked ? ((field('return_time') && field('return_time').value) || (tpI18n.yesShort || 'Yes')) : '';
			summaries.forEach(function (box) {
				box.hidden = !(from || to);
				var set = function (key, val, fallback) { var el = box.querySelector('[data-sum="' + key + '"]'); if (el) { el.textContent = val || fallback; } };
				set('from', from, tpI18n.pickup || 'Pick-up');
				set('to', to, tpI18n.dropoff || 'Drop-off');
				set('when', fmtWhen(), '—');
				set('vehicle', vehLabel, '—');
				set('people', people, '—');
				set('fare', fare, '—');
				set('return', ret, '—');
				var fr = box.querySelector('[data-sum-row="fare"]'); if (fr) { fr.hidden = !fare; }
				var rr = box.querySelector('[data-sum-row="return"]'); if (rr) { rr.hidden = !ret; }
			});
		};
		bookForm.addEventListener('input', syncSummary);
		bookForm.addEventListener('change', syncSummary);
		var fareWatch = bookForm.querySelector('[data-fare-estimate]');
		if (fareWatch && window.MutationObserver) { new MutationObserver(syncSummary).observe(fareWatch, { childList: true, subtree: true, attributes: true }); }
		syncSummary();
	}

	/* Signed-in booking: choosing a saved passenger / client fills their details. */
	var acct = document.querySelector('[data-account-prefill]');
	if (acct) {
		var data = {};
		try { data = JSON.parse(acct.getAttribute('data-account-prefill')) || {}; } catch (e) {}
		var acctForm = acct.closest('form');
		var set = function (name, value) {
			var el = acctForm && acctForm.querySelector('[name="' + name + '"]');
			if (el && value !== undefined && value !== null) { el.value = value; el.dispatchEvent(new Event('change', { bubbles: true })); }
		};
		var pick = acct.querySelector('[data-passenger-select]');
		if (pick) {
			pick.addEventListener('change', function () {
				var p = (data.passengers || {})[pick.value];
				if (p) {
					set('name', p.name); set('phone', p.phone);
					if (p.aid) { set('mobility_aid', p.aid); }
					if (p.ndis) { set('ndis_number', p.ndis); }
					var notes = acctForm.querySelector('[name="notes"]');
					if (notes && p.notes && notes.value.indexOf(p.notes) === -1) { notes.value = (notes.value ? notes.value + '\n' : '') + p.notes; }
				} else if (data.self) {
					set('name', data.self.name); set('phone', data.self.phone);
				} else {
					set('name', ''); set('phone', '');
				}
			});
		}
	}
	document.querySelectorAll('[data-address-for]').forEach(function (sel) {
		sel.addEventListener('change', function () {
			var input = document.getElementById(sel.getAttribute('data-address-for'));
			if (input && sel.value) {
				input.value = sel.value;
				input.dispatchEvent(new Event('change', { bubbles: true }));
				sel.value = '';
				input.focus();
			}
		});
	});

	/* Sign-up: show "Organisation name" only for coordinator accounts. */
	var orgField = document.querySelector('[data-org-field]');
	if (orgField) {
		var syncOrg = function () {
			var chosen = document.querySelector('[data-toggle-org]:checked');
			orgField.hidden = !(chosen && chosen.getAttribute('data-toggle-org') === '1');
		};
		document.querySelectorAll('[data-toggle-org]').forEach(function (r) { r.addEventListener('change', syncOrg); });
		syncOrg();
	}

	/* Pause / play all animations (remembered for the visitor). */
	var motionBtns = document.querySelectorAll('[data-motion-toggle]');
	var syncMotion = function () {
		var off = root.getAttribute('data-motion') === 'off';
		motionBtns.forEach(function (b) { b.setAttribute('aria-pressed', off ? 'true' : 'false'); });
	};
	motionBtns.forEach(function (b) {
		b.addEventListener('click', function () {
			var off = root.getAttribute('data-motion') !== 'off';
			if (off) { root.setAttribute('data-motion', 'off'); } else { root.removeAttribute('data-motion'); }
			store('tp-motion', off ? 'off' : null);
			syncMotion();
		});
	});
	syncMotion();

	/* Only run animations that are on screen. */
	var animated = document.querySelectorAll('.tp-anim');
	if (animated.length && 'IntersectionObserver' in window) {
		root.classList.add('tp-io');
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (e) { e.target.classList.toggle('is-inview', e.isIntersecting); });
		}, { rootMargin: '100px' });
		animated.forEach(function (el) { io.observe(el); });
	}

	/* Bottom bar "Menu" opens the main menu. */
	var menuBtn = document.querySelector('[data-open-menu]');
	var navToggle = document.querySelector('.nav-toggle');
	if (menuBtn && navToggle) {
		menuBtn.addEventListener('click', function () {
			var header = document.querySelector('.site-header');
			if (header && getComputedStyle(header).position !== 'sticky') { window.scrollTo(0, 0); }
			if (navToggle.getAttribute('aria-expanded') !== 'true') { navToggle.click(); }
			var first = document.querySelector('#primary-nav a');
			if (first) { first.focus(); }
		});
	}

	/* "Your next trip" countdown, e.g. "in 3 hours", "tomorrow". */
	var countdowns = document.querySelectorAll('[data-countdown]');
	if (countdowns.length && window.Intl && Intl.RelativeTimeFormat) {
		var rtf = new Intl.RelativeTimeFormat(document.documentElement.lang || 'en-AU', { numeric: 'auto' });
		var tick = function () {
			var now = Date.now() / 1000;
			countdowns.forEach(function (el) {
				var diff = +el.getAttribute('data-countdown') - now;
				var text;
				if (diff < 60) { text = tpI18n.now || 'Now'; }
				else if (diff < 3600) { text = rtf.format(Math.round(diff / 60), 'minute'); }
				else if (diff < 86400) { text = rtf.format(Math.round(diff / 3600), 'hour'); }
				else { text = rtf.format(Math.round(diff / 86400), 'day'); }
				el.textContent = text;
			});
		};
		tick();
		setInterval(tick, 30000);
	}

	/* Driver jobs: swipe left/right to change day. */
	var swipeArea = document.querySelector('[data-swipe-next]');
	if (swipeArea) {
		var sx = 0, sy = 0, tracking = false;
		swipeArea.addEventListener('touchstart', function (e) {
			if (e.touches.length !== 1 || e.target.closest('input, textarea, select, details[open], .table-scroll')) { tracking = false; return; }
			tracking = true; sx = e.touches[0].clientX; sy = e.touches[0].clientY;
		}, { passive: true });
		swipeArea.addEventListener('touchend', function (e) {
			if (!tracking) { return; }
			tracking = false;
			var dx = e.changedTouches[0].clientX - sx;
			var dy = e.changedTouches[0].clientY - sy;
			if (Math.abs(dx) > 80 && Math.abs(dx) > Math.abs(dy) * 2) {
				var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
				if (!reduce) {
					swipeArea.classList.add('is-swiping');
					swipeArea.style.transform = 'translateX(' + (dx > 0 ? 40 : -40) + 'px)';
					swipeArea.style.opacity = '.4';
				}
				window.location.href = swipeArea.getAttribute(dx < 0 ? 'data-swipe-next' : 'data-swipe-prev');
			}
		}, { passive: true });
	}

	/* Buttons show they are working, then the page moves on. */
	document.addEventListener('submit', function (e) {
		var formEl = e.target;
		if (e.defaultPrevented || !formEl.matches('form')) { return; }
		var btn = e.submitter || formEl.querySelector('[type="submit"]');
		if (btn && btn.hasAttribute('data-no-loading')) { return; } // downloads keep the page open
		if (btn && !btn.classList.contains('is-loading')) {
			btn.classList.add('is-loading');
			btn.setAttribute('aria-busy', 'true');
		}
	});
	window.addEventListener('pageshow', function () {
		document.querySelectorAll('.is-loading').forEach(function (b) { b.classList.remove('is-loading'); b.removeAttribute('aria-busy'); b.disabled = false; });
	});

	/* Gentle fade-in as sections scroll into view (not for reduced motion). */
	var revealOn = document.body.classList.contains('tp-reveal') && 'IntersectionObserver' in window &&
		!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	if (revealOn) {
		var revealEls = document.querySelectorAll('.site-main .section .container > *, .site-main .card, .feature, .service, .who__panel, .testimonial, .post-card, .journey__item');
		var revealIo = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) {
				if (en.isIntersecting) { en.target.classList.add('is-revealed'); revealIo.unobserve(en.target); }
			});
		}, { rootMargin: '0px 0px -8% 0px' });
		revealEls.forEach(function (el) {
			if (el.closest('.tp-component, .account, .driver-jobs, form')) { return; } // tools stay put
			var r = el.getBoundingClientRect();
			if (r.top < window.innerHeight) { return; } // already on screen: never hide it
			el.classList.add('reveal');
			revealIo.observe(el);
		});
	}

	/* Back to top: appears once the visitor has scrolled a long way. */
	var toTop = document.querySelector('[data-back-to-top]');
	if (toTop) {
		var ticking = false;
		var syncTop = function () {
			toTop.classList.toggle('is-visible', window.scrollY > 900);
			ticking = false;
		};
		window.addEventListener('scroll', function () {
			if (!ticking) { ticking = true; window.requestAnimationFrame(syncTop); }
		}, { passive: true });
		syncTop();
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

		/* Dropdowns: a ▾ button beside each parent item opens its sub-menu or the
		   services panel — by click, tap or keyboard. Hover still works with a mouse. */
		var parents = nav.querySelectorAll('.menu-item-has-children, .has-mega');
		var closeAll = function (except) {
			parents.forEach(function (li) {
				if (li !== except && !li.contains(except)) {
					li.classList.remove('is-open');
					var b = li.querySelector(':scope > .submenu-toggle');
					if (b) { b.setAttribute('aria-expanded', 'false'); }
				}
			});
		};
		parents.forEach(function (li, i) {
			var link = li.querySelector(':scope > a');
			var panel = li.querySelector(':scope > .mega') || li.querySelector(':scope > .sub-menu');
			if (!link || !panel) { return; }
			if (!panel.id) { panel.id = 'submenu-' + i; }
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'submenu-toggle';
			btn.setAttribute('aria-expanded', 'false');
			btn.setAttribute('aria-controls', panel.id);
			btn.setAttribute('aria-label', (window.tpI18n && tpI18n.showSubmenu ? tpI18n.showSubmenu : 'Show more: %s').replace('%s', link.textContent.trim()));
			btn.innerHTML = '<svg class="icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg>';
			link.insertAdjacentElement('afterend', btn);
			btn.addEventListener('click', function (e) {
				e.stopPropagation();
				var open = !li.classList.contains('is-open');
				li.classList.remove('is-dismissed');
				closeAll(li);
				li.classList.toggle('is-open', open);
				btn.setAttribute('aria-expanded', open ? 'true' : 'false');
			});
			li.addEventListener('keydown', function (e) {
				if (e.key === 'Escape' && !li.classList.contains('is-dismissed')) {
					e.stopPropagation();
					li.classList.remove('is-open');
					li.classList.add('is-dismissed');
					btn.setAttribute('aria-expanded', 'false');
					btn.focus();
				}
			});
			// Escape hides a hover/focus dropdown until the pointer or focus leaves the item.
			li.addEventListener('mouseleave', function () { li.classList.remove('is-dismissed'); });
			li.addEventListener('focusout', function (e) { if (!li.contains(e.relatedTarget)) { li.classList.remove('is-dismissed'); } });
		});
		document.addEventListener('click', function (e) { if (!nav.contains(e.target)) { closeAll(null); } });

		/* On computers, fall back to the Menu button when the full menu doesn't fit
		   (long menus, larger base text, or the visitor's text size buttons). */
		var wide = window.matchMedia ? window.matchMedia('(min-width: 64.01em)') : null;
		var inner = nav.parentElement;
		var fitNav = function () {
			if (!wide || !wide.matches) { root.classList.remove('tp-nav-compact'); return; }
			root.classList.remove('tp-nav-compact');
			var over = inner.scrollWidth > inner.clientWidth + 1 || nav.scrollWidth > nav.clientWidth + 1;
			root.classList.toggle('tp-nav-compact', over);
			if (!over) { setOpen(false); }
		};
		var fitTimer;
		var queueFit = function () { clearTimeout(fitTimer); fitTimer = setTimeout(fitNav, 60); };
		window.addEventListener('resize', queueFit);
		if (document.fonts && document.fonts.ready) { document.fonts.ready.then(fitNav); }
		if (window.MutationObserver) {
			new MutationObserver(queueFit).observe(root, { attributes: true, attributeFilter: ['data-text-size'] });
		}
		fitNav();
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
	var bookingAction = document.querySelector('form.booking-form input[name="action"][value="tp_booking"]');
	var form = bookingAction ? bookingAction.form : null;
	if (form) {
		var wheelchairs = form.querySelector('[name="wheelchairs"]');
		form.querySelectorAll('[name="vehicle"]').forEach(function (radio) {
			radio.addEventListener('change', function () {
				if (!wheelchairs) { return; }
				if (radio.value === 'sedan') { wheelchairs.value = 0; } else if (+wheelchairs.value === 0) { wheelchairs.value = 1; }
			});
		});

		/* "I am the passenger" copies the account holder's details. */
		var useMe = form.querySelector('[data-use-me]');
		if (useMe) {
			var meData = {};
			try { meData = JSON.parse(useMe.getAttribute('data-use-me')) || {}; } catch (err) {}
			useMe.addEventListener('change', function () {
				['name', 'phone', 'email'].forEach(function (k) {
					var f = form.querySelector('[name="' + k + '"]');
					if (f && useMe.checked && meData[k]) { f.value = meData[k]; f.dispatchEvent(new Event('input', { bubbles: true })); }
				});
			});
		}

		/* Capacity and return-time checks, shown as the person fills in the form. */
		var cap = {};
		try { cap = JSON.parse(form.getAttribute('data-capacity') || '{}'); } catch (err) {}
		var msgs = {};
		try { msgs = JSON.parse(form.getAttribute('data-messages') || '{}'); } catch (err) {}
		var liveError = function (input, text) {
			if (!input) { return; }
			input.setCustomValidity(text || '');
			var id = input.id + '-live';
			var box = document.getElementById(id);
			if (!box) {
				box = document.createElement('p');
				box.id = id;
				box.className = 'field__error field__error--live';
				box.setAttribute('aria-live', 'polite');
				input.insertAdjacentElement('afterend', box);
				input.setAttribute('aria-describedby', ((input.getAttribute('aria-describedby') || '') + ' ' + id).trim());
			}
			box.textContent = text || '';
			box.hidden = !text;
			input.setAttribute('aria-invalid', text ? 'true' : 'false');
		};
		var checkTrip = function () {
			var v = form.querySelector('[name="vehicle"]:checked');
			var c = v ? cap[v.value] : null;
			var pax = form.querySelector('[name="passengers"]');
			if (c && wheelchairs) {
				var tooMany = c[1] > 0 && +wheelchairs.value > c[1];
				liveError(wheelchairs, tooMany ? (+wheelchairs.value <= (cap.maxi_wat || [0, 2])[1] ? msgs.chairs : msgs.chairsCall) : '');
			}
			if (c && pax) { liveError(pax, +pax.value > c[0] ? (msgs.people || '').replace('%d', c[0]) : ''); }
			var t = form.querySelector('[name="time"]'), rt = form.querySelector('[name="return_time"]'), rc = form.querySelector('[name="return_trip"]');
			if (rt) { liveError(rt, rc && rc.checked && rt.value && t && t.value && rt.value <= t.value ? msgs.returnTime : ''); }
		};
		form.addEventListener('change', checkTrip);
		form.addEventListener('input', function (e) { if (e.target.type === 'number' || e.target.type === 'time') { checkTrip(); } });

		/* Friendly client-side check before the server validates again. */
		form.addEventListener('submit', function (e) {
			checkTrip();
			var flagged = form.querySelector('.field__error--live:not([hidden])');
			if (flagged && !flagged.closest('[hidden]')) { e.preventDefault(); flagged.previousElementSibling.focus(); return; }
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

	/* Print buttons on trip confirmations. */
	document.querySelectorAll('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });

	/* Slim header once the page scrolls. */
	var siteHeader = document.querySelector('.site-header');
	if (siteHeader && !document.body.classList.contains('tp-header--static')) {
		var onScroll = function () { siteHeader.classList.toggle('is-scrolled', window.scrollY > 80); };
		window.addEventListener('scroll', onScroll, { passive: true });
		onScroll();
	}

	/* Review slider: previous / next buttons scroll one card; buttons disable at the ends. */
	document.querySelectorAll('[data-slider]').forEach(function (slider) {
		var track = slider.querySelector('.slider__track');
		var prev = slider.querySelector('[data-slider-prev]');
		var next = slider.querySelector('[data-slider-next]');
		if (!track || !prev || !next) { return; }
		var rtl = getComputedStyle(track).direction === 'rtl';
		function step() {
			var card = track.querySelector('.testimonial');
			return card ? card.getBoundingClientRect().width + parseFloat(getComputedStyle(track).columnGap || 24) : track.clientWidth;
		}
		function update() {
			var max = track.scrollWidth - track.clientWidth - 2;
			var pos = Math.abs(track.scrollLeft);
			prev.disabled = pos <= 2;
			next.disabled = pos >= max;
			slider.classList.toggle('slider--static', max <= 0);
		}
		prev.addEventListener('click', function () { track.scrollBy({ left: (rtl ? 1 : -1) * step() }); });
		next.addEventListener('click', function () { track.scrollBy({ left: (rtl ? -1 : 1) * step() }); });
		track.addEventListener('scroll', update, { passive: true });
		track.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowRight') { e.preventDefault(); (rtl ? prev : next).click(); }
			if (e.key === 'ArrowLeft') { e.preventDefault(); (rtl ? next : prev).click(); }
		});
		window.addEventListener('resize', update);
		update();
	});

	/* Language menu: close on Escape or a click outside. */
	document.querySelectorAll('[data-lang-switch]').forEach(function (d) {
		document.addEventListener('click', function (e) { if (d.open && !d.contains(e.target)) { d.open = false; } });
		d.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && d.open) { d.open = false; d.querySelector('summary').focus(); }
		});
	});
	function langDismiss() { try { localStorage.setItem('tp-lang-offer', 'done'); } catch (err) {} }
	document.addEventListener('click', function (e) {
		if (e.target.closest && e.target.closest('[data-lang-choice]')) { langDismiss(); }
	});

	/* Offer the browser's language once, worded in that language. */
	var suggest = document.querySelector('[data-lang-suggest]');
	if (suggest) {
		var offers = {}, seen = null;
		try { offers = JSON.parse(suggest.getAttribute('data-lang-suggest')) || {}; seen = localStorage.getItem('tp-lang-offer'); } catch (err) {}
		var wanted = (navigator.languages && navigator.languages.length ? navigator.languages : [navigator.language || '']);
		var langPick = null;
		for (var li = 0; li < wanted.length && !langPick; li++) {
			var code = String(wanted[li]).toLowerCase().split('-')[0];
			if (code === 'en') { break; }
			if (offers[code]) { langPick = offers[code]; }
		}
		if (langPick && !seen) {
			suggest.setAttribute('lang', langPick.lang);
			suggest.setAttribute('dir', langPick.dir);
			suggest.querySelector('.lang-suggest__q').textContent = langPick.q;
			var yes = suggest.querySelector('.lang-suggest__yes');
			yes.textContent = langPick.yes;
			yes.href = langPick.url;
			var no = suggest.querySelector('.lang-suggest__no');
			no.textContent = langPick.no;
			no.addEventListener('click', function () { langDismiss(); suggest.hidden = true; });
			suggest.hidden = false;
		}
	}

	/* Lead tracking: count taps on phone / WhatsApp / SMS / email links, and
	   show a tracking number to visitors from a matching source. */
	(function () {
		var cfg = window.tpTrack;
		if (!cfg || !cfg.url) { return; }
		var src = ['', ''];
		var m = document.cookie.match(/(?:^|;\s*)tp_src=([^;]*)/);
		if (m) {
			try { src = decodeURIComponent(m[1]).split('|'); } catch (err) {}
		}
		var match = null;
		(cfg.numbers || []).forEach(function (n) {
			if (!match && n[0] === String(src[0] || '').toLowerCase() && (!n[1] || n[1] === String(src[1] || '').toLowerCase())) { match = n; }
		});
		var digits = function (s) { return String(s || '').replace(/[^\d+]/g, ''); };
		if (match && cfg.phone) {
			document.querySelectorAll('a[href^="tel:"]').forEach(function (a) {
				if (digits(a.getAttribute('href').slice(4)) !== digits(cfg.phone)) { return; }
				a.setAttribute('href', 'tel:' + match[3]);
				var walker = document.createTreeWalker(a, NodeFilter.SHOW_TEXT);
				while (walker.nextNode()) {
					var t = walker.currentNode;
					if (/\d{3}/.test(t.nodeValue)) { t.nodeValue = t.nodeValue.replace(/[\d+][\d\s()+-]{5,}\d/, match[2]); }
				}
			});
		}
		document.addEventListener('click', function (e) {
			var a = e.target.closest ? e.target.closest('a[href]') : null;
			if (!a) { return; }
			var href = a.getAttribute('href') || '';
			var type = /^tel:/i.test(href) ? 'call' : (/^sms:/i.test(href) ? 'sms' : (/^mailto:/i.test(href) ? 'email' : (/^https:\/\/(wa\.me|api\.whatsapp\.com)\//i.test(href) ? 'whatsapp' : '')));
			if (!type) { return; }
			var data = new FormData();
			data.append('type', type);
			data.append('page', location.pathname);
			if (navigator.sendBeacon) {
				navigator.sendBeacon(cfg.url, data);
			} else {
				try { fetch(cfg.url, { method: 'POST', body: data, keepalive: true }); } catch (err) {}
			}
		}, true);
	})();

	/* Web push: "Notify me" on the booking pages. */
	(function () {
		var box = document.querySelector('[data-push]');
		if (!box) { return; }
		var status = box.querySelector('.push-box__status');
		var onBtn = box.querySelector('[data-push-on]');
		var installBtn = box.querySelector('[data-install]');
		var say = function (t) { status.textContent = t; };
		var ios = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
		var standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
		var supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
		box.hidden = false;
		if (!supported) {
			if (ios && !standalone) { box.querySelector('.push-box__ios').hidden = false; onBtn.hidden = true; }
			else { box.hidden = true; }
			return;
		}
		var keyBytes = function (b64) {
			var pad = '='.repeat((4 - b64.length % 4) % 4);
			var raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
			var out = new Uint8Array(raw.length);
			for (var i = 0; i < raw.length; i++) { out[i] = raw.charCodeAt(i); }
			return out;
		};
		var done = function () { onBtn.hidden = true; say(box.getAttribute('data-done') || 'Notifications are on for this trip.'); };
		if (Notification.permission === 'granted') {
			navigator.serviceWorker.getRegistration('/').then(function (reg) {
				if (reg && sessionStorage) {
					try { if (sessionStorage.getItem('tp-push-' + box.getAttribute('data-ref'))) { done(); } } catch (err) {}
				}
			});
		}
		onBtn.addEventListener('click', function () {
			onBtn.disabled = true;
			Notification.requestPermission().then(function (perm) {
				if (perm !== 'granted') { throw new Error('denied'); }
				return navigator.serviceWorker.register(box.getAttribute('data-sw'), { scope: '/' });
			}).then(function () {
				return navigator.serviceWorker.ready;
			}).then(function (reg) {
				return reg.pushManager.getSubscription().then(function (s) {
					return s || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(box.getAttribute('data-key')) });
				});
			}).then(function (sub) {
				var data = new FormData();
				data.append('ref', box.getAttribute('data-ref'));
				data.append('t', box.getAttribute('data-t'));
				data.append('endpoint', sub.endpoint);
				return fetch(box.getAttribute('data-url'), { method: 'POST', body: data, credentials: 'omit' });
			}).then(function (r) {
				if (!r.ok) { throw new Error('save'); }
				try { sessionStorage.setItem('tp-push-' + box.getAttribute('data-ref'), '1'); } catch (err) {}
				done();
			}).catch(function (err) {
				onBtn.disabled = false;
				say(err && err.message === 'denied' ? (box.getAttribute('data-denied') || 'Notifications are blocked in your browser settings. We will still text you.') : (box.getAttribute('data-failed') || 'Sorry, that did not work on this device. We will still text you.'));
			});
		});
		// Offer "Add to home screen" where the browser supports it.
		window.addEventListener('beforeinstallprompt', function (e) {
			e.preventDefault();
			installBtn.hidden = false;
			installBtn.addEventListener('click', function () { e.prompt(); installBtn.hidden = true; }, { once: true });
		});
	})();

	/* Move focus to server-rendered errors / success so screen readers announce them. */
	var focusTarget = document.querySelector('[data-focus]');
	if (focusTarget) {
		focusTarget.focus({ preventScroll: true });
		focusTarget.scrollIntoView({ block: 'center' });
	}
})();
