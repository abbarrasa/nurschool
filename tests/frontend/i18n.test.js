import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createLoginForm } from '../../public/assets/login-form.js';
import { createHomeView } from '../../public/assets/home-view.js';
import { localizedRequest } from '../../public/assets/i18n.js';

for (const locale of ['es', 'en']) {
    const messages = JSON.parse(readFileSync(new URL(`../../translations/messages.${locale}.json`, import.meta.url)));
    for (const [status, key] of [[400, 'validation'], [401, 'credentials'], [422, 'validation'], [429, 'throttled'], [500, 'server'], [0, 'network']]) {
        test(`${locale}: login error ${status} uses the shared catalog and hides server details`, async () => {
            const options = createLoginForm('/api/login', messages, async () => {
                if (!status) throw new Error('Internal network diagnostics');
                return { ok: false, status, json: async () => ({ message: 'Internal exception' }) };
            });
            const state = { ...options.data(), ...options.methods, loading: false };
            await state.submit();
            assert.equal(state.error, messages[`login.error.${key}`]);
            assert.equal(state.loading, false);
            assert.equal(state.messages['login.submit'], locale === 'es' ? 'Entrar' : 'Sign in');
        });
    }
    for (const status of [500, 0]) {
        test(`${locale}: account error ${status} uses the shared catalog`, async () => {
            const options = createHomeView('/api/me', messages, async () => {
                if (!status) throw new Error('Internal network diagnostics');
                return { ok: false, status };
            });
            const state = options.data();
            await options.mounted.call(state);
            assert.equal(state.error, messages[`home.error.${status ? 'load' : 'network'}`]);
        });
    }
}

test('API requests carry the page language without losing authentication or request headers', async () => {
    const request = localizedRequest('en', async (url, options) => {
        assert.equal(url, '/api/login');
        assert.equal(options.headers.get('Accept-Language'), 'en');
        assert.equal(options.headers.get('Content-Type'), 'application/json');
        assert.equal(options.headers.get('Accept'), 'application/json');
        assert.equal(options.credentials, 'same-origin');
        assert.equal(options.body, '{}');
    });
    await request('/api/login', { credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: '{}' });
});

test('API language wrapper accepts requests without options', async () => {
    await localizedRequest('es', async (_, options) => assert.equal(options.headers.get('Accept-Language'), 'es'))('/api/me');
});
