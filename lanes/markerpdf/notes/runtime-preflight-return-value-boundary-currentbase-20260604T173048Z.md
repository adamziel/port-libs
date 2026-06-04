# markerPDF runtime preflight return-value boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260604T173048Z`
Session: `port-dev-markerpdf-runtime-preflight-20260604T173048Z`
Base accepted HEAD: `19f55ecbc24e8c512158a6164a801dda418ce296`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::process_single_pdf` returns without a value for existing Markdown, short embedded text, empty conversion output, successful conversion, and caught conversion exceptions.
- The same upstream function explicitly returns `0` only when `--min_length` preflight is active and `find_filetype(filepath)` returns `other`.
- Source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.
- This no-GPU markerPDF lane records the native PHP review boundary only; it does not launch Python, Torch multiprocessing, pdftext, pypdfium/PDFium, Surya/Texify/tabled models, OCR, Streamlit/FastAPI, or external PDF tools.

## Patch

- `BatchConverter::processFilePreflightPlan()` now exposes `upstream_return_value`, `upstream_return_type`, and `upstream_return_boundary`.
- Existing-output, short-text, and ready-for-conversion branches preserve upstream Python-None/null return metadata.
- Unsupported filetypes preserve upstream `return 0` metadata instead of being indistinguishable from other skip branches.
- `wordpress-marker-runtime-preflight-boundary-currentbase.php` now emits return-value review fields for WordPress import queues.

## Evidence

Red-first focused check after adding the return-value assertions and before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Failed as expected: `1 test files, 180 assertions, 1 failures` because `upstream_return_value` and related fields were absent.

Focused assigned gate after the patch:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Passed: `1 test files, 195 assertions, 0 failures`.

Adjacent batch gate:

`php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php`

Passed: `1 test files, 111 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Passed and emitted `unsupported_filetype_returns_zero=true`, `non_unsupported_branches_return_none=true`, `extension-spoof.pdf` return value `0`, existing/short/ready return values `null`, and all Python/model/multiprocessing/external-tool execution flags false.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP output-existence, filetype, embedded-text-length, and no-execution runtime preflight helpers. Full upstream runtime parity remains dependency-gated on Python, Torch multiprocessing, `pdftext`, `pypdfium2`/PDFium, PIL, Surya/Texify/tabled models, model downloads, Streamlit/FastAPI/Uvicorn, OCR/raster helpers, and live model workers; none were executed.

## Non-Overlap

This does not repeat accepted per-file skip status coverage, metadata-file ordering, numeric gate truthiness, negative chunk slicing, file-list admission, empty/invalid worker pool boundaries, single-document runtime preflight, batch progress/resume, marker app config, server upload/pagination/error artifacts, benchmark callback/error artifacts, output preview artifacts, xref repair, or native PDF parser/font/security/image/table/form/outline metadata slices. The bounded behavior is only the upstream `process_single_pdf` return-value boundary visible to batch review.
