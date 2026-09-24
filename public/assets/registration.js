import { createApp } from './vue.esm-browser.prod.js';
import { localizedRequest } from './i18n.js';
import { prepareVerificationNavigation } from './verification-navigation.js';
import { createRegistrationForm } from './registration-form.js';

const root = document.querySelector('#registration-app');
const verifying = window.location.pathname === '/verify-account';
const navigation = prepareVerificationNavigation(window.location, window.history, document.querySelectorAll('.language-switcher a[data-locale]'), verifying);
createApp(createRegistrationForm(JSON.parse(root.dataset.messages), localizedRequest(document.documentElement.lang), navigation.token, navigation.clear, verifying)).mount(root);
