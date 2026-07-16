# markerPDF runtime filetype review boundary current base

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260606T182126Z`
Session: `port-dev-markerpdf-runtime-preflight-20260606T182126Z`
Base accepted HEAD: `3dbd03ad2606ba7aa558ebd5c4e8b990b6b82f2a`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::process_single_pdf` applies the optional `min_length` gate before `convert_single_pdf()`.
- Inside that gate, upstream `marker/pdf/utils.py::find_filetype()` calls `filetype.guess(fpath)`, prints `Could not determine filetype for {fpath}` when no kind is available, prints `Found nonstandard filetype {mimetype}` when the mimetype is not supported, and returns `"other"`.
- `process_single_pdf()` returns integer `0` for `"other"` filetypes and does not call `convert_single_pdf()`, model code, or `save_markdown()`.
- Primary source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py` and `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/utils.py`.

## Patch

- Added `FiletypeDetector::findFiletypeReview()` to expose review-only upstream `find_filetype` diagnostics: unknown-kind return, nonstandard-mimetype return, PDF return, stdout message line, and no-model/no-external-tool flags.
- `BatchConverter::processFilePreflightPlan()` now records that filetype review payload when `min_length` is truthy.
- `BatchConverter` runtime worker preflight review now carries per-filename `filetype_review_by_filename`, `filetype_stdout_message_by_filename`, and `filetype_stdout_filenames` so WordPress import queues can explain why unsupported uploads were blocked.
- Added `MarkerRuntimeFiletypeReviewBoundaryCurrentBaseTest.php`.
- Added `wordpress-marker-runtime-filetype-review-boundary-currentbase.php`.
- Status delta: `phpPass 2639 -> 2640`, `wordpressScenarios 2233 -> 2234`; no new mapped upstream denominator behavior claimed.

## Evidence

Red-first focused run before source implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeFiletypeReviewBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL records upstream find_filetype stdout review for unknown and nonstandard uploads
Call to undefined method PortLibs\MarkerPDF\FiletypeDetector::findFiletypeReview()
1 test files, 0 assertions, 1 failures
```

Focused runtime family after patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeFiletypeReviewBoundaryCurrentBaseTest.php lanes/markerpdf/tests/FiletypeDetectorTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/BatchConverterTest.php
4 test files, 1374 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-filetype-review-boundary-currentbase.php
```

Emits `empty_upload_return_boundary=unknown-kind-return-other`, `image_upload_mimetype=image/png`, `image_upload_return_boundary=nonstandard-filetype-return-other`, `ready_pdf_status=ready-for-conversion`, `unsupported_uploads_block_converter=true`, `executes_python_or_models=false`, `executes_multiprocessing=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP file header sniffing, marker settings supported-filetype mapping, batch runtime preflight planning, process-single-PDF review rows, and the WordPress smoke path. Full upstream runtime parity remains gated by live Python, `filetype`, pdftext, pypdfium/PDFium, Torch multiprocessing, Surya/Texify/tabled models, OCR/raster helpers, Streamlit/FastAPI, and external PDF tools; none were executed.

## Non-Overlap

This does not repeat runtime numeric truthiness, output-folder conflict, metadata-file load/shape/value, spawn start-method, worker pool cleanup, worker return drain, text-length exception, markdown_exists, file-list extension filtering, symlink task identity, model share-memory handoff, server config/upload, remote GoToR annotation, or native PDF parser/font/xref/security/image/table/form/outline metadata slices. The bounded behavior is only upstream `find_filetype()` stdout diagnostics and `"other"` return boundaries during `process_single_pdf` `min_length` preflight.
