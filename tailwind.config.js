/**
 * Dev-only Tailwind config. The dashboard ships a PREBUILT, minified stylesheet
 * (resources/dist/warden.css) so the host app never runs a build step — this
 * config exists purely to regenerate that artifact when the markup changes:
 *
 *   npx tailwindcss@3 -c tailwind.config.js -i resources/css/warden.css \
 *     -o resources/dist/warden.css --minify
 *
 * Palette, type and tokens follow the Warden Design System (Beacon Blue accent,
 * dark-first "night" slate-blue surfaces, disciplined status colors, Archivo +
 * JetBrains Mono). `brand` = Beacon Blue, `ink` = night surfaces; the default
 * slate / emerald / amber / rose / sky shades are nudged to the DS values so the
 * existing markup adopts the brand without a class-by-class rewrite.
 */
module.exports = {
    darkMode: 'class',
    content: ['./resources/views/**/*.blade.php'],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Archivo', 'system-ui', '-apple-system', 'Segoe UI', 'sans-serif'],
                wordmark: ['"Archivo Expanded"', 'Archivo', 'system-ui', 'sans-serif'],
                mono: ['"JetBrains Mono"', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
            },
            colors: {
                // Beacon Blue — the single brand accent (trust / infra / "the signal").
                brand: {
                    300: '#8FB6FF',
                    400: '#5B97FF',
                    500: '#2E7BFF',
                    600: '#1F5FE0',
                    700: '#1747AE',
                },
                // Night — cool slate-blue dark surfaces (page → card → raised).
                ink: {
                    400: '#3C4866',
                    500: '#2E3950',
                    600: '#2E3950',
                    700: '#232C42',
                    750: '#1A2235',
                    800: '#151C2E',
                    850: '#111726',
                    900: '#0A0E18',
                    950: '#070A12',
                },
                // Nocturnal (blue-tinted) text ramp, mapped onto the slate shades
                // the markup already uses.
                slate: {
                    200: '#DCE3F1',
                    300: '#DCE3F1',
                    400: '#9BA7C0',
                    500: '#6A7794',
                    600: '#485470',
                },
                // Status — used only for state, matched to the DS values.
                emerald: { 300: '#5BD98F', 400: '#2ECC71', 500: '#2ECC71' },
                amber: { 300: '#FFC04D', 400: '#FFB020', 500: '#FFB020' },
                rose: { 200: '#FFD3D0', 300: '#FF8A84', 400: '#FF5A52', 500: '#FF5A52', 600: '#E2473F', 700: '#B5352F' },
                sky: { 400: '#5B97FF', 500: '#2E7BFF' },
            },
            boxShadow: {
                // Beacon glow for primary/focused surfaces.
                glow: '0 0 0 1px rgba(46,123,255,0.18), 0 6px 24px rgba(46,123,255,0.18)',
            },
        },
    },
    plugins: [],
}
