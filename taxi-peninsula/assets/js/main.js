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

	/* Move focus to server-rendered errors / success so screen readers announce them. */
	var focusTarget = document.querySelector('[data-focus]');
	if (focusTarget) {
		focusTarget.focus({ preventScroll: true });
		focusTarget.scrollIntoView({ block: 'center' });
	}
})();
