# markerPDF runtime preflight post-conversion boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T021207Z`
Session: `port-dev-markerpdf-runtime-preflight-20260605T021207Z`
Base accepted HEAD: `fc0cfdb875403d27b3d4eabdcceb8e9e19894af2`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::process_single_pdf` calls `convert_single_pdf(filepath, model_refs, metadata=metadata)` after `markdown_exists`, `find_filetype`, and `get_length_of_text` gates.
- When `full_text.strip()` is non-empty, upstream calls `save_markdown(...)` and returns Python `None`.
- When `full_text.strip()` is empty, upstream prints `Empty file: {filepath}.  Could not convert.`, does not call `save_markdown`, and returns Python `None`.
- When conversion raises, upstream prints `Error converting {filepath}: {e}`, prints `traceback.format_exc()`, does not call `save_markdown`, and returns Python `None`.
- Primary source inspected with `curl -fsSL https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.

## Patch

- `BatchConverter::processFile()` now attaches a `conversion_result` review block to `converted`, `skipped-empty-output`, and `error` results.
- The review block records the upstream post-conversion order, whether `save_markdown` is reached, empty-output stdout, exception message/traceback review metadata, Python `None` return boundaries, and no-execution flags.
- `wordpress-marker-runtime-preflight-boundary-currentbase.php` now smokes the three post-conversion branches with supplied PHP callbacks while keeping Python, models, multiprocessing, pdftext, pypdfium, and external PDF tools uninvoked.
- `lane-status.json` records +1 focused PASS case and the new runtime boundary as the current slice.

## Evidence

Baseline focused runtime file before adding this case:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Passed: `1 test files, 467 assertions, 0 failures`.

Red-first focused run after adding assertions before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Failed as expected on missing `conversion_result`.

Focused assigned gate after implementation:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Passed: `1 test files, 508 assertions, 0 failures`.

Adjacent batch/runtime gate:

`php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Passed: `2 test files, 619 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Passed and emitted `post_conversion_saved_boundary="saved-markdown-return-none"`, `post_conversion_saved_writes_markdown=true`, `post_conversion_empty_boundary="empty-output-print-return-none"`, `post_conversion_empty_writes_markdown=false`, `post_conversion_error_boundary="conversion-exception-print-return-none"`, `post_conversion_error_traceback_available=true`, and all Python/model/multiprocessing/external-tool execution flags false.

PHP lint:

- `php -l lanes/markerpdf/src/BatchConverter.php`: no syntax errors.
- `php -l lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`: no syntax errors.

Diff hygiene:

- `git diff --check -- lanes/markerpdf`: passed with no output.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP batch converter, output writer, error-review payloads, and supplied converter callbacks. Full upstream runtime execution remains intentionally out of scope under the no-GPU markerPDF directive because it requires Python, Torch multiprocessing, Surya/Texify/tabled models, `pdftext`, `pypdfium2`/PDFium, OCR/raster helpers, Streamlit/FastAPI paths, and external PDF tooling.

## Non-Overlap

This does not repeat accepted single-document runtime preflight, main runtime admission ordering, numeric truthiness gates, negative chunk slicing, file-list admission, output-folder conflict ordering, conversion-summary stdout, metadata JSON boundaries, model handoff branches, server/config/upload/benchmark artifacts, CCITT/image/filter parser boundaries, or native PDF parser/font/xref/security/image/table/form/outline metadata slices. The bounded behavior is only `convert.py::process_single_pdf` post-conversion return/write/stdout/error review.
