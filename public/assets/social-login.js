import { createApp } from './vue.esm-browser.prod.js';
import { localizedRequest } from './i18n.js';
import { createSocialLogin } from './social-login-form.js';

const root = document.querySelector('#social-login-app');
if (root) {
    createApp(createSocialLogin(root.dataset.error, new URLSearchParams(window.location.search).has('oauth_error'), localizedRequest(document.documentElement.lang))).mount(root);
}
