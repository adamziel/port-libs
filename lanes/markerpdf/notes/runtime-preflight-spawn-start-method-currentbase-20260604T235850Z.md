# markerPDF runtime spawn start-method preflight boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260604T235850Z`
Session: `port-dev-markerpdf-runtime-preflight-20260604T235850Z`
Base accepted HEAD: `43d6c6085912b0a2e7f68f49d9869c535f444985`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` computes absolute input/output folders, filters `os.listdir()` to regular files, calls `os.makedirs(out_folder, exist_ok=True)`, chunks and max-slices the queue, loads optional `metadata_file`, computes `total_processes`, then calls `mp.set_start_method('spawn')`.
- If that start method is already set, upstream raises `RuntimeError("Set start method to spawn twice...")` before the model handoff, stdout conversion summary, task tuple construction, multiprocessing pool launch, or `process_single_pdf`.
- Primary source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.

## Patch

- `BatchConverter::runtimeMainPreflightPlan()` now accepts `spawnStartMethodAlreadySet` and emits a `spawn_start_method` review block.
- The repeated-spawn branch preserves input listing, output-folder admission, chunking, metadata-file success, selected/missing metadata rows, and the upstream process count computed before `mp.set_start_method('spawn')`.
- The same branch blocks model handoff, conversion summary, task args, pool launch, Python/model execution, multiprocessing, and external PDF tools with `spawn-start-method-failed`.
- `wordpress-marker-runtime-preflight-boundary-currentbase.php` now exposes the spawn-collision review fields for WordPress batch import queues.
- `UPSTREAM_TEST_MANIFEST.json` maps `markerRuntimeMainPreflightBoundaryCurrentBaseBehaviors` from `3` to `4`; `lane-status.json` moves `phpPass` from `1148` to `1149` and `wordpressScenarios` from `1138` to `1139`.

## Evidence

Focused assigned gate:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Passed: `1 test file / 344 assertions / 0 failures`.

Adjacent runtime/batch gate:

`php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php`

Passed: `3 test files / 531 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Passed and emitted `spawn_start_method_error_boundary=spawn-start-method-failed`, `spawn_collision_metadata_loaded=true`, `spawn_collision_task_args_count=0`, `spawn_collision_conversion_summary_reached=false`, `executes_python_or_models=false`, `executes_multiprocessing=false`, and `executes_external_pdf_tools=false`.

Syntax and whitespace:

- `php -l lanes/markerpdf/src/BatchConverter.php`: no syntax errors.
- `php -l lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`: no syntax errors.
- `git diff --check -- lanes/markerpdf`: passed.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP batch runtime planner, directory/file discovery, output-folder admission, metadata loading, chunk slicing, worker-count review, and no-execution WordPress smoke path. Full upstream runtime execution remains dependency-gated on Python, Torch multiprocessing, `pdftext`, `pypdfium2`/PDFium, Surya/Texify/tabled models, model downloads, OCR/raster helpers, Streamlit/FastAPI/Uvicorn paths, and external PDF tools; none were run.

## Non-Overlap

This does not repeat accepted per-file `process_single_pdf` skip/return boundaries, runtime metadata ordering, malformed metadata JSON, output-folder conflict ordering, numeric gates, file-list filtering, model handoff MPS/CPU branching, conversion summary ordering, empty/invalid worker-pool boundaries, single-document preflight, batch progress/resume, marker app/server/benchmark artifacts, or native PDF parser/font/xref/security/image/table/form/outline metadata behavior. The bounded behavior is only `convert.py::main` repeated spawn start-method failure ordering before model handoff and pool launch.
