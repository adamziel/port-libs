# markerPDF runtime metadata-shape preflight boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T003109Z`
Session: `port-dev-markerpdf-runtime-preflight-20260605T003109Z`
Base accepted HEAD: `875b50f8ab06c9077ed5d53273541e18ff997d7c`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` loads `--metadata_file` with `json.load(f)`, computes `total_processes`, sets the multiprocessing start method, prepares the model handoff branch, prints the conversion summary, then builds task tuples with `metadata.get(os.path.basename(f))`.
- Therefore syntactically valid JSON that is not a mapping, such as a top-level list, succeeds at `json.load()` and fails later at the `metadata.get(...)` task-argument boundary. It is distinct from malformed JSON, which fails before spawn/model handoff and summary.
- Source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.

## Patch

- `BatchConverter::runtimeMainPreflightPlan()` now uses a runtime-specific metadata-file loader so parsed but non-mapping JSON is recorded as `metadata-get-failed` after spawn/model-handoff and conversion-summary review.
- The public `BatchConverter::loadMetadataFile()` remains strict for native PHP callers that need object-keyed basename metadata.
- The runtime review payload now exposes `metadata_json_type`, `metadata_get_available`, `metadata_shape_error_boundary`, and task-argument error fields.
- The WordPress runtime preflight smoke now emits the list-shaped metadata boundary while confirming no Python/model, multiprocessing, or external PDF tool execution.

## Evidence

Focused assigned gate:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Passed: `1 test files, 375 assertions, 0 failures`.

Adjacent runtime/batch gate:

`php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php`

Passed: `3 test files, 562 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Passed and emitted `metadata_shape_json_type=list`, `metadata_shape_load_success=true`, `metadata_shape_get_available=false`, `metadata_shape_error_boundary=metadata-get-failed`, `metadata_shape_summary_reached=true`, `metadata_shape_task_args_count=0`, `metadata_shape_pool_error_boundary=metadata-get-failed`, and execution flags false.

PHP lint:

- `php -l lanes/markerpdf/src/BatchConverter.php`
- `php -l lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Passed: no syntax errors detected.

JSON validation:

`php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " OK\n"; }'`

Passed for both markerPDF JSON files.

`git diff --check -- lanes/markerpdf`

Passed with no output.

## Counters

- Focused markerPDF PHP PASS cases move `1188 -> 1189`.
- WordPress scenarios move `1171 -> 1172`.
- Mapped runtime-main preflight behaviors move `3 -> 4`.

## Dependency Closure

No new support component is needed. This reuses native PHP JSON decoding, batch runtime planning, chunk/task metadata review, conversion-summary review, and no-execution WordPress smoke output. Full live upstream runtime parity remains intentionally out of scope under the no-GPU markerPDF rule because it requires Python, Torch multiprocessing, Surya/Texify/tabled models, `pdftext`, `pypdfium2`/PDFium, PIL/OCR/raster helpers, model downloads, and Streamlit/FastAPI/Uvicorn runtime paths.

## Non-Overlap

This does not repeat accepted per-file `process_single_pdf` skip gates, metadata-file ordering exceptions, malformed JSON decode failures, output-folder file-conflict admission, runtime numeric gates, negative chunk slicing, input file-list filtering, empty/invalid worker-pool boundaries, conversion-summary ordering, model-handoff branches, spawn-start-method failures, single-document runtime preflight, batch progress/resume, server/benchmark artifacts, or native PDF parser/xref/font/image/form/outline/metadata behavior. The bounded behavior is only parsed-but-non-mapping `metadata_file` JSON failing at `metadata.get(...)` during task tuple construction.
