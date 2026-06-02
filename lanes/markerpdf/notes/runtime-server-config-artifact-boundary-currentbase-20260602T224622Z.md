# markerPDF Runtime Server Config Artifact Boundary Current Base

Micro-slice: `runtime-server-config-artifact-boundary-currentbase`
Session: `port-dev-markerpdf-runtime77-20260602T224622Z`
Base accepted HEAD: `46dcbc383630b2d55e601d02ab9f1a9bd647b8e2`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `marker_server.py` creates `UPLOAD_DIRECTORY`, maps `--host`, `--port`, `--api_key`, and `--datalab_url` into `app.state`, selects local mode when the API key is absent, and later uses the same state when uploaded PDFs are routed through local conversion or remote Datalab multipart/polling.
- `convert_pdf_from_upload()` removes the temporary upload in `finally`; `convert_pdf_remote()` forwards `file`, `max_pages`, `langs`, `force_ocr`, `paginate`, and `extract_images` without storing API-key values in benchmark artifacts.
- Primary upstream file inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker_server.py`

## Patch

- Added `BenchmarkRunner::writeServerConfigArtifactJson()` and `readServerConfigArtifactJson()` for review-only marker server startup config plus upload-route artifact roundtrips.
- Artifact payloads preserve local/remote mode, Datalab URL, Uvicorn host/port metadata, upload-directory status, multipart field boundaries, local upload option behavior, upload cleanup, benchmark context, and no-live-runtime execution flags.
- API-key values, uploaded PDF bytes, and raw runtime payloads are excluded from artifact JSON.
- Added `MarkerRuntimeServerConfigArtifactBoundaryCurrentBaseTest.php` and `wordpress-marker-runtime-server-config-artifact-boundary-currentbase.php`.
- Status delta: PHP behavior tests `930 -> 933`; mapped semantics `654 -> 655 / 78`.

## Evidence

Red-first focused run before source implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeServerConfigArtifactBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL roundtrips marker server config as benchmark review artifact without exposing secrets
Call to undefined method PortLibs\MarkerPDF\BenchmarkRunner::writeServerConfigArtifactJson()
FAIL records local config artifacts while preserving upload local option boundaries
Call to undefined method PortLibs\MarkerPDF\BenchmarkRunner::writeServerConfigArtifactJson()
FAIL rejects malformed server config artifact inputs and tampered roundtrips
Expected InvalidArgumentException, got Error

1 test files, 1 assertions, 3 failures
```

Focused green after source implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeServerConfigArtifactBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS roundtrips marker server config as benchmark review artifact without exposing secrets
PASS records local config artifacts while preserving upload local option boundaries
PASS rejects malformed server config artifact inputs and tampered roundtrips

1 test files, 73 assertions, 0 failures
```

Adjacent runtime/server artifact family:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeServerConfigArtifactBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeServerConfigErrorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeConvertServerBenchmarkArtifactUploadCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeServerUploadBenchmarkErrorCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeServerConvertBenchmarkOutputBundleCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeServerBenchmarkOutputErrorRoundtripCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeConvertServerOutputPaginationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerServerAdapterTest.php lanes/markerpdf/tests/BenchmarkRunnerTest.php
Focused test run: 9 selected test files (root lock skipped)
9 test files, 601 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-server-config-artifact-boundary-currentbase.php
```

Passed: emitted `artifact_schema="markerpdf.server_config_artifact.v1"`, `artifact_status="remote"`, `artifact_sha256_matches_readback=true`, `roundtrip_preserves_config_boundary=true`, `api_key_configured=true`, `raw_api_key_excluded=true`, `uploaded_pdf_bytes_excluded=true`, `upload_directory_created=true`, `upload_removed=true`, `upload_route="remote"`, forwarded multipart fields `file,max_pages,langs,force_ocr,paginate,extract_images`, and all FastAPI/Uvicorn/live HTTP/external-tool/Python/model flags false.

Required checks:

```text
php -l lanes/markerpdf/src/BenchmarkRunner.php
No syntax errors detected in lanes/markerpdf/src/BenchmarkRunner.php

php -l lanes/markerpdf/tests/MarkerRuntimeServerConfigArtifactBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/MarkerRuntimeServerConfigArtifactBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-marker-runtime-server-config-artifact-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-marker-runtime-server-config-artifact-boundary-currentbase.php

php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, flags: JSON_THROW_ON_ERROR); echo $f, " OK\n"; }'
lanes/markerpdf/lane-status.json OK
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json OK

git diff --check -- lanes/markerpdf
passed with no output
```

## Dependency Closure

No new support component is needed. This reuses the native `MarkerServerAdapter`, `BenchmarkRunner`, JSON artifact write/read boundaries, upload temp-file cleanup path, and WordPress smoke harness. Full upstream markerPDF runtime parity remains gated by live FastAPI/Uvicorn execution, Python `requests`, pdftext, pypdfium2/PDFium, Surya/Torch model downloads, tabled-pdf, Texify, benchmark/model workflows, Streamlit/PIL preview paths, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted marker server startup config error payloads, upload pagination/error cleanup, remote polling errors, standalone server upload success artifacts, successful output bundles, failed upload error artifacts, benchmark runtime telemetry, report-write error artifacts, output Markdown/image sanitization, or PDF parser/security/font/xref/table/image/form/page/outline/metadata slices. The bounded behavior is only the review-only artifact that composes startup config state with upload-route/output context while excluding API keys and uploaded PDF bytes.
