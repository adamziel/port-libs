# markerPDF runtime import-order preflight boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260606T162118Z`
Session: `port-dev-markerpdf-runtime-preflight-20260606T162118Z`
Base accepted HEAD: `b0745b711922fec4e93573eb719ea5fcb3413b9d`
Date: `2026-06-06T16:21:18Z`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Primary source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.
- `convert.py` assigns `PYTORCH_ENABLE_MPS_FALLBACK=1`, `IN_STREAMLIT=true`, and `PDFTEXT_CPU_WORKERS=1` before importing `pypdfium2`.
- `convert.py` imports `pypdfium2` before `argparse`, `torch.multiprocessing`, `marker.convert`, `marker.output`, `marker.pdf.*`, `marker.models`, and `marker.settings`. The upstream comment says the `pypdfium2` import needs to stay near the top to avoid warnings.
- `convert.py` imports `marker.logger.configure_logging`, then calls `configure_logging()` at import time before runtime argument parsing.

## Implementation

- `MarkerRuntimePlanner::conversionImportBoundaryPlan()` now records the import-time environment assignments, import ordering, pypdfium2 warning-avoidance boundary, and logging setup as review-only native metadata.
- The plan explicitly records that it does not import Python modules, `pypdfium2`, Torch, Marker models, multiprocessing, or external PDF tools.
- Added `MarkerRuntimeImportOrderBoundaryCurrentBaseTest.php`.
- Added `wordpress-marker-runtime-import-order-boundary-currentbase.php`.

## Red-First Probe

Before the source edit, the focused boundary test failed because the native planner did not expose the import-order review contract:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeImportOrderBoundaryCurrentBaseTest.php

1 test files, 0 assertions, 1 failures
Call to undefined method PortLibs\MarkerPDF\MarkerRuntimePlanner::conversionImportBoundaryPlan()
```

## Evidence

Focused new test:

```sh
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeImportOrderBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 29 assertions, 0 failures
```

Adjacent runtime planner family:

```sh
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeImportOrderBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php
```

Result:

```text
2 test files, 105 assertions, 0 failures
```

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-marker-runtime-import-order-boundary-currentbase.php
```

Result: emits `pypdfium_before_argparse=true`, `pypdfium_before_torch=true`, `configure_logging_before_parse_args=true`, `review_only=true`, `executes_python_or_models=false`, `executes_pypdfium=false`, and `executes_external_pdf_tools=false`.

Syntax and JSON:

```sh
php -l lanes/markerpdf/src/MarkerRuntimePlanner.php
php -l lanes/markerpdf/tests/MarkerRuntimeImportOrderBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-marker-runtime-import-order-boundary-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
git diff --check -- lanes/markerpdf
```

Result: no PHP syntax errors, `json ok`, and clean whitespace diff.

## Dependency Closure

No new support component is needed. This reuses the existing native runtime planner and logging/environment boundary metadata. No Python, `pypdfium2`/PDFium, Torch multiprocessing, `pdftext`, Surya/Texify/tabled models, model downloads, OCR/raster helpers, Streamlit/FastAPI/Uvicorn, live model workers, or external PDF tools are executed.

## Non-Overlap

This does not repeat accepted runtime file-list admission, input/output symlink filtering, duplicate symlink target task identity, output regular-file or parent conflicts, metadata-file ordering/open/load/shape/value/duplicate-key behavior, numeric `--max`/`--min_length` truthiness, negative chunk slicing, zero chunk failure, invalid worker Pool creation, pool result drain/cleanup, conversion summary, per-file `process_single_pdf` skip/return/save/error boundaries, single-document runtime preflight, server/app/config, benchmark artifacts, output preview artifacts, xref repair, or native PDF parser/font/security/image/table/form/outline metadata slices. The bounded behavior is only `convert.py` import-time environment assignment, `pypdfium2` import order, and logging setup review before runtime parsing.
