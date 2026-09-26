import js from '@eslint/js';
import globals from 'globals';

export default [
    { ignores: ['public/assets/vue.esm-browser.prod.js', 'public/bundles/**', 'vendor/**', 'var/**', 'node_modules/**'] },
    js.configs.recommended,
    { files: ['public/assets/**/*.js'], languageOptions: { globals: globals.browser } },
    { files: ['tests/frontend/**/*.js', 'eslint.config.js'], languageOptions: { globals: { ...globals.node, ...globals.browser } } },
];
