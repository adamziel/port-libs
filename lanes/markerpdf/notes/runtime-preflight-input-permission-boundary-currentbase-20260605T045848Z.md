# markerPDF runtime input permission preflight boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T045848Z`
Session: `port-dev-markerpdf-runtime-preflight-20260605T045848Z`
Base accepted HEAD: `790f14bb8a62977a43839ba78bb37c3251b8547b`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` builds the input list with `os.listdir(in_folder)` and `os.path.isfile(f)` before `os.makedirs(out_folder, exist_ok=True)`, chunking, metadata loading, spawn setup, model handoff, conversion summary, task tuple construction, or `mp.Pool`.
- Python raises `PermissionError` at `os.listdir()` for unreadable input directories. The native PHP preflight should fail closed at that same boundary instead of converting a failed `scandir()` into an empty successful queue.
- Source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.

## Patch

- `BatchConverter::inputDirectoryListing()` now rejects unreadable input directories and failed directory scans before returning file candidates.
- `runtimeMainPreflightErrorBoundary()` now maps that failure to an upstream-style `PermissionError` with output creation, metadata loading, spawn/model handoff, conversion summary, task args, pool launch, and execution flags all blocked.
- `wordpress-marker-runtime-preflight-boundary-currentbase.php` now emits the unreadable-folder boundary for WordPress batch import review.
- `UPSTREAM_TEST_MANIFEST.json` maps runtime-main preflight behaviors from `3` to `4`; `lane-status.json` moves markerPDF behavior tests from `1438` to `1439` and WordPress scenarios from `1361` to `1362`.

## Evidence

Red-first probe before patch:

`php -r 'require "tools/bootstrap.php"; ... chmod($d, 0000); ... runtimeMainPreflightErrorBoundary($d,$out); ...'`

Observed PHP `scandir(...): Permission denied` warnings and a successful empty plan (`success=true`, `selected_count=0`).

Focused assigned gate:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Passed: `1 test files, 664 assertions, 0 failures`.

Adjacent runtime/batch gate:

`php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php`

Passed: `3 test files, 851 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Passed and emitted `unreadable_input_boundary_error_class=PermissionError`, `unreadable_input_boundary_path_type=directory`, `unreadable_input_boundary_listing_success=false`, `unreadable_input_boundary_blocks_output_creation=true`, `unreadable_input_boundary_metadata_load_reached=false`, `unreadable_input_boundary_task_args_count=0`, and all Python/model/multiprocessing/external-tool execution flags false.

## Dependency Closure

No new support component is needed. This reuses PHP filesystem readability checks, the existing runtime-main preflight planner, the WordPress smoke path, and the native no-execution error-boundary review contract. Full upstream runtime execution remains intentionally out of scope under the no-GPU markerPDF rule because it requires Python, Torch multiprocessing, Surya/Texify/tabled models, `pdftext`, `pypdfium2`/PDFium, PIL/OCR/raster helpers, model downloads, and Streamlit/FastAPI runtime paths.

## Non-Overlap

This does not repeat accepted output-folder conflicts, missing/file-valued input folder boundaries, metadata JSON shape/value boundaries, numeric gate truthiness, negative chunk slicing, spawn-start failures, model handoff branches, worker pool creation/cleanup boundaries, conversion summary ordering, process_single_pdf return-value gates, single-document runtime preflight, server/benchmark artifacts, or native PDF parser/font/xref/security/image/table/form/outline metadata slices. The bounded behavior is only unreadable input-directory `os.listdir()` PermissionError preflight.
