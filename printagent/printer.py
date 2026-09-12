from __future__ import annotations

import platform
import re
import shutil
import subprocess
import tempfile
import time
import zipfile
from pathlib import Path

from config import Config


class PrinterError(RuntimeError):
    pass


SUPPORTED_EXTENSIONS = frozenset({
    "pdf",
    "doc", "docx",
    "xls", "xlsx",
    "ppt", "pptx",
    "txt", "rtf",
    "jpg", "jpeg", "png", "bmp", "gif", "tif", "tiff", "webp",
})

SUMATRA_EXTENSIONS = frozenset({"pdf", "jpg", "jpeg", "png", "bmp", "gif", "tif", "tiff", "webp"})
OFFICE_EXTENSIONS = frozenset({"doc", "docx", "xls", "xlsx", "ppt", "pptx", "rtf"})
WINDOWS_JOB_DISCOVERY_TIMEOUT = 10


def validate_downloaded_file(file_path: Path, extension: str) -> None:
    normalized_extension = extension.lower().lstrip(".")
    if normalized_extension not in SUPPORTED_EXTENSIONS:
        raise PrinterError(f"Unsupported print file type: .{normalized_extension}")
    if not file_path.is_file() or file_path.stat().st_size == 0:
        raise PrinterError(f"Downloaded print file is missing or empty: {file_path}")

    with file_path.open("rb") as downloaded_file:
        header = downloaded_file.read(8192)

    if normalized_extension == "pdf" and not header.startswith(b"%PDF-"):
        raise PrinterError("Downloaded file is not a valid PDF")
    if normalized_extension in {"jpg", "jpeg"} and not header.startswith(b"\xff\xd8\xff"):
        raise PrinterError("Downloaded file is not a valid JPEG image")
    if normalized_extension == "png" and not header.startswith(b"\x89PNG\r\n\x1a\n"):
        raise PrinterError("Downloaded file is not a valid PNG image")
    if normalized_extension == "bmp" and not header.startswith(b"BM"):
        raise PrinterError("Downloaded file is not a valid BMP image")
    if normalized_extension == "gif" and not header.startswith((b"GIF87a", b"GIF89a")):
        raise PrinterError("Downloaded file is not a valid GIF image")
    if normalized_extension in {"tif", "tiff"} and not header.startswith((b"II*\x00", b"MM\x00*")):
        raise PrinterError("Downloaded file is not a valid TIFF image")
    if normalized_extension == "webp" and not (header.startswith(b"RIFF") and header[8:12] == b"WEBP"):
        raise PrinterError("Downloaded file is not a valid WebP image")
    if normalized_extension == "rtf" and not header.lstrip().startswith(b"{\\rtf"):
        raise PrinterError("Downloaded file is not a valid RTF document")
    if normalized_extension == "txt" and b"\x00" in header:
        raise PrinterError("Downloaded text document contains binary data")
    if normalized_extension in {"doc", "xls", "ppt"} and not header.startswith(b"\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1"):
        raise PrinterError("Downloaded file is not a valid legacy Office document")
    if normalized_extension in {"docx", "xlsx", "pptx"}:
        _validate_open_xml(file_path, normalized_extension)


def _validate_open_xml(file_path: Path, extension: str) -> None:
    expected_directory = {"docx": "word/", "xlsx": "xl/", "pptx": "ppt/"}[extension]

    try:
        with zipfile.ZipFile(file_path) as archive:
            entries = archive.infolist()
            if len(entries) > 10_000:
                raise PrinterError("Office document contains too many archive entries")
            if sum(entry.file_size for entry in entries) > 250 * 1024 * 1024:
                raise PrinterError("Office document expands beyond the safety limit")

            names = {entry.filename for entry in entries}
            if "[Content_Types].xml" not in names or not any(name.startswith(expected_directory) for name in names):
                raise PrinterError(f"Downloaded file is not a valid {extension.upper()} document")
    except (OSError, zipfile.BadZipFile) as exc:
        raise PrinterError(f"Downloaded file is not a valid {extension.upper()} document") from exc


class WindowsDocumentPrinter:
    def __init__(self, config: Config) -> None:
        self.config = config

    def print_file(self, file_path: Path, copies: int) -> None:
        if not file_path.is_file():
            raise PrinterError(f"Print file does not exist: {file_path}")

        extension = file_path.suffix.lstrip(".").lower()
        if extension not in SUPPORTED_EXTENSIONS:
            raise PrinterError(f"Unsupported print file type: .{extension}")

        method = self.config.print_method
        operating_system = platform.system()
        if operating_system == "Linux":
            if method not in {"auto", "cups"}:
                raise PrinterError("Ubuntu printing requires PRINT_METHOD=auto or cups")
            self._print_with_cups(file_path, copies, extension)
            return
        if operating_system != "Windows":
            raise PrinterError(f"Physical printing is not supported on {operating_system}")
        if method == "cups":
            raise PrinterError("PRINT_METHOD=cups is supported only on Linux")
        if method == "sumatra" and extension not in SUMATRA_EXTENSIONS:
            raise PrinterError(f"SumatraPDF cannot print .{extension}; use PRINT_METHOD=auto or shell")
        if extension in SUMATRA_EXTENSIONS and (
            method == "sumatra"
            or (method == "auto" and self.config.sumatra_path and self.config.sumatra_path.is_file())
        ):
            self._print_with_sumatra(file_path, copies)
            return
        self._print_with_windows_shell(file_path, copies)

    def _print_with_cups(self, file_path: Path, copies: int, extension: str) -> None:
        lp_executable = shutil.which("lp")
        lpstat_executable = shutil.which("lpstat")
        cancel_executable = shutil.which("cancel")
        if lp_executable is None or lpstat_executable is None or cancel_executable is None:
            raise PrinterError("CUPS lp, lpstat, and cancel commands are required; install the cups-client package")

        if extension in OFFICE_EXTENSIONS:
            with tempfile.TemporaryDirectory(prefix="elegansky-convert-") as directory:
                converted_path = self._convert_office_to_pdf(file_path, Path(directory))
                job_id = self._submit_to_cups(lp_executable, converted_path, copies, file_path.name)
                self._wait_for_cups_job(lpstat_executable, cancel_executable, job_id)
            return

        job_id = self._submit_to_cups(lp_executable, file_path, copies, file_path.name)
        self._wait_for_cups_job(lpstat_executable, cancel_executable, job_id)

    def _convert_office_to_pdf(self, file_path: Path, output_directory: Path) -> Path:
        libreoffice_executable = shutil.which("libreoffice") or shutil.which("soffice")
        if libreoffice_executable is None:
            raise PrinterError("LibreOffice is required to print Office documents on Ubuntu")

        result = subprocess.run(
            [
                libreoffice_executable,
                "--headless",
                "--convert-to",
                "pdf",
                "--outdir",
                str(output_directory),
                str(file_path),
            ],
            capture_output=True,
            text=True,
            timeout=180,
            check=False,
        )
        converted_files = list(output_directory.glob("*.pdf"))
        if result.returncode != 0 or len(converted_files) != 1:
            detail = result.stderr.strip() or result.stdout.strip() or "unknown LibreOffice conversion error"
            raise PrinterError(detail)

        converted_path = converted_files[0]
        validate_downloaded_file(converted_path, "pdf")

        return converted_path

    def _submit_to_cups(self, lp_executable: str, file_path: Path, copies: int, job_name: str) -> str:
        command = [lp_executable]
        if self.config.default_printer:
            command.extend(["-d", self.config.default_printer])
        command.extend(["-n", str(copies), "-t", job_name, "--", str(file_path)])

        result = subprocess.run(command, capture_output=True, text=True, timeout=120, check=False)
        if result.returncode != 0:
            detail = result.stderr.strip() or result.stdout.strip() or "unknown CUPS error"
            raise PrinterError(detail)

        match = re.search(r"request id is ([^\s]+)", result.stdout, flags=re.IGNORECASE)
        if match is None:
            raise PrinterError("CUPS accepted the file but did not return a job ID")

        return match.group(1)

    def _wait_for_cups_job(self, lpstat_executable: str, cancel_executable: str, job_id: str) -> None:
        deadline = time.monotonic() + self.config.cups_job_timeout
        destination = job_id.rsplit("-", 1)[0]

        while time.monotonic() < deadline:
            active = subprocess.run(
                [lpstat_executable, "-W", "not-completed", "-o", destination],
                capture_output=True,
                text=True,
                timeout=30,
                check=False,
            )
            if job_id not in active.stdout:
                completed = subprocess.run(
                    [lpstat_executable, "-W", "completed", "-o", destination],
                    capture_output=True,
                    text=True,
                    timeout=30,
                    check=False,
                )
                if completed.returncode == 0 and job_id in completed.stdout:
                    return

            time.sleep(self.config.cups_poll_interval)

        cancellation = subprocess.run(
            [cancel_executable, job_id],
            capture_output=True,
            text=True,
            timeout=30,
            check=False,
        )
        if cancellation.returncode != 0:
            cancellation_detail = cancellation.stderr.strip() or cancellation.stdout.strip()
            if "already completed" in cancellation_detail.lower():
                return

            completed = subprocess.run(
                [lpstat_executable, "-W", "completed", "-o", destination],
                capture_output=True,
                text=True,
                timeout=30,
                check=False,
            )
            if completed.returncode == 0 and job_id in completed.stdout:
                return

            detail = cancellation_detail or "CUPS refused cancellation"
            raise PrinterError(
                f"CUPS job {job_id} timed out and cancellation was not confirmed: {detail}. "
                "Check the CUPS queue before reconnecting the printer"
            )

        raise PrinterError(
            f"CUPS job {job_id} did not complete within {self.config.cups_job_timeout} seconds and was cancelled"
        )

    def _print_with_sumatra(self, file_path: Path, copies: int) -> None:
        executable = self.config.sumatra_path
        if executable is None or not executable.is_file():
            raise PrinterError("SUMATRA_PATH does not point to SumatraPDF.exe")

        win32print = self._load_win32print()
        printer_name = self.config.default_printer or win32print.GetDefaultPrinter()
        existing_job_ids = set(self._windows_jobs(win32print, printer_name))

        command = [str(executable), "-silent", "-print-settings", f"{copies}x"]
        command.extend(["-print-to", printer_name])
        command.append(str(file_path))

        result = subprocess.run(command, capture_output=True, text=True, timeout=300, check=False)
        if result.returncode != 0:
            detail = result.stderr.strip() or result.stdout.strip() or "unknown SumatraPDF error"
            raise PrinterError(detail)

        self._wait_for_windows_spooler(win32print, printer_name, existing_job_ids, file_path)

    def _print_with_windows_shell(self, file_path: Path, copies: int) -> None:
        try:
            import win32api
        except ImportError as exc:
            raise PrinterError("pywin32 is required for Windows shell printing") from exc

        win32print = self._load_win32print()
        printer_name = self.config.default_printer or win32print.GetDefaultPrinter()
        existing_job_ids = set(self._windows_jobs(win32print, printer_name))
        for _ in range(copies):
            result = win32api.ShellExecute(0, "printto", str(file_path), f'"{printer_name}"', ".", 0)
            if result <= 32:
                raise PrinterError(f"Windows print command failed with code {result}")

        self._wait_for_windows_spooler(win32print, printer_name, existing_job_ids, file_path)

    @staticmethod
    def _load_win32print():
        try:
            import win32print
        except ImportError as exc:
            raise PrinterError("pywin32 is required for Windows spooler monitoring") from exc

        return win32print

    @staticmethod
    def _windows_jobs(win32print, printer_name: str) -> dict[int, dict]:
        printer_handle = win32print.OpenPrinter(printer_name)
        try:
            jobs = win32print.EnumJobs(printer_handle, 0, 999, 1)
        finally:
            win32print.ClosePrinter(printer_handle)

        return {int(job["JobId"]): job for job in jobs}

    @staticmethod
    def _matching_windows_job_ids(jobs: dict[int, dict], existing_job_ids: set[int], file_path: Path) -> set[int]:
        expected_path = str(file_path).casefold()
        expected_name = file_path.name.casefold()

        return {
            job_id
            for job_id, job in jobs.items()
            if job_id not in existing_job_ids
            and (
                expected_path in str(job.get("pDocument", "")).casefold()
                or expected_name in str(job.get("pDocument", "")).casefold()
            )
        }

    @staticmethod
    def _windows_failure_status(win32print, job: dict) -> str | None:
        status = int(job.get("Status", 0))
        failure_flags = {
            win32print.JOB_STATUS_BLOCKED_DEVQ: "printer queue is blocked",
            win32print.JOB_STATUS_ERROR: "printer reported an error",
            win32print.JOB_STATUS_OFFLINE: "printer is offline",
            win32print.JOB_STATUS_PAPEROUT: "printer is out of paper",
            win32print.JOB_STATUS_USER_INTERVENTION: "printer needs user intervention",
        }
        for flag, message in failure_flags.items():
            if status & flag:
                detail = str(job.get("pStatus", "")).strip()
                return f"{message}: {detail}" if detail else message

        return None

    @staticmethod
    def _windows_job_completed(win32print, job: dict) -> bool:
        status = int(job.get("Status", 0))
        return bool(status & (win32print.JOB_STATUS_COMPLETE | win32print.JOB_STATUS_PRINTED))

    @staticmethod
    def _cancel_windows_jobs(win32print, printer_name: str, job_ids: set[int]) -> list[int]:
        failed_cancellations: list[int] = []
        printer_handle = win32print.OpenPrinter(printer_name)
        try:
            for job_id in job_ids:
                try:
                    win32print.SetJob(printer_handle, job_id, 0, None, win32print.JOB_CONTROL_CANCEL)
                except Exception:
                    failed_cancellations.append(job_id)
        finally:
            win32print.ClosePrinter(printer_handle)

        return failed_cancellations

    def _wait_for_windows_spooler(
        self,
        win32print,
        printer_name: str,
        existing_job_ids: set[int],
        file_path: Path,
    ) -> None:
        started_at = time.monotonic()
        deadline = started_at + self.config.windows_job_timeout
        discovery_deadline = min(deadline, started_at + WINDOWS_JOB_DISCOVERY_TIMEOUT)
        tracked_job_ids: set[int] = set()

        while time.monotonic() < deadline:
            jobs = self._windows_jobs(win32print, printer_name)
            if not tracked_job_ids:
                tracked_job_ids = self._matching_windows_job_ids(jobs, existing_job_ids, file_path)
                if not tracked_job_ids:
                    if time.monotonic() >= discovery_deadline:
                        # A small job can be submitted and removed before the first
                        # enumeration. Sumatra's successful exit remains the best
                        # available confirmation in that race.
                        return
                    time.sleep(self.config.windows_poll_interval)
                    continue

            active_job_ids = tracked_job_ids.intersection(jobs)
            if not active_job_ids:
                return

            completed_job_ids = {
                job_id
                for job_id in active_job_ids
                if self._windows_job_completed(win32print, jobs[job_id])
            }
            tracked_job_ids.difference_update(completed_job_ids)
            if not tracked_job_ids:
                return

            for job_id in tracked_job_ids:
                failure = self._windows_failure_status(win32print, jobs[job_id])
                if failure:
                    failed_cancellations = self._cancel_windows_jobs(win32print, printer_name, tracked_job_ids)
                    cancellation_note = (
                        f" Cancellation failed for Windows job(s): {sorted(failed_cancellations)}."
                        if failed_cancellations
                        else " The queued job was cancelled."
                    )
                    raise PrinterError(f"Windows job {job_id} failed because {failure}.{cancellation_note}")

            time.sleep(self.config.windows_poll_interval)

        failed_cancellations = self._cancel_windows_jobs(win32print, printer_name, tracked_job_ids)
        if failed_cancellations:
            raise PrinterError(
                f"Windows print job timed out after {self.config.windows_job_timeout} seconds and cancellation "
                f"failed for job(s) {sorted(failed_cancellations)}. Check the Windows print queue before retrying"
            )

        raise PrinterError(
            f"Windows print job did not complete within {self.config.windows_job_timeout} seconds and was cancelled"
        )


WindowsPdfPrinter = WindowsDocumentPrinter
