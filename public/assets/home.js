import { createApp } from './vue.esm-browser.prod.js';
import { createHomeView } from './home-view.js';

import { localizedRequest } from './i18n.js';

const root = document.querySelector('#home-app');
createApp(createHomeView(root.dataset.profileUrl, JSON.parse(root.dataset.messages), localizedRequest(document.documentElement.lang), () => window.location.replace(root.dataset.loginUrl))).mount(root);
// Recheck the server after browser history navigation instead of showing a cached session.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) window.location.reload();
});
