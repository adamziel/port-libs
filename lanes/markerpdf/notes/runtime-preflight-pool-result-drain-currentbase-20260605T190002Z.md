# markerPDF Runtime Pool Result Drain Boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T190002Z`
Session: `port-dev-markerpdf-runtime-preflight-20260605T190002Z`
Base accepted HEAD: `4e4dadda554ebde678816b2f0359edfa9505904d`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` drains worker results with:

`list(tqdm(pool.imap(process_single_pdf, task_args), total=len(task_args), desc="Processing PDFs", unit="pdf"))`

The returned list is not assigned. Therefore `process_single_pdf()` return values such as unsupported-filetype `0` and Python `None` from skipped, short, empty, error, or converted branches advance tqdm progress but do not affect the conversion summary, task queue, or cleanup path.

Primary source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`

## Implementation

- `BatchConverter::runtimeMainPreflightPlan()` now adds `worker_pool.pool_result_drain` after per-file `process_single_pdf` preflight and before pool cleanup.
- The review records the `pool.imap(...)` call, `list(tqdm(...))` drain, lack of assignment, progress total source, ordered return rows, `0` return filenames, `NoneType` return filenames, status/return-boundary maps, and the cleanup-after-drain boundary.
- Failed Pool creation keeps result draining blocked with `pool-process-count-failed`, preserving the existing zero/negative worker behavior.
- `wordpress-marker-runtime-preflight-boundary-currentbase.php` now asserts and emits that unsupported sidecar/filetype `0` returns and `None` returns are ignored by `convert.py::main` before cleanup, without launching Python, Torch multiprocessing, model workers, or external PDF tools.

## Evidence

Red-first focused run before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Result: `1 test files, 1106 assertions, 1 failures`; failure was missing `worker_pool.pool_result_drain`.

Focused run after implementation:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Result: `1 test files, 1142 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Result: passed and emitted `runtime_pool_result_drain_reached=true`, `runtime_pool_result_values_ignored=true`, `runtime_pool_result_zero_return_filenames=[upload-notes.txt, extension-spoof.pdf]`, `runtime_pool_result_none_return_filenames=[ready-for-marker.pdf, short-text.pdf, already-imported.pdf]`, `runtime_pool_result_cleanup_after_drain=true`, and `zero_worker_result_drain_blocked_by=pool-process-count-failed`.

## Non-Overlap

This does not repeat accepted runtime input/output admission, metadata JSON load/shape/value boundaries, numeric truthiness, negative chunk slicing, spawn/model handoff, share-memory slot review, Pool creation, worker cleanup, `process_single_pdf` preflight return values, post-conversion error handling, save-markdown exceptions, single-document runtime preflight, server/benchmark artifacts, or native PDF parser/font/xref/security/image/table/form/outline metadata slices. The bounded behavior is only the upstream main-loop result-drain boundary after `pool.imap()` and before cleanup.

## Dependency Closure

No new support component is needed. This reuses native `BatchConverter` runtime planning and per-file preflight review metadata. Live OCR, Surya/Texify/Torch, pypdfium/PDFium, Streamlit/FastAPI workers, Python multiprocessing, and external PDF tools remain intentionally out of scope for the current no-GPU markerPDF lane.
