from __future__ import annotations

import argparse
import logging
import signal
import tempfile
import time
from pathlib import Path
from threading import Event

from api_client import ApiClient, ApiError, AuthenticationError, PrintJob
from config import Config, ConfigurationError
from logger import configure_logging
from printer import SUPPORTED_EXTENSIONS, PrinterError, WindowsDocumentPrinter, validate_downloaded_file


class PrintAgent:
    def __init__(self, config: Config) -> None:
        self.config = config
        self.api = ApiClient(config)
        self.printer = WindowsDocumentPrinter(config)
        self.stop_event = Event()
        self.log = logging.getLogger("printagent")

    def stop(self, *_args: object) -> None:
        self.stop_event.set()

    def run(self, once: bool = False) -> None:
        self.log.info("Print agent started for %s", self.config.printer_name)

        while not self.stop_event.is_set():
            try:
                self.api.heartbeat()
                job = self.api.next_job()
                if job is not None:
                    self.process(job)
                elif once:
                    self.log.info("No pending print jobs")
                    return
            except AuthenticationError as exc:
                self.log.error("Printer authentication failed: %s", exc)
            except ApiError as exc:
                self.log.warning("Print server unavailable: %s", exc)
            except Exception:
                self.log.exception("Unexpected agent loop error")

            if once:
                return
            self.stop_event.wait(self.config.check_interval)

    def process(self, job: PrintJob) -> None:
        self.log.info("Processing job %s: %s", job.id, job.original_name)
        local_path: Path | None = None

        try:
            self.api.claim(job.id)
            local_path = self.download(job)
            self.api.mark_printing(job.id)
            self.printer.print_file(local_path, job.copies)
        except (ApiError, PrinterError, OSError, ValueError) as exc:
            self.log.exception("Job %s failed", job.id)
            self.report_failed_until_ack(job.id, str(exc))
        else:
            self.report_printed_until_ack(job.id)
            self.log.info("Job %s printed successfully", job.id)
        finally:
            if local_path is not None and self.config.cleanup_downloads:
                local_path.unlink(missing_ok=True)

    def download(self, job: PrintJob) -> Path:
        self.config.download_dir.mkdir(parents=True, exist_ok=True)
        extension = job.file_extension.lower()
        if extension not in SUPPORTED_EXTENSIONS:
            raise PrinterError(f"Server returned an unsupported file type: .{extension}")
        with tempfile.NamedTemporaryFile(
            prefix=f"job-{job.id}-",
            suffix=f".{extension}",
            dir=self.config.download_dir,
            delete=False,
        ) as temporary_file:
            local_path = Path(temporary_file.name)

        try:
            self.api.download(job.id, local_path)
            validate_downloaded_file(local_path, extension)
            return local_path
        except Exception:
            local_path.unlink(missing_ok=True)
            raise

    def report_printed_until_ack(self, job_id: int) -> None:
        while not self.stop_event.is_set():
            try:
                self.api.mark_printed(job_id)
                return
            except ApiError as exc:
                self.log.error("Job %s printed locally; retrying server acknowledgement: %s", job_id, exc)
                self.stop_event.wait(self.config.report_retry_interval)

        raise ApiError("Agent stopped before printed status was acknowledged")

    def report_failed_until_ack(self, job_id: int, message: str) -> None:
        while not self.stop_event.is_set():
            try:
                self.api.mark_failed(job_id, message[:2000])
                return
            except ApiError as exc:
                self.log.error("Retrying failure report for job %s: %s", job_id, exc)
                self.stop_event.wait(self.config.report_retry_interval)


def main() -> int:
    parser = argparse.ArgumentParser(description="Elegansky Windows print agent")
    parser.add_argument("--once", action="store_true", help="Run one polling cycle and exit")
    args = parser.parse_args()

    try:
        config = Config.from_env()
    except ConfigurationError as exc:
        print(f"Configuration error: {exc}")
        return 2

    configure_logging(config.log_level, config.log_file)
    agent = PrintAgent(config)
    signal.signal(signal.SIGINT, agent.stop)
    if hasattr(signal, "SIGTERM"):
        signal.signal(signal.SIGTERM, agent.stop)
    agent.run(once=args.once)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
