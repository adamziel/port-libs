# Runtime Preflight Symlink File-Filter Boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T135806Z`

Accepted base: `7c27a6118223c3a795b10dae9f12e2e6310f566a`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` builds batch candidates with `os.listdir(in_folder)`, joins each basename to the input folder, then filters with `os.path.isfile()`. Python `os.path.isfile()` follows symlinks to regular files and returns false for directory symlinks and broken symlinks.

## Patch

- `BatchConverter::runtimeMainPreflightPlan()` now records symlink review metadata in `input_listing`: all symlink basenames, symlinks that remain file candidates, symlinks skipped as non-files, broken symlinks, and the explicit `os.path.isfile` symlink filter note.
- The runtime task preflight keeps regular-file symlink paths as task args, preserving basename metadata lookup before pool launch, while directory and broken symlinks are excluded before chunking and task construction.
- The WordPress runtime preflight smoke now exercises the same upload-folder boundary without running Python, Torch, multiprocessing workers, models, OCR, or external PDF tools.

## Evidence

- `php -l lanes/markerpdf/src/BatchConverter.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php` => no syntax errors.
- `jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json` => valid JSON.
- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php` => `1 test files, 919 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php` emitted `symlink_filter=os.path.isfile follows regular-file symlinks and excludes directory or broken symlinks`, `file_symlink_basenames=[linked-report.pdf]`, `skipped_symlink_basenames=[broken-upload.pdf, linked-directory.pdf]`, `broken_symlink_basenames=[broken-upload.pdf]`, `symlink_task_args_count=2`, `symlink_link_path_preserved=true`, `symlink_link_metadata_title=Symlinked Upload`, `symlink_directory_excluded=true`, `symlink_broken_excluded=true`, `executes_python_or_models=false`, `executes_multiprocessing=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- `phpPass`: `1890 -> 1891`.
- `markerRuntimeMainPreflightBoundaryCurrentBaseBehaviors`: `3 -> 4`.
- `mappedMarkerRuntimeMainPreflightBoundaryCurrentBaseBehaviors`: `3 -> 4`.
- `wordpressScenarios`: unchanged at `1711`; this enriches the existing WordPress runtime preflight smoke instead of adding a new example file.

## Non-Overlap

This is a runtime file-list boundary only. It does not touch searchable-PDF text extraction, fonts, CMaps, stream filters, xref repair, annotations, forms, attachments, table/equation handoffs, live OCR, Surya/Texify/Torch model execution, GPU workers, Streamlit/FastAPI model workers, or upstream benchmark parity.

## Dependency Closure

No new support component is needed. The slice reuses native PHP filesystem inspection and the existing runtime preflight planner. Live upstream model/runtime execution remains intentionally out of scope under the current no-GPU markerPDF directive.
