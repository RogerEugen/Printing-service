from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path

from dotenv import load_dotenv


class ConfigurationError(ValueError):
    pass


def _positive_int(name: str, default: int) -> int:
    raw_value = os.getenv(name, str(default))
    try:
        value = int(raw_value)
    except ValueError as exc:
        raise ConfigurationError(f"{name} must be an integer") from exc
    if value <= 0:
        raise ConfigurationError(f"{name} must be greater than zero")
    return value


def _boolean(name: str, default: bool) -> bool:
    return os.getenv(name, str(default)).strip().lower() in {"1", "true", "yes", "on"}


@dataclass(frozen=True)
class Config:
    server_url: str
    printer_token: str
    printer_name: str
    check_interval: int
    report_retry_interval: int
    request_timeout: int
    download_dir: Path
    default_printer: str | None
    print_method: str
    sumatra_path: Path | None
    cleanup_downloads: bool
    verify_tls: bool
    log_level: str
    log_file: Path

    @classmethod
    def from_env(cls) -> "Config":
        load_dotenv()
        server_url = os.getenv("PRINT_SERVER_URL", "").strip().rstrip("/")
        token = os.getenv("PRINTER_TOKEN", "").strip()
        printer_name = os.getenv("PRINTER_NAME", "").strip()
        print_method = os.getenv("PRINT_METHOD", "auto").strip().lower()

        if not server_url.startswith(("http://", "https://")):
            raise ConfigurationError("PRINT_SERVER_URL must start with http:// or https://")
        if len(token) < 40:
            raise ConfigurationError("PRINTER_TOKEN is missing or too short")
        if not printer_name:
            raise ConfigurationError("PRINTER_NAME is required")
        if print_method not in {"auto", "sumatra", "shell"}:
            raise ConfigurationError("PRINT_METHOD must be auto, sumatra, or shell")

        sumatra_raw = os.getenv("SUMATRA_PATH", "").strip()
        return cls(
            server_url=server_url,
            printer_token=token,
            printer_name=printer_name,
            check_interval=_positive_int("CHECK_INTERVAL", 5),
            report_retry_interval=_positive_int("REPORT_RETRY_INTERVAL", 5),
            request_timeout=_positive_int("REQUEST_TIMEOUT", 30),
            download_dir=Path(os.getenv("DOWNLOAD_DIR", "./downloads")).expanduser().resolve(),
            default_printer=os.getenv("DEFAULT_PRINTER", "").strip() or None,
            print_method=print_method,
            sumatra_path=Path(sumatra_raw).expanduser().resolve() if sumatra_raw else None,
            cleanup_downloads=_boolean("CLEANUP_DOWNLOADS", True),
            verify_tls=_boolean("VERIFY_TLS", True),
            log_level=os.getenv("LOG_LEVEL", "INFO").upper(),
            log_file=Path(os.getenv("LOG_FILE", "./logs/printagent.log")).expanduser().resolve(),
        )
