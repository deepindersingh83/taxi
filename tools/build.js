/**
 * Build the theme assets: node tools/build.js
 * 1. Writes the right-to-left (Arabic) copies main-rtl.css / patterns-rtl.css.
 * 2. Writes main.min.css (and main-rtl.min.css): main + patterns + animations
 *    in one file, so the page needs a single stylesheet request.
 * 3. Writes *.min.js and admin.min.css next to each source file.
 * The theme loads the .min versions unless SCRIPT_DEBUG is on.
 * Run after editing any CSS or JS. With --check it changes nothing and exits 1
 * if a built file is out of date (used by CI). After CSS changes also run
 * tools/critical.js against a test site (the theme ignores an out-of-date
 * critical file, so forgetting only costs speed, never looks).
 */
const esbuild = require('esbuild');
const rtlcss = require('rtlcss');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

const theme = path.join(__dirname, '..', 'taxi-peninsula');
const css = (name) => path.join(theme, 'assets/css', name);
const read = (file) => fs.readFileSync(file, 'utf8');
const check = process.argv.includes('--check');
let stale = 0;
const write = (out, code) => {
	if (check) {
		if (!fs.existsSync(out) || read(out) !== code) { console.error('Out of date: ' + path.relative(theme, out)); stale++; }
		return false;
	}
	fs.writeFileSync(out, code);
	return true;
};
const minify = (code, loader) => esbuild.transformSync(code, {
	loader,
	minify: true,
	target: loader === 'css' ? ['chrome100', 'safari15', 'firefox100'] : ['es2017'],
	legalComments: 'none',
}).code;

// 1. Mirrored stylesheets for Arabic. (Animations are not mirrored: the vehicles drive the same way.)
for (const name of ['main', 'patterns']) {
	write(css(name + '-rtl.css'), rtlcss.process(read(css(name + '.css'))));
}

// 2. One stylesheet for the front end.
const bundles = {
	'main.min.css': ['main.css', 'patterns.css', 'animations.css'],
	'main-rtl.min.css': ['main-rtl.css', 'patterns-rtl.css', 'animations.css'],
};
for (const [out, parts] of Object.entries(bundles)) {
	// In --check mode the -rtl sources were not rewritten, so build them in memory.
	const src = parts.map((p) => (check && p.endsWith('-rtl.css') ? rtlcss.process(read(css(p.replace('-rtl', '')))) : read(css(p)))).join('\n');
	const code = minify(src, 'css');
	if (write(css(out), code)) { console.log(out.padEnd(32), (src.length / 1024).toFixed(1) + ' KB →', (code.length / 1024).toFixed(1) + ' KB'); }
}

// 3. Everything else, one file each.
for (const rel of ['assets/css/admin.css', 'assets/js/main.js', 'assets/js/live.js', 'assets/js/admin.js']) {
	const src = path.join(theme, rel);
	const code = read(src);
	const min = minify(code, rel.endsWith('.css') ? 'css' : 'js');
	if (write(src.replace(/\.(css|js)$/, '.min.$1'), min)) { console.log(rel.padEnd(32), (code.length / 1024).toFixed(1) + ' KB →', (min.length / 1024).toFixed(1) + ' KB'); }
}

// Critical CSS is made from a running site by tools/critical.js; report when it no longer matches.
for (const [crit, bundle] of [['critical.min.css', 'main.min.css'], ['critical-rtl.min.css', 'main-rtl.min.css']]) {
	if (!fs.existsSync(css(crit))) { continue; }
	const want = crypto.createHash('sha1').update(read(css(bundle))).digest('hex').slice(0, 12);
	const have = (read(css(crit)).match(/^\/\*bundle:([0-9a-f]+)\*\//) || [])[1];
	if (want !== have) { console.warn('Note: ' + crit + ' is out of date — run: node tools/critical.js <site URL> (the theme skips it until then)'); }
}

if (check && stale) { console.error(stale + ' built file(s) out of date — run: node tools/build.js'); process.exit(1); }
