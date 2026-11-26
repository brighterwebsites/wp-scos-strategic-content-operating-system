// Runs the JS mirror (assets/meta-box.js, scos-same-url block) against the fixture.
// node tests/same-url-test.js
const fs = require('fs'), path = require('path');
const src = fs.readFileSync(path.join(__dirname, '../site-essentials/Modules/SeoMeta/assets/meta-box.js'), 'utf8');
const block = src.split('// scos-same-url:start')[1].split('// scos-same-url:end')[0];
const code = block.slice(block.indexOf('\n') + 1); // drop the rest of the marker comment line
const window = {};
new Function('window', code)(window);
const cases = JSON.parse(fs.readFileSync(path.join(__dirname, 'fixtures/same-url-cases.json'), 'utf8'));
let fail = 0;
for (const c of cases) {
	const got = window.scosSameUrl(c.a, c.b);
	if (got !== c.same) { fail++; console.log('FAIL', c.why, c.a, c.b, got); }
}
console.log(`${cases.length - fail}/${cases.length} pass`);
process.exit(fail ? 1 : 0);
