# VS Code with Docker

Open the repository root in a local VS Code window and run **Developer: Reload
Window** after installing the recommended extensions. Keep the Compose services
running (`docker compose up -d`). The VS Code user must be able to run
`docker compose exec -T nurschool-php php -v` without sudo.

The workspace settings use `bin/php-docker` as the PHP executable. A container's
`/usr/local/bin/php` is not a host executable, and `php.validate.executablePath`
does not accept a shell command such as `docker exec ...`.
The adapter maps repository paths to `/var/www/nurschool`, preserves stdin and
exit codes, runs as the host UID/GID, and disables Xdebug for editor processes.
It requires Bash, Python 3 and Docker Compose on Linux/macOS. It does not start containers.
Twiggy's external PHP reflection helpers are copied into ignored `var/editor/`
so they can be executed through the existing bind mount. JSON paths returned by
these helpers are translated back to the local workspace.

## Extensions and navigation

Install the workspace recommendations in `.vscode/extensions.json`:

- **Intelephense** provides PHP completion and Ctrl+click/F12 on class names,
  imported classes and methods. Keep Composer dependencies installed so `vendor/`
  is indexed. A namespace segment alone does not necessarily identify a file.
- **ESLint** checks application JavaScript on save using `eslint.config.js`.
  The vendored Vue distribution and generated bundle assets are excluded.
- **Twiggy** provides Twig language support and Twig-CS-Fixer diagnostics for
  saved files. Formatting on save is disabled as recommended by Twiggy. Its
  Symfony metadata comes from the Docker-backed console.
- **Twig Pathfinder** provides Ctrl+click/F12 on literal route names inside
  `path('app_home')` / `url(...)`, and on template references. Dynamic route
  expressions may not be resolvable statically.

Use **Intelephense: Index workspace** if PHP definitions are missing. For Twig,
check **Output > Twiggy Language Server**. For JavaScript, check **Output > ESLint**.
Extensions that execute project tools require a trusted workspace. ESLint uses
VS Code's extension runtime; the host does not need Node just for editor linting.

## Dependencies and checks

Install PHP development dependencies with Composer inside the container.
For command-line JavaScript checks or reinstalling packages, use Node 22.13+
(or Node 24 LTS) and pnpm. The current setup was verified with pnpm 11.

```sh
bin/php-docker /usr/bin/composer install
pnpm install --frozen-lockfile
pnpm lint
pnpm test
python3 -m unittest discover -s tests/tooling -v
bin/php-docker -l src/Kernel.php
bin/php-docker bin/console lint:twig templates
bin/php-docker vendor/bin/twig-cs-fixer lint
bin/php-docker -d sys_temp_dir=/var/www/nurschool/var vendor/bin/phpstan analyse --no-progress
bin/php-docker vendor/bin/simple-phpunit
```

The PHPStan command avoids an existing `/tmp/phpstan` cache that may belong to
another container user. The configuration retains the project's analysis level.
PHPUnit integration tests may require the dedicated database fixtures described
in the main README. The repository does not contain `bin/phpunit`.

**Terminal > Run Task** exposes PHP lint for the current saved file, full Symfony
Twig syntax validation, Twig style checks (with entries in Problems), and PHPStan.
PHP and JavaScript diagnostics run on save; Twiggy also runs Twig-CS-Fixer against
saved files. Symfony's `lint:twig` task is the authoritative check for registered
Twig extensions and functions. No tool automatically reformats application files.

Only the shared settings, tasks and recommendations are tracked under `.vscode/`.
Other local editor files remain ignored. If opening the folder inside a Dev
Container instead, change the PHP executable settings to `/usr/local/bin/php` in
that environment; the host adapter is intended for the local VS Code window.

References: [VS Code PHP validation](https://code.visualstudio.com/docs/languages/php),
[Twiggy](https://github.com/moetelo/twiggy), and
[Twig Pathfinder](https://marketplace.visualstudio.com/items?itemName=Shifumi-dev.vscode-twig-open-include).
