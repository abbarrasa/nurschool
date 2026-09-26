# Nurschool
Assistant to the nursing service for schools written in PHP.

Nurschool is a nursing service assistant for schools. It provides tools for the management of medical files of students and communication between nurses and students' tutors. It also provides tools such as forums and blogs for the diffusion of the nursing function at school in order to generate a community around it.

Nurschool is a PHP project based on a Symfony 7 and API Platform implementation.

## Project motivation

Nurschool was born out of the need to support school nurses in monitoring and
assessing students who require nursing care and ongoing health support at school.
The project aims to help these professionals follow each student's needs over time
and support continuity of care within the school community.

It also reflects my personal motivation as a Software Engineer to integrate
artificial intelligence throughout the entire software development lifecycle.
From requirements analysis and architecture to implementation, testing,
documentation, deployment, and maintenance, Nurschool provides a practical setting
for exploring how AI can support software engineering while preserving technical
rigor, code quality, and professional judgment.


## Local installation with Docker

This guide starts with Docker, the Docker Compose v2 plugin, and GNU Make already
available. Installing those packages is outside its scope because the procedure
depends on the operating system. Run the commands from the repository root in a
POSIX-compatible terminal. On Windows, use a configured WSL environment with
Docker integration; the Makefile's native Windows fallback does not provide the
same UID/GID handling as Linux or macOS.

### Docker access and system users

Start the Docker daemon or Docker Desktop using the mechanism provided by your
installation. Verify that your regular development account can access the intended
local Docker context:

```sh
docker context show
docker info
docker compose version
make show-user-ids
```

For a conventional Linux Docker Engine installation running as root, an
administrator can grant the development account access through the `docker`
group. Run the following from that account, not from a root login shell:

```sh
sudo groupadd --force docker
sudo usermod -aG docker "$(id -un)"
```

Sign out and back in, then check `id -nG` and `docker info` without `sudo`.
Membership in this group grants root-equivalent control through Docker; grant it
only to trusted development accounts. Docker Desktop and rootless Docker have
different access arrangements and do not require this group recipe. See the
[Docker post-installation documentation](https://docs.docker.com/engine/install/linux-postinstall/).

The application does not require creating host accounts named `www-data`, `nginx`,
or `mysql`. These are identities inside the images, separate from the developer's
host account and from database login accounts:

| Identity | Responsibility | Required filesystem access |
| --- | --- | --- |
| Host development account | Edits the checkout and runs Make targets | Owns the checkout and generated development files |
| PHP CLI user | Runs Composer and console commands through the Makefile | Writes dependencies and Symfony runtime files |
| PHP-FPM worker (`www-data` in the current PHP image) | Handles web requests | Reads application files and writes `var/` |
| Nginx worker (`nginx` in the current web image) | Serves static assets and forwards PHP requests | Traverses the project mount and reads `public/` |
| MariaDB service user | Runs the database server | Uses the Docker-managed database volume |

On Linux and macOS, the Makefile obtains the caller's numeric UID/GID and passes
`--user UID:GID` to its `docker exec` commands. Run `make` as your regular account,
not with `sudo`, so Composer does not create root-owned files in the checkout.
Although Make also exports `HOST_UID` and `HOST_GID`, the current Compose file and
Dockerfile do not consume them to change PHP-FPM or Nginx worker identities. Access
to the Docker daemon and access to bind-mounted project files are separate concerns.

### Local environment configuration

Create `.env` from `env.dist` only if it does not already exist:

```sh
test -f .env || cp env.dist .env
chmod 600 .env
```

Edit `.env` and provide the following values. Replace every placeholder with a
local value; use separate database user and root passwords. Long random hexadecimal
values are convenient for secrets and avoid URL-encoding issues in database URLs.

```dotenv
APP_ENV=dev
APP_SECRET=<random-application-secret>
APP_SHARE_DIR=var/share
DEFAULT_URI=http://localhost:8080
DATABASE_NAME=nurschool
DATABASE_USER=nurschool
DATABASE_PASSWORD=<local-application-database-password>
DATABASE_ROOT_PASSWORD=<local-database-root-password>
DATABASE_URL="mysql://${DATABASE_USER}:${DATABASE_PASSWORD}@nurschool-database:3306/${DATABASE_NAME}?serverVersion=mariadb-11.4.0&charset=utf8mb4"
```

Keep the other entries supplied by `env.dist`. The current browser login uses
sessions, so generating JWT keys is not required for this login flow. If a
password contains reserved URL characters, encode its value in `DATABASE_URL`
while keeping the actual password in `DATABASE_PASSWORD`.

Use `nurschool-database` as the database hostname: `127.0.0.1` inside the PHP
container refers to PHP's own container. Compose reads `.env` for database
initialization and injects `DATABASE_URL` into PHP. Symfony's `.env.local` does not
supply Compose interpolation values and cannot override an already injected
`DATABASE_URL`. Check for conflicting exported shell variables if the effective
configuration differs from `.env`.

The checkout is bind-mounted at `/var/www/nurschool` in PHP and Nginx. MariaDB uses
the named volume `nurschool-db-data` (normally prefixed by the Compose project
name). The current ports are host `8080` for HTTP and host `3306` for MariaDB;
ensure they are available. The Compose file publishes both on all host interfaces.
Docker Desktop must also be allowed to share the checkout directory.

### Build, start, and install application dependencies

```sh
make build
make start
make show
make composer-install
```

`make build` builds the PHP image; Compose obtains the Nginx and MariaDB images
when starting the services. `make start` creates the Compose network and database
volume automatically. No manual Docker network or host database directory is
required. The container names are `nurschool-php`, `nurschool-web`, and
`nurschool-db`.

`make composer-install` installs the versions in `composer.lock` inside PHP and
intentionally skips Composer scripts. The web page is not ready until dependencies,
permissions, and database initialization are complete. Use `make composer-update`
only when intentionally updating dependency versions.

### Bind-mount permissions and ACLs

The following ACL commands apply to a local Linux Docker Engine using ordinary
UID/GID mapping and a filesystem with POSIX ACL support. They require `setfacl`
and `getfacl` on the host. They are not portable instructions for Docker Desktop,
rootless Docker, or a daemon configured with user namespace remapping. In those
setups, first determine the host identities actually used for bind-mount access
and adapt the permissions and CLI user mapping. Container IDs must not be assumed
to equal host IDs. See [Docker UID/GID mapping](https://docs.docker.com/engine/security/rootless/uid-gid-mapping/).

Inspect the worker identities instead of assuming that the host's `www-data` or
`nginx` accounts use the same IDs as the images. Run this block on the host, after
the containers have started:

```sh
NURSCHOOL_DEV_UID=$(id -u)
NURSCHOOL_PHP_UID=$(docker exec nurschool-php id -u www-data)
NURSCHOOL_NGINX_UID=$(docker exec nurschool-web id -u nginx)

mkdir -p var/cache var/log var/share
sudo setfacl -R -m "u:${NURSCHOOL_DEV_UID}:rwX,u:${NURSCHOOL_PHP_UID}:rwX" var
sudo setfacl -dR -m "u:${NURSCHOOL_DEV_UID}:rwX,u:${NURSCHOOL_PHP_UID}:rwX" var
getfacl -n var var/cache var/log
```

The first ACL applies to existing files and directories. The default ACL is
inherited by new runtime files and directories, allowing both CLI and PHP-FPM to
continue working with them. Capital `X` grants traversal on directories without
making every regular file executable. Check ACL masks and any `effective:` entries
in `getfacl` output when diagnosing denied access. This follows Symfony's
[shared runtime directory permissions guidance](https://symfony.com/doc/7.4/setup/file_permissions.html).

PHP also needs to read the application and environment files, while Nginx needs
to read public assets. For a restrictive checkout, grant this access explicitly;
the environment-file rule is needed after the `chmod 600 .env` step above:

```sh
sudo setfacl -m "u:${NURSCHOOL_PHP_UID}:--x,u:${NURSCHOOL_NGINX_UID}:--x" .
sudo setfacl -R -m "u:${NURSCHOOL_PHP_UID}:rX" bin config public src templates translations vendor
sudo setfacl -m "u:${NURSCHOOL_PHP_UID}:r--" composer.json composer.lock symfony.lock
sudo setfacl -R -m "u:${NURSCHOOL_NGINX_UID}:rX" public
for NURSCHOOL_ENV_FILE in .env .env.local .env.dev .env.dev.local; do
    if [ -f "$NURSCHOOL_ENV_FILE" ]; then
        sudo setfacl -m "u:${NURSCHOOL_PHP_UID}:r--" "$NURSCHOOL_ENV_FILE"
    fi
done
```

Reapply read ACLs if an editor replaces a restricted file or a dependency install
recreates directories. Runtime write access belongs in `var/`, not across the
source tree. Do not use recursive `chmod 777` or assign the entire checkout to the
web worker. If earlier commands created root-owned generated files, inspect their
ownership and repair only the affected paths before retrying. For example, if
`ls -ldn var` confirms that an existing local `var/` belongs to root, restore its
ownership before creating subdirectories and reapplying the runtime ACLs:

```sh
sudo chown -R "$(id -u):$(id -g)" var
```

This example assumes the ordinary Linux UID/GID mapping described above. Do not
change the ownership of MariaDB's managed volume to your development account.

The mounts use the shared SELinux label option `:z`. On SELinux-enabled hosts,
check mount labels as well as Unix permissions and ACLs if access remains denied.
Do not disable SELinux to resolve a project-directory permission problem.

To verify runtime access, use disposable files, then remove them:

```sh
docker exec --user "${NURSCHOOL_DEV_UID}:$(id -g)" nurschool-php sh -c 'touch var/.cli-permission-check && rm var/.cli-permission-check'
docker exec --user www-data nurschool-php sh -c 'test -r .env && touch var/.web-permission-check && rm var/.web-permission-check'
docker exec --user nginx nurschool-web sh -c 'test -r public/assets/app.css'
```

### Initialize Symfony and the database

Wait until MariaDB is ready to accept connections; inspect its startup output with
`docker compose logs nurschool-database` if necessary. Compose startup ordering does
not guarantee database readiness. Open the PHP shell using the Makefile:

```sh
make bash
```

Inside that shell, initialize the application as the mapped CLI user:

```sh
php -d xdebug.mode=off bin/console cache:clear
php -d xdebug.mode=off bin/console assets:install public
php -d xdebug.mode=off bin/console doctrine:migrations:migrate --no-interaction
php -d xdebug.mode=off bin/console doctrine:schema:validate
php -d xdebug.mode=off bin/console app:user:create-super-admin
exit
```

On first startup with an empty volume, MariaDB creates the database and application
database account from `.env`. The initial migration creates the tables and four
application roles; the interactive command then creates the first login account.
The database account and the Nurschool superadministrator are different accounts.
Changing initialization variables later does not update users or passwords in an
existing database volume. Preserve that volume and update existing database
accounts deliberately rather than deleting data to repeat initialization.

Open [Nurschool locally](http://localhost:8080/login) and sign in with the account
created above. No host PHP, Composer, Node.js installation, or frontend build is
required to run the application; Node.js is needed only for frontend tests.

### Daily Makefile commands

| Command | Purpose |
| --- | --- |
| `make show-user-ids` | Display the host platform and UID/GID used by Make |
| `make build` | Rebuild the PHP image after Dockerfile changes |
| `make start` | Create or start services and apply changed Compose configuration |
| `make stop` | Stop services while preserving containers and database data |
| `make restart` | Stop and start services |
| `make show` | Inspect containers and Docker volumes, images, and networks |
| `make composer-install` | Install locked dependencies as the development user |
| `make composer-update` | Intentionally update dependencies and the lock file |
| `make console` | Open an interactive PHP shell as the development user |
| `make logs` | Follow `var/log/dev.log` when that file exists |

After changing the Dockerfile, run `make build` followed by `make start`. After
changing Compose-provided environment variables, run `make start` so Compose can
recreate affected containers.

`make logs` only follows Symfony's log file; for container startup or stderr logs,
use `docker compose logs nurschool-php nurschool-web nurschool-database`. The current
`make test` points to an absent `bin/phpunit`; use the verification commands below.
The optional `code-style-install` target also contains `makedir` instead of
`mkdir` and is not part of this installation procedure. These existing Makefile
limitations are documented here without changing its targets.


## Login

Open `/login` to sign in with the email and password of an existing `User`.
Twig renders the page shell; Vue.js submits credentials and retrieves session data
through API Platform. The backend verifies the stored password hash using Symfony
Security and the Doctrine user provider. No database migration is required.

- `POST /api/login`, `Content-Type: application/json`: `{"email":"user@example.com","password":"your-password"}`.
  Returns `200 {"email":"user@example.com"}` and a session cookie. Invalid credentials
  return `401` with the same message whether the email exists or not. Invalid JSON,
  missing fields, invalid email or empty password return `400`; non-JSON requests
  return `415`. Credentials in URLs or HTML form bodies are not accepted.
- `GET /api/me`, `Accept: application/json`: returns the authenticated user's email,
  or `401` without a valid session. Password hashes are never returned.

The frontend uses same-origin requests and an HttpOnly, SameSite=Lax session cookie
(Secure on HTTPS). It displays validation, authentication, network and server errors,
blocks duplicate submissions and navigates to the protected home page on success or when a session already exists.
No token or password is stored in browser storage. Use HTTPS in production.
Future operations that change data and use this session must include CSRF protection.

Vue 3.5.30 is vendored in `public/assets/vue.esm-browser.prod.js` from
`https://cdn.jsdelivr.net/npm/vue@3.5.30/dist/vue.esm-browser.prod.js`; its MIT license
is alongside it in `vue-LICENSE.txt`. No CDN request or frontend build is needed at runtime.

### Verification

Install PHP dependencies with `composer install`. The Docker PHP container has the
extensions required by these commands; the tests additionally require PDO SQLite.
The test bootstrap uses a unique temporary SQLite database and removes it on exit.
It does not use or change the configured application database.

```sh
docker exec nurschool-php php vendor/bin/simple-phpunit tests/Functional/LoginTest.php
docker exec -e XDEBUG_MODE=coverage nurschool-php php vendor/bin/simple-phpunit --coverage-text --coverage-clover var/coverage.xml
docker exec nurschool-php php -d xdebug.mode=off vendor/bin/phpstan analyse --no-progress --memory-limit=512M
node --test tests/frontend/*.test.js
```

The frontend tests use Node's built-in test runner (Node 20+) and fake API responses;
no npm dependencies or live external service calls are needed.


## Presentation theme and protected home

`nurschool_home` (`/`) requires an authenticated session. Anonymous visitors are
redirected to `/login`; API clients still receive JSON `401` responses. The home
page loads the current account through `/api/me` from Vue, without embedding user
data in Twig. If the session expires, Vue returns the visitor to login.

The shared layout displays **Salir** for authenticated users. It submits `POST
/logout` with a session CSRF token; Symfony invalidates the session and redirects
to `/login`. GET, missing/invalid tokens and replayed tokens cannot log a user out.

Bulma **1.0.4** is stored locally under `public/assets/vendor/bulma/` (including its
MIT license), downloaded from `https://cdn.jsdelivr.net/npm/bulma@1.0.4/`.
There are no runtime CDN requests or build requirements.

The CSS framework is isolated behind a presentation theme:

- `config/packages/twig.yaml`: `ui_theme` selects the theme adapter.
- `templates/ui/bulma.html.twig`: `stylesheets()` loads the theme entry point and
  `classes(name)` maps semantic presentation names to Bulma classes.
- `public/assets/themes/bulma.css`: imports the vendor stylesheet and contains all
  framework-specific overrides. Vendor files remain unchanged.
- `public/assets/app.css`: application-owned layout and selectors, independent
  of framework classes and variables. Vue uses IDs/data attributes, never Bulma selectors.

To replace Bulma, add an adapter implementing the same two macros, provide its
stylesheet/vendor assets, and change `ui_theme`. The base layout, business views,
Vue authentication/data-loading logic and backend do not need to change. A
functional test renders the login and home with an alternative adapter to verify
this contract. Framework replacements still need visual review for spacing and
responsive behavior.


## Internationalization

Symfony Translation is configured in `config/packages/translation.yaml`:
`framework.enabled_locales` is `['es', 'en']` and `framework.default_locale` is
`es`. Spanish is also the translation fallback. This configuration is the source
of truth for the language selector and locale validation.

Use the language links in the shared navigation to switch languages. A valid
`?_locale=es` or `?_locale=en` selection takes precedence and is stored for one
year in an HttpOnly, SameSite=Lax cookie (Secure on HTTPS). Unsupported or malformed
selections are ignored. For pages, the saved preference takes precedence over
`Accept-Language`; without either, the default is Spanish. Regional browser
languages such as `en-GB` are matched to the supported language. The preference
survives login and logout without changing authentication or session data.

Vue sends the page language in `Accept-Language` on API requests. For API requests,
this header takes precedence over the preference cookie, keeping existing tabs
consistent when another tab changes the selected language. An explicit valid
`_locale` query parameter still has the highest priority. Responses include
`Content-Language` and vary on `Accept-Language` and `Cookie`; preference changes
and error responses are not cached.

The `translations/messages.es.json` and `translations/messages.en.json` catalogs
contain all application-owned browser text, including accessible labels, account
and login states, errors, and the role identifiers seeded by the initial migration.
Twig uses `trans` and supplies the small set of translated presentation messages
needed by each Vue component as escaped JSON data attributes. User/application
data continues to come exclusively from API Platform; no account data is embedded
in templates. Vue code contains no language-specific messages or locale lists.

Internal exception messages, diagnostics, and console output are written in
technical English. Browser errors use translated messages without exposing the
original exception or stack trace, including in debug mode. HTTP status codes and
protocol headers such as `Allow` and `Retry-After` are preserved. Native browser
validation messages are provided by the browser and follow its own language
settings. Developer tooling and third-party API documentation are not translated
application views.

To add a language, update `framework.enabled_locales`, create a complete
`messages.<locale>.json` catalog, and add its `locale.<locale>` display name to
every catalog. For new visible text, add the same key to every enabled catalog
and use `trans` in Twig or include the translated key in the Vue presentation
messages. Keep theme classes in the existing Twig presentation adapter. No
database migration is required for locale selection or translations.

Translation verification includes catalog parity and real Symfony loading,
locale selection and persistence, authenticated navigation and logout in both
languages, malformed input, routing/authorization errors, and Vue error states:

```sh
docker exec -e SHELL_VERBOSITY=-1 nurschool-php php vendor/bin/simple-phpunit tests/Functional/InternationalizationTest.php
docker exec -e XDEBUG_MODE=coverage -e SHELL_VERBOSITY=-1 nurschool-php php vendor/bin/simple-phpunit --coverage-text --coverage-clover var/coverage.xml
docker exec nurschool-php php -d xdebug.mode=off vendor/bin/phpstan analyse --no-progress --memory-limit=512M
node --test tests/frontend/*.test.js
```

## Public registration and email verification

`GET /register` renders the Twig/Vue form. `POST /api/registrations` accepts JSON:

```json
{"email":"nurse@example.com","password":"a-long-secret-password","roles":["ROLE_NURSE","ROLE_ADMIN"]}
```

Email and password are required. Passwords require at least 12 characters and at
most 72 bytes (the bcrypt limit); emails are trimmed and lowercased. Omit `roles`
or pass `[]` for basic access. Only `ROLE_NURSE` and `ROLE_ADMIN` can be selected;
all registered users receive `ROLE_USER`. **Public registration intentionally
allows administrator self-selection**, as required by the product specification.
No other role or verification state can be supplied by the caller.

The API returns 201 after the account and its verification email are committed
atomically to the same Doctrine connection. Invalid data returns 422 and an
existing email returns 409. An enqueue failure rolls back the account. SendGrid
is contacted only by a Messenger worker; later delivery failures do not delete
the account. This implementation does not provide resend or expired-account
recovery.

Configure `MAILER_FROM`, `REGISTRATION_BASE_URL` (the trusted public HTTPS origin),
`REGISTRATION_TTL` (seconds; default 604800 = seven days), `MAILER_DSN`,
`SENDGRID_VERIFICATION_TEMPLATE_ES`, and `SENDGRID_VERIFICATION_TEMPLATE_EN`.
Use a verified sender, an API key with Mail Send permission, and active dynamic
templates with IDs of the form `d-` followed by 32 hexadecimal characters.
Each template defines its translated subject and body and receives `{{url}}`
and `{{ttl}}` (seconds). The `verification` family locale mapping lives in `config/services.yaml`;
keep it aligned with `framework.enabled_locales` when adding languages. Missing
or invalid template IDs fail before enqueueing rather than silently discarding
mail. Keep credentials in environment secrets or uncommitted `.env.local`.

Set `MAILER_DSN=sendgrid+dynamic://YOUR_URL_ENCODED_API_KEY@default` to use the
custom Mailer transport. The default `null://null` deliberately discards emails;
it is only suitable for local development. Tests use the real custom transport
with a dedicated mock HTTP client and a disposable SQLite Doctrine queue.

Apply `php bin/console doctrine:migrations:migrate` before using the feature.
Existing accounts are explicitly grandfathered as verified; trusted accounts
created by `app:user:create-super-admin` are verified by the command. Newly
registered accounts cannot authenticate until verification succeeds.

The email links to `/verify-account` with a 256-bit random token in the URL
fragment, keeping it out of HTTP access logs and referrers. Vue removes the
fragment from history after copying it to the language selector links. Locale
navigation preserves query parameters and carries the token only in the fragment;
successful verification removes it from those links. Vue requires confirmation before posting JSON
`{"token":"..."}` to `/api/account-verifications`. This avoids consuming links
through ordinary mail scanner GET requests. The user record stores only SHA-256
hashes and atomically consumes unexpired tokens; invalid, expired, and reused
tokens return 422. Successful verification returns 200 and does not log in the
user automatically. Browser messages use the translation catalogs; email
translations are maintained in the configured SendGrid templates.

`VerificationEmailSender` is the enqueue boundary. To replace this integration,
implement that interface and change its service alias in `config/services.yaml`.
The registration service and API do not depend on a transport implementation.
The registration view uses the same semantic Twig theme adapter as login; theme
replacement does not require changing Vue requests or backend behavior.

Registration checks (inside the PHP container unless stated otherwise):

```sh
php vendor/bin/simple-phpunit tests/Functional/RegistrationTest.php
XDEBUG_MODE=coverage php vendor/bin/simple-phpunit --coverage-text --coverage-clover var/coverage.xml
php -d xdebug.mode=off vendor/bin/phpstan analyse --no-progress --memory-limit=512M
php bin/console lint:twig templates
# On a host with Node.js:
node --test tests/frontend/*.test.js
```

## SendGrid dynamic template queue

`Mail/SendGrid/Message/DynamicTemplateEmail` extends Symfony's `Email` and stores
its template ID and JSON variables in internal MIME headers, which Symfony
serializes without overriding internal methods. Its empty MIME body allows
validation and profiler rendering; no `content` or `subject` is sent to SendGrid.
Local subjects, text, HTML, and attachments are explicitly rejected to avoid
silently discarding content. The transport supports To, Cc, Bcc and one Reply-To;
exactly one From address is required. The delivery envelope controls recipients,
including development overrides. Cc/Bcc-only envelopes use one personalization
per recipient so no hidden recipient is disclosed to another. Duplicate
addresses are sent once, with To taking precedence over Cc and Bcc. The visible
From and its display name come from the email, independently of the envelope
sender. Custom SMTP bounce addresses are not sent to the API; SendGrid manages
bounce handling through its authenticated domain configuration.
These internal template headers are converted to API fields, not forwarded as
email headers. Additional custom headers are not part of this transport's API.

`Mail/SendGrid/Transport/SendGridTransport` extends `AbstractTransport`, preserving
`MessageEvent`, `SentMessageEvent`, `FailedMessageEvent`, and the provider message
ID. Its factory registers `sendgrid+dynamic://KEY@default`. API credentials are
resolved from the DSN by the worker and are not stored in queued messages.
The official `symfony/sendgrid-mailer` bridge is no longer required. Mailer,
HttpClient, Messenger and Doctrine Messenger remain required dependencies.

`SendGridVerificationEmailSender` uses `SendGridTemplateProvider` to select the
locale-specific template and calls `MailerInterface`. Messenger routes Symfony's
`SendEmailMessage` to Doctrine. There is no application-specific queue message or
handler. All messages submitted to the configured Mailer follow this routing;
this transport only accepts `DynamicTemplateEmail` instances.

To send another email using the configured template provider, inject
`MailerInterface` and `SendGridTemplateProvider`:

```php
use Nurschool\Mail\SendGrid\Message\DynamicTemplateEmail;

$email = (new DynamicTemplateEmail(
    $templates->getTemplateId($locale),
    ['url' => $verificationUrl, 'ttl' => $ttl],
))->from($sender)->to($recipient);

$mailer->send($email);
```

The template defines the translated subject and complete content. The caller
supplies no local HTML, text or subject. If adding other template families,
extend the provider's configuration rather than embedding template IDs in mail
senders.

Install dependencies and apply migrations before accepting registrations. The
queue uses `doctrine://default` with `auto_setup: false` so DDL cannot implicitly
commit a registration transaction. Migration `Version20260923123000` creates
`messenger_messages`, shared by the `sendgrid` and `failed` queues.

```sh
composer install
php bin/console doctrine:migrations:migrate
php bin/console messenger:consume sendgrid --time-limit=3600 --memory-limit=128M
```

In this Docker environment, prefix console commands with
`docker exec nurschool-php`. Run the worker under Supervisor, systemd, or another
process manager in production, configured to restart it when it exits. Run
`php bin/console messenger:stop-workers` after deployment so workers reload code
and configuration. No worker is started automatically by this repository.

Network failures, HTTP 408/429, and HTTP 5xx receive at most three retries with
exponential delays starting at one second. Other rejected responses (including
400/401/403) move directly to `failed`. A missing API key in the DSN prevents
transport initialization. Only HTTP 202 counts as acceptance; it does not
guarantee inbox delivery. Failures are Mailer `TransportException` instances;
permanent failures also
implement Messenger's `UnrecoverableExceptionInterface`. Temporary failures do
not implement `RecoverableExceptionInterface`, so the configured retry limit
continues to apply. Exceptions omit HTTP body and credentials. Inspect and retry
failed messages after fixing the cause:

```sh
php bin/console messenger:failed:show --transport=failed
php bin/console messenger:failed:retry --transport=failed
```

Doctrine/Messenger provides at-least-once delivery. A worker crash after SendGrid
accepts a message but before acknowledgement can cause a duplicate email. Queue
rows include the recipient and template variables, including the verification
link token; restrict database and failure-queue access and remove obsolete failed
messages according to your retention policy. Successful messages are removed on
acknowledgement. The token expiration starts at registration, not worker delivery;
retrying a message after expiration does not renew its link.

Targeted queue checks:

```sh
php vendor/bin/simple-phpunit --filter 'DynamicTemplate|SendGrid|RegistrationTest'
```

When upgrading from the earlier application-specific queue message, stop new
registrations and drain both its pending and failed messages using the old code
before deploying this refactor. Existing serialized `SendDynamicTemplateEmail`
rows cannot be consumed after its class is removed; the queue schema itself is
unchanged. Do not delete pending email rows as part of deployment.

### Messenger migration regression test

`Version20260923123000` queues explicit MariaDB `CREATE TABLE` and `DROP TABLE`
statements through `addSql()`. `MessengerMigrationTest` runs Doctrine's real
`MigrateCommand` against a disposable MariaDB database, checks version tracking,
queue persistence with `auto_setup: false`, transaction rollback, and reversal.
SQLite queue tests use transport setup separately because production DDL targets
MariaDB. Without `MESSENGER_MIGRATION_TEST_HOST`, the MariaDB test is skipped.

To run it using the local Docker environment (wait for the health check to pass):

```sh
docker run -d --rm --name nurschool-migration-test --network nurschool_default --tmpfs /var/lib/mysql -e MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 -e MARIADB_DATABASE=nurschool_migration_test -e MARIADB_USER=migration_test -e MARIADB_PASSWORD=migration_test mariadb:11.4
docker exec nurschool-migration-test healthcheck.sh --connect --innodb_initialized
docker exec -e MESSENGER_MIGRATION_TEST_HOST=nurschool-migration-test nurschool-php php -d xdebug.mode=off vendor/bin/simple-phpunit tests/Integration/MessengerMigrationTest.php
docker stop nurschool-migration-test
```

The fixed test credentials apply only to the disposable database. No application
connection or data is used. Editing an already recorded migration does not cause
Doctrine to execute it again; an installation missing the table requires an
explicit deployment repair rather than merely rerunning `migrate`.

## Social sign-in (Google and Facebook)

Both `/login` and `/register` mount a shared Vue interface from
`templates/security/social_login.html.twig`. Vue starts the flow with a same-origin
JSON POST to `/api/oauth/{provider}/start`. API Platform owns both this operation
and the GET `/api/oauth/{provider}/callback`; both set `openapi: false` and are
excluded from public OpenAPI documentation. They remain reachable by browsers.
Symfony Security handles the callback, rotates the authenticated session, applies
the existing user checker, and redirects to `nurschool_home`.

Configure the following environment variables privately (for example in `.env.local`):

- `OAUTH_BASE_URL`: canonical public origin, HTTPS in production; never derived from the request Host header.
- `OAUTH_GOOGLE_CLIENT_ID` and `OAUTH_GOOGLE_CLIENT_SECRET`: Google web application credentials.
- `OAUTH_FACEBOOK_CLIENT_ID` and `OAUTH_FACEBOOK_CLIENT_SECRET`: Facebook application credentials.
- `OAUTH_FACEBOOK_VERSION`: Graph API version enabled for the application (default `v23.0`).

Register these exact authorized redirect URIs with the respective provider,
replacing the origin with `OAUTH_BASE_URL`:

- `https://your-domain.example/api/oauth/google/callback`
- `https://your-domain.example/api/oauth/facebook/callback`

Enable Google email/OpenID scopes and Facebook Login with the email permission.
Configure consent screens, application domains, test users, and production access
in the provider dashboards. Facebook users must grant email access and have an
email available. Missing email, unverified Google email, denied consent, expired
state, and provider/network failures all return a translated retry message.
See [Google's web-server flow](https://developers.google.com/identity/protocols/oauth2/web-server)
and [Facebook's manual flow](https://developers.facebook.com/docs/facebook-login/guides/advanced/manual-flow/).

Run `php bin/console doctrine:migrations:migrate` before enabling sign-in. The new
`social_identity` table identifies accounts by provider and subject, independently
of later email changes. New users receive only `ROLE_USER`, are marked verified,
and receive an unknowable random hashed local password. No access or refresh
tokens are persisted. An email collision with an existing local or other-provider
account is rejected: users must use their original sign-in method. Linking existing
accounts is intentionally outside this flow and would require reauthentication.

The session binds one pending attempt to a provider and random single-use state
with a ten-minute expiry. Starting another attempt replaces the previous one.
Failures return to `/login?oauth_error=1`, where both social buttons and password
login remain available. Provider error details and account existence are not exposed.

No additional packages are needed: the implementation uses the installed Symfony
HttpClient/Security and API Platform components. Presentation classes remain in the
shared Twig theme adapter; changing that adapter and its stylesheet leaves OAuth
and Vue data loading unchanged. Automated tests use Symfony MockHttpClient and a
disposable SQLite database, never live provider credentials.

Validation commands (run inside the PHP container from `/var/www/nurschool`):

```sh
php -d xdebug.mode=off vendor/bin/simple-phpunit --filter 'SocialLoginTest|OAuthFlowTest|ProviderClientTest'
php -d xdebug.mode=coverage vendor/bin/simple-phpunit --coverage-clover var/oauth-coverage.xml
php -d xdebug.mode=off vendor/bin/phpstan analyse --no-progress
```

Run `node --test tests/frontend/*.test.js` for the frontend suite.

### Adding an OAuth provider

`ProviderClient` orchestrates HTTP calls and validates the common identity shape.
It discovers services implementing `Nurschool\OAuth\Provider\ProviderInterface`
through Symfony's `nurschool.oauth_provider` autoconfiguration tag. Provider names
must be unique. Google and Facebook own their credentials, endpoints, scopes,
request parameters, and profile-specific trust checks in separate services.

To add another network:

1. Implement `ProviderInterface` in a service under `src/OAuth/Provider`. Return a
   stable `name()`, construct the authorization URL, describe token/profile calls
   with `ProviderRequest`, and map the provider response to `subject` and `email`.
   Validate any provider-specific verification claims before returning the identity.
2. Inject that service's configuration and secrets in `config/services.yaml`.
   Symfony registers implementations automatically; do not edit `ProviderClient`.
3. Add the provider name to the allowed route requirements in
   `src/ApiResource/SocialLogin.php`, register its callback with the provider, and
   add its translated button to the shared social-login template when exposing it.
4. Test its requests and trust checks with fake HTTP responses. The client test
   includes a third provider with different response fields and HTTP options.

`ProviderRequest` supports provider-specific methods and Symfony HttpClient options,
including JSON/form bodies, headers, and query parameters. The shared client always
applies its timeout and redirect safeguards. Credentials and tokens stay server-side.
