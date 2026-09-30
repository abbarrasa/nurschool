import test from 'node:test';
import assert from 'node:assert/strict';
import { createPasswordResetForm } from '../../public/assets/password-reset-form.js';

const messages = { 'password_reset.requested': 'sent', 'password_reset.success': 'changed', 'password_reset.mismatch': 'mismatch', 'password_reset.error': 'failed' };
function instance(request, resetting = false, clear = () => {}) {
    const component = createPasswordResetForm(messages, request, 'secret', resetting, clear);
    return { ...component.data(), ...component.methods };
}
test('request trims email and displays the generic success message', async () => {
    const vm = instance(async (url, options) => {
        assert.equal(url, '/api/password-reset-requests');
        assert.deepEqual(JSON.parse(options.body), { email: 'ana@example.com' });
        return { ok: true };
    });
    vm.email = ' ana@example.com ';
    await vm.submit();
    assert.equal(vm.success, 'sent');
});
test('reset checks confirmation and clears secrets and navigation after success', async () => {
    let requests = 0;
    let cleared = false;
    const vm = instance(async (url, options) => {
        requests++;
        assert.equal(url, '/api/password-resets');
        assert.deepEqual(JSON.parse(options.body), { token: 'secret', password: 'new-password' });
        return { ok: true };
    }, true, () => { cleared = true; });
    vm.password = 'new-password';
    await vm.submit();
    assert.equal(vm.error, 'mismatch');
    assert.equal(requests, 0);
    vm.confirmation = vm.password;
    await vm.submit();
    assert.equal(vm.success, 'changed');
    assert.equal(vm.token, '');
    assert.equal(vm.password, '');
    assert.equal(vm.confirmation, '');
    assert.equal(cleared, true);
});
test('translated expired-link errors and network failures allow retry', async () => {
    const vm = instance(async () => ({ ok: false, json: async () => ({ message: 'expired' }) }), true);
    await vm.submit();
    assert.equal(vm.error, 'expired');
    assert.equal(vm.loading, false);
    const offline = instance(async () => { throw new Error('offline'); });
    await offline.submit();
    assert.equal(offline.error, 'failed');
    assert.equal(offline.loading, false);
});
test('pending submission prevents duplicate requests', async () => {
    let complete;
    let requests = 0;
    const vm = instance(() => { requests++; return new Promise(resolve => { complete = resolve; }); });
    const pending = vm.submit();
    await vm.submit();
    assert.equal(requests, 1);
    complete({ ok: true });
    await pending;
});
