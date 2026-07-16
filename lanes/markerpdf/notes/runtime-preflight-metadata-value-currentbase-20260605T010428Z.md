# markerPDF runtime metadata value preflight boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T010428Z`
Session: `port-dev-markerpdf-runtime-preflight-20260605T010428Z`
Base accepted HEAD: `0ea8dd0772ccf1520f53c121288a94ef07992eca`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` loads a truthy `--metadata_file` with `json.load(f)`, then builds task tuples with `metadata.get(os.path.basename(f))`.
- Upstream `marker/convert.py::convert_single_pdf()` only calls `metadata.get("languages", langs)` when the per-file metadata value is truthy. Therefore a top-level metadata JSON object can contain dict, list, null, scalar, zero, or missing per-file values; task construction still succeeds, while truthy non-dict values fail later inside `convert_single_pdf()` and are caught by `process_single_pdf()`.
- Source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py` and `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py`.

## Patch

- `BatchConverter::runtimeMainPreflightPlan()` now preserves per-file metadata values from runtime metadata JSON instead of rejecting scalar values as a metadata-file shape error.
- Added a `metadata_value_review` block that records selected per-file metadata value types, truthy non-dict filenames, falsy non-dict filenames, and the review-only `convert-single-pdf-metadata-get-failed` boundary.
- Worker-pool task args now preserve scalar/list/null values for runtime review while keeping pool launchability and no-execution flags intact.
- The WordPress runtime preflight smoke now emits metadata value review fields for dict, list, null, scalar, zero, and missing metadata cases.

## Evidence

Focused assigned gate:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Passed: `1 test files, 413 assertions, 0 failures`.

Adjacent runtime/batch gate:

`php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php`

Passed: `3 test files, 600 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Passed and emitted `metadata_value_truthy_non_mapping_filenames=["ready-for-marker.pdf","upload-notes.txt"]`, `metadata_value_falsy_non_mapping_filenames=["extension-spoof.pdf","short-text.pdf"]`, `metadata_value_conversion_error_boundary=convert-single-pdf-metadata-get-failed`, `metadata_value_blocks_task_args=false`, `metadata_value_pool_launchable=true`, and execution flags false.

PHP lint:

- `php -l lanes/markerpdf/src/BatchConverter.php`
- `php -l lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Passed: no syntax errors detected.

## Counters

- Focused markerPDF PHP PASS cases move `1220 -> 1221`.
- WordPress scenarios move `1196 -> 1197` in lane status.
- Manifest mapped runtime-main preflight behaviors move `3 -> 4`; manifest mapped semantics move `717 -> 718` without changing the upstream denominator.

## Dependency Closure

No new support component is needed. This reuses native PHP JSON decoding, runtime batch preflight planning, task-argument review, conversion-summary review, and the existing no-execution WordPress smoke path. Full live upstream runtime parity remains intentionally out of scope under the no-GPU markerPDF rule because it requires Python, Torch multiprocessing, Surya/Texify/tabled models, `pdftext`, `pypdfium2`/PDFium, PIL/OCR/raster helpers, model downloads, and Streamlit/FastAPI/Uvicorn runtime paths.

## Non-Overlap

This does not repeat accepted per-file `process_single_pdf` skip gates, metadata-file ordering exceptions, malformed JSON decode failures, top-level non-mapping JSON `metadata.get(...)` failure, output-folder file-conflict admission, runtime numeric gates, negative chunk slicing, input file-list filtering, empty/invalid worker-pool boundaries, conversion-summary ordering, model-handoff branches, spawn-start-method failures, single-document runtime preflight, batch progress/resume, server/benchmark artifacts, or native PDF parser/xref/font/image/form/outline/metadata behavior. The bounded behavior is only per-file metadata object values after successful `metadata.get(os.path.basename(f))` task tuple construction.
