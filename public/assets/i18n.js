// Keep API responses in the language of this page, even if another tab changes the cookie.
export function localizedRequest(locale, request = fetch) {
    return (url, options = {}) => {
        const headers = new Headers(options.headers);
        headers.set('Accept-Language', locale);
        return request(url, { ...options, headers });
    };
}
