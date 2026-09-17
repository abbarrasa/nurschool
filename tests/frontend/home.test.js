import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createHomeView } from '../../public/assets/home-view.js';

async function mount(request, onUnauthenticated = () => {}) {
    const options = createHomeView('/api/me', request, onUnauthenticated);
    const state = options.data();
    await options.mounted.call(state);
    return state;
}

test('loads account from API with session credentials and no cache', async () => {
    const state = await mount(async (url, options) => {
        assert.equal(url, '/api/me');
        assert.equal(options.credentials, 'same-origin');
        assert.equal(options.cache, 'no-store');
        return { ok: true, json: async () => ({ email: 'student@example.com' }) };
    });
    assert.equal(state.user.email, 'student@example.com');
    assert.equal(state.loading, false);
    assert.equal(state.error, '');
});

test('expired session returns visitor to login', async () => {
    let redirects = 0;
    const state = await mount(async () => ({ status: 401 }), () => { redirects++; });
    assert.equal(redirects, 1);
    assert.equal(state.user, null);
    assert.equal(state.loading, false);
});

test('server error is shown without misreporting an expired session', async () => {
    const state = await mount(async () => ({ status: 500 }), () => assert.fail('Unexpected redirect'));
    assert.match(state.error, /cargar tu cuenta/);
    assert.equal(state.user, null);
    assert.equal(state.loading, false);
});

test('network failure is shown on home', async () => {
    const state = await mount(async () => { throw new Error('offline'); });
    assert.match(state.error, /conectar con el servidor/);
    assert.equal(state.loading, false);
});
