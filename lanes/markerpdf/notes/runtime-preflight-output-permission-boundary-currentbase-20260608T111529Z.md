# Runtime Output Permission Boundary Current Base

Slice: `markerpdf-runtime-preflight-boundary-current-base-20260608T111529Z`

Accepted base: `e9c8df6061c444c862955dfe47e8f5bcb299d3b3`

## Source Truth

Upstream `sddai/markerPDF` `convert.py` at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` runs:

- `os.listdir(in_folder)` and `os.path.isfile(...)` input selection.
- `os.makedirs(out_folder, exist_ok=True)`.
- chunk slicing.
- optional `metadata_file = os.path.abspath(args.metadata_file); open(...); json.load(...)`.
- `mp.set_start_method("spawn")`, model handoff, summary, task tuple creation, and `mp.Pool`.

This slice maps the missing output-folder permission boundary: if the selected output folder is missing under an unwritable parent, upstream `os.makedirs(..., exist_ok=True)` fails with a `PermissionError` after input listing and before chunking, metadata loading, spawn setup, model handoff, task args, or pool launch.

## Patch

- `BatchConverter::outputFolderCreationPlan()` now records the nearest existing output-creation parent, parent writability/searchability, and `output-folder-parent-permission-denied` when native review detects the parent cannot create the missing output folder.
- The native planner still does not create folders, execute Python, load models, launch multiprocessing, or call external PDF tools.
- Added a focused WordPress smoke showing an upload queue whose input files are listed, then output permission failure blocks metadata/model/pool stages.

## Evidence

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeOutputPermissionBoundaryCurrentBaseTest.php`

Result before implementation: `1 test files, 3 assertions, 1 failures`.

Focused passing:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeOutputPermissionBoundaryCurrentBaseTest.php`

Result after implementation: `1 test files, 31 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-output-permission-currentbase.php`

Result: exits `0`, with `output_creation_permission_error=true`, `blocked_before_metadata_load=true`, `blocked_before_model_handoff=true`, `blocked_before_worker_pool=true`, `executes_python_or_models=false`, `executes_multiprocessing=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP filesystem metadata (`file_exists`, `is_dir`, `is_writable`, `is_executable`) to model the upstream `os.makedirs(..., exist_ok=True)` preflight boundary without executing Python, Torch/Surya/Texify/OCR, PDFium, multiprocessing, raster rendering, or external PDF tools.

## Non-Overlap

This does not repeat accepted runtime slices for argparse, input listing order, output file/symlink/parent shape conflicts, metadata-file open/json boundaries, spawn setup, model share-memory slots/errors, process return values, selected-file disappearance, worker path-kind changes, named destinations, native PDF parsing, OCR, or model execution.
