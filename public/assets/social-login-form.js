export function createSocialLogin(message, failed, request = fetch, navigate = url => window.location.assign(url)) {
    return {
        data: () => ({ loading: false, error: failed ? message : '' }),
        methods: {
            async start(provider) {
                if (this.loading) return;
                this.loading = true;
                this.error = '';
                try {
                    const response = await request(`/api/oauth/${provider}/start`, {
                        method: 'POST', credentials: 'same-origin', cache: 'no-store',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: '{}',
                    });
                    if (!response.ok) throw new Error('Social sign-in could not start.');
                    const data = await response.json();
                    navigate(data.url);
                } catch { this.error = message; }
                finally { this.loading = false; }
            },
        },
    };
}
