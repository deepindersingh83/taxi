/**
 * Live driver location: the driver's phone sends its position while on the
 * way; the customer's booking page shows it on a map.
 */
(function () {
	'use strict';
	var cfg = window.tpLive;
	if (!cfg) { return; }
	var t = cfg.i18n || {};

	/* Driver ---------------------------------------------------------------- */
	document.querySelectorAll('[data-share-location]').forEach(function (box) {
		var id = box.getAttribute('data-share-location');
		var status = box.querySelector('[data-share-status]');
		var start = box.querySelector('[data-share-start]');
		var stop = box.querySelector('[data-share-stop]');
		var watch = null;
		var last = 0;
		var key = 'tpShare';

		function send(pos) {
			var now = Date.now();
			if (now - last < 20000) { return; }
			last = now;
			fetch(cfg.post, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
				body: JSON.stringify({ booking: +id, lat: pos.coords.latitude, lng: pos.coords.longitude })
			}).then(function (r) { return r.json(); }).then(function (d) {
				if (d && d.stop) { end(); return; }
				status.textContent = t.sharing + ' ' + t.sent + ' ' + new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) + '.';
			}).catch(function () {});
		}
		function begin() {
			if (!navigator.geolocation) { status.textContent = t.denied; return; }
			watch = navigator.geolocation.watchPosition(send, function (err) {
				if (err.code === 1) { status.textContent = t.denied; end(); }
			}, { enableHighAccuracy: true, maximumAge: 15000, timeout: 30000 });
			status.textContent = t.sharing;
			start.hidden = true; stop.hidden = false;
			try { localStorage.setItem(key, '1'); } catch (e) {}
		}
		function end() {
			if (watch !== null) { navigator.geolocation.clearWatch(watch); watch = null; }
			start.hidden = false; stop.hidden = true;
		}
		start.addEventListener('click', begin);
		stop.addEventListener('click', function () {
			end();
			status.textContent = t.stopped;
			try { localStorage.removeItem(key); } catch (e) {}
		});
		// Drivers who agreed once keep sharing on their next trips without another tap.
		try { if (localStorage.getItem(key) === '1') { begin(); } } catch (e) {}
	});

	/* Customer -------------------------------------------------------------- */
	document.querySelectorAll('[data-driver-live]').forEach(function (box) {
		var text = box.querySelector('[data-live-text]');
		var mapWrap = box.querySelector('[data-live-map]');
		var frame = mapWrap && mapWrap.querySelector('iframe');
		var url = cfg.get + (cfg.get.indexOf('?') > -1 ? '&' : '?') + 'ref=' + encodeURIComponent(box.getAttribute('data-ref')) + '&t=' + encodeURIComponent(box.getAttribute('data-t'));
		var shown = '';

		function mapUrl(lat, lng) {
			var d = 0.012;
			return cfg.mapBase + '?bbox=' + [lng - d, lat - d / 1.6, lng + d, lat + d / 1.6].join(',') + '&layer=mapnik&marker=' + lat + ',' + lng;
		}
		function poll() {
			if (document.hidden) { return; }
			fetch(url, { credentials: 'omit', cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (d) {
				if (!d) { return; }
				if (d.sharing) {
					if (text.textContent !== d.text) { text.textContent = d.text; }
					var src = mapUrl(d.lat, d.lng);
					if (src !== shown && frame) { frame.src = src; shown = src; }
					mapWrap.hidden = false;
				} else {
					mapWrap.hidden = true;
					if (['completed', 'cancelled', 'no_show', 'onboard'].indexOf(d.status) > -1) { clearInterval(timer); }
				}
			}).catch(function () {});
		}
		var timer = setInterval(poll, 20000);
		document.addEventListener('visibilitychange', poll);
	});
})();
