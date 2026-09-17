export function createHomeView(profileUrl, request = fetch, onUnauthenticated = () => {}) {
    return {
        data: () => ({ user: null, loading: true, error: '' }),
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
                    this.error = 'No se ha podido cargar tu cuenta. Recarga la página para volver a intentarlo.';
                }
            } catch {
                this.error = 'No se ha podido conectar con el servidor. Comprueba tu conexión y recarga la página.';
            } finally {
                this.loading = false;
            }
        },
    };
}
