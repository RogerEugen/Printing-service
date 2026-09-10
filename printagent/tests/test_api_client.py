from __future__ import annotations

import unittest

from api_client import PrintJob


class PrintJobTest(unittest.TestCase):
    def test_parses_laravel_resource_envelope(self) -> None:
        job = PrintJob.from_response({
            "data": {
                "id": 7,
                "original_name": "team-photo.png",
                "mime_type": "image/png",
                "file_extension": "png",
                "copies": 3,
            }
        })

        self.assertEqual(job.id, 7)
        self.assertEqual(job.original_name, "team-photo.png")
        self.assertEqual(job.mime_type, "image/png")
        self.assertEqual(job.file_extension, "png")
        self.assertEqual(job.copies, 3)


if __name__ == "__main__":
    unittest.main()
