# AskOnce

Ask once. Stop chasing.

Laravel 13 / Blade / Tailwind / Alpine application. Product requirements live in [spec.md](spec.md).

## Implemented milestone

- Registration creates one organization with an owner and a 100 MB storage quota.
- Sign-in, sign-out, email verification and password reset.
- Tenant-scoped client directory with creation and editing.
- Five-type request builder, request progress dashboard and business review.
- Secure, revocable client links; per-item native-fetch autosave and partial submission.
- Private multi-file uploads, replacements, quota checks and authorized downloads.
- Request activation, email sending, completion, cancellation, reopening and deletion.
- Local demo admin/user shortcuts, an admin overview and seeded customers/requests.
- Docker services for FrankenPHP, PostgreSQL, Redis, Horizon, scheduler and local Mailpit.

Automatic reminders run every 15 minutes, send in the organization’s morning, and stop after completion, cancellation, expiry, unsubscribe, a reported hard bounce, or five reminders. Business updates appear in a tenant-scoped inbox and are queued for email. Item saves are batched for 30 minutes, or notified on Submit. Durable delivery claims suppress duplicate jobs; ambiguous SMTP failures pause delivery for review. No provider bounce webhook is configured; businesses can report a bounced address in the request screen.

## Local development

Requires PHP 8.4+, Composer and Node 24+.

```sh
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm ci
npm run build
php artisan serve
# In separate terminals:
php artisan queue:work
php artisan schedule:work
```

To populate local demo accounts and customers, run `php artisan db:seed`. The seeder is idempotent and refuses production. It creates `admin@askonce.test` and `demo@askonce.test` (password: `password`), with a separate organization, three sample customers and three requests for each account. One-click shortcuts appear on the local login page; the server rejects shortcut login in every other environment. Customers use request links without logging in.

Open http://127.0.0.1:8000. Local defaults use SQLite and log mail. Verification and password-reset URLs appear in `storage/logs/laravel.log`. No external email is sent by default.

```sh
php artisan test
vendor/bin/pint
```

## Docker development

Start Docker first. Copy `.env.docker.example` to `.env.docker`, fill `APP_KEY` (from `php artisan key:generate --show`) and set `DB_PASSWORD`. Add the same value as `POSTGRES_PASSWORD` in `.env.docker` for Compose interpolation. These files must not be committed.

```sh
docker compose --env-file .env.docker build
docker compose --env-file .env.docker up -d
docker compose --env-file .env.docker exec app php artisan migrate --force
docker compose --env-file .env.docker exec app php artisan db:seed --force
```

App: http://localhost:8080. Development inbox: http://localhost:8025. Ports bind to loopback; PostgreSQL and Redis are internal only.

Use external SMTP, `APP_ENV=production`, `APP_DEBUG=false` and an HTTPS `APP_URL` for deployment. Horizon access is closed outside local development. Configure off-site database and upload backups and prove a restore before beta; Use `php artisan askonce:backup` for a checksummed database and upload archive. Daily backups are opt-in with `BACKUPS_ENABLED=true`; `BACKUP_DISK` selects a separately configured private filesystem destination. Archive creation is not a transactional snapshot across the database and filesystem: quiesce writes for a restore-grade capture. Local copies alone are insufficient. Keep `APP_KEY` separately so encrypted request links remain readable after a restore. Privacy and beta terms pages still need operator details and legal review before public launch.

## Organization boundary

`CurrentOrganization` is a request-scoped service populated after authentication and before route binding. `Client` has a global organization scope and stamps the active organization on creation; supplied organization IDs cannot be mass-assigned. Reads without context fail closed. Resource updates also use an ownership policy. Future business models must use the same boundary, with explicit separate token authorization for public client requests.

Docker PHP upload limits are 50 MB per file and 100 MB per POST. For other local PHP servers, configure the same limits in PHP’s ini. Client upload batches are limited to 90 MB, with MIME and extension validation on the server. Public pages load only self-hosted assets.
