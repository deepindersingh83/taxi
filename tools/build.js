/**
 * Build the theme assets: node tools/build.js
 * 1. Writes the right-to-left (Arabic) copies main-rtl.css / patterns-rtl.css.
 * 2. Writes *.min.css / *.min.js next to each source file. The theme loads the
 *    .min versions unless SCRIPT_DEBUG is on.
 * Run after editing any CSS or JS. With --check it changes nothing and exits 1
 * if a built file is out of date (used by CI).
 */
const esbuild = require('esbuild');
const rtlcss = require('rtlcss');
const fs = require('fs');
const path = require('path');

const theme = path.join(__dirname, '..', 'taxi-peninsula');
const files = [
	'assets/css/main.css', 'assets/css/main-rtl.css', 'assets/css/patterns.css', 'assets/css/patterns-rtl.css',
	'assets/css/animations.css', 'assets/css/admin.css',
	'assets/js/main.js', 'assets/js/live.js', 'assets/js/admin.js',
];
const check = process.argv.includes('--check');
let stale = 0;
const write = (out, code) => {
	if (check) {
		if (!fs.existsSync(out) || fs.readFileSync(out, 'utf8') !== code) { console.error('Out of date: ' + path.relative(theme, out)); stale++; }
		return false;
	}
	fs.writeFileSync(out, code);
	return true;
};
for (const name of ['main', 'patterns']) {
	const src = path.join(theme, 'assets/css', name + '.css');
	write(src.replace(/\.css$/, '-rtl.css'), rtlcss.process(fs.readFileSync(src, 'utf8')));
}
for (const rel of files) {
	const src = path.join(theme, rel);
	if (!fs.existsSync(src)) { continue; }
	const out = src.replace(/\.(css|js)$/, '.min.$1');
	const code = fs.readFileSync(src, 'utf8');
	const res = esbuild.transformSync(code, {
		loader: rel.endsWith('.css') ? 'css' : 'js',
		minify: true,
		target: rel.endsWith('.css') ? ['chrome100', 'safari15', 'firefox100'] : ['es2017'],
		legalComments: 'none',
	});
	if (!write(out, res.code)) { continue; }
	console.log(rel.padEnd(32), (code.length / 1024).toFixed(1) + ' KB →', (res.code.length / 1024).toFixed(1) + ' KB');
}
if (check && stale) { console.error(stale + ' minified file(s) out of date — run: node tools/build.js'); process.exit(1); }
