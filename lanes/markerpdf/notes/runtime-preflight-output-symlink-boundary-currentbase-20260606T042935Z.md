# markerPDF runtime output symlink preflight boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260606T042935Z`
Session: `port-dev-markerpdf-runtime-preflight-20260606T042935Z`
Base accepted HEAD: `9fecdcbe71562bc1bac82854e69d6378cb0f5882`
Date: `2026-06-06T04:29:35Z`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Primary source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.
- `convert.py::main` normalizes paths, lists input files, and then calls `os.makedirs(out_folder, exist_ok=True)` before chunk slicing, metadata JSON loading, spawn/model handoff, conversion summary, task tuples, or `torch.multiprocessing.Pool`.
- Python `os.makedirs(..., exist_ok=True)` accepts an output path that is a symlink to a directory, but raises `FileExistsError` when the path entry is a symlink to a file or a broken symlink.

## Implementation

- `BatchConverter::outputFolderCreationPlan()` now records output-folder symlink review metadata:
  `output_folder_is_symlink`, `output_folder_makedirs_follows_symlink`,
  `output_folder_symlink_target_exists`, `output_folder_symlink_target_type`,
  `output_folder_broken_symlink`, and `output_folder_symlink_target_blocked`.
- Directory symlink outputs are treated as existing directories, preserving the symlink path in task args.
- File-valued and broken output symlinks now produce `FileExistsError` review metadata and block before chunking, metadata loading, model handoff, task args, multiprocessing, or external tools.
- Added `MarkerRuntimeOutputSymlinkBoundaryCurrentBaseTest.php`.
- Added `wordpress-marker-runtime-output-symlink-boundary-currentbase.php`.

## Red-First Probe

Before the source edit, a broken output symlink was treated as a missing creatable output folder:

```text
blocked=false
path_type=missing
chunking_reached=true
pool_error=null
```

That was inconsistent with upstream `os.makedirs(..., exist_ok=True)`, which raises `FileExistsError` for the same broken symlink path entry.

## Evidence

Focused new test:

```sh
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeOutputSymlinkBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 59 assertions, 0 failures
```

Adjacent runtime preflight family:

```sh
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'MarkerRuntime.*(Preflight|OutputSymlink|MetadataFileOpen|PoolContextManager|Argparse).*CurrentBaseTest\.php$' | sort)
```

Result:

```text
6 test files, 1415 assertions, 0 failures
```

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-marker-runtime-output-symlink-boundary-currentbase.php
```

Result: emitted `directory_symlink_output_accepted=true`, `directory_symlink_task_out_folder_preserved=true`, `file_symlink_output_rejected_before_metadata=true`, `broken_symlink_output_rejected_before_chunking=true`, `blocked_output_worker_task_args={"file_symlink":0,"broken_symlink":0}`, `executes_python_or_models=false`, `executes_multiprocessing=false`, and `executes_external_pdf_tools=false`.

Syntax:

```sh
php -l lanes/markerpdf/src/BatchConverter.php
php -l lanes/markerpdf/tests/MarkerRuntimeOutputSymlinkBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-marker-runtime-output-symlink-boundary-currentbase.php
```

Result: no syntax errors.

## Dependency Closure

No new support component is needed. This reuses native PHP filesystem inspection, runtime preflight planning, task tuple planning, and WordPress smoke paths. No Python, Torch multiprocessing, `pdftext`, `pypdfium2`/PDFium, PIL, Surya/Texify/tabled models, model downloads, OCR/raster helpers, Streamlit/FastAPI/Uvicorn, live model workers, or external PDF tools were executed.

## Non-Overlap

This does not repeat accepted runtime file-list admission, input symlink filtering, duplicate symlink target task identity, ordinary output regular-file conflict, output parent conflict, metadata-file ordering/open/load/shape/value/duplicate-key behavior, numeric `--max`/`--min_length` truthiness, negative chunk slicing, zero chunk failure, invalid worker Pool creation, pool result drain/cleanup, conversion summary, per-file `process_single_pdf` skip/return/save/error boundaries, single-document runtime preflight, server/app/config, benchmark artifacts, output preview artifacts, xref repair, or native PDF parser/font/security/image/table/form/outline metadata slices. The bounded behavior is only `convert.py::main` output-folder symlink handling at the `os.makedirs(out_folder, exist_ok=True)` boundary.
