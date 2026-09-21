export function createHomeView(profileUrl, messages, request = fetch, onUnauthenticated = () => {}) {
    return {
        data: () => ({ messages, user: null, loading: true, error: '' }),
        async mounted() {
            try {
                const response = await request(profileUrl, {
                    credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store',
                });
                if (response.status === 401) {
                    onUnauthenticated();
                } else if (response.ok) {
                    this.user = await response.json();
                } else {
                    this.error = messages['home.error.load'];
                }
            } catch {
                this.error = messages['home.error.network'];
            } finally {
                this.loading = false;
            }
        },
    };
}
