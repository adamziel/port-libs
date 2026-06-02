# markerPDF Runtime Convert Server Upload Pagination

Micro-slice: `runtime-convert-server-upload-pagination-currentbase`
Session: `port-dev-markerpdf-runtime76-20260602T223633Z`
Base accepted HEAD: `ba26c84773f1060ee6d968d946c818afcf0a3c26`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `marker_server.py::convert_pdf_upload()` accepts `max_pages`, `langs`, `force_ocr`, `paginate`, `extract_images`, and a PDF-only upload form field.
- `marker_server.py::convert_pdf_from_upload()` saves the uploaded PDF, mutates `params.filepath`, routes to local or remote conversion, catches body errors as `success=false`, and removes the temporary upload in `finally`.
- `marker_server.py::convert_pdf_remote()` forwards the upload file plus pagination/image fields to Datalab, polls `request_check_url`, and returns the completed response.
- `marker.postprocessors.markdown::get_full_text()` writes paginated page starts as two newlines, `{pnum}`, and the configured page separator.

Primary upstream files inspected:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker_server.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/postprocessors/markdown.py`

## Patch

- Added `MarkerServerAdapter::serverUploadPaginationReview()` for successful `/marker/upload` conversions whose server response contains upstream paginated Markdown.
- The review payload composes upload cleanup state, sanitized upload filename, byte length/hash, normalized form fields, route plan, POST/poll request counts, remote multipart values, server response hashes, and page-segment metadata.
- Raw uploaded PDF bytes, API-key headers, and image payloads are intentionally excluded from the review payload.
- Added `MarkerRuntimeConvertServerUploadPaginationCurrentBaseTest.php`.
- Added `wordpress-marker-runtime-convert-server-upload-pagination-currentbase.php`.
- Updated `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`: behavior tests `921 -> 923` pass / `0` fail; mapped semantics `648 -> 649 / 78`.

## Evidence

PHP lint:

```text
php -l lanes/markerpdf/src/MarkerServerAdapter.php
No syntax errors detected in lanes/markerpdf/src/MarkerServerAdapter.php

php -l lanes/markerpdf/tests/MarkerRuntimeConvertServerUploadPaginationCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/MarkerRuntimeConvertServerUploadPaginationCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-marker-runtime-convert-server-upload-pagination-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-marker-runtime-convert-server-upload-pagination-currentbase.php
```

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeConvertServerUploadPaginationCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS reviews successful remote upload pagination without retaining uploaded PDF bytes
PASS reviews local upload pagination while preserving the upstream upload-route guard boundary

1 test files, 103 assertions, 0 failures
```

Adjacent runtime upload/output gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeConvertServerOutputPaginationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeServerUploadPaginationErrorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeConvertServerUploadPaginationCurrentBaseTest.php lanes/markerpdf/tests/MarkerServerAdapterTest.php
Focused test run: 4 selected test files (root lock skipped)
21 PASS cases
4 test files, 252 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-convert-server-upload-pagination-currentbase.php
```

Passed. The emitted payload preserved `request_methods=["POST","GET"]`, `remote_fields.paginate=true`, `remote_fields.extract_images=false`, `page_sequence=[7,8]`, page segment text without page markers, upload cleanup, WordPress queue metadata, and review-only execution flags. It excluded raw uploaded PDF bytes, the API key, and the base64 image payload.

Lane metadata validation:

```text
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, flags: JSON_THROW_ON_ERROR); echo $f, " OK\n"; }'
```

Whitespace check:

```text
git diff --check -- lanes/markerpdf
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses `MarkerServerAdapter`, the existing local converter callback seam, the remote-client callback boundary, temporary upload handling, and page separator parsing. Full upstream runtime parity remains blocked by live FastAPI/Uvicorn execution, Python `requests`, Datalab access, Poetry, `pdftext`, `pypdfium2`/PDFium, Surya/Torch model downloads, tabled-pdf, Texify, Streamlit/PIL preview paths, benchmark workflows, OCR/rendering helpers, and actual Python/model workers.

## Non-Overlap

This does not repeat accepted marker server config planning, direct `/marker` local option guard, upload pagination remote-error cleanup, completed server-output pagination decoration, remote polling error boundaries, benchmark upload artifact JSON, benchmark output bundles, runtime preview artifacts, or any PDF parser/security/font/xref/table/image/form/outline/metadata current-base slice. The bounded behavior is only successful `/marker/upload` paginated conversion review that ties upload cleanup and remote request forwarding to upstream page markers.
