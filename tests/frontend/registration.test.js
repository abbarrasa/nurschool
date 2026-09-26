import test from 'node:test';
import assert from 'node:assert/strict';
import { createRegistrationForm } from '../../public/assets/registration-form.js';

const messages = { 'registration.success': 'sent', 'registration.verified': 'verified', 'registration.error': 'failed' };
function instance(request, token = '') {
    const component = createRegistrationForm(messages, request, token);
    return { ...component.data(), ...component.methods };
}
test('registration sends selected roles and clears the password after success', async () => {
    let body;
    const vm = instance(async (url, options) => { assert.equal(url, '/api/registrations'); body = JSON.parse(options.body); return { ok: true }; });
    Object.assign(vm, { email: ' test@example.com ', password: 'secure-password', roles: ['ROLE_ADMIN'] });
    await vm.submit();
    assert.deepEqual(body, { email: 'test@example.com', password: 'secure-password', roles: ['ROLE_ADMIN'] });
    assert.equal(vm.password, '');
    assert.equal(vm.success, 'sent');
});
test('verification submits the token only', async () => {
    const vm = instance(async (url, options) => { assert.equal(url, '/api/account-verifications'); assert.deepEqual(JSON.parse(options.body), { token: 'secret' }); return { ok: true }; }, 'secret');
    await vm.submit();
    assert.equal(vm.success, 'verified');
});
test('translated API errors and network failures leave the form usable', async () => {
    const vm = instance(async () => ({ ok: false, json: async () => ({ message: 'invalid' }) }));
    await vm.submit();
    assert.equal(vm.error, 'invalid');
    assert.equal(vm.loading, false);
    const offline = instance(async () => { throw new Error('offline'); });
    await offline.submit();
    assert.equal(offline.error, 'failed');
    assert.equal(offline.loading, false);
});
