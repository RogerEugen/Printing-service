from __future__ import annotations

import os
import unittest
from pathlib import Path
from unittest.mock import patch

from config import Config, ConfigurationError


class ConfigTest(unittest.TestCase):
    def test_loads_valid_environment(self) -> None:
        environment = {
            "PRINT_SERVER_URL": "https://print.internal/",
            "PRINTER_TOKEN": "a" * 80,
            "PRINTER_NAME": "OFFICE-01",
            "DOWNLOAD_DIR": "./test-downloads",
        }

        with patch("config.load_dotenv"), patch.dict(os.environ, environment, clear=True):
            config = Config.from_env()

        self.assertEqual(config.server_url, "https://print.internal")
        self.assertEqual(config.printer_name, "OFFICE-01")
        self.assertEqual(config.download_dir, Path("./test-downloads").resolve())
        self.assertEqual(config.cups_job_timeout, 300)
        self.assertEqual(config.cups_poll_interval, 2)
        self.assertEqual(config.windows_job_timeout, 300)
        self.assertEqual(config.windows_poll_interval, 2)

    def test_rejects_a_missing_device_token(self) -> None:
        environment = {
            "PRINT_SERVER_URL": "https://print.internal",
            "PRINTER_TOKEN": "",
            "PRINTER_NAME": "OFFICE-01",
        }

        with patch("config.load_dotenv"), patch.dict(os.environ, environment, clear=True):
            with self.assertRaises(ConfigurationError):
                Config.from_env()

    def test_accepts_cups_as_the_print_method(self) -> None:
        environment = {
            "PRINT_SERVER_URL": "http://127.0.0.1:8001",
            "PRINTER_TOKEN": "a" * 80,
            "PRINTER_NAME": "CANON-UBUNTU",
            "PRINT_METHOD": "cups",
        }

        with patch("config.load_dotenv"), patch.dict(os.environ, environment, clear=True):
            config = Config.from_env()

        self.assertEqual(config.print_method, "cups")


if __name__ == "__main__":
    unittest.main()
