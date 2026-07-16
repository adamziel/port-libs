# markerPDF runtime main preflight boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260603T085712Z`
Session: `port-dev-markerpdf-runtime-preflight-20260603T085712Z`
Base accepted HEAD: `b658d7df866a87d85e21955ec3e4c2081cbbc693`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` sets `PYTORCH_ENABLE_MPS_FALLBACK=1`, `IN_STREAMLIT=true`, and `PDFTEXT_CPU_WORKERS=1` at import time, normalizes input/output folders with `os.path.abspath`, creates the output folder with `os.makedirs(..., exist_ok=True)`, chunks `os.listdir()` files with `math.ceil`, loads optional `--metadata_file` JSON by basename, sets torch multiprocessing to `spawn`, clamps `total_processes = min(len(files_to_convert), args.workers)`, builds `(filepath, out_folder, metadata.get(basename), min_length)` task tuples, and then hands those tuples to `process_single_pdf`.
- Upstream still requires Python, Torch multiprocessing, model loading, `pdftext`, `pypdfium2`/PDFium, and model workers for live execution. This slice records only the native PHP review/admission boundary.

Source URLs inspected:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert_single.py`

## Patch

- Added `BatchConverter::runtimeMainPreflightPlan()` as a no-execution review payload for `convert.py::main`.
- The plan records absolute input/output paths, output-folder creation requirements without mutating the filesystem, chunk/max slicing, selected filenames, metadata-file basename coverage, task tuples, worker-count clamping, empty-task queue risk, invalid-worker risk, and the per-file `process_single_pdf` handoff boundary.
- Updated `wordpress-marker-runtime-preflight-boundary-currentbase.php` to emit the runtime main preflight order, selected filenames, metadata coverage, worker pool launchability, and no-execution flags alongside existing per-file preflight statuses.
- Updated lane status and manifest counts: behavior tests `1000 -> 1002`, WordPress scenarios `1000 -> 1002`, mapped markerPDF semantics `688 / 78 -> 689 / 78`.

## Evidence

Focused assigned gate:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Passed: `1 test files, 112 assertions, 0 failures`.

Adjacent runtime/batch gate:

`php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php`

Passed: `3 test files, 291 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Passed: emitted `runtime_main_order`, selected filenames, metadata filenames, missing metadata filenames, `runtime_total_processes=4`, `runtime_pool_launchable=true`, `runtime_pool_error_boundary=null`, and all Python/model/multiprocessing/external-tool execution flags false.

PHP lint:

- `php -l lanes/markerpdf/src/BatchConverter.php`
- `php -l lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Passed: no syntax errors detected.

## Dependency Closure

No new support component is needed. This slice reuses native PHP task planning, metadata-file JSON loading, output markdown existence/path helpers, and existing runtime planner semantics. Full upstream runtime parity remains gated on Python, Torch multiprocessing, Surya/Texify/tabled model workers, `pdftext`, `pypdfium2`/PDFium, PIL, model downloads, Streamlit/FastAPI/Uvicorn runtime paths, and external OCR/rendering helpers; none were executed.

## Non-Overlap

This does not repeat accepted per-file `process_single_pdf` skip gates, single-document `convert_single.py` admission, MarkerRuntimePlanner multiprocessing model-sharing details, batch progress/resume metadata, runtime conversion pool planning, server upload/pagination/error artifacts, benchmark callback/error artifacts, output preview artifacts, or native PDF parser/font/xref/security/image/table/form/outline metadata slices. The bounded behavior is only `convert.py::main` batch admission before task-pool launch.
