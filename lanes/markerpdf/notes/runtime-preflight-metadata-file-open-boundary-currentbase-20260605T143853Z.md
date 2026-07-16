# markerPDF runtime metadata_file open boundary

Slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T143327Z`
Base accepted HEAD: `381798fcad9b34f8ddd3161bb0f61bf77da880ad`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` normalizes `args.metadata_file` with `os.path.abspath()`, then calls `open(metadata_file, "r")` and `json.load(f)` after input listing, output folder creation, and chunk selection.
- Python `open()` follows symlinks to regular files and raises `IsADirectoryError` for directory-valued metadata paths before JSON decoding, `mp.set_start_method("spawn")`, model handoff, conversion summary, task tuple construction, or `mp.Pool`.

## Implementation

- `BatchConverter::runtimeMetadataFilePathPlan()` now records metadata-file path type, symlink status, target type, and the upstream `open(metadata_file, "r")` order.
- `BatchConverter::loadRuntimeMetadataFile()` now fails directory-valued metadata paths before `json_decode()`, preserving the upstream `IsADirectoryError` boundary rather than misclassifying the directory as malformed JSON.
- Added `MarkerRuntimeMetadataFileOpenBoundaryCurrentBaseTest.php` for symlinked metadata JSON load-through and directory metadata-file fail-closed behavior.
- Added `wordpress-marker-runtime-metadata-file-open-boundary-currentbase.php` as a WordPress batch-import smoke.

## Evidence

Red-first before source edit:

```bash
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeMetadataFileOpenBoundaryCurrentBaseTest.php
```

Result: `1 test files, 2 assertions, 1 failures` with missing `metadata_file_path_exists`.

Focused green after source edit:

```bash
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeMetadataFileOpenBoundaryCurrentBaseTest.php
```

Result: `1 test files, 43 assertions, 0 failures`.

Adjacent runtime family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeMetadataFileOpenBoundaryCurrentBaseTest.php
```

Result: `2 test files, 962 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-marker-runtime-metadata-file-open-boundary-currentbase.php
```

Result: emitted `symlink_metadata_open_follows_symlink=true`, `symlink_metadata_loaded_filenames=["queued.pdf"]`, `directory_metadata_error_class=IsADirectoryError`, `directory_metadata_blocks_spawn=true`, `directory_metadata_blocks_model_handoff=true`, `directory_metadata_task_args_count=0`, `executes_python_or_models=false`, `executes_multiprocessing=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted runtime input-folder errors, output-folder conflicts, relative or empty metadata-file paths, missing/malformed/list-shaped metadata JSON, duplicate metadata keys, numeric metadata keys, per-file metadata value risks, spawn-start failures, model-handoff branches, pool creation/cleanup boundaries, process_single_pdf return values, single-document preflight, server/benchmark artifacts, or native PDF parser/font/xref/security/image/table/form/outline metadata slices. The bounded behavior is only metadata-file open path semantics for symlinked regular files and directory-valued metadata paths.

## Dependency Closure

No new support component is needed. This reuses native runtime planning, path inspection, JSON metadata loading, task-argument construction, and WordPress smoke patterns. Live OCR, pdftext/PDFium execution, Surya/Texify/Torch model execution, Streamlit/FastAPI workers, multiprocessing, benchmark model downloads, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF direction.
