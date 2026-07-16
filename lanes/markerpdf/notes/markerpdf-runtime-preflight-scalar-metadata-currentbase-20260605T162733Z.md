# markerPDF Runtime Preflight Scalar Metadata Boundary

Slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T162733Z`
Session: `port-dev-markerpdf-runtime-preflight-20260605T162733Z`
Base accepted HEAD: `cf274bcd9662639c582a7b638303ea4b1facefb6`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` runs `json.load(f)` for a truthy `--metadata_file`, then builds task args with `metadata.get(os.path.basename(f))` after model handoff and the conversion summary.
- Python JSON scalar roots do not provide `.get()`. A top-level JSON float therefore fails as an AttributeError on `float`, not `int`, while object-valued metadata files can still carry per-file float values through task args.

## Implementation

- `BatchConverter::jsonMetadataType()` now distinguishes PHP floats from ints so runtime metadata preflight reports Python-compatible `float` type labels.
- `MarkerRuntimePreflightBoundaryCurrentBaseTest.php` now covers top-level scalar metadata JSON files (`str`, `NoneType`, `bool`, `float`) blocking at `metadata-get-failed` after model handoff and before task args.
- The same test now proves per-file float metadata values inside an object metadata file stay queued in task args while being reported as `float`.
- Added `wordpress-marker-runtime-preflight-scalar-metadata-currentbase.php` to show WordPress batch import preflight blocks scalar metadata files without launching Python, multiprocessing, pdftext, pypdfium, models, or external PDF tools.

## Verification

```text
php -l lanes/markerpdf/src/BatchConverter.php
php -l lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-marker-runtime-preflight-scalar-metadata-currentbase.php
```

All passed.

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1074 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightDuplicateMetadataCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 36 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-scalar-metadata-currentbase.php
```

Emits `scalar_metadata_json_type=float`, `scalar_metadata_error_boundary=metadata-get-failed`, `scalar_metadata_error_message="'float' object has no attribute 'get'"`, `scalar_task_args_count=0`, `object_task_args_count=2`, and execution flags false.

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-duplicate-metadata-currentbase.php
```

Passed adjacent duplicate-metadata smoke after the shared metadata loader type change.

## Dependency Closure

No new support component is needed. This reuses the native runtime preflight planner, JSON metadata loader, task-argument planner, and focused PHP test harness. Live pdftext, pypdfium2/PDFium, Surya/OCR, Torch/model workers, Streamlit/FastAPI runtime paths, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Non-Overlap

This does not repeat accepted missing/malformed metadata-file load boundaries, list-shaped top-level metadata shape errors, duplicate metadata key last-value-wins behavior, numeric-string metadata filenames, relative metadata paths, empty `metadata_file` argv, file listing, chunking, worker-count, or per-file unsupported-filetype preflight. The new behavior is specifically Python-compatible scalar metadata root typing, with `float` preserved separately from `int`.
