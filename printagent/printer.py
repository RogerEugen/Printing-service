from __future__ import annotations

import platform
import subprocess
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
        if platform.system() != "Windows":
            raise PrinterError("Physical printing is supported only on Windows")
        if not file_path.is_file():
            raise PrinterError(f"Print file does not exist: {file_path}")

        extension = file_path.suffix.lstrip(".").lower()
        if extension not in SUPPORTED_EXTENSIONS:
            raise PrinterError(f"Unsupported print file type: .{extension}")

        method = self.config.print_method
        if method == "sumatra" and extension not in SUMATRA_EXTENSIONS:
            raise PrinterError(f"SumatraPDF cannot print .{extension}; use PRINT_METHOD=auto or shell")
        if extension in SUMATRA_EXTENSIONS and (
            method == "sumatra"
            or (method == "auto" and self.config.sumatra_path and self.config.sumatra_path.is_file())
        ):
            self._print_with_sumatra(file_path, copies)
            return
        self._print_with_windows_shell(file_path, copies)

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
