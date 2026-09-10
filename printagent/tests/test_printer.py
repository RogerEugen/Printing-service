from __future__ import annotations

import tempfile
import unittest
import zipfile
from pathlib import Path

from printer import PrinterError, validate_downloaded_file


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


if __name__ == "__main__":
    unittest.main()
