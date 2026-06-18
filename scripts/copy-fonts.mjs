// scripts/copy-fonts.mjs
//
// Copies the woff2 files we actually use from @fontsource into
// public/assets/fonts/ so the runtime can serve them from our own
// origin instead of fetching from fonts.gstatic.com.
//
// We pull only the `latin` subset and only the weights we reference
// in Tailwind config (Inter 400/500/600/700, JetBrains Mono 400/500).
// Skipping latin-ext, cyrillic, vietnamese, etc. keeps the deploy
// payload to ~80 KB across the six woff2s.

import { copyFileSync, mkdirSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const projectRoot = join(__dirname, '..');
const destDir = join(projectRoot, 'public', 'assets', 'fonts');
mkdirSync(destDir, { recursive: true });

const jobs = [
    { from: '@fontsource/inter/files/inter-latin-400-normal.woff2',                       to: 'inter-400.woff2' },
    { from: '@fontsource/inter/files/inter-latin-500-normal.woff2',                       to: 'inter-500.woff2' },
    { from: '@fontsource/inter/files/inter-latin-600-normal.woff2',                       to: 'inter-600.woff2' },
    { from: '@fontsource/inter/files/inter-latin-700-normal.woff2',                       to: 'inter-700.woff2' },
    { from: '@fontsource/jetbrains-mono/files/jetbrains-mono-latin-400-normal.woff2',     to: 'jetbrains-mono-400.woff2' },
    { from: '@fontsource/jetbrains-mono/files/jetbrains-mono-latin-500-normal.woff2',     to: 'jetbrains-mono-500.woff2' },
];

let copied = 0;
for (const { from, to } of jobs) {
    const src = join(projectRoot, 'node_modules', from);
    const dst = join(destDir, to);
    if (! existsSync(src)) {
        console.error(`[copy-fonts] missing source: ${from}`);
        process.exit(1);
    }
    copyFileSync(src, dst);
    copied++;
}
console.log(`[copy-fonts] copied ${copied} woff2 files → public/assets/fonts/`);
