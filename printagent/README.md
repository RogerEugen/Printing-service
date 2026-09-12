# Elegansky Windows Print Agent

Background Python worker that authenticates as one printer, claims assigned jobs from Laravel, downloads private documents or images, prints them through Windows, and reports the final status.

For the complete VPS and Windows production checklist in Swahili, see [`../DEPLOYMENT_GUIDE_SW.md`](../DEPLOYMENT_GUIDE_SW.md).

## Windows setup

Open PowerShell in this directory:

```powershell
py -m venv venv
.\venv\Scripts\Activate.ps1
python -m pip install -r requirements.txt
Copy-Item .env.example .env
```

Create the printer in the Laravel admin UI, copy the token shown once, and configure `.env`:

```dotenv
PRINT_SERVER_URL=https://your-private-print-server
PRINTER_TOKEN=the-one-time-device-token
PRINTER_NAME=OFFICE-PRINTER-01
CHECK_INTERVAL=5
DOWNLOAD_DIR=.\downloads
DEFAULT_PRINTER=Exact Windows Printer Name
```

`PRINTER_NAME` identifies the agent in logs. `DEFAULT_PRINTER` is the exact Windows print queue name; leave it empty to use the Windows default printer.

## Document and image printing methods

Recommended for reliable unattended PDF and image printing:

```dotenv
PRINT_METHOD=sumatra
SUMATRA_PATH=C:\Program Files\SumatraPDF\SumatraPDF.exe
```

`PRINT_METHOD=auto` uses SumatraPDF for PDF and image files when the configured executable exists. Word, Excel, PowerPoint, TXT and RTF files use their Windows `printto` association. Install Microsoft Office or another compatible application and confirm each format can be printed manually from the same Windows account. `PRINT_METHOD=shell` forces Windows file-association printing for every format. Do not use `PRINT_METHOD=sumatra` when the queue includes Office documents.

Supported types are PDF, DOC/DOCX, XLS/XLSX, PPT/PPTX, TXT, RTF, JPG/JPEG, PNG, BMP, GIF, TIFF and WebP. The agent validates downloaded signatures and Office archive structure before sending a file to a local application.

## Ubuntu localhost testing

On Linux, `PRINT_METHOD=auto` or `PRINT_METHOD=cups` submits PDF, image and text files through the local `lp` command. Office and RTF files are converted to PDF with LibreOffice first.

```bash
sudo systemctl enable --now cups
sudo apt install libreoffice
lpstat -p -d
```

Set `DEFAULT_PRINTER` to the exact CUPS queue name, for example `iR2224-UFR-II`. The agent checks the `lp` exit status and only reports a job as printed after CUPS accepts it.

`CUPS_JOB_TIMEOUT` controls how long the agent waits for the spool job to complete (default 300 seconds), while `CUPS_POLL_INTERVAL` controls status polling. If the printer stays unavailable until the timeout, the agent cancels the CUPS job before reporting failure so it cannot print unexpectedly after reconnection.

On Windows, `WINDOWS_JOB_TIMEOUT` and `WINDOWS_POLL_INTERVAL` provide the equivalent spooler monitoring. A successful SumatraPDF or Windows shell submission is not reported as printed while its matching job remains queued. Jobs with spooler error/offline/paper-out status, or jobs still queued at timeout, are cancelled before the agent reports failure.

## Run and test

```powershell
python -m unittest discover -s tests -v
python agent.py --once
python agent.py
```

`--once` sends a heartbeat and checks once, which is useful for connectivity testing. The normal command runs continuously. Run it under Windows Task Scheduler or a service wrapper using a dedicated low-privilege Windows account that can access the selected printer.

For a hidden Task Scheduler worker, use these action values:

```text
Program/script: C:\Windows\System32\wscript.exe
Add arguments: "C:\path\to\printagent\run_agent_windows_hidden.vbs"
Start in: C:\path\to\printagent
```

The VBScript waits for Python instead of launching a detached process, so Task Scheduler continues to show `Running`, can detect failures, and does not leave a console window that a user can accidentally close. Use **Run only when user is logged on** for per-user SumatraPDF installations and Office file associations.

The agent logs to `logs/printagent.log`, deletes temporary print files after processing by default, catches individual job failures, and continues polling. If printing succeeds but the network drops, it retries the `printed` acknowledgement without printing the document again.

## Troubleshooting

- `401`: the token is wrong, disabled, or belongs to another printer. Create a new printer/token if the original was lost.
- Timeouts/server unavailable: confirm the private URL is reachable over the existing company network and that TLS certificates are trusted.
- Printer unavailable: verify the exact Windows printer name and print a test page as the same Windows account.
- PDF/image helper failure: verify `SUMATRA_PATH` and run its command interactively.
- Office document failure: install the matching Office application, verify its `printto` file association, and test printing as the agent's Windows user.
- Set `VERIFY_TLS=false` only for temporary local development with a self-signed endpoint; keep TLS verification enabled in company use.

Never commit `.env`, tokens, downloaded documents/images, or logs. The included `.gitignore` excludes them.
