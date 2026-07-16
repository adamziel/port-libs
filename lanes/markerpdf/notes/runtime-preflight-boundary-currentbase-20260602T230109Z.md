# Runtime preflight boundary current base, 2026-06-02

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260602T230109Z`
Session: `port-dev-markerpdf-runtime-preflight-20260602T230109Z`
Base accepted HEAD: `6f594c5023956f9645fdddcb49b23249e78ea785`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` top-level `convert.py` has `process_single_pdf(args)` unpack `(filepath, out_folder, metadata, min_length)`, checks `markdown_exists(out_folder, fname)` first, then applies `--min_length` through `find_filetype(filepath)` and `get_length_of_text(filepath)` before calling `convert_single_pdf()` and `save_markdown()`.
- Upstream runtime still executes Python, Torch multiprocessing, pdftext/pypdfium, and model workers for live conversion. This slice records the PHP review/preflight boundary only.
- Primary upstream URL inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`

## Patch

- Added `BatchConverter::processTaskPreflightPlan()` and `BatchConverter::processFilePreflightPlan()`.
- The review plan records the upstream preflight order: `markdown_exists`, `find_filetype`, `get_length_of_text`, `convert_single_pdf`, then `save_markdown`.
- Preflight statuses now expose `skipped-existing`, `skipped-unsupported-filetype`, `skipped-short-text`, and `ready-for-conversion`, with metadata keys, filetype/text-length checks, converter/save handoff booleans, and non-execution flags.
- `BatchConverter::processFile()` now reuses the preflight plan and carries it into skipped, converted, empty-output, and error results.
- Added WordPress smoke `examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`.
- Updated lane status and manifest: behavior tests `945 -> 947`, mapped runtime semantics `664 -> 665 / 78`.

## Verification

Before patch:

`php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php`

Result: `1 test files, 61 assertions, 0 failures` with 8 PASS cases.

After patch:

`php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php`

Result: `1 test files, 103 assertions, 0 failures` with 10 PASS cases.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Result: emitted `skipped-existing`, `skipped-unsupported-filetype`, `skipped-short-text`, and `ready-for-conversion`; all execution flags false.

Adjacent runtime gate:

`php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/ChunkConversionPlannerTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php lanes/markerpdf/tests/SingleDocumentConverterTest.php`

Result: `4 test files, 225 assertions, 0 failures`.

PHP lint:

- `php -l lanes/markerpdf/src/BatchConverter.php`: no syntax errors.
- `php -l lanes/markerpdf/tests/BatchConverterTest.php`: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`: no syntax errors.

Metadata/diff:

- `jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json`: passed.
- `git diff --check -- lanes/markerpdf`: passed.

## Dependency Closure

No new support component is needed. This reuses `OutputWriter::markdownExists()`, `FiletypeDetector::findFiletype()`, `PdfTextExtractor::getLengthOfText()` through `BatchConverter::embeddedTextLength()`, and the existing supplied-converter callback path. Full upstream parity remains gated by Python, Torch multiprocessing, `pdftext`, `pypdfium2`/PDFium, Surya/Texify/tabled model downloads, Streamlit/FastAPI/Uvicorn, OCR/raster helpers, and live model workers.

## Non-Overlap

This does not repeat accepted runtime conversion multiprocessing planning, batch progress/resume metadata, per-file conversion error telemetry, marker app/server config planning, upload pagination/output artifact boundaries, or any PDF parser/security/font/xref/table/image/form/outline metadata current-base slice. The bounded behavior is only the inspectable `process_single_pdf` preflight decision before converter invocation.
