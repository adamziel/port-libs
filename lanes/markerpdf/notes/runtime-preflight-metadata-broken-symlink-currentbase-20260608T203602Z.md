# markerPDF runtime metadata broken-symlink preflight

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260608T203602Z`
Session: `port-dev-markerpdf-runtime-preflight-20260608T203602Z`
Accepted base: `e76c4cc82ad1172514b0791041ad64c954f9e499`

## Source Truth

Upstream markerPDF `convert.py::main` resolves `args.metadata_file` with `os.path.abspath()` after input listing, output creation, and chunk selection, then calls `open(metadata_file, "r")` for `json.load()` before `torch.multiprocessing.set_start_method("spawn")`, model handoff, conversion summary, task tuple construction, or `Pool.imap(process_single_pdf, task_args)`.

For a broken metadata-file symlink, Python follows the symlink during `open()` and fails with `FileNotFoundError`. The native PHP no-GPU preflight now records that path as an explicit broken symlink and keeps all later model/pool stages blocked for WordPress review.

## Implementation

- `BatchConverter::runtimeMetadataFilePathPlan()` now emits:
  - `metadata_file_broken_symlink`
  - `metadata_file_open_broken_symlink_fails`
- The existing `metadata-file-load-failed` / `FileNotFoundError` load boundary is preserved.
- No Python, Torch, model loading, multiprocessing, pdftext, PDFium, OCR, or external PDF tools are executed.

## Evidence

Red-first focused run before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeMetadataBrokenSymlinkBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PHP Warning: Undefined array key "metadata_file_broken_symlink"
FAIL records broken metadata_file symlink open failure after chunking before spawn
1 test files, 15 assertions, 1 failures
```

Focused verification after the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeMetadataBrokenSymlinkBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records broken metadata_file symlink open failure after chunking before spawn
1 test files, 37 assertions, 0 failures
```

Adjacent guard:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeMetadataFileOpenBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records metadata_file open path type before json load and model handoff
1 test files, 43 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-metadata-broken-symlink-currentbase.php
```

Result: emits `metadata_file_path_type=broken-symlink`, `metadata_file_broken_symlink=true`, `metadata_file_open_broken_symlink_fails=true`, `metadata_error_class=FileNotFoundError`, `blocks_spawn=true`, `blocks_model_handoff=true`, `task_args_count=0`, and all execution flags false.

## Non-Overlap

This does not repeat accepted metadata-file missing/directory/symlink-open success, relative/dash/tilde metadata paths, scalar/list/duplicate/numeric metadata JSON, input broken-symlink listdir, output symlink/makedirs boundaries, chunk/max numeric gates, spawn/model/share-memory, worker pool context/cleanup/result-drain, process_single_pdf preflight, server/benchmark artifacts, or native PDF parser/font/xref/security/image/table/form/outline behavior. The bounded behavior is only broken metadata-file symlink review at upstream `open(metadata_file, "r")` after chunking and before spawn/model/task/pool stages.

## Dependency Closure

No new support component is needed. This reuses native PHP runtime planning, filesystem path classification, JSON metadata load boundaries, focused `TestRunner`, and a WordPress smoke harness. Live OCR, Surya/Texify/Torch model execution, Streamlit/FastAPI workers, PDFium rendering, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.
