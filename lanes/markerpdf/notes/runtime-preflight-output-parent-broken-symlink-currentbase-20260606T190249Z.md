# Runtime Preflight Output Parent Broken Symlink Boundary

Slice: `markerpdf-runtime-preflight-boundary-current-base-20260606T190249Z`

Accepted base: `05f7c529bb0252dd89e85dabbaacf5c39c827fd9`

## Source Truth

Upstream `sddai/markerPDF` `convert.py::main` lists input files, calls
`os.makedirs(out_folder, exist_ok=True)`, then chunks files, loads
`metadata_file`, prepares model handoff, builds task args, and launches the
Torch multiprocessing pool.

A local Python probe against `os.makedirs($broken_parent_symlink/marker-output,
exist_ok=True)` reports `FileNotFoundError: [Errno 2] No such file or directory`
when the intermediate parent is a broken symlink. PHP reports the same parent
as `file_exists=false` and `is_link=true`, so the native preflight needs an
explicit broken-symlink parent boundary.

## Implementation

`BatchConverter::filesystemPathType()` now classifies broken symlinks as
`broken-symlink`. `outputFolderParentConflict()` treats a broken symlink in the
parent chain as an output creation conflict, and `outputFolderCreationPlan()`
maps that case to `output-folder-parent-broken-symlink` with Python-style
`FileNotFoundError` metadata.

The plan still preserves the older parent-file boundary as
`output-folder-parent-not-directory` / `NotADirectoryError`, and target symlink
behavior remains unchanged.

## Evidence

Red-first focused run after adding the fixture:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeOutputSymlinkBoundaryCurrentBaseTest.php`

Result: `1 test files / 67 assertions / 1 failure`; expected
`broken-symlink`, actual `missing`.

Focused after fix:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeOutputSymlinkBoundaryCurrentBaseTest.php`

Result: `1 test files / 85 assertions / 0 failures`.

Adjacent runtime preflight family:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeOutputSymlinkBoundaryCurrentBaseTest.php`

Result: `2 test files / 1303 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-output-symlink-boundary-currentbase.php`

Result: `broken_parent_symlink_output_rejected_before_metadata=true`, all
blocked output task-arg counts are `0`, and no Python/models, multiprocessing,
or external PDF tools execute.

## Non-Overlap

This slice does not repeat the accepted output target symlink boundaries,
ordinary output parent file conflicts, metadata-file open boundaries, worker
cleanup/runtime model handoff boundaries, or native PDF parser/xref/filter
slices. It is limited to the `os.makedirs(out_folder, exist_ok=True)` broken
intermediate parent symlink failure before metadata/model work.

## Dependency Closure

No new support component is needed. The patch reuses native runtime preflight
path classification, output-folder creation planning, argparse/listing/chunk/
metadata/model handoff boundaries, and the existing WordPress runtime output
symlink smoke. Live OCR, Surya/Texify/Torch model execution, and upstream model
benchmark parity remain intentionally out of scope for this no-GPU markerPDF
lane.
