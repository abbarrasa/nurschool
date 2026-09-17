import { createApp } from './vue.esm-browser.prod.js';
import { createLoginForm } from './login-form.js';

const root = document.querySelector('#login-app');
createApp(createLoginForm(root.dataset.loginUrl, fetch, () => window.location.assign(root.dataset.homeUrl))).mount(root);
