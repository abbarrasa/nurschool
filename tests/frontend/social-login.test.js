import test from 'node:test';
import assert from 'node:assert/strict';
import { createSocialLogin } from '../../public/assets/social-login-form.js';

test('social login redirects using the API and permits retries after errors', async () => {
    let calls = 0;
    let destination;
    const component = createSocialLogin('Translated error', true, async (url, options) => {
        assert.equal(url, '/api/oauth/google/start');
        assert.equal(options.headers['Content-Type'], 'application/json');
        if (++calls === 1) throw new Error('Offline');
        return { ok: true, json: async () => ({ url: 'https://accounts.google.com/example' }) };
    }, url => { destination = url; });
    const vm = component.data();
    assert.equal(vm.error, 'Translated error');
    await component.methods.start.call(vm, 'google');
    assert.equal(vm.error, 'Translated error');
    assert.equal(vm.loading, false);
    await component.methods.start.call(vm, 'google');
    assert.equal(destination, 'https://accounts.google.com/example');
    assert.equal(vm.error, '');
});

test('HTTP errors keep social buttons usable and duplicate clicks are ignored', async () => {
    let calls = 0;
    const component = createSocialLogin('Try again', false, async () => { calls++; return { ok: false }; });
    const vm = component.data();
    vm.loading = true;
    await component.methods.start.call(vm, 'facebook');
    assert.equal(calls, 0);
    vm.loading = false;
    await component.methods.start.call(vm, 'facebook');
    assert.equal(vm.error, 'Try again');
    assert.equal(vm.loading, false);
});
