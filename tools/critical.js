/**
 * Make the critical CSS: the rules from main.min.css that style what is on
 * screen when a page first opens (phone and desktop), for the main page types.
 *
 *   node tools/critical.js http://localhost:8080
 *
 * Needs a test site running this theme (with SCRIPT_DEBUG off) and Playwright
 * (npm i -g playwright, or NODE_PATH_PW pointing at it). Writes
 * assets/css/critical.min.css and critical-rtl.min.css, each starting with
 * the hash of the stylesheet it was made from; the theme only uses a file
 * whose hash matches the current main.min.css / main-rtl.min.css.
 *
 * Extra pages can be passed after the URL: node tools/critical.js URL /my-page/
 */
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const esbuild = require('esbuild');

let pw;
try { pw = require(process.env.NODE_PATH_PW || 'playwright'); } catch (e) {
	console.error('Playwright is needed: npm i -g playwright, then run with NODE_PATH_PW=$(npm root -g)/playwright');
	process.exit(1);
}

const base = (process.argv[2] || '').replace(/\/$/, '');
if (!/^https?:\/\//.test(base)) { console.error('Usage: node tools/critical.js http://your-test-site [/extra-page/ …]'); process.exit(1); }
const theme = path.join(__dirname, '..', 'taxi-peninsula');

// Page types to cover. Missing pages (404) are fine: the 404 page is a page type too.
const pages = ['/', '/book-a-taxi/', '/contact/', '/my-account/', '/faq/', '/blog/', '/about-us/', '/wheelchair-taxi/frankston/', '/nope-404/', '/?s=taxi'].concat(process.argv.slice(3));
const viewports = [{ width: 390, height: 844, isMobile: true, hasTouch: true }, { width: 1366, height: 900 }];

// Runs in the page: index paths of the rules that style something in the first screen.
function collect() {
	const sheet = [...document.styleSheets].find((s) => s.href && /\/assets\/css\/main(-rtl)?\.min\.css/.test(s.href));
	if (!sheet) { return { error: 'main.min.css not found on the page (is SCRIPT_DEBUG on?)' }; }
	const vh = innerHeight, vw = innerWidth;
	const seen = new Map();
	const onScreen = (sel) => {
		if (seen.has(sel)) { return seen.get(sel); }
		let hit = false;
		try {
			for (const el of document.querySelectorAll(sel)) {
				// Hidden elements have no box: judge them by the nearest parent that has one, so the
				// rules that hide them (e.g. a label hidden on phones, a closed menu) are kept too.
				let e = el, r = null;
				while (e && e !== document.documentElement) { const b = e.getBoundingClientRect(); if (b.width || b.height) { r = b; break; } e = e.parentElement; }
				if (!r) { r = document.body.getBoundingClientRect(); }
				// Anything above or left of the screen counts (skip links, visually hidden text).
				if (r.top < vh && r.left < vw) { hit = true; break; }
			}
		} catch (e) { hit = false; }
		seen.set(sel, hit);
		return hit;
	};
	// Split a selector list on top-level commas only.
	const split = (text) => { const out = []; let depth = 0, cur = ''; for (const ch of text) { if (ch === '(') depth++; if (ch === ')') depth--; if (ch === ',' && !depth) { out.push(cur); cur = ''; } else { cur += ch; } } out.push(cur); return out; };
	const plain = (sel) => sel
		.replace(/::?(before|after|placeholder|marker|selection|backdrop|first-letter|first-line|-webkit-[\w-]+|-moz-[\w-]+)/g, '')
		.replace(/:(hover|focus|focus-visible|focus-within|active|visited|target|checked|disabled|invalid|valid|placeholder-shown|autofill|user-invalid)/g, '')
		.trim() || '*';
	const always = /^(:root|html|body|\*)(\[[^\]]*\])*$/; // Page-wide rules with no further target.
	const kept = [];
	const walk = (rules, prefix) => {
		[...rules].forEach((rule, i) => {
			const id = prefix + i;
			if (rule.selectorText !== undefined) {
				if (split(rule.selectorText).some((s) => always.test(plain(s)) || onScreen(plain(s)))) { kept.push(id); }
			} else if (rule.cssRules && rule.type !== CSSRule.KEYFRAMES_RULE) {
				walk(rule.cssRules, id + '.');
			}
		});
	};
	walk(sheet.cssRules, '');
	return { kept, href: sheet.href };
}

// Runs in the page: the kept rules, in their original order, with their @media/@supports wrappers.
function serialise(keep) {
	const sheet = [...document.styleSheets].find((s) => s.href && /\/assets\/css\/main(-rtl)?\.min\.css/.test(s.href));
	const set = new Set(keep);
	const names = new Set();
	const out = (rules, prefix) => {
		let css = '';
		[...rules].forEach((rule, i) => {
			const id = prefix + i;
			if (rule.selectorText !== undefined) {
				if (set.has(id)) {
					css += rule.cssText;
					const m = rule.style.animationName || '';
					m.split(',').forEach((n) => n.trim() && n.trim() !== 'none' && names.add(n.trim()));
				}
			} else if (rule.type === CSSRule.KEYFRAMES_RULE) {
				// added below, only when used
			} else if (rule.cssRules) {
				const inner = out(rule.cssRules, id + '.');
				if (inner) { css += rule.cssText.slice(0, rule.cssText.indexOf('{')) + '{' + inner + '}'; }
			}
		});
		return css;
	};
	let css = out(sheet.cssRules, '');
	[...sheet.cssRules].forEach((rule) => { if (rule.type === CSSRule.KEYFRAMES_RULE && names.has(rule.name)) { css += rule.cssText; } });
	return css;
}

// Add one or two pages of every type from the sitemap (every page, since pages use different templates).
async function discover() {
	const get = async (u) => { try { const r = await fetch(u); return r.ok ? await r.text() : ''; } catch (e) { return ''; } };
	const locs = (xml) => [...xml.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1]);
	const found = [];
	for (const map of locs(await get(base + '/wp-sitemap.xml'))) {
		const urls = locs(await get(map));
		found.push(...(/-page-\d/.test(map) ? urls.slice(0, 40) : urls.slice(0, 2)));
	}
	// Partner pages are not in the sitemap: take one linked from the site, if any.
	const home = await get(base + '/');
	const partner = (home.match(/href="([^"]*\/book-from\/[^"]+)"/) || [])[1];
	if (partner) { found.push(partner); }
	return found.map((u) => u.replace(/^https?:\/\/[^/]+/, '')).filter((u) => u.startsWith('/'));
}

(async () => {
	for (const p of await discover()) { if (!pages.includes(p)) { pages.push(p); } }
	console.log('Checking ' + pages.length + ' pages on phone and desktop…');
	const browser = await pw.chromium.launch();
	for (const [lang, file, bundle] of [['', 'critical.min.css', 'main.min.css'], ['ar', 'critical-rtl.min.css', 'main-rtl.min.css']]) {
		const keep = new Set();
		let last = null;
		for (const vp of viewports) {
			const ctx = await browser.newContext({ viewport: { width: vp.width, height: vp.height }, isMobile: !!vp.isMobile, hasTouch: !!vp.hasTouch, reducedMotion: 'no-preference' });
			await ctx.route(/^https?:\/\//, (r) => (r.request().url().startsWith(base) ? r.continue() : r.fulfill({ status: 204, body: '' })));
			const page = await ctx.newPage();
			if (lang) { await page.goto(base + '/?lang=' + lang); }
			// Visitor display settings (dark mode, high contrast, larger text) change the first screen too.
			const variants = [null, { theme: 'dark' }, { contrast: 'high', textSize: 'lg' }];
			for (const p of pages) {
				for (const v of variants) {
					if (v && p !== '/') { continue; }
					await page.goto(base + p, { waitUntil: 'load' }).catch(() => {});
					if (v) {
						await page.evaluate((v) => { const h = document.documentElement; if (v.theme) h.dataset.theme = v.theme; if (v.contrast) h.dataset.contrast = v.contrast; if (v.textSize) h.dataset.textSize = v.textSize; }, v);
					}
					await page.waitForTimeout(300);
					const res = await page.evaluate(collect);
					if (res.error) { console.error(p + ': ' + res.error); process.exit(1); }
					res.kept.forEach((k) => keep.add(k));
				}
			}
			last = page;
			if (vp !== viewports[viewports.length - 1]) { await ctx.close(); }
		}
		const raw = await last.evaluate(serialise, [...keep]);
		const min = esbuild.transformSync(raw, { loader: 'css', minify: true, target: ['chrome100', 'safari15', 'firefox100'] }).code.trim();
		const hash = crypto.createHash('sha1').update(fs.readFileSync(path.join(theme, 'assets/css', bundle))).digest('hex').slice(0, 12);
		fs.writeFileSync(path.join(theme, 'assets/css', file), '/*bundle:' + hash + '*/' + min + '\n');
		console.log(file.padEnd(24), (min.length / 1024).toFixed(1) + ' KB', '(' + keep.size + ' rules)');
		await last.context().close();
	}
	await browser.close();
})();
