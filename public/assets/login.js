import { createApp } from './vue.esm-browser.prod.js';
import { createLoginForm } from './login-form.js';

import { localizedRequest } from './i18n.js';

const root = document.querySelector('#login-app');
createApp(createLoginForm(root.dataset.loginUrl, JSON.parse(root.dataset.messages), localizedRequest(document.documentElement.lang), () => window.location.assign(root.dataset.homeUrl))).mount(root);
