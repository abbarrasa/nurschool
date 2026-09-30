# PROJECT MEMORY

This file tracks the project's architecture, stack decisions, and technical guidelines that must be respected across all sessions.

---

## 1. TECH STACK & ARCHITECTURE

### Frontend & Backend Separation
- **Backend:** Symfony with **API Platform** for a strict REST API.
- **Frontend:** **Twig** and **Vue.js**. Twig provides the page structure and mounts the Vue.js interface, while Vue.js handles interactivity and data loading via API calls.
- **Business Logic:** Centralized in the backend (validation, authorization, and persistence). Do not pass application data directly from backend controllers into Twig templates.

### Frontend Styling
- **Framework:** **Bulma CSS**.
- **Isolation:** Third-party assets and framework-specific styles must be isolated in a presentation theme loaded through one shared entry point. 
- **Decoupling:** Centralize framework classes in reusable Twig presentation helpers. Business templates and Vue.js logic must use semantic presentation helpers and application-owned selectors instead of depending directly on Bulma.

---

## 2. TRANSLATIONS & LITERALS

- **Source of Truth:** Supported locales are strictly defined in `framework.enabled_locales` inside Symfony's `translation.yaml`. Do not hardcode separate lists.
- **User Interface:** All user-visible text (labels, buttons, placeholders, validation, and browser-facing error messages) must use the application's translation mechanism and catalogs.
- **Server-Side Literals:** Internal server strings not exposed to the browser (exceptions, logs, diagnostics) must be written in **English**. Separate translated user-facing messages from internal English messages if an error must be shown.

---

## 3. QUALITY GATES & TESTING

Every feature must implement automated coverage for the changed behavior:
- **Unit Tests:** For isolated domain, service, and validation logic.
- **Functional Tests:** For HTTP endpoints, controllers, forms, authentication, and error responses.
- **Integration Tests:** For persistence, Doctrine, messaging, or external adapters (use fakes/test doubles unless live calls are explicitly authorized).

### Static Analysis & Tooling
- Run **PHPStan** at the project's configured level and resolve any introduced errors. Do not weaken rules, exclude new code, or lower the analysis level to pass.
- If the repository lacks test tooling or PHPStan configuration, add the minimal development dependencies required to run them as part of the feature work.

---

## 4. PASSWORD RECOVERY

- **API:** `POST /api/password-reset-requests` accepts an email and returns the same HTTP 202 message for known and unknown accounts. `POST /api/password-resets` accepts a token and new password.
- **Token lifecycle:** Cryptographically random tokens are stored only as SHA-256 hashes in user records, separately from account-verification tokens. Queue payloads contain tokens only inside authenticated ciphertext. They expire after `PASSWORD_RESET_TTL` seconds (default: 3,600); the expiry instant itself is invalid. The value must be positive and is shared by token generation and SendGrid template data. A new request replaces the previous token. Password updates and token consumption use one conditional database update to prevent concurrent reuse.
- **Account rules:** Reuse the current password policy (at least 12 characters, at most 72 bytes) and Symfony password hasher. Recovery must not change roles or account-verification status.
- **Reset persistence:** Resolve the account using a valid token before hashing with its configured password hasher. Recheck expiry and token ownership in the conditional update, then refresh the managed entity to keep Doctrine state synchronized.
- **Delivery:** Token persistence and email enqueueing share a Doctrine transaction. SendGrid template family `password_reset` defaults to `d-94364cdab2c64321b691171c8d4bf420` for both enabled locales and receives `url`, `ttl` in seconds, and `locale`. Configure the public HTTPS origin through `PASSWORD_RESET_BASE_URL`. Delivery delays never extend the lifetime.
- **Delivery failures:** Catch failures only around the token/email transaction, after Doctrine rolls back, and retain the same generic HTTP 202 body for registered and unknown emails. Failed template validation, queue insertion, and encryption leave the previous token and queued messages unchanged and still consume request quota. Log an error with the exception class only; never log the recipient, raw token, payload, exception message, or trace. Validation errors and throttling keep their existing statuses. HTTP 202 is an acknowledgment, not a delivery guarantee; operations must monitor server-side failure logs. Response timing is not normalized.
- **Frontend:** Twig mounts Vue on `/forgot-password` and `/reset-password`; the login page links to recovery. Keep the token in the URL fragment, hide it from the address bar, preserve it during locale changes, and clear it after successful consumption. All messages use translation catalogs and existing presentation helpers.
- **Deployment:** Apply `Version20260930120000`, configure the sender and Messenger consumer, and verify the published SendGrid template uses the documented variables. Automated tests use fake template IDs and disposable databases, never live email delivery.
- **Request throttling:** Symfony RateLimiter limits requests per normalized email, including unknown accounts, before lookup or enqueueing. `PASSWORD_RESET_REQUEST_LIMIT` defaults to 3; `PASSWORD_RESET_REQUEST_INTERVAL` defaults to `1 hour`. The fixed window starts at the first request. Excess requests return translated HTTP 429 with `Retry-After` and leave the current token intact. Valid requests consume quota even if enqueueing fails. Token lifetime and throttle interval are independent.
- **Throttle storage:** Use the dedicated filesystem pool `cache.password_reset_requests` and `flock` locking to preserve counters across requests and serialize concurrent consumption on one host. Clear this pool between functional tests. Multiple hosts require a shared cache and lock store; clearing the pool resets counters.
- **Named lock configuration:** Define `password_reset_requests` beneath `framework.lock.resources` so Symfony creates `lock.password_reset_requests.factory`; keep named resources separate from the `enabled` option.
- **Submission throttling:** `POST /api/password-resets` uses an independent per-IP sliding-window limiter before parsing JSON, looking up tokens, or hashing passwords. `PASSWORD_RESET_SUBMISSION_LIMIT` defaults to 10 and `PASSWORD_RESET_SUBMISSION_INTERVAL` defaults to `15 minutes`. Invalid attempts consume quota; rejection returns translated HTTP 429 with `Retry-After` and leaves the password/token intact. Missing client IPs are rejected with HTTP 429. Use the dedicated filesystem pool `cache.password_reset_submissions` and named `flock` resource `password_reset_submissions`; clear both recovery pools between functional tests.
- **Proxy and deployment rules:** Derive the IP through `Request::getClientIp()` and configure only trusted reverse proxies; never trust forwarded headers directly. Multiple hosts require shared counters and lock storage. A reverse-proxy limit is still needed to reject high-volume traffic before PHP; clients sharing an IP share the submission quota. Per-IP throttling of the email-request endpoint remains unimplemented.


## 5. ENCRYPTED EMAIL QUEUES

- **Serialization:** Both `sendgrid` and `failed` transports must use `Nurschool\Messenger\EncryptedQueueSerializer`. Encrypt the complete serialized envelope and headers so retries, error stamps, recipients, and bearer tokens never appear in plaintext queue rows.
- **Cryptography:** `PayloadCipher` uses AES-256-GCM with fresh 12-byte nonces, 16-byte tags, versioned payloads, and a fixed authentication context. Authenticate before deserialization and reject legacy plaintext, unsupported versions, tampering, and wrong keys. OpenSSL is a required platform dependency.
- **Key management:** `QUEUE_ENCRYPTION_KEY` is a base64-encoded random 32-byte key without a production default. Producers, consumers, failure-queue commands, and all hosts must share it. Keep it outside Git and database backups; tests always replace it with a test-only key. The local development key is held in ignored `.env.local`.
- **Existing rows:** With producers and workers stopped, run `app:queue:encrypt-existing` to encrypt legacy `sendgrid`/`failed` rows atomically, without deserializing objects or changing IDs, queues, and timestamps. The command is idempotent and validates already encrypted rows with the configured key.
- **Token usage:** Only workers decrypt queued email data. Links and password-reset token validation remain unchanged; consuming a token still uses the SHA-256 hash and expiry stored on the user.
- **Technical debt and limitations:** One active key is supported; drain both queues before rotating it. Historical plaintext backups require separate handling and cannot be fixed by encrypting current rows. Runtime processes and mail recipients necessarily see plaintext tokens after decryption.
