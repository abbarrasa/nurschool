export function createRegistrationForm(messages, request = fetch, token = '', onVerified = () => {}, verifying = Boolean(token)) {
    return {
        data: () => ({ messages, email: '', password: '', roles: [], token, verifying, loading: false, error: '', success: '' }),
        methods: {
            async submit() {
                if (this.loading) return;
                this.loading = true;
                this.error = '';
                try {
                    const response = await request(this.verifying ? '/api/account-verifications' : '/api/registrations', {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                        body: JSON.stringify(this.verifying ? { token: this.token } : { email: this.email.trim(), password: this.password, roles: this.roles }),
                    });
                    if (response.ok) {
                        this.success = this.verifying ? messages['registration.verified'] : messages['registration.success'];
                        this.password = '';
                        if (this.verifying) onVerified();
                    } else {
                        const data = await response.json();
                        this.error = data.message || messages['registration.error'];
                    }
                } catch { this.error = messages['registration.error']; }
                finally { this.loading = false; }
            },
        },
    };
}
