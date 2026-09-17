export function createLoginForm(loginUrl, request = fetch, onAuthenticated = () => {}) {
    return {
    data: () => ({ email: '', password: '', error: '', loading: true, user: null }),
    async mounted() {
        try {
            const response = await request('/api/me', { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (response.ok) {
                this.user = await response.json();
                onAuthenticated();
            }
        } catch { /* Keep the form available if the session check fails. */ }
        finally { this.loading = false; }
    },
    methods: {
        async submit() {
            if (this.loading) return;
            this.loading = true;
            this.error = '';
            try {
                const response = await request(loginUrl, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ email: this.email.trim(), password: this.password }),
                });
                if (response.ok) {
                    this.user = await response.json();
                    this.password = '';
                    onAuthenticated();
                } else if (response.status === 401) {
                    this.error = 'Email o contraseña incorrectos.';
                } else if (response.status === 400 || response.status === 422) {
                    this.error = 'Introduce un email válido y una contraseña.';
                } else if (response.status === 429) {
                    this.error = 'Demasiados intentos. Espera un momento y vuelve a intentarlo.';
                } else {
                    this.error = 'No se ha podido iniciar sesión. Inténtalo de nuevo más tarde.';
                }
            } catch {
                this.error = 'No se ha podido conectar con el servidor. Inténtalo de nuevo.';
            } finally {
                this.loading = false;
            }
        },
    },
};
}
