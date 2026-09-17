import { createApp } from './vue.esm-browser.prod.js';
import { createHomeView } from './home-view.js';

const root = document.querySelector('#home-app');
createApp(createHomeView(root.dataset.profileUrl, fetch, () => window.location.replace(root.dataset.loginUrl))).mount(root);
// Recheck the server after browser history navigation instead of showing a cached session.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) window.location.reload();
});
