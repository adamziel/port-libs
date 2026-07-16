# markerPDF runtime worker path-kind boundary current-base

Slice: `markerpdf-runtime-preflight-boundary-current-base-20260608T102954Z`
Base: `6c009f4b63e232febe2df2538598096a435fd432`
Date: 2026-06-08 UTC

## Source Truth

Upstream `sddai/markerPDF` at manifest commit
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` builds `task_args` from
`os.listdir()` plus `os.path.isfile()` before `mp.Pool(...).imap()`. The
worker-side `process_single_pdf()` then checks `markdown_exists()` first and
only calls `find_filetype(filepath)` when `min_length` is truthy. If
`find_filetype()` cannot identify a file, it returns `"other"` and
`process_single_pdf()` returns `0` before `convert_single_pdf()`.

Primary inspected sources:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/utils.py`

## Behavior Ported

`BatchConverter::processFilePreflightPlan()` already records selected worker
paths that disappear, become broken symlinks, become non-files, or become
unreadable before worker preflight. This slice adds the missing directory
classification for queued paths whose symlink target changes from a regular
file to a directory before `process_single_pdf()` runs:

- `selected_input_directory_at_worker_preflight`
- aggregate `selected_input_directory_filenames`
- aggregate `filepath_is_file_at_worker_preflight_by_filename`
- aggregate `filepath_is_readable_at_worker_preflight_by_filename`

The generic `selected-input-not-file-before-worker-preflight` boundary remains
unchanged, so existing consumers keep the accepted non-file behavior while
WordPress import review can distinguish directory target swaps from other
non-file races.

## Evidence

Red-first focused run before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeWorkerPathKindBoundaryCurrentBaseTest.php`

Result: failed after 9 assertions with undefined
`selected_input_directory_at_worker_preflight`.

After implementation:

`php -l lanes/markerpdf/src/BatchConverter.php && php -l lanes/markerpdf/tests/MarkerRuntimeWorkerPathKindBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-marker-runtime-worker-path-kind-boundary-currentbase.php`

Result: no syntax errors.

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeWorkerPathKindBoundaryCurrentBaseTest.php`

Result: `1 test files, 44 assertions, 0 failures`.

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntime*CurrentBaseTest.php`

Result: `36 test files, 2980 assertions, 0 failures`.

`php lanes/markerpdf/examples/wordpress-marker-runtime-worker-path-kind-boundary-currentbase.php`

Result: exits 0 and emits
`directory_symlink_boundary=selected-input-not-file-before-worker-preflight`,
`directory_symlink_path_type=directory`,
`directory_symlink_classified=true`,
`directory_symlink_rejected_before_converter=true`,
`broken_symlink_boundary=selected-input-broken-symlink-before-worker-preflight`,
`broken_symlink_path_type=broken-symlink`,
`broken_symlink_rejected_before_converter=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP runtime
preflight planner, filesystem path-kind probes, `FiletypeDetector`, focused
test harness, and WordPress smoke pattern. Live Python, Torch multiprocessing,
pdftext, pypdfium/PDFium, Surya OCR/layout models, Texify, Streamlit/FastAPI
workers, raster decoding, action execution, external PDF tools, and online
services were not executed.

## Non-Overlap

This does not repeat accepted runtime input-folder list errors, output symlink
makedirs boundaries, input directory symlink listing, duplicate symlink target
task identity, selected-file missing behavior, markdown-exists path collisions,
generic filetype review, metadata-file ordering/shape/value boundaries,
worker-init/model-handoff, pool context/result drain, or native PDF parser
font/xref/security/image/table/form/outline metadata slices. The bounded
behavior is only worker-side selected path kind review for queued symlink
targets that become directories or broken symlinks before the optional
`min_length` filetype gate.
