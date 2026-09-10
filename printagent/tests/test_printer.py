from __future__ import annotations

import tempfile
import unittest
import zipfile
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import patch

from printer import PrinterError, WindowsDocumentPrinter, validate_downloaded_file


class DownloadedFileValidationTest(unittest.TestCase):
    def test_accepts_a_png_image(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            image_path = Path(directory) / "photo.png"
            image_path.write_bytes(b"\x89PNG\r\n\x1a\nimage")

            validate_downloaded_file(image_path, "png")

    def test_accepts_a_structurally_valid_docx_document(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            document_path = Path(directory) / "letter.docx"
            with zipfile.ZipFile(document_path, "w") as archive:
                archive.writestr("[Content_Types].xml", "<Types/>")
                archive.writestr("word/document.xml", "<document/>")

            validate_downloaded_file(document_path, "docx")

    def test_rejects_an_executable_disguised_as_a_pdf(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            document_path = Path(directory) / "invoice.pdf"
            document_path.write_bytes(b"MZ executable content")

            with self.assertRaisesRegex(PrinterError, "not a valid PDF"):
                validate_downloaded_file(document_path, "pdf")

    def test_rejects_an_unknown_extension(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            document_path = Path(directory) / "payload.exe"
            document_path.write_bytes(b"MZ executable content")

            with self.assertRaisesRegex(PrinterError, "Unsupported print file type"):
                validate_downloaded_file(document_path, "exe")


class CupsPrintingTest(unittest.TestCase):
    def test_submits_a_pdf_to_the_configured_cups_printer(self) -> None:
        config = SimpleNamespace(
            print_method="cups",
            default_printer="iR2224-UFR-II",
            cups_job_timeout=300,
            cups_poll_interval=2,
        )

        with tempfile.TemporaryDirectory() as directory:
            document_path = Path(directory) / "report.pdf"
            document_path.write_bytes(b"%PDF-1.4\n%%EOF")

            with (
                patch("printer.platform.system", return_value="Linux"),
                patch("printer.shutil.which", side_effect=lambda command: f"/usr/bin/{command}"),
                patch("printer.subprocess.run") as run,
            ):
                run.side_effect = [
                    SimpleNamespace(returncode=0, stdout="request id is iR2224-UFR-II-42 (1 file(s))", stderr=""),
                    SimpleNamespace(returncode=0, stdout="", stderr=""),
                    SimpleNamespace(returncode=0, stdout="iR2224-UFR-II-42 rogers completed", stderr=""),
                ]

                WindowsDocumentPrinter(config).print_file(document_path, 2)

        self.assertEqual(run.call_count, 3)
        run.assert_any_call(
            [
                "/usr/bin/lp",
                "-d",
                "iR2224-UFR-II",
                "-n",
                "2",
                "-t",
                "report.pdf",
                "--",
                str(document_path),
            ],
            capture_output=True,
            text=True,
            timeout=120,
            check=False,
        )

        run.assert_any_call(
            ["/usr/bin/lpstat", "-W", "completed", "-o", "iR2224-UFR-II"],
            capture_output=True,
            text=True,
            timeout=30,
            check=False,
        )

    def test_cancels_a_cups_job_that_does_not_complete_before_timeout(self) -> None:
        config = SimpleNamespace(
            print_method="cups",
            default_printer="iR2224-UFR-II",
            cups_job_timeout=300,
            cups_poll_interval=2,
        )

        with tempfile.TemporaryDirectory() as directory:
            document_path = Path(directory) / "report.pdf"
            document_path.write_bytes(b"%PDF-1.4\n%%EOF")

            with (
                patch("printer.platform.system", return_value="Linux"),
                patch("printer.shutil.which", side_effect=lambda command: f"/usr/bin/{command}"),
                patch("printer.time.monotonic", side_effect=[0, 0, 301]),
                patch("printer.time.sleep"),
                patch("printer.subprocess.run") as run,
            ):
                run.side_effect = [
                    SimpleNamespace(returncode=0, stdout="request id is iR2224-UFR-II-43 (1 file(s))", stderr=""),
                    SimpleNamespace(returncode=0, stdout="iR2224-UFR-II-43 rogers active", stderr=""),
                    SimpleNamespace(returncode=0, stdout="", stderr=""),
                ]

                with self.assertRaisesRegex(PrinterError, "was cancelled"):
                    WindowsDocumentPrinter(config).print_file(document_path, 1)

        run.assert_any_call(
            ["/usr/bin/cancel", "iR2224-UFR-II-43"],
            capture_output=True,
            text=True,
            timeout=30,
            check=False,
        )

    def test_reports_when_cups_does_not_confirm_cancellation(self) -> None:
        config = SimpleNamespace(
            print_method="cups",
            default_printer="iR2224-UFR-II",
            cups_job_timeout=60,
            cups_poll_interval=2,
        )

        with tempfile.TemporaryDirectory() as directory:
            document_path = Path(directory) / "report.pdf"
            document_path.write_bytes(b"%PDF-1.4\n%%EOF")

            with (
                patch("printer.platform.system", return_value="Linux"),
                patch("printer.shutil.which", side_effect=lambda command: f"/usr/bin/{command}"),
                patch("printer.time.monotonic", side_effect=[0, 0, 61]),
                patch("printer.time.sleep"),
                patch("printer.subprocess.run") as run,
            ):
                run.side_effect = [
                    SimpleNamespace(returncode=0, stdout="request id is iR2224-UFR-II-44 (1 file(s))", stderr=""),
                    SimpleNamespace(returncode=0, stdout="iR2224-UFR-II-44 rogers active", stderr=""),
                    SimpleNamespace(returncode=1, stdout="", stderr="client-error-not-possible"),
                    SimpleNamespace(returncode=0, stdout="", stderr=""),
                ]

                with self.assertRaisesRegex(PrinterError, "cancellation was not confirmed"):
                    WindowsDocumentPrinter(config).print_file(document_path, 1)

    def test_treats_an_already_completed_job_as_success_during_timeout_race(self) -> None:
        config = SimpleNamespace(
            print_method="cups",
            default_printer="iR2224-UFR-II",
            cups_job_timeout=60,
            cups_poll_interval=2,
        )

        with tempfile.TemporaryDirectory() as directory:
            document_path = Path(directory) / "report.pdf"
            document_path.write_bytes(b"%PDF-1.4\n%%EOF")

            with (
                patch("printer.platform.system", return_value="Linux"),
                patch("printer.shutil.which", side_effect=lambda command: f"/usr/bin/{command}"),
                patch("printer.time.monotonic", side_effect=[0, 0, 61]),
                patch("printer.time.sleep"),
                patch("printer.subprocess.run") as run,
            ):
                run.side_effect = [
                    SimpleNamespace(returncode=0, stdout="request id is iR2224-UFR-II-45 (1 file(s))", stderr=""),
                    SimpleNamespace(returncode=0, stdout="iR2224-UFR-II-45 rogers active", stderr=""),
                    SimpleNamespace(
                        returncode=1,
                        stdout="",
                        stderr="cancel-job failed: Job #45 is already completed - can't cancel.",
                    ),
                ]

                WindowsDocumentPrinter(config).print_file(document_path, 1)


if __name__ == "__main__":
    unittest.main()
