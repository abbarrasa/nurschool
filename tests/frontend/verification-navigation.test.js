import test from 'node:test';
import assert from 'node:assert/strict';
import { prepareVerificationNavigation } from '../../public/assets/verification-navigation.js';
import { createRegistrationForm } from '../../public/assets/registration-form.js';

test('locale navigation preserves the token and query until successful verification', async () => {
    let location = new URL('https://example.com/verify-account?_locale=es&source=a%26b#secret');
    for (const [index, locale] of ['en', 'es', 'en'].entries()) {
        const links = [{ href: `/verify-account?_locale=${locale}&source=a%26b` }];
        const history = { state: { existing: true }, replaceState(state, title, url) {
            assert.equal(new URL(links[0].href).hash, '#secret');
            assert.deepEqual(state, { existing: true });
            location = new URL(url, location);
        } };
        const navigation = prepareVerificationNavigation(location, history, links, true);
        assert.equal(location.hash, '');
        assert.equal(location.searchParams.get('source'), 'a&b');
        const next = new URL(links[0].href);
        assert.equal(next.searchParams.get('_locale'), locale);
        assert.equal(next.searchParams.get('source'), 'a&b');
        location = next;
        const component = createRegistrationForm({}, async (url, options) => {
            assert.equal(url, '/api/account-verifications');
            assert.deepEqual(JSON.parse(options.body), { token: 'secret' });
            return index === 2 ? { ok: true } : { ok: false, json: async () => ({ message: 'Try again' }) };
        }, navigation.token, navigation.clear, true);
        await component.methods.submit.call(component.data());
        assert.equal(new URL(links[0].href).hash, index === 2 ? '' : '#secret');
        assert.equal(new URL(links[0].href).search, next.search);
    }
});

test('missing tokens and unrelated registration fragments do not change navigation', () => {
    for (const [url, verifying] of [['/verify-account?_locale=en', true], ['/register#section', false]]) {
        const links = [{ href: '/register?_locale=es' }];
        const navigation = prepareVerificationNavigation(new URL(url, 'https://example.com'), {
            replaceState() { assert.fail('History must not change'); },
        }, links, verifying);
        assert.equal(navigation.token, '');
        navigation.clear();
        assert.equal(links[0].href, '/register?_locale=es');
    }
});
