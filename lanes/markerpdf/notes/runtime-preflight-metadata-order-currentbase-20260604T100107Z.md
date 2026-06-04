# markerPDF runtime preflight metadata-file ordering boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260604T100107Z`
Session: `port-dev-markerpdf-runtime-preflight-20260604T100107Z`
Base accepted HEAD: `613c825fdd5671749fa77c50e6601924cba4364e`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` normalizes input/output paths, lists `os.listdir(in_folder)` entries, filters `os.path.isfile`, creates the output folder, computes chunk slicing and optional `--max`, then loads optional `--metadata_file` JSON before building `(filepath, out_folder, metadata.get(os.path.basename(f)), min_length)` task tuples.
- Source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.
- Upstream live execution still requires Python, Torch multiprocessing, pdftext, pypdfium2/PDFium, Surya/Texify/tabled models, and model workers. This slice records only the native no-execution runtime review boundary.

## Patch

- Reordered `BatchConverter::runtimeMainPreflightPlan()` so input folder listing and chunk slicing happen before optional metadata-file JSON loading.
- Reused the selected file list when building task tuples, avoiding a second queue listing inside the runtime preflight plan.
- Added focused assertions that a missing input folder and invalid chunk count are reported before an unreadable metadata file, while a valid input queue still reports the metadata-file read failure.
- Extended the WordPress runtime preflight smoke with review booleans for missing-input and invalid-chunk error precedence.
- Updated lane status from `1044` to `1045` current-base PHP behavior PASS cases.

## Evidence

Focused assigned gate:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Passed: `1 test files, 138 assertions, 0 failures`.

Adjacent runtime/batch gate:

`php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php`

Passed: `3 test files, 317 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Passed: emitted `missing_input_error_precedes_metadata_file=true`, `invalid_chunk_error_precedes_metadata_file=true`, and all Python/model/multiprocessing/external-tool execution flags false.

PHP lint:

- `php -l lanes/markerpdf/src/BatchConverter.php`: no syntax errors.
- `php -l lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`: no syntax errors.

## Dependency Closure

No new support component is needed. This slice reuses native PHP folder/file discovery, chunk planning, JSON metadata loading, task tuple planning, and existing no-execution runtime review fields. GPU/model/OCR/PDFium/PIL execution remains intentionally out of scope under the markerPDF no-GPU directive.

## Non-Overlap

This does not repeat accepted per-file `process_single_pdf` skip gates, numeric `--max`/`--min_length` truthiness, single-document runtime preflight, batch progress/resume, marker app config, server upload/pagination/error artifacts, benchmark callback/error artifacts, output preview artifacts, EmbeddedFiles name-tree limits, or native PDF parser/font/xref/security/image/table/form/outline metadata slices. The bounded behavior is only `convert.py::main` runtime error ordering before metadata-file loading.
