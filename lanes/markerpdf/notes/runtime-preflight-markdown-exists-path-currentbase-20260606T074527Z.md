# markerPDF Runtime Preflight Markdown Exists Path Boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260606T074527Z`
Session: `port-dev-markerpdf-runtime-preflight-20260606T074527Z`
Base accepted HEAD: `14b06837c7204bb9dfbc7b1b9cd2c689fde1b931`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` checks `marker.output.markdown_exists()` at the start of `convert.py::process_single_pdf()` and returns Python `None` before filetype, text-length, converter, or `save_markdown()` work when it is true.
- Upstream `marker/output.py::markdown_exists()` uses `os.path.exists(get_markdown_filepath(...))`, so a directory occupying the generated `.md` path counts as existing output.
- Source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py` and `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/output.py`.
- Live Python, Torch multiprocessing, pdftext, pypdfium/PDFium, Surya/Texify/tabled models, OCR/raster helpers, Streamlit/FastAPI, and external PDF tools remain intentionally out of scope.

## Patch

- `OutputWriter::markdownExists()` now mirrors upstream `os.path.exists()` by using `file_exists()` instead of regular-file-only detection.
- `BatchConverter::processFilePreflightPlan()` records the generated Markdown path, path type, upstream function name, and whether a directory collision counted as existing.
- `BatchConverter::runtimeProcessSinglePdfPreflightReview()` carries those path/type fields into worker-pool review rows, filename maps, existing-output lists, and pool-drain return-boundary evidence.
- Added a focused test file covering direct `process_single_pdf` preflight and `convert.py::main` worker review/result drain for directory collisions at generated Markdown paths.
- Added a WordPress smoke showing the directory collision blocks before filetype, text-length, converter, model, multiprocessing, `save_markdown()`, or external-tool work while a ready control remains convertible.
- Lane status moves `phpPass 2454 -> 2455` and `wordpressScenarios 2092 -> 2093`; mapped manifest coverage is unchanged because this deepens the existing runtime `markdown_exists` behavior instead of adding a new upstream test unit.

## Evidence

Red-first focused run after adding the regression and before the source fix:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeMarkdownExistsPathBoundaryCurrentBaseTest.php`

Result: `1 test files, 4 assertions, 2 failures`; the directory `.md` path was treated as `ready-for-conversion`, producing an OutputWriter directory write warning and missing worker review evidence.

Focused run after fix:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeMarkdownExistsPathBoundaryCurrentBaseTest.php`

Result: `1 test files, 41 assertions, 0 failures`.

Adjacent runtime/batch family:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeMarkdownExistsPathBoundaryCurrentBaseTest.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Result: `3 test files, 1315 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-markdown-exists-path-boundary-currentbase.php`

Result: emitted `directory_markdown_counts_as_existing=true`, `blocked_before_filetype=true`, `blocked_before_text_length=true`, `blocked_before_converter=true`, `worker_review_records_directory=true`, `pool_drain_return_none=true`, `ready_control_invoke_converter=true`, and all Python/model/multiprocessing/external-tool execution flags false.

PHP lint:

- `php -l lanes/markerpdf/src/OutputWriter.php`: no syntax errors.
- `php -l lanes/markerpdf/src/BatchConverter.php`: no syntax errors.
- `php -l lanes/markerpdf/tests/MarkerRuntimeMarkdownExistsPathBoundaryCurrentBaseTest.php`: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-markdown-exists-path-boundary-currentbase.php`: no syntax errors.

Diff hygiene:

- `git diff --check -- lanes/markerpdf`: clean.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP `OutputWriter`, `BatchConverter`, runtime task preflight, filetype detector, supplied text-length callback boundary, and WordPress smoke path. Full upstream runtime execution remains dependency-gated on Python, Torch multiprocessing, pdftext, pypdfium/PDFium, Surya/Texify/tabled models, OCR/raster helpers, Streamlit/FastAPI, and external PDF tools.

## Non-Overlap

This does not repeat accepted runtime output-folder conflicts, metadata JSON load/shape/value boundaries, spawn start-method failures, model handoff branching, conversion-summary ordering, pool process-count creation, worker cleanup, non-PDF sidecar task preflight, numeric truthiness, negative chunk slicing, input file-list admission, post-conversion empty/error boundaries, text-length exception handling, single-document runtime preflight, output artifact metadata, server/benchmark artifact work, or native PDF parser/font/xref/security/image/table/form/outline metadata slices. The bounded behavior is only worker-side `markdown_exists()` path semantics before converter/model invocation or Markdown writes.
