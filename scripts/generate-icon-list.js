// One-off generator: reads the installed @fortawesome/fontawesome-free SVGs and
// writes the icon name catalogue used by the admin IconPickerField.
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..', 'node_modules', '@fortawesome', 'fontawesome-free', 'svgs');

function names(dir) {
    return fs.readdirSync(path.join(root, dir))
        .filter((f) => f.endsWith('.svg'))
        .map((f) => f.replace(/\.svg$/, ''))
        .sort();
}

const solid = names('solid');
const brands = names('brands');

const out = [
    '// Generated from node_modules/@fortawesome/fontawesome-free (do not edit by hand).',
    '// Regenerate: node scripts/generate-icon-list.js',
    '',
    `export const solidIcons = ${JSON.stringify(solid, null, 0)};`,
    '',
    `export const brandIcons = ${JSON.stringify(brands, null, 0)};`,
    '',
].join('\n');

const target = path.join(__dirname, '..', 'resources', 'js', 'pages', 'admin', 'content', 'fields', 'icons.js');
fs.writeFileSync(target, out, 'utf8');

// Sanity checks against names we rely on.
const mustHave = ['award', 'truck-fast', 'shield-halved', 'flask', 'clock', 'certificate'];
for (const n of mustHave) {
    if (!solid.includes(n)) console.log('MISSING solid:', n);
}
for (const n of ['instagram', 'facebook-f', 'x-twitter', 'linkedin-in', 'youtube', 'whatsapp']) {
    if (!brands.includes(n)) console.log('MISSING brand:', n);
}

console.log(`wrote ${target}: ${solid.length} solid, ${brands.length} brands`);
