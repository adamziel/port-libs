# Runtime Preflight Pool Cleanup MPS Boundary

Slice: `markerpdf-runtime-preflight-boundary-current-base-20260608T143709Z`

Source truth:
- Upstream `sddai/markerPDF` `convert.py` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` sets `model_lst = None` for the MPS branch, passes it through `mp.Pool(... initargs=(model_lst,))`, drains `pool.imap(...)`, assigns `pool._worker_handler.terminate = worker_exit`, exits the pool context, then executes `del model_lst`.
- No GPU/model execution is in scope for this lane; the PHP port records a native preflight boundary only.

Implementation:
- `BatchConverter::convertMainPoolCleanupPlan()` now records whether `del model_lst` is deleting a parent loaded/shared `model_lst` reference or the MPS `None` parent reference after worker-side model loading.
- The cleanup plan keeps existing observable keys while adding explicit `cleanup_after_context_exit`, `model_list_value_before_delete`, parent-share-memory, parent-shared-delete, and worker-loaded-model cleanup markers.

Focused verification:
- `php -l lanes/markerpdf/src/BatchConverter.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/MarkerRuntimePoolCleanupMpsBoundaryCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-pool-cleanup-mps-currentbase.php` => no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePoolCleanupMpsBoundaryCurrentBaseTest.php` => 1 selected test file, 58 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePoolCleanupMpsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePoolContextManagerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeWorkerInitBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php` => 4 selected test files, 1375 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-marker-runtime-pool-cleanup-mps-currentbase.php` => exits 0 with MPS `model_lst` value `None`, CUDA `model_lst` value `model_lst`, and no Python/models/multiprocessing/external PDF tools.
- `php -r '$data=json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status.json OK\n";'` => `lane-status.json OK`.
- `git diff --check -- lanes/markerpdf` => exits 0.

Dependency closure:
- No new support component is needed. This slice reuses the existing runtime preflight planner and does not invoke Python, multiprocessing, OCR, models, rasterization, or external PDF tools.
