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

        command = [str(executable), "-silent", "-print-settings", f"{copies}x"]
        if self.config.default_printer:
            command.extend(["-print-to", self.config.default_printer])
        else:
            command.append("-print-to-default")
        command.append(str(file_path))

        result = subprocess.run(command, capture_output=True, text=True, timeout=300, check=False)
        if result.returncode != 0:
            detail = result.stderr.strip() or result.stdout.strip() or "unknown SumatraPDF error"
            raise PrinterError(detail)

    def _print_with_windows_shell(self, file_path: Path, copies: int) -> None:
        try:
            import win32api
            import win32print
        except ImportError as exc:
            raise PrinterError("pywin32 is required for Windows shell printing") from exc

        printer_name = self.config.default_printer or win32print.GetDefaultPrinter()
        for _ in range(copies):
            result = win32api.ShellExecute(0, "printto", str(file_path), f'"{printer_name}"', ".", 0)
            if result <= 32:
                raise PrinterError(f"Windows print command failed with code {result}")


WindowsPdfPrinter = WindowsDocumentPrinter
