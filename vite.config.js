import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import rtlcss from 'rtlcss';

// Mirrors the compiled CSS of the app-rtl entry only; app-ltr is left as written.
// rtlcss is run on the finished stylesheet, so Bootstrap's physical rules flip and our
// logical-property rules stay as they are.
const rtlOnly = {
    postcssPlugin: 'rtl-only',
    OnceExit(root, { result, postcss }) {
        if (!/app-rtl/.test(result.opts.from || '')) {
            return;
        }

        const mirrored = postcss.parse(rtlcss.process(root.toString()), { from: result.opts.from });

        root.removeAll();
        root.append(mirrored.nodes);
    },
};

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/scss/app-ltr.scss',
                'resources/scss/app-rtl.scss',
                'resources/css/font-cairo.css',
                'resources/css/font-tajawal.css',
                'resources/js/app.js',
                'resources/js/uploader.js',
                'resources/js/attachments.js',
                'resources/js/translations.js',
                'resources/js/clients.js',
            ],
            refresh: true,
        }),
    ],
    css: {
        postcss: {
            plugins: [rtlOnly],
        },
        preprocessorOptions: {
            scss: {
                quietDeps: true,
                silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'mixed-decls', 'if-function'],
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
