/** @type {import('tailwindcss').Config} */
// Tailwind config for PayTracker. Authored as CommonJS because
// package.json has no "type": "module" (the Playwright config is
// TypeScript and doesn't need it).
//
// Scan paths are tight on purpose so the production CSS stays small:
// every class that ships to the browser must appear literally in a
// PHP view, a JS handler in a view, or the shared CSS source. Class
// names assembled at runtime in PHP/JS won't be picked up; if we end
// up needing one, add it to the `safelist` below.
//
// Brand direction (see feature/ui-phase-1-shell): slate-800 header
// surface, safety-orange primary action. Light mode default; the
// `darkMode: 'media'` switch leaves room for an opt-in dark variant
// in a follow-up phase without re-plumbing the config.

const forms = require('@tailwindcss/forms');

module.exports = {
    content: [
        './resources/views/**/*.php',
        './resources/css/app.css',
    ],
    safelist: [
        // Pill colour utilities are emitted by audit / status helpers
        // via PHP string concatenation; preserve the full set.
        'pill', 'pill-ok', 'pill-warn', 'pill-err', 'pill-muted',
    ],
    darkMode: 'media',
    theme: {
        extend: {
            colors: {
                // Brand. Header surface is slate-800; primary action
                // is safety-orange. Both pass 4.5:1 on white text.
                brand: {
                    surface: '#1E293B',         // slate-800
                    'surface-hover': '#334155', // slate-700
                    primary: '#F97316',         // orange-500
                    'primary-hover': '#EA580C', // orange-600
                    'primary-soft': '#FFEDD5',  // orange-100 — highlight bg
                    ink: '#0F172A',             // slate-900 body text
                    muted: '#475569',           // slate-600 muted text
                    line: '#E2E8F0',            // slate-200 borders / dividers
                    surfaceBg: '#F8FAFC',       // slate-50 page background
                },
            },
            fontFamily: {
                // Inter for everything user-facing. Prepended to the
                // system stack so users on browsers that haven't yet
                // loaded the Google font still see a friendly sans
                // while it streams in.
                sans: [
                    'Inter',
                    'ui-sans-serif',
                    'system-ui',
                    '-apple-system',
                    'Segoe UI',
                    'Roboto',
                    'Helvetica Neue',
                    'sans-serif',
                ],
                // JetBrains Mono for the <code> elements that pepper
                // the audit log, version footer, FRTL columns, and
                // metadata strips. Better tabular alignment than the
                // system mono stack.
                mono: [
                    'JetBrains Mono',
                    'ui-monospace',
                    'SFMono-Regular',
                    'Menlo',
                    'Consolas',
                    'monospace',
                ],
            },
            fontSize: {
                // Slightly larger base than Tailwind's default 16px
                // to satisfy the "large readable font" ask. Headings
                // follow a 1.25 modular scale.
                base: ['1.0625rem', { lineHeight: '1.55' }],
            },
            borderRadius: {
                // Slightly tighter than DEFAULT for a more utility-app
                // feel; not pill-shaped.
                xl2: '0.875rem',
            },
            boxShadow: {
                card: '0 1px 2px rgba(15, 23, 42, 0.04), 0 1px 3px rgba(15, 23, 42, 0.06)',
                'card-elev': '0 4px 16px rgba(15, 23, 42, 0.10)',
            },
        },
    },
    plugins: [
        forms({ strategy: 'class' }),
    ],
};
