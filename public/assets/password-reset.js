import { createApp } from './vue.esm-browser.prod.js';
import { localizedRequest } from './i18n.js';
import { prepareVerificationNavigation } from './verification-navigation.js';
import { createPasswordResetForm } from './password-reset-form.js';

const root = document.querySelector('#password-reset-app');
const resetting = window.location.pathname === '/reset-password';
const navigation = prepareVerificationNavigation(window.location, window.history, document.querySelectorAll('.language-switcher a[data-locale]'), resetting);
createApp(createPasswordResetForm(JSON.parse(root.dataset.messages), localizedRequest(document.documentElement.lang), navigation.token, resetting, navigation.clear)).mount(root);
