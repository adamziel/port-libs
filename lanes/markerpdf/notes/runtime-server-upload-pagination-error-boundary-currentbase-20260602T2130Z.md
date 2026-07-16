# markerPDF Runtime Server Upload Pagination Error Boundary

Micro-slice: `runtime-server-upload-pagination-error-boundary-currentbase`
Session: `port-dev-markerpdf-runtime61-20260602T213035Z`
Base accepted HEAD: `c3b759a859020b8775e124d837d858198d98558e`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `marker_server.py::convert_pdf_upload()` exposes form fields `max_pages`, `langs`, `force_ocr`, `paginate`, and `extract_images`, plus a PDF-only `UploadFile`.
- `marker_server.py::convert_pdf_from_upload()` rejects non-PDF content types before the guarded conversion body, saves the upload under `UPLOAD_DIRECTORY`, mutates `params.filepath`, then routes directly to `convert_pdf_local()` or `convert_pdf_remote()`.
- The upload-local route bypasses the direct `/marker` local assertion that requires `extract_images=True` and `paginate=False`; the upload-remote route forwards pagination/image flags into the multipart Datalab request.
- Exceptions inside upload save/convert return `{"success": False, "error": str(e)}` and the temporary PDF is removed in `finally`.
- Upstream inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker_server.py`

## Implementation

- Added `MarkerServerAdapter::uploadRoutePlan()` as a native non-executing boundary for `/marker/upload`.
- The plan records the PDF-only file field, invalid content-type status/detail, normalized form values, upload directory, local route bypass of the direct `/marker` option guard, remote multipart field forwarding, upload-body error behavior, and cleanup guarantees.
- Added focused coverage proving `paginate=true`, `extract_images=false`, `max_pages=3`, `langs=English,Spanish`, and `force_ocr=true` are forwarded to the remote request before a malformed remote initial response becomes an upload `success=false` payload and removes the temporary PDF.
- Added a WordPress smoke for a PDF import endpoint inspecting the upload route plan and remote upload failure without launching FastAPI, Uvicorn, live HTTP, Python, models, or external PDF tools.

## Evidence

Baseline before this patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerServerAdapterTest.php
1 test files, 63 assertions, 0 failures
```

Focused new test:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeServerUploadPaginationErrorBoundaryCurrentBaseTest.php
1 test files, 34 assertions, 0 failures
```

Adjacent server adapter gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerServerAdapterTest.php lanes/markerpdf/tests/MarkerRuntimeServerUploadPaginationErrorBoundaryCurrentBaseTest.php
2 test files, 97 assertions, 0 failures
```

Runtime/server family:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeServerUploadPaginationErrorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerServerAdapterTest.php lanes/markerpdf/tests/MarkerRuntimeServerConfigErrorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php
4 test files, 216 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-server-upload-pagination-error-boundary-currentbase.php
```

Passed. The emitted payload had `remote_paginate=true`, `remote_extract_images=false`, `remote_max_pages=3`, `remote_langs=English,Spanish`, `error_success=false`, `error_contains_request_check_url=true`, `upload_removed=true`, and all FastAPI/Uvicorn/live HTTP/Python/model/external-tool flags false.

Syntax, JSON, and whitespace:

```text
php -l lanes/markerpdf/src/MarkerServerAdapter.php
php -l lanes/markerpdf/tests/MarkerRuntimeServerUploadPaginationErrorBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-marker-runtime-server-upload-pagination-error-boundary-currentbase.php
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, flags: JSON_THROW_ON_ERROR); echo $f, " OK\n"; }'
git diff --check -- lanes/markerpdf
```

All passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- Behavior tests move `849 -> 850` PASS lines.
- WordPress scenarios move `849 -> 850`.
- Mapped markerPDF current-base semantics move `596 -> 597 / 78`.

## Non-Overlap

This does not repeat accepted marker server startup config planning, upload save/cleanup happy path, upload conversion error cleanup without pagination, remote polling missing-status/exhaustion behavior, direct `/marker` local pagination/image guard, Streamlit app config, benchmark runtime telemetry, or PDF parser/security/font/xref/table/image/form/outline/layout current-base slices. The new behavior is specifically the upload endpoint pagination/image form contract plus remote multipart forwarding and upload-body error cleanup boundary.

## Dependency Closure

No new support component is needed. This slice reuses the native `MarkerServerAdapter`, existing remote-client callback seam, temp upload file handling, lane JSON artifacts, and WordPress smoke harness. Full upstream markerPDF runtime parity remains gated on live FastAPI/Uvicorn execution, Python `requests`, Datalab API access, Poetry, `pdftext`, `pypdfium2`/PDFium, Surya/Torch model downloads, tabled-pdf, Texify, Streamlit/PIL preview paths, benchmark workflows, OCR/rendering helpers, and actual Python/model workers.
