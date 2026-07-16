# markerPDF Runtime Convert Server Output Pagination Boundary

Micro-slice: `runtime-convert-server-output-pagination-boundary-currentbase`
Session: `port-dev-markerpdf-runtime67-20260602T214835Z`
Base accepted HEAD: `46b872b82e6663ed85da04f0c1274e2577b1e5b5`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` describes `CommonParams.paginate` as page output separated by two newlines, `{PAGE_NUMBER}`, 48 hyphen characters, and two newlines.
- Upstream `marker_server.py::convert_pdf_remote()` forwards `paginate` and `extract_images` to the Datalab multipart request, then returns the completed poll payload unchanged.
- Upstream `marker_server.py::convert_pdf_local()` returns `{"markdown": full_text, "images": encoded, "metadata": metadata, "success": True}`.
- Upstream `marker.postprocessors.markdown::merge_lines()` inserts page-start blocks when `settings.PAGINATE_OUTPUT` is enabled, and `get_full_text()` writes `"\n\n{" + str(block.pnum) + "}" + settings.PAGE_SEPARATOR`.

Primary upstream files inspected:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker_server.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/postprocessors/markdown.py`

## Patch

- Added `MarkerServerAdapter::UPSTREAM_PAGE_SEPARATOR` and `serverOutputPaginationPlan()` for upstream paginated Markdown output markers.
- Completed local and remote conversion responses now receive `metadata.server_output_pagination` only when the returned Markdown actually contains the upstream page marker pattern.
- Existing unmarked server responses keep their previous shape, even when pagination was requested.
- The response decoration preserves exact upstream Markdown and existing metadata while exposing page sequence, marker offsets, segment hashes, monotonicity, and review-only execution flags for WordPress import splitting.
- Added `MarkerRuntimeConvertServerOutputPaginationBoundaryCurrentBaseTest.php`.
- Added `wordpress-marker-runtime-convert-server-output-pagination-boundary-currentbase.php`.
- Updated `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`: behavior tests `870 -> 873` pass / `0` fail; mapped semantics `614 -> 615 / 78`.

## Evidence

Focused runtime output test:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeConvertServerOutputPaginationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records upstream page separators on completed remote conversion output
PASS preserves paginated local upload output while keeping upload route option boundaries
PASS keeps unmarked server output responses unchanged even when pagination was requested

1 test files, 52 assertions, 0 failures
```

Adjacent marker server family:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerServerAdapterTest.php lanes/markerpdf/tests/MarkerRuntimeServerUploadPaginationErrorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeServerConfigErrorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeConvertServerOutputPaginationBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 192 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-convert-server-output-pagination-boundary-currentbase.php
```

Passed: emitted `request_methods=["POST","GET"]`, `remote_paginate=true`, `remote_extract_images=false`, `metadata_page_count=2`, `metadata_page_sequence=[1,2]`, marker review metadata, segment text without marker strings, preserved WordPress queue metadata, and execution flags for FastAPI/Uvicorn/live HTTP/Python/models/external PDF tools all false.

PHP lint:

```text
php -l lanes/markerpdf/src/MarkerServerAdapter.php
No syntax errors detected in lanes/markerpdf/src/MarkerServerAdapter.php

php -l lanes/markerpdf/tests/MarkerRuntimeConvertServerOutputPaginationBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/MarkerRuntimeConvertServerOutputPaginationBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-marker-runtime-convert-server-output-pagination-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-marker-runtime-convert-server-output-pagination-boundary-currentbase.php
```

Lane metadata validation:

```text
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, flags: JSON_THROW_ON_ERROR); echo $f, " OK\n"; }'
lanes/markerpdf/lane-status.json OK
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json OK
```

Whitespace check:

```text
git diff --check -- lanes/markerpdf
```

Passed with no output.

## Dependency Closure

No new support component is needed. This slice reuses the native `MarkerServerAdapter`, existing remote callback boundary, local conversion normalizer, and WordPress smoke pattern. Full upstream runtime parity remains gated by FastAPI/Uvicorn, Python `requests`, pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, model downloads, and live Python multiprocessing/server infrastructure.

## Non-Overlap

This does not repeat marker server startup config, upload pagination/error cleanup, direct `/marker` local option guard, remote polling malformed-status/exhaustion handling, runtime preview artifact persistence, benchmark runtime error telemetry, or PDF parser/security/font/xref/table/image/form/outline/metadata slices. The bounded behavior is only completed marker server conversion output that already contains upstream paginated Markdown page markers.
