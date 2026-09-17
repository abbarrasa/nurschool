# nurschool-monolithic

Assistant to the nursing service for schools written in PHP.

Nurschool is a nursing service assistant for schools. It provides tools for the management of medical files of students and communication between nurses and students' tutors. It also provides tools such as forums and blogs for the diffusion of the nursing function at school in order to generate a community around it.

Nurschool is a PHP project  based on a Symfony 6 and API Platform implementation.


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

### Local MariaDB setup

When using Docker Compose, set the host in `.env`'s `DATABASE_URL` to
`nurschool-database` instead of `127.0.0.1` (which points at the PHP container).
After changing it, recreate PHP and apply the initial migration:

```sh
docker compose up -d --no-deps nurschool-php
docker exec nurschool-php php bin/console doctrine:database:create --if-not-exists
docker exec nurschool-php php bin/console doctrine:migrations:migrate --no-interaction
docker exec nurschool-php php bin/console doctrine:schema:validate
```

The initial migration creates the user/role tables and four roles; it does not
create login accounts.

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
