# markerPDF runtime preflight symlink duplicate-target boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T170456Z`
Session: `port-dev-markerpdf-runtime-preflight-20260605T170456Z`
Base accepted HEAD: `1fd65111f67f51b2d9aa737f5be6be428c62949a`

## Source truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` builds file candidates with `os.listdir(in_folder)`, joins each basename, filters only with `os.path.isfile`, then builds task tuples as `(f, out_folder, metadata.get(os.path.basename(f)), args.min_length)`.
- The upstream path does not realpath-deduplicate regular files and symlinks before `task_args`; basename metadata lookup uses the queued entry basename, not the resolved target basename.

## Implementation

- Added `BatchConverter::runtimeTaskArgIdentityReview()` and exposed it at `worker_pool.task_arg_identity_review`.
- The review records task-arg identity rows, duplicate resolved targets, duplicate target groups, symlink filenames, and explicit review booleans:
  - `no_dedupe_before_task_args=true` when duplicate resolved targets are queued;
  - `metadata_lookup_uses_entry_basename=true`;
  - `target_basename_metadata_fallback=false`.
- Added a focused runtime test proving a regular PDF and a symlink to the same PDF target are both queued, the symlink path is preserved, and metadata remains keyed to the entry basename.
- Added `wordpress-marker-runtime-preflight-symlink-duplicate-target-currentbase.php` as a WordPress import smoke for duplicate queued PDF targets without Python, multiprocessing, OCR, models, or external PDF tools.

## Verification

Red-first focused check before source edit:

```sh
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
```

Result: `1 test files, 1075 assertions, 1 failures`; failed on missing `worker_pool.task_arg_identity_review`.

Focused check after source edit:

```sh
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
```

Result: `1 test files, 1105 assertions, 0 failures`.

Adjacent regression check:

```sh
php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
```

Result: `2 test files, 1216 assertions, 0 failures`.

Syntax checks:

```sh
php -l lanes/markerpdf/src/BatchConverter.php
php -l lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-marker-runtime-preflight-symlink-duplicate-target-currentbase.php
```

Result: no syntax errors.

Example smoke:

```sh
php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-symlink-duplicate-target-currentbase.php
```

Result: emitted `duplicate_resolved_targets_found=true`, `duplicate_resolved_target_group_count=1`, `no_dedupe_before_task_args=true`, `metadata_lookup_uses_entry_basename=true`, `target_basename_metadata_fallback=false`, `linked_copy_metadata=null`, `linked_copy_path_preserved=true`, and all Python/model/multiprocessing/external-tool flags false.

## Non-overlap

This does not repeat accepted scalar metadata JSON, duplicate metadata-key last-value-wins, numeric-string metadata filenames, relative metadata paths, empty `metadata_file`, input symlink file filtering, output folder conflicts, chunking, worker-count, pool cleanup, conversion summary, per-file unsupported-filetype preflight, server upload/config artifacts, or native PDF parser/font/xref/security/image/table/form/outline metadata slices. The bounded behavior is only the `convert.py` task-argument identity boundary for duplicate resolved file targets.

## Dependency closure

No new support component is needed. This reuses native PHP filesystem inspection, symlink detection, realpath identity grouping, runtime task tuple planning, and the existing focused PHP test harness. Live pdftext, pypdfium2/PDFium, Surya/OCR, Texify/Torch model workers, Streamlit/FastAPI runtime paths, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
