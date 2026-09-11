# Docker production deployment

This deployment is isolated under `/srv/apps/printer/admin01` and publishes the application only on host loopback port `127.0.0.1:18082`. Existing host Nginx is the only intended entry point.

## Production layout

- Project: `/srv/apps/printer/admin01/projects/printersystem`
- Persistent MySQL: `/srv/apps/printer/admin01/data/mysql`
- Persistent Laravel storage: `/srv/apps/printer/admin01/data/storage`
- Host reverse proxy template: `docker/nginx/printersystem-host.conf`
- Queue unit template: `docker/systemd/elegansky-printer-worker.service`

The Compose project name is `elegansky-printer`; it does not reuse another application's containers, network, database or volumes.

Frontend assets are built and verified before the production release is pushed, so the VPS does not repeat npm downloads. Image builds use the host network only while downloading locked Composer dependencies. Runtime services remain on the isolated `printer-internal` Docker network, and only the web container publishes `127.0.0.1:18082`.

## First deployment

Clone the Laravel-only production branch directly into the required directory, then create the untracked production environment:

```bash
cd /srv/apps/printer/admin01/projects
git clone --branch production-printersystem --single-branch https://github.com/RogerEugen/Printing-service.git printersystem
cd printersystem
./docker/scripts/create-production-env.sh
```

The script refuses to overwrite an existing `.env`, generates independent strong values for `APP_KEY`, `DB_PASSWORD`, and `MYSQL_ROOT_PASSWORD`, and does not print them. Edit `.env` interactively on the server to review the non-secret URL/settings. Keep `APP_DEBUG=false`.

The privileged deployment operator then runs:

```bash
cd /srv/apps/printer/admin01/projects/printersystem
docker compose config --quiet
docker compose build --pull
docker compose up -d db storage-init app web
docker compose run --rm app php artisan migrate --force
docker compose run --rm app php artisan optimize:clear
docker compose run --rm app php artisan optimize
```

Do not run the test suite against the production database. Tests must pass locally or against an isolated test database before deployment.

Create the first production administrator through hidden password prompts; the password is never passed as a command argument:

```bash
docker compose run --rm app php artisan app:create-admin admin01
```

## Host Nginx and queue worker

The committed host Nginx file is a new HTTPS site; it does not replace an existing site. It expects the DNS-01 certificate at `/etc/letsencrypt/live/printer.eleganskyfinance.com`. Confirm that certificate and the internal DNS override before enabling it. Validate the whole Nginx configuration before reload:

```bash
sudo install -o root -g root -m 0644 docker/nginx/printersystem-host.conf /etc/nginx/sites-available/printersystem
sudo ln -s /etc/nginx/sites-available/printersystem /etc/nginx/sites-enabled/printersystem
sudo nginx -t
sudo systemctl reload nginx
```

Install the worker unit, then enable only that unit:

```bash
sudo install -o root -g root -m 0644 docker/systemd/elegansky-printer-worker.service /etc/systemd/system/elegansky-printer-worker.service
sudo systemctl daemon-reload
sudo systemctl enable --now elegansky-printer-worker.service
```

The host systemd service needs Docker daemon access, but the queue process inside its container runs as the non-root `www-data` user.

## Verification

```bash
docker compose ps
curl --fail --silent --show-error http://127.0.0.1:18082/up
docker compose run --rm app php artisan about
docker compose run --rm app php artisan migrate:status
docker compose run --rm app php artisan route:list
systemctl status elegansky-printer-worker --no-pager
nginx -t
```

Confirm separately that Nginx, WireGuard, Samba AD, dnsmasq and existing company applications remain healthy. Do not restart or reconfigure them for these checks.

## Application updates

```bash
cd /srv/apps/printer/admin01/projects/printersystem
git pull --ff-only
docker compose build --pull
docker compose run --rm app php artisan migrate --force
docker compose run --rm app php artisan optimize:clear
docker compose run --rm app php artisan optimize
docker compose up -d --no-deps app web
sudo systemctl restart elegansky-printer-worker.service
```

Back up the dedicated MySQL data and Laravel storage together before migrations. Never use `migrate:fresh`, `db:wipe`, broad recursive deletion, or globally writable permissions.
