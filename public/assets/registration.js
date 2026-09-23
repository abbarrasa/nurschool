import { createApp } from './vue.esm-browser.prod.js';
import { localizedRequest } from './i18n.js';
import { createRegistrationForm } from './registration-form.js';

const root = document.querySelector('#registration-app');
const token = window.location.pathname === '/verify-account' ? window.location.hash.slice(1) : '';
// Keep the token out of subsequent navigation and browser history.
if (token) window.history.replaceState(null, '', window.location.pathname + window.location.search);
createApp(createRegistrationForm(JSON.parse(root.dataset.messages), localizedRequest(document.documentElement.lang), token, () => {}, window.location.pathname === '/verify-account')).mount(root);
