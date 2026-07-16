# markerPDF runtime metadata dash-file boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260608T050808Z`
Session: `port-dev-markerpdf-runtime-preflight-20260608T050808Z`
Base accepted HEAD: `a7130e39566f87e0f070ab864cbb860b9ffe3872`

## Source truth

- Upstream `sddai/markerPDF` pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` parses `--metadata_file` as a string, then only if it is truthy computes `metadata_file = os.path.abspath(args.metadata_file)` and calls `open(metadata_file, "r")` before `json.load(f)`.
- Because `argparse` accepts `--metadata_file -` and upstream uses `open()` directly, `-` is a literal process-cwd path. It is not stdin, not a response file, and not resolved against the input or output folder.

## Implementation

- Added explicit `BatchConverter::runtimeMetadataFilePathPlan()` review fields for dash metadata paths:
  `metadata_file_is_dash_literal`, `metadata_file_dash_path`,
  `metadata_file_dash_treated_as_stdin=false`, `metadata_file_stdin_read=false`,
  and `metadata_file_open_uses_filesystem_path`.
- Added `MarkerRuntimeMetadataDashFileBoundaryCurrentBaseTest.php` proving `--metadata_file -` opens the literal cwd `-` JSON file, ignores input/output `-` decoys, preserves basename metadata for selected WordPress upload files, and avoids Python/model/multiprocessing/external-tool execution.
- Added `wordpress-marker-runtime-metadata-dash-file-currentbase.php` as the WordPress smoke for the same no-GPU runtime boundary.

## Verification

- `php -l lanes/markerpdf/src/BatchConverter.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/MarkerRuntimeMetadataDashFileBoundaryCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-metadata-dash-file-currentbase.php` => no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeMetadataDashFileBoundaryCurrentBaseTest.php` => 1 test file / 38 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeMetadataDashFileBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeMetadataFileOpenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeArgparseBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeArgparseResponseFileBoundaryCurrentBaseTest.php` => 5 test files / 1498 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-marker-runtime-metadata-dash-file-currentbase.php` => emitted `metadata_file_is_dash_literal=true`, `metadata_file_dash_treated_as_stdin=false`, `metadata_file_stdin_read=false`, `metadata_file_open_uses_filesystem_path=true`, `metadata_loaded_filenames=["dash.pdf"]`, `task_args_count=2`, and all execution flags false.
- `git diff --check -- lanes/markerpdf` => 0 whitespace/errors.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted relative metadata-file cwd resolution, empty metadata-file truthiness, option-looking metadata values, response-file literal handling, metadata symlink/directory open semantics, duplicate/numeric/scalar metadata, input/output folder abspath, input listing, symlink file filtering, task identity, chunk/max slicing, worker-count/Pool creation, process-single preflight, model handoff, share-memory errors, or native PDF parser/font/xref/security/image/table/form/outline work. The bounded behavior is only `--metadata_file -` being a literal cwd file path before model handoff.

## Dependency Closure

No new support component is needed. This reuses the native PHP `BatchConverter` runtime preflight planner, JSON metadata-file loader, focused `TestRunner`, and WordPress smoke harness. Full live OCR/model execution, Surya/Texify/Torch workers, pdftext/PDFium runtime calls, Streamlit/FastAPI workers, stdin-driven metadata loading, and exact upstream model benchmark parity remain out of scope under the no-GPU markerPDF directive.
