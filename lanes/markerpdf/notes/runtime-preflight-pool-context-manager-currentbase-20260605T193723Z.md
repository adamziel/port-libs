# markerPDF Runtime Pool Context Manager Boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T193723Z`
Session: `port-dev-markerpdf-runtime-preflight-20260605T193723Z`
Base accepted HEAD: `6f05ed9ef56a3e997ebab442f86ef1aa7076de74`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` builds `task_args` and then enters:

`with mp.Pool(processes=total_processes, initializer=worker_init, initargs=(model_lst,)) as pool:`

Inside that context manager it drains `list(tqdm(pool.imap(process_single_pdf, task_args), ...))` and assigns `pool._worker_handler.terminate = worker_exit`. Only after the `with` block exits does it delete `model_lst`.

Primary source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`

## Implementation

- `BatchConverter::runtimeMainPreflightPlan()` now adds `worker_pool.pool_context_manager` for the successful task-args path.
- The review records the upstream `with mp.Pool(...) as pool` call, selected process count, worker init argument, pool-imap wrapping, result draining inside the context, worker-handler override inside the context, context exit before `del model_lst`, and zero-worker blocked context entry.
- `wordpress-marker-runtime-pool-context-manager-currentbase.php` asserts the same WordPress import-queue review without launching Python, Torch multiprocessing, model workers, or external PDF tools.

## Evidence

Red-first focused run before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePoolContextManagerBoundaryCurrentBaseTest.php`

Result: `1 test files, 1 assertions, 1 failures`; failure was missing `worker_pool.pool_context_manager`.

Focused run after implementation:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePoolContextManagerBoundaryCurrentBaseTest.php`

Result: `1 test files, 32 assertions, 0 failures`.

Adjacent runtime family:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePoolContextManagerBoundaryCurrentBaseTest.php`

Result: `2 test files, 1174 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-pool-context-manager-currentbase.php`

Result: passed and emitted `context_manager_call=with mp.Pool(processes=total_processes, initializer=worker_init, initargs=(model_lst,)) as pool`, `context_enter_success=true`, `processes=2`, `wraps_pool_imap=true`, `result_drain_inside_context=true`, `worker_handler_override_inside_context=true`, `context_exit_after_worker_handler_override=true`, `model_list_delete_after_context_exit=true`, `zero_worker_context_blocked_by=pool-process-count-failed`, and no Python/model/multiprocessing/external-tool execution.

## Non-Overlap

This does not repeat accepted runtime input/output admission, metadata JSON load/shape/value boundaries, numeric truthiness, negative chunk slicing, spawn/model handoff, share-memory slot review, Pool creation, Pool result-drain behavior, worker cleanup metadata, `process_single_pdf` preflight return values, post-conversion error handling, save-markdown exceptions, single-document runtime preflight, server/benchmark artifacts, or native PDF parser/font/xref/security/image/table/form/outline metadata slices. The bounded behavior is only the upstream `with mp.Pool(...) as pool` context-manager boundary around `pool.imap()` and before `del model_lst`.

## Dependency Closure

No new support component is needed. This reuses native `BatchConverter` runtime planning and per-file preflight review metadata. Live OCR, Surya/Texify/Torch, pypdfium/PDFium, Streamlit/FastAPI workers, Python multiprocessing, and external PDF tools remain intentionally out of scope for the current no-GPU markerPDF lane.
