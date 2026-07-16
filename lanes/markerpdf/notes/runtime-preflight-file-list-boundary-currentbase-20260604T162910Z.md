# markerPDF runtime preflight file-list boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260604T162910Z`
Session: `port-dev-markerpdf-runtime-preflight-20260604T162910Z`
Base accepted HEAD: `b18e5d2e4161aadd72774b3f234307a542942db0`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` builds file candidates with `os.listdir(in_folder)` and keeps only `os.path.isfile(f)` entries before chunking, `--max`, metadata lookup, and worker-pool task construction.
- Upstream does not filter regular files by `.pdf` extension at this runtime admission boundary. Non-PDF regular sidecars can become task candidates and are only handled later by per-file preflight/conversion behavior.
- Source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.
- This no-GPU markerPDF lane records the native PHP review boundary only; it does not launch Python, Torch multiprocessing, pdftext, pypdfium/PDFium, Surya/Texify/tabled models, OCR, Streamlit/FastAPI, or external PDF tools.

## Patch

- `BatchConverter::runtimeMainPreflightPlan()` now exposes an `input_listing` review payload for the upstream `os.listdir + os.path.isfile` stage.
- The payload records directory entry basenames, regular-file task candidates, skipped non-file entries, the file-only filter policy, `extension_filter_active=false`, non-PDF regular files, and non-PDF files selected after chunking/max slicing.
- `inputFiles()` now reuses the same listing helper, preserving the existing queue behavior while making the runtime boundary inspectable.
- The WordPress runtime preflight smoke now creates an upload sidecar text file and a directory named `nested.pdf`, proving the sidecar remains a task candidate while the directory is skipped.

## Evidence

Red-first focused check after adding the test and before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Failed as expected: `1 test files, 162 assertions, 1 failures` because `input_listing` was absent.

Focused assigned gate after the patch:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Passed: `1 test files, 177 assertions, 0 failures`.

Adjacent runtime/batch gate:

`php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php`

Passed: `3 test files, 356 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Passed and emitted `runtime_file_candidates` with `upload-notes.txt`, `runtime_skipped_non_file_entries=["nested.pdf"]`, `runtime_extension_filter_active=false`, `runtime_selected_non_pdf_filenames=["upload-notes.txt"]`, `runtime_total_processes=5`, and all Python/model/multiprocessing/external-tool execution flags false.

PHP lint:

- `php -l lanes/markerpdf/src/BatchConverter.php`: no syntax errors.
- `php -l lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`: no syntax errors.

## Dependency Closure

No new support component is needed. This reuses native PHP directory/file discovery, chunk planning, metadata/task tuple planning, and existing no-execution runtime review fields. Full upstream runtime parity remains dependency-gated on Python, Torch multiprocessing, `pdftext`, `pypdfium2`/PDFium, PIL, Surya/Texify/tabled models, model downloads, Streamlit/FastAPI/Uvicorn, OCR/raster helpers, and live model workers; none were executed.

## Non-Overlap

This does not repeat accepted per-file `process_single_pdf` skip gates, runtime metadata-file ordering, numeric `--max`/`--min_length` truthiness, negative chunk slicing, empty/invalid worker pool boundaries, single-document runtime preflight, batch progress/resume, marker app config, server upload/pagination/error artifacts, benchmark callback/error artifacts, output preview artifacts, classic xref rebuild EOF bounds, or native PDF parser/font/xref/security/image/table/form/outline metadata slices. The bounded behavior is only `convert.py::main` file-list admission before chunking and worker task construction.
