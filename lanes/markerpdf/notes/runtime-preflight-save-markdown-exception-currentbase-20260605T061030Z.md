# markerPDF runtime save_markdown exception boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T061030Z`
Session: `port-dev-markerpdf-runtime-preflight-20260605T061030Z`
Base accepted HEAD: `71490a93df4cf6044eeb41e4b9e398006aa2b59b`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps `convert.py::process_single_pdf()` inside a broad exception boundary after the `markdown_exists`, `find_filetype`, and `get_length_of_text` gates.
- `convert_single_pdf()` returns `full_text`, `images`, and metadata. If `full_text.strip()` is non-empty, upstream calls `save_markdown()` inside the same exception boundary; any writer failure prints the upstream error and traceback, returns Python `None`, and must not leave a successful Markdown result.
- Source inspected: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py>.

## Patch

- `BatchConverter::processFile()` now separates converter callback failures from `saveMarkdown()` failures.
- Non-empty conversion output that fails during `saveMarkdown()` now returns an error result with `conversion_success=true`, `save_markdown_reached=true`, `save_markdown_writes_markdown=false`, `error_boundary=save-markdown-exception-print-return-none`, and Python `None` return metadata.
- `OutputWriter::saveMarkdownArtifacts()` now suppresses expected `mkdir()` warnings for file-valued subfolder collisions and reports them through the normal `RuntimeException` path.
- The WordPress runtime preflight smoke now emits the save-failure review fields while keeping the fixture outside the batch input queue so accepted file-list/task-arg boundaries do not change.
- Manifest runtime-main current-base behaviors move `3 -> 4`; lane status moves `phpPass 1504 -> 1505` and `wordpressScenarios 1408 -> 1409`.

## Evidence

Red-first focused run after adding the save-failure assertion before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Failed as expected: `1 test files, 694 assertions, 1 failures`; the save_markdown subfolder collision was still reported as `conversion-exception-print-return-none` with `save_markdown_reached=false`.

Focused assigned gate after implementation:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Passed: `1 test files, 712 assertions, 0 failures`.

Adjacent runtime/output family:

`php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/OutputWriterTest.php`

Passed: `3 test files, 848 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Passed and emitted `post_conversion_save_error_boundary=save-markdown-exception-print-return-none`, `post_conversion_save_error_after_conversion=true`, `post_conversion_save_error_reaches_save_markdown=true`, `post_conversion_save_error_writes_markdown=false`, `post_conversion_save_error_traceback_available=true`, `executes_python_or_models=false`, `executes_multiprocessing=false`, and `executes_external_pdf_tools=false`.

PHP lint:

- `php -l lanes/markerpdf/src/BatchConverter.php`: no syntax errors.
- `php -l lanes/markerpdf/src/OutputWriter.php`: no syntax errors.
- `php -l lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`: no syntax errors.

JSON and diff hygiene:

- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'`: passed.
- `git diff --check -- lanes/markerpdf`: passed with no output.

## Dependency Closure

No new support component is needed. This reuses the native PHP batch converter, output writer, error-review payloads, supplied converter callback path, and WordPress smoke path. Full upstream runtime execution remains intentionally out of scope under the no-GPU markerPDF rule because it requires Python, Torch multiprocessing, Surya/Texify/tabled models, `pdftext`, `pypdfium2`/PDFium, OCR/raster helpers, Streamlit/FastAPI paths, and external PDF tooling.

## Non-Overlap

This does not repeat accepted runtime output-folder creation failures before metadata/model handoff, metadata JSON load/shape/value boundaries, process_single_pdf skip gates, text-length exception handling, post-conversion empty-output handling, converter callback exception handling, single-document runtime preflight, server/config/upload/benchmark artifacts, AcroForm comment-reference parsing, or native PDF parser/font/xref/security/image/table/form/outline metadata slices. The bounded behavior is only `process_single_pdf` save_markdown/output-writer failure after non-empty conversion output has already been produced.
