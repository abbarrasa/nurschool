export function createPasswordResetForm(messages, request = fetch, token = '', resetting = false, clear = () => {}) {
    return {
        data: () => ({ messages, email: '', password: '', confirmation: '', token, resetting, loading: false, error: '', success: '' }),
        methods: {
            async submit() {
                if (this.loading) return;
                this.error = '';
                if (this.resetting && this.password !== this.confirmation) {
                    this.error = messages['password_reset.mismatch'];
                    return;
                }
                this.loading = true;
                try {
                    const response = await request(this.resetting ? '/api/password-resets' : '/api/password-reset-requests', {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                        body: JSON.stringify(this.resetting ? { token: this.token, password: this.password } : { email: this.email.trim() }),
                    });
                    if (response.ok) {
                        this.success = messages[this.resetting ? 'password_reset.success' : 'password_reset.requested'];
                        this.password = '';
                        this.confirmation = '';
                        if (this.resetting) {
                            this.token = '';
                            clear();
                        }
                    } else {
                        const data = await response.json();
                        this.error = data.message || messages['password_reset.error'];
                    }
                } catch { this.error = messages['password_reset.error']; }
                finally { this.loading = false; }
            },
        },
    };
}
