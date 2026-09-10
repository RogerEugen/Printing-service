from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
from typing import Any

import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry

from config import Config


class ApiError(RuntimeError):
    pass


class AuthenticationError(ApiError):
    pass


@dataclass(frozen=True)
class PrintJob:
    id: int
    original_name: str
    mime_type: str
    file_extension: str
    copies: int

    @classmethod
    def from_response(cls, payload: dict[str, Any]) -> "PrintJob":
        data = payload.get("data", payload)
        original_name = str(data["original_name"])
        inferred_extension = Path(original_name).suffix.lstrip(".").lower() or "pdf"

        return cls(
            id=int(data["id"]),
            original_name=original_name,
            mime_type=str(data.get("mime_type", "application/pdf")),
            file_extension=str(data.get("file_extension", inferred_extension)).lower(),
            copies=int(data["copies"]),
        )


class ApiClient:
    def __init__(self, config: Config) -> None:
        self.config = config
        self.session = requests.Session()
        self.session.headers.update({
            "Authorization": f"Bearer {config.printer_token}",
            "Accept": "application/json",
            "User-Agent": f"EleganskyPrintAgent/1.0 ({config.printer_name})",
        })
        retry = Retry(
            total=3,
            connect=3,
            read=3,
            backoff_factor=0.5,
            status_forcelist=(429, 502, 503, 504),
            # GET /jobs/next changes state by atomically claiming a job, so GET
            # requests must never be retried automatically after a lost response.
            allowed_methods=frozenset({"POST"}),
        )
        self.session.mount("http://", HTTPAdapter(max_retries=retry))
        self.session.mount("https://", HTTPAdapter(max_retries=retry))

    def heartbeat(self) -> None:
        self._request("POST", "/api/printer/heartbeat")

    def next_job(self) -> PrintJob | None:
        response = self._request("GET", "/api/printer/jobs/next")
        if response.status_code == 204:
            return None
        return PrintJob.from_response(response.json())

    def claim(self, job_id: int) -> None:
        self._request("POST", f"/api/printer/jobs/{job_id}/claim")

    def download(self, job_id: int, destination: Path) -> None:
        try:
            response = self._request("GET", f"/api/printer/jobs/{job_id}/download", stream=True)
            with destination.open("wb") as output:
                for chunk in response.iter_content(chunk_size=128 * 1024):
                    if chunk:
                        output.write(chunk)
        except requests.RequestException as exc:
            raise ApiError(str(exc)) from exc

    def mark_printing(self, job_id: int) -> None:
        self._request("POST", f"/api/printer/jobs/{job_id}/printing")

    def mark_printed(self, job_id: int) -> None:
        self._request("POST", f"/api/printer/jobs/{job_id}/printed")

    def mark_failed(self, job_id: int, message: str) -> None:
        self._request("POST", f"/api/printer/jobs/{job_id}/failed", json={"error_message": message})

    def _request(self, method: str, path: str, **kwargs: Any) -> requests.Response:
        try:
            response = self.session.request(
                method,
                f"{self.config.server_url}{path}",
                timeout=self.config.request_timeout,
                verify=self.config.verify_tls,
                **kwargs,
            )
        except requests.RequestException as exc:
            raise ApiError(str(exc)) from exc

        if response.status_code == 401:
            raise AuthenticationError("server rejected the device token")
        if response.status_code >= 400:
            try:
                detail = response.json().get("message", response.text)
            except requests.ValueError:
                detail = response.text
            raise ApiError(f"HTTP {response.status_code}: {detail}")
        return response
