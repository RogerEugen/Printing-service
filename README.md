# Elegansky Private Printing System

Laravel 13 + Blade application for private company document and image printing. Employees upload files, administrators manage users and printers, and a separately authenticated Windows agent performs physical printing.

For the VPS and Windows production checklist, see [`../DEPLOYMENT_GUIDE_SW.md`](../DEPLOYMENT_GUIDE_SW.md).

## Installation

Laravel and Breeze are already installed. Do not reinstall them.

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
```

Configure MySQL in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=eleganskyPrinterSystem
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
QUEUE_CONNECTION=database
```

Uploaded files use Laravel's private `local` disk under `storage/app/private/print-jobs`. Supported types are PDF, DOC/DOCX, XLS/XLSX, PPT/PPTX, TXT, RTF, JPG/JPEG, PNG, BMP, GIF, TIFF and WebP. Validation checks both the extension and detected content; Open XML Office files also have their archive structure checked. Do not run `storage:link` for print documents.

## Database and first administrator

Set a development-only password, migrate, and seed:

```dotenv
DEV_ADMIN_PASSWORD=replace-with-a-strong-development-password
```

```bash
php artisan migrate
php artisan db:seed
```

The username is `admin`. Remove `DEV_ADMIN_PASSWORD` from production configuration after initial provisioning. In production, create the first administrator through a secure deployment procedure or a one-time interactive command; never hard-code its password.

The seeder creates one development printer and prints its device token once in the terminal. Printers created in the admin UI also show their token once.

## Authentication and roles

Breeze authenticates with `username` and `password`; email registration, verification, and password-reset routes are disabled. Only administrators create accounts. Supported roles are exactly `admin` and `employee`.

- Employee dashboard: `/dashboard`
- Admin dashboard: `/admin/dashboard`
- Login: `/login`

## Printer API

Every request needs `Authorization: Bearer DEVICE_TOKEN`. Device tokens are generated randomly and only SHA-256 hashes are stored.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/api/printer/heartbeat` | Mark device online and update last seen time |
| GET | `/api/printer/jobs/next` | Atomically lock and claim the next assigned pending job |
| POST | `/api/printer/jobs/{job}/claim` | Idempotently confirm the claim |
| GET | `/api/printer/jobs/{job}/download` | Authorized private document/image download |
| POST | `/api/printer/jobs/{job}/printing` | Mark printing started |
| POST | `/api/printer/jobs/{job}/printed` | Mark printing successful |
| POST | `/api/printer/jobs/{job}/failed` | Record failure and `error_message` |

Agents only access jobs assigned to their authenticated printer. The claim operation uses a database transaction and `lockForUpdate()`.

## Running locally

```bash
php artisan config:clear
php artisan cache:clear
php artisan serve
npm run dev
```

Laravel's database queue is available for maintenance and notifications:

```bash
php artisan queue:work
```

The business print queue is the `print_jobs` table. Physical printing never depends on `queue:work`; it is handled by the Python agent.

## Verification

```bash
php artisan migrate
php artisan test
php artisan route:list
vendor/bin/pint --format agent
npm run build
```

This application is intended for the company private network/WireGuard. Terminate TLS at the existing private infrastructure, keep `.env` secret, back up MySQL and private storage together, and never expose printer agents or private document storage publicly.
