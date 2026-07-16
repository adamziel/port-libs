# markerPDF Runtime Server Config Error Boundary Current Base

Micro-slice: `runtime-server-config-error-boundary-currentbase`
Session: `port-dev-markerpdf-runtime45-20260602T1957Z`
Base accepted HEAD: `9738a1da84787c344a2ab7b6f217cf3e482a95c5`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `marker_server.py` sets `UPLOAD_DIRECTORY = "./uploads"` at import time, creates it when missing, and maps `main()` CLI args into `app.state.API_KEY`, `app.state.LOCAL`, and `app.state.DATALAB_URL` before calling `uvicorn.run(app, host=args.host, port=args.port)`.
- `app.state.LOCAL` is derived from whether `api_key` is `None`; local lifespan startup loads models, while remote startup keeps Datalab URL/API-key state for later requests.
- Primary upstream file inspected: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker_server.py

## Patch

- Added `MarkerServerAdapter::serverConfigPlan()` for marker server host, port, API-key configured state, local/remote mode, Datalab URL, upload-directory planning/creation, app-state metadata, and Uvicorn command metadata without executing Uvicorn, FastAPI, live HTTP, Python, or models.
- Added `MarkerServerAdapter::serverConfigErrorBoundary()` so WordPress import services can receive structured `success=false` config errors before startup.
- API keys are intentionally represented as `api_key_configured` and are not returned in plan payloads.
- Added `MarkerRuntimeServerConfigErrorBoundaryCurrentBaseTest.php` and `wordpress-marker-runtime-server-config-error-boundary-currentbase.php`.
- Status delta: PHP behavior tests `749 -> 753`; mapped semantics `535 -> 536 / 78`.

## Evidence

Red-first focused run before source implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeServerConfigErrorBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL plans marker server remote startup config without exposing api keys or running uvicorn
Call to undefined method PortLibs\MarkerPDF\MarkerServerAdapter::serverConfigErrorBoundary()
FAIL keeps upstream local mode when marker server api key is omitted
Call to undefined method PortLibs\MarkerPDF\MarkerServerAdapter::serverConfigPlan()
FAIL returns config error payloads for invalid ports before server startup
Call to undefined method PortLibs\MarkerPDF\MarkerServerAdapter::serverConfigErrorBoundary()
FAIL returns config error payloads when upload directory initialization fails
Call to undefined method PortLibs\MarkerPDF\MarkerServerAdapter::serverConfigErrorBoundary()
1 test files, 0 assertions, 4 failures
```

Focused green after source implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeServerConfigErrorBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS plans marker server remote startup config without exposing api keys or running uvicorn
PASS keeps upstream local mode when marker server api key is omitted
PASS returns config error payloads for invalid ports before server startup
PASS returns config error payloads when upload directory initialization fails
1 test files, 43 assertions, 0 failures
```

Adjacent runtime/server family:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeServerConfigErrorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerServerAdapterTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/ChunkConversionPlannerTest.php lanes/markerpdf/tests/SingleDocumentConverterTest.php lanes/markerpdf/tests/ModelPipelinePlannerTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 247 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-server-config-error-boundary-currentbase.php
```

Passed: emitted remote startup config with `LOCAL=false`, `api_key_configured=true`, `raw_api_key_exposed=false`, Uvicorn host `0.0.0.0`, port `8173`, created upload-directory status, invalid-port `success=false`, blocked-directory `success=false`, and execution flags for Uvicorn, FastAPI, live HTTP, Python/models, and external PDF tools all false.

Required checks:

```text
php -l lanes/markerpdf/src/MarkerServerAdapter.php
No syntax errors detected in lanes/markerpdf/src/MarkerServerAdapter.php

php -l lanes/markerpdf/tests/MarkerRuntimeServerConfigErrorBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/MarkerRuntimeServerConfigErrorBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-marker-runtime-server-config-error-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-marker-runtime-server-config-error-boundary-currentbase.php

php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, flags: JSON_THROW_ON_ERROR); echo $f, " OK\n"; }'
lanes/markerpdf/lane-status.json OK
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json OK

git diff --check -- lanes/markerpdf
passed with no output
```

## Dependency Closure

No new support component is needed. This reuses the native `MarkerServerAdapter`, filesystem temp-directory boundary, and WordPress smoke pattern. Full upstream markerPDF runtime parity remains gated by live FastAPI/Uvicorn execution, Python `requests`, pdftext, pypdfium2/PDFium, Surya/Torch model downloads, tabled-pdf, Texify, Streamlit paths, benchmark workflows, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted marker server upload conversion error handling, remote polling malformed-status/exhaustion behavior, direct local converter normalization/failure handling, runtime conversion pool planning, Streamlit preflight, benchmark callback sandboxing, or PDF parser/security/font/xref/table/image/form/outline/metadata slices. The bounded behavior is only marker server startup configuration planning and startup config error payloads.
