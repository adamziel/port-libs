# markerPDF runtime leading tilde path boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260608T174934Z`
Session: `port-dev-markerpdf-runtime-preflight-20260608T174934Z`
Base accepted HEAD: `cc291776a175c8775482b61d9b74bdc658b69dca`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` calls `os.path.abspath(args.in_folder)` and `os.path.abspath(args.out_folder)` before `os.listdir(in_folder)` and `os.makedirs(out_folder, exist_ok=True)`.
- The same upstream runtime computes optional `metadata_file = os.path.abspath(args.metadata_file)` before `open(metadata_file, "r")` and `json.load(f)`.
- Python `os.path.abspath()` does not call `expanduser()`, so `~/uploads`, `~/marker-output`, and `~/metadata.json` remain literal cwd-relative paths under a `~` directory before task args, model handoff, or pool launch.

## Patch

- `BatchConverter::runtimeInputOutputPathPlan()` now exposes leading-tilde review fields for input and output folder arguments, including literal resolved paths and explicit `*_tilde_expanded_to_home=false` markers.
- `BatchConverter::runtimeMetadataFilePathPlan()` records the same literal leading-tilde boundary for `--metadata_file`, preserving process-cwd resolution and basename metadata matching.
- Added a focused runtime test that creates a literal cwd `~` directory, verifies the file listing, task args, selected metadata, and no-execution flags.
- Added a WordPress smoke showing a WordPress uploads-style `~/wp-uploads` path remains literal and sidecar files remain upstream task candidates before worker-side PDF admission.

## Evidence

Red-first focused run after adding the test:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeTildePathBoundaryCurrentBaseTest.php`

Result before implementation: `1 test files / 12 assertions / 1 failure`; the new review key `input_folder_has_leading_tilde` was missing.

Focused after implementation:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeTildePathBoundaryCurrentBaseTest.php`

Result: `1 test files / 45 assertions / 0 failures`.

Adjacent runtime metadata/preflight family:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeTildePathBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeMetadataBasenameLookupBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeMetadataDashFileBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeMetadataFileOpenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeMetadataTaskArgBoundaryCurrentBaseTest.php`

Result: `6 test files / 1453 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-tilde-path-boundary-currentbase.php`

Result: exits 0 and emits `input_tilde_literal=true`, `output_tilde_literal=true`, `metadata_tilde_literal=true`, `metadata_loaded_from_process_cwd_tilde=true`, `sidecar_remains_task_candidate_before_worker_preflight=true`, `task_args_count=2`, `executes_python_or_models=false`, `executes_multiprocessing=false`, and `executes_external_pdf_tools=false`.

Syntax and artifact checks:

- `php -l lanes/markerpdf/src/BatchConverter.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/MarkerRuntimeTildePathBoundaryCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-tilde-path-boundary-currentbase.php` => no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'` => `markerpdf json ok`.
- `git diff --check -- lanes/markerpdf` => no whitespace errors.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP batch runtime planner, `os.path.abspath` mirror, metadata-file preflight, input listing, task tuple planner, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, multiprocessing, Streamlit/FastAPI worker paths, PDF raster rendering, and exact upstream benchmark parity remain intentionally out of scope for the current no-GPU markerPDF lane.

## Non-Overlap

This does not repeat accepted runtime argparse, relative metadata basename lookup, dash metadata file handling, metadata open failures, metadata JSON failures, output-folder creation conflicts, input symlink/listing boundaries, worker path-kind checks, special-file preflight, empty queue, pool cleanup, model-handoff review, `process_single_pdf` return paths, or native parser/xref/filter/font/security/image/form/outline behavior. The bounded behavior is only leading `~` path preservation by upstream `os.path.abspath()` before metadata/model/pool execution.
