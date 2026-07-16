# markerPDF runtime empty queue model handoff boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260606T194312Z`
Session: `port-dev-markerpdf-runtime-preflight-20260606T194312Z`
Base accepted HEAD: `480dfafaed3237c669efe5b3c7297199c7dcf83c`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` computes `total_processes = min(len(files_to_convert), args.workers)`, calls `mp.set_start_method('spawn')`, prepares the model handoff, prints the conversion summary, builds `task_args`, then enters `mp.Pool(processes=total_processes, ...)`.
- There is no upstream early return for an empty selected chunk. A zero-length `files_to_convert` queue still reaches spawn/model-handoff/summary/task-args construction and only then fails at `Pool(processes=0)`.
- Primary source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`

## Patch

- Added `BatchConverter::runtimeMainPreflightPlan()` review metadata under `worker_pool.empty_task_queue_model_handoff`.
- The new review records that empty selected chunks do not short-circuit before spawn, model handoff, share-memory review, conversion summary, or empty `task_args`; it separates the lane-level `empty-task-queue` classification from the upstream `Pool(processes=0)` `ValueError` boundary.
- Added MPS-specific review fields showing that worker model loading is planned when `model_lst` is `None`, but worker initialization is blocked because Pool creation fails before workers start.
- Added a focused WordPress smoke for empty-chunk import queues without launching Python, Torch multiprocessing, models, OCR, Streamlit/FastAPI, or external PDF tools.

## Red-First Evidence

Before the planner field existed:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeEmptyQueueModelHandoffBoundaryCurrentBaseTest.php`

Result: `1 test files, 3 assertions, 3 failures`; failures were undefined `empty_task_queue_model_handoff` review rows.

## Verification

Focused behavior:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeEmptyQueueModelHandoffBoundaryCurrentBaseTest.php`

Result: `1 test files, 58 assertions, 0 failures`.

Adjacent runtime family:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeEmptyQueueModelHandoffBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePoolContextManagerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeWorkerInitBoundaryCurrentBaseTest.php`

Result: `4 test files, 1347 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-empty-queue-model-handoff-currentbase.php`

Result: emitted `empty_queue_review_reached=true`, `spawn_reached_before_empty_pool_failure=true`, `parent_loads_models_before_empty_pool_failure=true`, `pool_creation_error_boundary=pool-process-count-failed`, `mps_worker_model_load_blocked_by_empty_pool=true`, and all Python/model/multiprocessing/external-tool execution flags false.

Syntax:

`php -l lanes/markerpdf/src/BatchConverter.php && php -l lanes/markerpdf/tests/MarkerRuntimeEmptyQueueModelHandoffBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-marker-runtime-empty-queue-model-handoff-currentbase.php`

Result: no syntax errors.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP convert.py runtime preflight planner, directory chunking, model-handoff review, Pool creation review, and WordPress smoke path. Full upstream runtime execution remains intentionally out of scope under the no-GPU markerPDF directive because it requires Python, Torch multiprocessing, Surya/Texify/table models, `pdftext`, `pypdfium2`/PDFium, OCR/raster helpers, and Streamlit/FastAPI runtime paths.

## Non-Overlap

This does not repeat accepted numeric gate truthiness, output-folder conflict, metadata-file loading, scalar/list metadata shape, symlink input, process_single_pdf return-value, pool context-manager, worker-init, model share-memory slot, or conversion-summary-only runtime slices. The bounded behavior is only the empty selected queue branch where upstream still reaches spawn/model handoff/summary/task-args before failing Pool creation.
