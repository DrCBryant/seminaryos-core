# Codex Cloud development runtime

This setup is for development only. It does not alter Composer dependencies,
registrar rules, production records, or ICDSoft deployment requirements.
PR #3 remains draft until review, even after local verification.

## Provisioning findings

The attached image is Debian 13 (trixie), Linux x86_64, running as `agent`
(uid 1000). PHP and Composer were initially absent. Neither `sudo` nor
`/usr/local/bin/cloud-agent-packages` exists. System directories are not writable
by this user. `apt-get -s install php8.5-cli` reports `Unable to locate package
php8.5-cli`; the configured repositories are Debian trixie snapshots. Do not
modify sudo, sandbox policy, or system package repositories to work around this.

The available Cloud tools expose environment status and diagnostics only. No
setup-script, configuration-draft, request-changes, save, or publish tool is
callable in this session. Status reports `source_config_id: null` and
`source_config_version_id: null`; this is not a published reusable environment.
A file in the repository does not register an initialization hook by itself.

A user-owned standalone runtime works without root or Docker. The pinned
[static-php-cli build](https://dl.static-php.dev/static-php-cli/bulk/)
is PHP 8.5.8, and [official Composer](https://getcomposer.org/download/) is
2.11.1. These are development tools, not application dependencies.
The setup script pins SHA-256 checksums and verifies downloads before execution.
The PHP checksum was recorded from the initial HTTPS download, not from an
independent signed release manifest. Composer's checksum was also compared with
the publisher's `composer.phar.sha256sum` endpoint. Updating either pinned
artifact requires review; the PHP pin is not a claim to be the latest 8.5 patch.

## Reproducible initialization

From the repository root, run:

```bash
bash .agents/setup-cloud.sh
composer install --prefer-dist --no-interaction --no-progress
composer check-platform-reqs
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --no-interaction
fi
```

The script installs runtime files under `/workspace/seminaryos-runtime` and
launchers under `$HOME/.local/bin`, already on PATH in this image. It verifies
PHP 8.5, required loaded extensions, and an actual PDO SQLite connection.
It does not create `.env`, migrate, seed, upgrade dependencies, or deploy.
It can be rerun using the verified download cache without fetching artifacts
again. It refuses to overwrite unrelated `php` or `composer` launchers.

`SEMINARYOS_RUNTIME_DIR` and `SEMINARYOS_BIN_DIR` override the two installation
locations. If the latter changes, put it on PATH explicitly in task shells.
The Composer launcher uses a workspace cache. The PHP launcher propagates
`PHPRC` to Composer/Artisan child processes and sets a 512 MB development memory
limit without disabling TLS certificate verification or changing proxy settings.

This particular bulk build bundles `intl`, XML, mbstring, zip, sqlite3, pcntl,
and the other required extensions. Its PDO SQLite driver is provided through
the bundled Swoole SQLite support: `PDO::getAvailableDrivers()` includes
`sqlite`, while `php -m` does not list a separate `pdo_sqlite` module. The setup
therefore verifies a real SQLite query instead of relying on that module name.
The build includes other unused extensions; no Redis or Swoole server is started
and SeminaryOS does not acquire a production dependency on them. Prefer native
PHP 8.5 packages with ordinary `pdo_sqlite` if a future supported image supplies
them.

In this image sandboxed network commands cannot reach the inherited proxy
(`curl error 7`, proxy port 8080). Approved execution outside the command sandbox
can reach it. Use the platform's normal command escalation for network-dependent
initialization; do not change proxy variables, bypass TLS, or embed credentials.
The preview server also needs normal command escalation in this image to bind
port 8000. Offline PHP, Composer platform checks, and tests run in the ordinary
sandbox. Node 24 requires `NODE_USE_ENV_PROXY=1` for the existing fonts build
plugin to honor the inherited proxy; this was verified without changing Vite
configuration or adding dependencies.

## Persistence across tasks

OpenAI's [Cloud environments documentation](https://developers.openai.com/codex/cloud/environments/)
describes manual setup scripts, optional maintenance scripts on cached resumes,
and container caching for up to 12 hours. Setup runs in a separate Bash session:
an `export PATH=...` there alone does not persist into the agent phase. The
user PATH launchers avoid that problem on this image. These documented product
features do not establish that this particular attached instance has been
configured or published.

Verified installation in the current filesystem is distinct from a saved Cloud
configuration. Existing launchers make `php` and `composer` available to fresh
command shells on this instance. Persistence after environment replacement or
snapshot publication has not been established.

When the Cloud settings/onboarding UI offers an initialization command, configure
it to run the commands above from the repository root. Use the same commands in
the optional maintenance script to refresh locked dependencies after a cached
resume. Ensure the selected checkout actually contains this PR's setup script;
it is not yet on the default branch while PR #3 is draft. Preserve existing settings.
Ensure its PATH includes `$HOME/.local/bin`, and allow HTTPS downloads from
`dl.static-php.dev`, `getcomposer.org`, Packagist, and GitHub dependency archive
hosts as needed. Keep the platform's CA/proxy configuration intact. Save and
publish the environment through that UI, then verify a new task with:

```bash
php -v
composer --version
composer check-platform-reqs
composer test
```

That UI configuration and a new-task validation remain proposed until performed;
the agent cannot publish them with the currently available tools. If no reusable
initialization hook exists, agents should run the idempotent script when PHP or
Composer is absent. This requires no repeated hand-written installation steps.
The lowest-cost fallback is this existing image plus the rootless script; if
third-party PHP builds are unsuitable, keep the existing GitHub Actions test
workflow and use a local PHP 8.5 development installation rather than buying
production infrastructure. A supported preconfigured Cloud PHP image is another
option if offered, but its availability and price are not verified here.

## Disposable tests and browser preview

For a fresh checkout, create the ignored local environment without overwriting
an existing file, then generate a local application key:

```bash
php -r "file_exists('.env') || copy('.env.example', '.env');"
php artisan key:generate --no-interaction
composer test
```

PHPUnit forces SQLite `:memory:` and testing cache/session/mail/queue settings.
No manual test migration or seed is needed. Do not point tests at preview or
production records.

For preview, create a new ignored `database/preview.sqlite` and set the local
`.env` to `APP_ENV=local`, `DB_CONNECTION=sqlite`, empty `DB_URL`, and the absolute
path to that preview file as `DB_DATABASE`. Set `CACHE_STORE=file`,
`SESSION_DRIVER=file`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=log`, and the actual
local preview origin as `APP_URL`. Verify those settings before running:

```bash
php artisan migrate --no-interaction
npm install --ignore-scripts --package-lock=false
NODE_USE_ENV_PROXY=1 npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Loopback binding is sufficient for the shared Cloud browser. Use `0.0.0.0` only
if the platform's authenticated port-forwarding feature requires it; do not
create a public tunnel or deploy a preview. Opening the shared browser is not
proof that a separate authenticated port-forwarding URL exists. `/admin` should
redirect an unauthenticated visitor to `/admin/login`. A successful protected
panel login requires a synthetic local user; do not create registrar records or
use production credentials to obtain one.

## Verified results on this Cloud instance (2026-11-08)

The installer and its cached rerun succeeded. Fresh ordinary command shells
resolved both launchers without exporting a new PATH. Actual output included:

```text
PHP 8.5.8 (cli) (built: Jul  7 2026 05:18:24) (NTS gcc 14.2.0)
Built by static-php-cli 2.8.6
Composer version 2.11.1 2026-10-30 14:52:10
PDO SQLite 3.45.2
```

- `composer install --prefer-dist --no-interaction --no-progress`: succeeded
  through the Cloud proxy; 145 locked packages, no dependency updates.
- `composer check-platform-reqs`: exit 0; all installed platform requirements
  reported success, including PHP 8.5.8, intl, XML, and zip.
- `composer test` inside Codex Cloud: passed, 51 tests, 235 assertions.
- `NODE_USE_ENV_PROXY=1 npm run build`: succeeded. The existing fonts plugin
  reported that optional `fontaine` is absent; no package was added.
- A new, ignored preview SQLite database migrated successfully without seeds.
  The loopback Laravel server started on port 8000 after normal command escalation.
- HTTP: `/` returned 200; `/admin` returned 302 to `/admin/login`; the login
  page returned 200.
- Playwright with installed Chromium followed the admin redirect and verified
  one email input, one password field, and one Sign in button. A screenshot was
  visually inspected. Chromium's initial dump-DOM attempts stalled; Playwright's
  DOM-ready navigation succeeded.
- No authenticated external port-forward URL or signed-in application session
  was verified. No synthetic users or registrar records were created. Shared
  desktop capability is advertised but its state is unknown and no desktop
  control tool is callable here; browser verification used headless Chromium.
- `bash -n .agents/setup-cloud.sh` and `git diff --check`: passed.

Reusable environment settings remain proposed, not applied or published.
