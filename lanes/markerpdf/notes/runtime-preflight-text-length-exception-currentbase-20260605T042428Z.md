# markerPDF Runtime Preflight Text-Length Exception Boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T042428Z`
Session: `port-dev-markerpdf-runtime-preflight-20260605T042428Z`
Base accepted HEAD: `53dec48565a675bc0cc37c451daad9c43f45b44d`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` wraps `process_single_pdf()` conversion work in `try/except Exception`, including the `--min_length` `find_filetype()` and `get_length_of_text()` preflight before `convert_single_pdf()`.
- A `get_length_of_text()` failure prints `Error converting {filepath}: {e}` and a traceback, then returns Python `None`; it must not call `convert_single_pdf()` or `save_markdown()`.
- Source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.
- Live Python, Torch multiprocessing, pdftext, pypdfium/PDFium, Surya/Texify/tabled models, OCR/raster helpers, Streamlit/FastAPI, and external PDF tools remain intentionally out of scope.

## Patch

- `BatchConverter::processFilePreflightPlan()` now catches text-length callback failures after PDF filetype admission and records a review-only `preflight-exception-print-return-none` boundary.
- The returned preflight row includes `error_stage=get_length_of_text`, exception class/message, upstream-style error output/traceback metadata, Python `None` return semantics, and `should_invoke_converter=false`.
- `BatchConverter::processFile()` now carries non-ready preflight return metadata into skipped/error results, so text-length preflight errors do not invoke supplied converters or write Markdown.
- The existing WordPress runtime preflight smoke now emits `text_length_error_*` fields.
- Manifest runtime main preflight behavior coverage moves `3 -> 4`; lane status moves `phpPass 1405 -> 1406` and `wordpressScenarios 1339 -> 1340`.

## Evidence

Red-first focused run after adding the regression and before the fix:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Result: `1 test files, 613 assertions, 1 failures`; the `native pdftext length boundary unavailable` exception escaped.

Focused run after fix:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Result: `1 test files, 635 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Result: emitted `text_length_error_status=error`, `text_length_error_stage=get_length_of_text`, `text_length_error_boundary=preflight-exception-print-return-none`, `text_length_error_converter_called=false`, `text_length_error_writes_markdown=false`, and all Python/model/multiprocessing/external-tool execution flags false.

## Dependency Closure

No new support component is needed. This reuses the native PHP runtime planner, filetype detection, output markdown detection, supplied text-length callback boundary, and WordPress smoke path. Full runtime parity remains dependency-gated on live Python/Torch multiprocessing, pdftext, pypdfium/PDFium, Surya/Texify/tabled models, OCR/raster helpers, Streamlit/FastAPI, and external PDF tools.

## Non-Overlap

This does not repeat accepted runtime output-folder conflicts, metadata JSON load/shape/value boundaries, spawn start-method failures, model handoff branching, conversion-summary ordering, pool process-count creation, worker cleanup, non-PDF sidecar task preflight, numeric truthiness, negative chunk slicing, input file-list admission, post-conversion empty/error boundaries, single-document runtime preflight, server/benchmark artifact work, pdftext dictionary keep_chars/core/script slices, or native PDF parser/font/xref/security/image/table/form/outline metadata slices. The bounded behavior is only worker-side `get_length_of_text()` exception handling before converter/model invocation or Markdown writes.
