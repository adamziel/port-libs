# markerPDF runtime preflight output-parent boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T053207Z`

Base accepted HEAD: `84ab27111aed7a1f7263c1f4f4ca36b52258db2f`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` normalizes input/output folders with `os.path.abspath`, builds the input file list, then calls `os.makedirs(out_folder, exist_ok=True)` before chunk slicing, metadata-file JSON loading, spawn setup, model handoff, conversion summary, task tuple construction, or `mp.Pool`.
- Python `os.makedirs(child, exist_ok=True)` raises `NotADirectoryError` when an intermediate output path component is a regular file. The native PHP runtime preflight should report that boundary before metadata or worker planning.
- Source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.

## Red First

Probe on the accepted base:

`php -r 'require "tools/bootstrap.php"; ... runtimeMainPreflightPlan($input, $parentFile . "/marker-output", workers: 2) ...'`

Observed tuple:

`[false,null,true,1,true]`

Those values are `output_folder_creation_blocked`, `output_folder_creation_error_class`, `metadata_load_reached`, `task_args_count`, and `console_summary_reached`, proving the plan incorrectly passed through a file-valued parent.

## Patch

- `BatchConverter::outputFolderCreationPlan()` now checks the output folder's ancestor path components without creating directories.
- A file-valued parent records `output-folder-parent-not-directory`, `NotADirectoryError`, parent conflict path/type, and blocks at the same `os.makedirs(out_folder, exist_ok=True)` stage as upstream.
- The blocked plan keeps chunking, metadata loading, spawn/model handoff, conversion summary, task args, Pool launch, Python, model execution, multiprocessing, and external PDF tools unreached.
- `wordpress-marker-runtime-preflight-boundary-currentbase.php` now emits parent-conflict review fields for WordPress import queues.

## Verification

Focused test:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Passed: `1 test files, 691 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Expected output includes `output_parent_conflict_error_boundary=output-folder-parent-not-directory`, `output_parent_conflict_error_class=NotADirectoryError`, metadata/task/summary blocked fields, and all Python/model/external-tool flags false.

## Dependency Closure

No new support component is needed. This slice reuses native PHP filesystem inspection and the existing batch runtime preflight planner. Full upstream runtime execution remains intentionally out of scope under the no-GPU markerPDF lane rule because it requires Python, Torch multiprocessing, pdftext, pypdfium/PDFium, Surya/Texify/tabled models, OCR/raster helpers, Streamlit/FastAPI runtime paths, and external PDF tools.

## Non-Overlap

This does not repeat accepted final output-folder file conflicts, input-folder list/permission failures, metadata JSON shape/value boundaries, numeric gate truthiness, negative chunk slicing, spawn-start failures, model handoff branches, worker pool creation/cleanup boundaries, conversion summary ordering, process_single_pdf return-value gates, single-document runtime preflight, server/benchmark artifacts, or native PDF parser/font/xref/security/image/table/form/outline metadata slices. The bounded behavior is only intermediate output path component failure at the upstream `os.makedirs(out_folder, exist_ok=True)` runtime boundary.
