# markerPDF runtime preflight Pool creation boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T013949Z`
Session: `port-dev-markerpdf-runtime-preflight-20260605T013949Z`
Base accepted HEAD: `e4a30166c8c87cc4ce5ad1a9952a522af849b4b2`
Date: `2026-06-05T01:44:54Z`

## Source truth

- Upstream `sddai/markerPDF` pinned by the manifest: `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Primary source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.
- `convert.py::main` computes `total_processes = min(len(files_to_convert), args.workers)`, prints the conversion summary, builds `task_args`, and then creates `mp.Pool(processes=total_processes, initializer=worker_init, initargs=(model_lst,))` before `pool.imap(...)`.

## Implementation

- `BatchConverter::runtimeMainPreflightPlan()` now preserves negative worker counts in the upstream `min(...)` computation instead of clamping them to zero.
- Added a native non-executing `pool_creation` review block under `worker_pool` for successful and failed `mp.Pool(...)` creation.
- Empty chunks, zero workers, and negative workers now record the upstream `ValueError` boundary before `pool.imap`, while retaining existing queue diagnostics such as `empty-task-queue` and `invalid-worker-count`.
- Updated the WordPress runtime preflight smoke to emit zero/negative worker Pool creation metadata without launching Python, Torch multiprocessing, pdftext, pypdfium, OCR/model workers, or external PDF tools.

## Focused verification

New focused case:

`records zero and negative multiprocessing Pool creation failures before pool imap`

Delta: +1 focused PASS case and +54 assertions in `MarkerRuntimePreflightBoundaryCurrentBaseTest.php` (from 16 cases / 413 assertions to 17 cases / 467 assertions).

Commands:

```sh
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 467 assertions, 0 failures
```

Adjacent runtime/batch family:

```sh
php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
```

Result:

```text
2 test files, 578 assertions, 0 failures
```

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php
```

Result: emitted `zero_worker_pool_creation_success=false`, `zero_worker_pool_creation_error_boundary=pool-process-count-failed`, `zero_worker_pool_creation_error_class=ValueError`, `zero_worker_pool_imap_reached=false`, `negative_worker_total_processes=-2`, and all Python/model/multiprocessing/external-tool execution flags false.

Syntax:

```sh
php -l lanes/markerpdf/src/BatchConverter.php
php -l lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php
```

Result: no syntax errors.

## Dependency closure

No new support component is needed. This reuses native PHP runtime planning, file listing, metadata review, task tuple planning, and WordPress smoke paths. Live Python execution, Torch multiprocessing, Surya/Texify/Torch models, pdftext, pypdfium/PDFium, OCR, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the no-GPU markerPDF lane rule.

## Non-overlap

This does not repeat accepted runtime file-list, metadata JSON shape, output-folder conflict, spawn start-method, model handoff, conversion summary, numeric gate truthiness, negative chunk slicing, per-file process preflight return-value, server/app/config, pdftext dictionary, parser/xref, security, form, font, image/filter, annotation, outline, table, or supplied-boundary slices. The bounded behavior is only the `mp.Pool(...)` process-count creation boundary after task args and before `pool.imap`.
