import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createLoginForm } from '../../public/assets/login-form.js';

function form(request) {
    const options = createLoginForm('/api/login', request);
    const state = { ...options.data(), ...options.methods, mounted: options.mounted };
    state.loading = false;
    state.email = ' student@example.com ';
    state.password = ' password with spaces ';
    return state;
}

test('sends JSON credentials and clears password after successful login', async () => {
    const state = form(async (url, options) => {
        assert.equal(url, '/api/login');
        assert.equal(options.method, 'POST');
        assert.equal(options.credentials, 'same-origin');
        assert.equal(options.headers['Content-Type'], 'application/json');
        assert.deepEqual(JSON.parse(options.body), { email: 'student@example.com', password: ' password with spaces ' });
        return { ok: true, json: async () => ({ email: 'student@example.com' }) };
    });
    await state.submit();
    assert.equal(state.user.email, 'student@example.com');
    assert.equal(state.password, '');
    assert.equal(state.error, '');
    assert.equal(state.loading, false);
});

for (const [status, message] of [[401, 'Email o contraseña incorrectos.'], [400, 'Introduce un email válido y una contraseña.'],
    [422, 'Introduce un email válido y una contraseña.'], [429, 'Demasiados intentos. Espera un momento y vuelve a intentarlo.'],
    [500, 'No se ha podido iniciar sesión. Inténtalo de nuevo más tarde.']]) {
    test(`shows a safe error for HTTP ${status} and allows retry`, async () => {
        const state = form(async () => ({ ok: false, status }));
        await state.submit();
        assert.equal(state.error, message);
        assert.equal(state.loading, false);
        assert.equal(state.user, null);
    });
}

test('network errors leave the form usable', async () => {
    const state = form(async () => { throw new Error('offline'); });
    await state.submit();
    assert.match(state.error, /conectar con el servidor/);
    assert.equal(state.loading, false);
});

test('blocks duplicate requests while login is pending', async () => {
    let finish;
    let calls = 0;
    const state = form(() => { calls++; return new Promise(resolve => { finish = resolve; }); });
    const pending = state.submit();
    await state.submit();
    assert.equal(calls, 1);
    assert.equal(state.loading, true);
    finish({ ok: false, status: 401 });
    await pending;
    assert.equal(state.loading, false);
});

test('restores existing session through API', async () => {
    const state = form(async (url, options) => {
        assert.equal(url, '/api/me');
        assert.equal(options.credentials, 'same-origin');
        return { ok: true, json: async () => ({ email: 'student@example.com' }) };
    });
    await state.mounted();
    assert.equal(state.user.email, 'student@example.com');
});

test('failed session check still enables login', async () => {
    const state = form(async () => { throw new Error('offline'); });
    state.loading = true;
    await state.mounted();
    assert.equal(state.loading, false);
    assert.equal(state.user, null);
});

for (const action of ['submit', 'mounted']) {
    test(`${action} navigates to home only after authentication succeeds`, async () => {
        let navigations = 0;
        const options = createLoginForm('/api/login', async () => ({ ok: true, json: async () => ({ email: 'student@example.com' }) }), () => { navigations++; });
        const state = { ...options.data(), ...options.methods, mounted: options.mounted, loading: false };
        await state[action]();
        assert.equal(navigations, 1);
    });
    test(`${action} does not navigate after failed authentication`, async () => {
        let navigations = 0;
        const options = createLoginForm('/api/login', async () => ({ ok: false, status: 401 }), () => { navigations++; });
        const state = { ...options.data(), ...options.methods, mounted: options.mounted, loading: false };
        await state[action]();
        assert.equal(navigations, 0);
    });
}
