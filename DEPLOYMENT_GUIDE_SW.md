# Mwongozo wa Deployment: Elegansky Private Printing System

Mwongozo huu unatenganisha majukumu mawili:

- **VPS:** Laravel, MySQL, private document storage na printer API.
- **Windows print PC:** Python agent, Windows printer driver na printer ya ofisini.

Mfumo unatakiwa kupatikana kupitia private company network/WireGuard na HTTPS. Printer yenyewe haipaswi kuwekwa wazi kwenye internet. Usitumie `php artisan serve` kama production web server, na usibadilishe WireGuard au Nginx iliyopo bila msimamizi wa miundombinu.

## 1. Checklist kabla ya deployment

- Tengeneza backup ya MySQL na `storage/app/private/print-jobs` kama unahamisha data iliyopo.
- Pata private HTTPS URL, kwa mfano `https://print.company.internal`.
- Hakikisha Windows PC inaweza kufungua URL hiyo kupitia WireGuard/company network.
- Hakikisha VPS ina PHP 8.3+, PHP-FPM, Composer, MySQL client/server, Node/npm ya kujenga assets, na extensions za PHP zinazohitajika na Laravel/MySQL (`curl`, `dom/xml`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `zip`, na `intl`).
- Tengeneza MySQL database na database user maalumu; usitumie MySQL `root` kwenye application.
- Windows PC iwe na printer driver sahihi na iweze kuchapisha test page kabla ya kuwasha agent.

## 2. Kuweka Laravel kwenye VPS

Mfano huu unatumia `/var/www/elegansky-printer` kama project directory. Badilisha path kulingana na VPS yako.

```bash
cd /var/www/elegansky-printer/printersystem
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
cp .env.example .env
php artisan key:generate
```

Weka production values kwenye `.env`:

```dotenv
APP_NAME="Elegansky Print"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://print.company.internal

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=elegansky_printer
DB_USERNAME=elegansky_app
DB_PASSWORD=WEKA_PASSWORD_NDEFU_HAPA

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=database

FILESYSTEM_DISK=local
PRINT_MAX_UPLOAD_KB=20480
PRINT_MAX_COPIES=20
PRINTER_OFFLINE_AFTER_SECONDS=90
DEV_ADMIN_PASSWORD=
```

Sheria muhimu za `.env`:

- Usiiweke Git na usiitume WhatsApp/email kama plain text.
- `APP_KEY` izalishwe mara moja na ihifadhiwe kwenye backup ya siri; usiibadilishe kila deployment.
- `APP_DEBUG` lazima iwe `false` production.
- `DEV_ADMIN_PASSWORD` ibaki tupu production.
- Usitumie password ya `rootadmin` production; hiyo ilikuwa ya local testing tu.

Malizia database na Laravel caches:

```bash
php artisan migrate --force
php artisan config:clear
php artisan cache:clear
php artisan optimize
```

Web-server user anahitaji write permission kwenye `storage` na `bootstrap/cache`, lakini si project nzima. Usitumie `chmod -R 777`.

Nginx/hosting iliyopo ipelekwe kwenye:

```text
/var/www/elegansky-printer/printersystem/public
```

Msimamizi wa Nginx ahakikishe:

- request zote za Laravel zinaanzia `public/index.php`;
- PHP requests zinatumwa PHP-FPM 8.3 au mpya;
- HTTPS certificate ni trusted na Windows PC;
- upload/body limit ni angalau sawa na `PRINT_MAX_UPLOAD_KB` pamoja na PHP `upload_max_filesize` na `post_max_size`;
- HTTP inaelekezwa HTTPS;
- private URL/port 443 inapatikana kupitia company network/WireGuard tu.

Baada ya Nginx/PHP-FPM kuwekwa, kagua:

```bash
php artisan about
php artisan migrate:status
php artisan route:list
curl -I https://print.company.internal/login
```

Endesha `php artisan test` kwenye local/staging database **kabla** ya deployment, si dhidi ya production database. Ikiwa HTTPS inatamatishwa kwenye reverse proxy tofauti, Laravel aamini IP ya proxy hiyo tu na Nginx itume forwarded HTTPS headers sahihi; usiamini proxy zote bila sababu.

Laravel queue worker inaweza kuendeshwa kwa Supervisor/systemd kwa background maintenance, lakini si lazima kwa physical printing. Physical jobs zinachukuliwa moja kwa moja na Python agent. Baada ya kila code deployment tumia `php artisan queue:restart` ikiwa queue worker ipo.

## 3. Kutengeneza admin wa kwanza production

Usi-run development seeder production kwa sababu pia hutengeneza sample printer. Tumia Tinker ndani ya VPS:

```bash
php artisan tinker
```

Kisha, ukitumia password mpya yenye nguvu badala ya mfano huu:

```php
App\Models\User::query()->updateOrCreate(
    ['username' => 'admin01'],
    ['password' => 'WEKA-PASSWORD-NDEFU-MPYA', 'role' => App\Enums\UserRole::Admin, 'is_active' => true]
);
```

Toka kwa `exit`. Password itahashiwa na model; haitahifadhiwa plain text.

## 4. Kuandaa Windows print PC

### 4.1 Printer na applications

1. Install driver rasmi ya printer kwenye Windows.
2. Fungua **Settings > Bluetooth & devices > Printers & scanners**.
3. Print Windows test page.
4. Install SumatraPDF kwa PDF na images.
5. Kama kampuni itaprint DOCX/XLSX/PPTX, install Microsoft Office au application yenye Windows `printto` support na ujaribu kila format manually.
6. PowerShell inaweza kuonyesha jina halisi la queue:

```powershell
Get-Printer | Select-Object Name, PrinterStatus, PortName
```

Nakili jina kamili la printer litakalowekwa kwenye `DEFAULT_PRINTER`.

### 4.2 Copy na install agent

Copy folder ya `printagent` kwenda, kwa mfano:

```text
C:\EleganskyPrintAgent
```

Usicopy `venv`, `.env`, `downloads` au `logs` kutoka Ubuntu. Fungua PowerShell kwenye Windows:

```powershell
cd C:\EleganskyPrintAgent
py -m venv venv
.\venv\Scripts\python.exe -m pip install --upgrade pip
.\venv\Scripts\python.exe -m pip install -r requirements.txt
Copy-Item .env.example .env
```

Ikiwa command `py` haipo lakini `python` ipo, tumia `python -m venv venv` badala yake.

Kwenye Laravel admin:

1. Login kwa private production URL.
2. Nenda **Printers > Add Printer**.
3. Weka jina, Windows device/queue name na location.
4. Create printer na nakili device token inayoonyeshwa mara moja.
5. Usitumie token ya Ubuntu test agent kwa Windows production printer.

Hariri `C:\EleganskyPrintAgent\.env`:

```dotenv
PRINT_SERVER_URL=https://print.company.internal
PRINTER_TOKEN=WEKA_DEVICE_TOKEN_HAPA
PRINTER_NAME=OFFICE-PRINTER-01
CHECK_INTERVAL=5
REPORT_RETRY_INTERVAL=5
REQUEST_TIMEOUT=30
WINDOWS_JOB_TIMEOUT=300
WINDOWS_POLL_INTERVAL=2
DOWNLOAD_DIR=.\downloads
DEFAULT_PRINTER=Jina Kamili Kutoka Get-Printer
PRINT_METHOD=auto
SUMATRA_PATH=C:\Program Files\SumatraPDF\SumatraPDF.exe
CLEANUP_DOWNLOADS=true
VERIFY_TLS=true
LOG_LEVEL=INFO
LOG_FILE=.\logs\printagent.log
```

Kwa PDF na images pekee unaweza kutumia `PRINT_METHOD=sumatra`. Tumia `auto` ikiwa pia una documents za Office. `VERIFY_TLS=false` ni ya test ya muda mfupi tu; production ibaki `true`.

### 4.3 Connectivity na printing test

```powershell
Invoke-WebRequest https://print.company.internal/login -UseBasicParsing
cd C:\EleganskyPrintAgent
.\venv\Scripts\python.exe -m unittest discover -s tests -v
.\venv\Scripts\python.exe agent.py --once
```

`--once` ikikubali, acha agent ikiendelea:

```powershell
.\run_agent_windows.cmd
```

Kisha login kama employee, upload PDF ndogo, chagua Windows printer mpya na uthibitishe:

- agent log inaonyesha job imechukuliwa;
- karatasi imetoka mara moja tu;
- web status imekuwa `Printed`;
- admin printer status imekuwa `Online`.

## 5. Kuwasha agent automatically kwenye Windows

Fungua **Task Scheduler > Create Task**:

- **Name:** `Elegansky Print Agent`
- **User:** dedicated low-privilege Windows account inayoweza kutumia printer
- **Trigger:** `At log on` ya account hiyo (rahisi na salama kwa mwanzo)
- **Action / Program:** `C:\Windows\System32\wscript.exe`
- **Add arguments:** `"C:\EleganskyPrintAgent\run_agent_windows_hidden.vbs"`
- **Start in:** `C:\EleganskyPrintAgent`
- Washa `Restart the task if it fails`.
- Zima setting ya kusimamisha task baada ya muda mfupi.

Anza kwa **Run only when user is logged on**, hasa kama SumatraPDF imewekwa kwenye profile ya user au Office `printto` inatumika. Hidden launcher inasubiri Python process, hivyo Task Scheduler inaendelea kuonyesha `Running`, inaweza kugundua process failure, na haitoi console ambayo mtumiaji anaweza kufunga kwa bahati mbaya. Baada ya unattended test kufaulu, unaweza kutathmini `Run whether user is logged on or not` kwa dedicated account hiyo. Usitumie Administrator account isipokuwa driver inahitaji na sababu imeandikwa.

Windows Firewall haihitaji inbound port kwa agent: agent hutuma outbound HTTPS kwenda VPS. Usifungue printer port kwenye public internet.

## 6. Utaratibu wa deployment updates

Kabla ya update, weka app maintenance mode na backup data:

```bash
cd /var/www/elegansky-printer/printersystem
php artisan down
```

Baada ya ku-copy/pull release iliyojaribiwa:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan up
```

Kwa Windows agent update:

1. Stop Task Scheduler task.
2. Backup `.env` pekee sehemu salama.
3. Replace source files bila kufuta `.env`.
4. Run `venv\Scripts\python.exe -m pip install -r requirements.txt`.
5. Run tests na `agent.py --once`.
6. Start task tena.

## 7. Backup, monitoring na security checklist

- Backup MySQL na `storage/app/private/print-jobs` pamoja; database pekee haitoshi kurejesha documents.
- Linda `.env`, MySQL credentials, `APP_KEY`, device tokens na backups.
- Kila physical printer/agent iwe na token yake.
- Device token iliyowahi kubandikwa kwenye chat au screenshot ichukuliwe kama imevuja: usiitumie production; disable record yake na tengeneza token/printer mpya.
- Token ikipotea au kuonekana kwa mtu asiyehusika, disable printer record ya zamani na tengeneza printer/token mpya.
- Kagua `storage/logs/laravel.log` kwenye VPS na `logs/printagent.log` kwenye Windows.
- Hakikisha Windows clock na VPS clock ziko sahihi.
- Usifute failed job bila kukagua kama karatasi ilitoka, ili kuepuka duplicate printing.
- Weka retention/cleanup policy ya private uploaded files kulingana na sera ya kampuni.
- Badilisha password ya local test `admin01/rootadmin` kabla ya production.

## 8. Mpangilio wa kufanya deployment siku yenyewe

1. Backup na verify VPS prerequisites.
2. Deploy Laravel, build assets na configure production `.env`.
3. Configure existing HTTPS/private Nginx route na permissions.
4. Run migrations, optimize na endpoint test.
5. Create production `admin01` kwa password mpya yenye nguvu.
6. Create Windows printer record na copy one-time token.
7. Install/copy Windows agent na configure `.env`.
8. Run Windows manual print test, agent `--once`, kisha end-to-end PDF test.
9. Configure Task Scheduler.
10. Test network interruption, printer offline, reboot ya Windows, na kuhakikisha agent inaanza tena bila duplicate print.
