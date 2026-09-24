export function prepareVerificationNavigation(location, history, localeLinks, verifying) {
    const token = verifying ? location.hash.slice(1) : '';
    const links = Array.from(localeLinks);
    if (token) {
        // Preserve the fragment on locale links before hiding it from the address bar.
        // Fragments are not included in HTTP requests or referrer headers.
        for (const link of links) {
            const url = new URL(link.href, location.href);
            url.hash = location.hash;
            link.href = url.href;
        }
        history.replaceState(history.state, '', location.pathname + location.search);
    }

    return {
        token,
        clear() {
            if (!token) return;
            for (const link of links) {
                const url = new URL(link.href, location.href);
                url.hash = '';
                link.href = url.href;
            }
        },
    };
}
