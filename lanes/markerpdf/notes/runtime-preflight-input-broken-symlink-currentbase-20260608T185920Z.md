# markerpdf-runtime-preflight-boundary-current-base-20260608T185920Z

## Source Truth

- Upstream `sddai/markerPDF` pinned commit: `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Relevant upstream boundary: `convert.py::main` computes `in_folder = os.path.abspath(args.in_folder)` and then calls `os.listdir(in_folder)` before `os.makedirs(out_folder, exist_ok=True)`, chunking, metadata-file JSON loading, `mp.set_start_method('spawn')`, model handoff, conversion summary, `task_args`, or `mp.Pool`.
- A broken input-folder symlink therefore fails at the same listdir boundary as a missing input folder, with a `FileNotFoundError`. The native PHP port records that boundary without executing Python, Torch, OCR/model code, multiprocessing, pypdfium/PIL, or external PDF tools.

## Patch

- `BatchConverter::runtimeInputOutputPathPlan()` now embeds `input_folder_listdir_boundary_review` for the upstream `os.listdir(in_folder)` stage.
- The review records input path type, symlink target availability, success/error state, upstream-style error class/message preview, and the downstream stages blocked by an input listing failure.
- Added focused coverage for a WordPress upload folder symlink that points to a missing target. The plan fails before output-folder creation, metadata loading, spawn/model handoff, task args, and worker pool launch.
- Added a WordPress smoke for the same boundary.

## Red-First Evidence

Before the planner change:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeInputBrokenSymlinkBoundaryCurrentBaseTest.php`

Failed because `input_folder_listdir_boundary_review` was missing:

`1 test files, 7 assertions, 1 failures`

## Verification

- `php -l lanes/markerpdf/src/BatchConverter.php`  
  `No syntax errors detected in lanes/markerpdf/src/BatchConverter.php`
- `php -l lanes/markerpdf/tests/MarkerRuntimeInputBrokenSymlinkBoundaryCurrentBaseTest.php`  
  `No syntax errors detected in lanes/markerpdf/tests/MarkerRuntimeInputBrokenSymlinkBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-input-broken-symlink-currentbase.php`  
  `No syntax errors detected in lanes/markerpdf/examples/wordpress-marker-runtime-input-broken-symlink-currentbase.php`
- `php -r '$data=json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($data)) { fwrite(STDERR, "invalid json\n"); exit(1); } echo "lane-status json ok\n";'`  
  `lane-status json ok`
- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeInputBrokenSymlinkBoundaryCurrentBaseTest.php`  
  `1 test files, 36 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeInputBrokenSymlinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeInputOutputSameFolderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeTildePathBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeOutputSymlinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeSpecialFileBoundaryCurrentBaseTest.php`  
  `6 test files, 1484 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-marker-runtime-input-broken-symlink-currentbase.php`  
  Exits `0` and emits `input_path_type=broken-symlink`, `listdir_error_reason=broken-symlink`, `metadata_load_reached=false`, `model_handoff_reached=false`, and `task_args_count=0`.
- `git diff --check -- lanes/markerpdf`  
  No output.

## Dependency Closure

No new support component is needed. This slice reuses native PHP filesystem inspection and the existing markerPDF runtime preflight planner. GPU/model execution, live OCR, Surya/Texify/Torch, Streamlit/FastAPI model workers, multiprocessing, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF lane.

## Non-overlap

This does not repeat accepted runtime argparse, tilde path, input symlink directory success, input-folder missing/file/unreadable errors, output symlink/permission/nested folder boundaries, metadata-file load/shape/value/dash/relative path boundaries, chunk/max numeric gates, spawn-start failures, model/share-memory handoffs, worker pool context/cleanup, selected-file disappearance, server/benchmark artifacts, or native PDF parser/font/xref/security/image/table/form/outline behavior. The bounded behavior is only explicit review metadata for a broken input-folder symlink failing at upstream `os.listdir(in_folder)`.
