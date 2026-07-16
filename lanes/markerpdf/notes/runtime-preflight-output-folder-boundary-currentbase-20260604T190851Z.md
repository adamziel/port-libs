# markerPDF runtime output-folder preflight boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260604T190851Z`
Session: `port-dev-markerpdf-runtime-preflight-20260604T190851Z`
Base accepted HEAD: `4140f0a103e9cfaf2c69a035a54c05b9d3689171`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` top-level `convert.py::main` normalizes input/output paths, builds the input file list, then calls `os.makedirs(out_folder, exist_ok=True)` before chunk slicing, metadata-file JSON loading, model handoff, task tuple construction, or `torch.multiprocessing.Pool`.
- If `out_folder` already exists as a regular file, Python raises `FileExistsError` at the `os.makedirs(..., exist_ok=True)` boundary. The native PHP plan records that boundary without mutating the filesystem or launching Python/model workers.
- Source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.

## Patch

- `BatchConverter::runtimeMainPreflightPlan()` now records `output_folder_creation_call`, `output_folder_creation_order`, output path type, creation requirement, and creation-blocked/error metadata for upstream `os.makedirs(out_folder, exist_ok=True)`.
- When the output path is a regular file, the plan short-circuits before chunking, metadata loading, worker-pool sizing, task args, model handoff, multiprocessing, or external PDF tools.
- The normal non-blocked plan keeps existing chunking/metadata/worker behavior and adds `chunking_reached`, `chunk_error_boundary`, and `metadata_load_reached` review fields.
- `wordpress-marker-runtime-preflight-boundary-currentbase.php` now emits the output conflict review fields for WordPress import queue diagnostics.
- `UPSTREAM_TEST_MANIFEST.json` maps `markerRuntimeMainPreflightBoundaryCurrentBaseBehaviors` from `1` to `2`; `lane-status.json` moves markerPDF PHP behavior tests and WordPress scenarios from `1081` to `1082`.

## Evidence

Red-first focused check after adding the output-folder conflict assertions and before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Failed as expected: `1 test files, 195 assertions, 1 failures`, with the new case throwing `Batch metadata file is not readable` instead of stopping at the output-folder creation boundary.

Focused assigned gate after the patch:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Passed: `1 test files, 219 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Passed and emitted `output_conflict_creation_blocked=true`, `output_conflict_path_type=file`, `output_conflict_error_class=FileExistsError`, `output_conflict_metadata_load_reached=false`, `output_conflict_pool_error_boundary=output-folder-create-failed`, and all Python/model/multiprocessing/external-tool execution flags false.

## Dependency Closure

No new support component is needed. This reuses native PHP folder/file inspection, existing runtime preflight payloads, metadata/task planning, and no-execution review flags. Full upstream runtime parity remains intentionally out of scope under the no-GPU markerPDF directive because it requires Python, Torch multiprocessing, `pdftext`, `pypdfium2`/PDFium, PIL, Surya/Texify/tabled models, model downloads, OCR/raster helpers, Streamlit/FastAPI/Uvicorn, and live model workers; none were executed.

## Non-Overlap

This does not repeat accepted per-file `process_single_pdf` skip gates, return-value boundaries, metadata-file ordering, numeric `--max`/`--min_length` truthiness, negative chunk slicing, file-list admission, empty/invalid worker pool boundaries, single-document runtime preflight, batch progress/resume, marker app config, server upload/pagination/error artifacts, benchmark callback/error artifacts, output preview artifacts, xref repair, or native PDF parser/font/security/image/table/form/outline metadata slices. The bounded behavior is only `convert.py::main` output-folder creation conflict ordering before metadata and worker launch.
