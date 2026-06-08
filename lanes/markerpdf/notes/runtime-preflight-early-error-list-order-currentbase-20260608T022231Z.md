# markerPDF runtime preflight early-error list order boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260608T022231Z`

Base accepted HEAD: `02ca21f0a770f96178de4e85f83f87d2bf977c2c`

## Source truth

Pinned upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` maps `convert.py::main` as:

- `files = [os.path.join(in_folder, f) for f in os.listdir(in_folder)]`
- `files = [f for f in files if os.path.isfile(f)]`
- `os.makedirs(out_folder, exist_ok=True)`
- chunk selection, optional `metadata_file` JSON load, `mp.set_start_method('spawn')`, model handoff, summary print, task tuple construction, and `Pool.imap(process_single_pdf, task_args)`

The native no-GPU PHP runtime preflight already mirrored the successful path's `os.listdir` filesystem order, but early branches that failed after input listing omitted the order metadata fields.

## Implementation

- `BatchConverter::runtimeMainPreflightPlan()` now carries `entry_order_source`, `sort_applied_before_chunking`, and `preserves_os_listdir_order` through these early branches:
  - output folder creation failure after input listing;
  - metadata file load failure after chunking;
  - repeated spawn start-method failure after metadata load;
  - metadata shape failure where Python `metadata.get(...)` is unavailable before task args.
- Added a focused test covering all four branches with mixed PDF, non-PDF, and skipped directory inputs.
- Added a WordPress smoke showing early runtime failures preserve selected upload order metadata without launching Python, models, multiprocessing, or external PDF tools.

## Evidence

Red-first focused run before the source fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightEarlyErrorListOrderBoundaryCurrentBaseTest.php
```

Failed as expected with missing `entry_order_source` after `2` assertions.

Focused after fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightEarlyErrorListOrderBoundaryCurrentBaseTest.php
```

Result: `1 test files, 52 assertions, 0 failures`.

Runtime family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntime*CurrentBaseTest.php
```

Result: `33 test files, 2864 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-marker-runtime-early-error-list-order-currentbase.php
```

Result: emits `entry_order_preserved_on_early_errors=true`, `file_order_preserved_on_early_errors=true`, and all execution flags false.

## Non-overlap

This does not repeat runtime argparse admission, duplicate/scalar/numeric metadata value handling, input/output symlink admission, hardlink task identity, selected-file-gone worker preflight, markdown-exists return boundaries, server upload pagination, model share-memory failures, pool context cleanup, searchable PDF parser/xref/font/image/annotation/form behavior, OCR, table recognition, or any GPU/model path. The bounded behavior is specifically order metadata preservation in `convert.py::main` branches that already listed input files before a later runtime preflight failure.

## Dependency closure

No new support component is needed. This slice reuses native PHP runtime planning around `os.listdir` order, `os.path.isfile` filtering, `os.makedirs`, metadata-file loading, spawn preflight, and task tuple review. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
