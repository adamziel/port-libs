# markerpdf runtime MPS device case boundary current-base

Slice: `markerpdf-runtime-preflight-boundary-current-base-20260608T034419Z`

Base accepted HEAD: `e0a13ef9a780753d5899fbbc435cefb0324e5b29`

## Source truth

Pinned upstream `sddai/markerPDF` `convert.py` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` branches only on exact lowercase string matches:

```python
if settings.TORCH_DEVICE == "mps" or settings.TORCH_DEVICE_MODEL == "mps":
```

That branch disables parent `load_all_models()` / `share_memory()` handoff and passes `None` to `worker_init`, causing worker-side model loading. Non-lowercase values such as `MPS` do not enter the upstream MPS branch.

Reference: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`

## Implementation

- `BatchConverter::convertMainModelHandoffPlan()` now checks raw device strings for exact `mps` without case-folding or trimming.
- `MarkerRuntimePlanner::convertPyMultiprocessingPlan()` uses the same exact lowercase/raw-string boundary and marks worker-side model loading only when `worker_init` receives `None`.
- Added a focused test for uppercase `TORCH_DEVICE=MPS` and `TORCH_DEVICE_MODEL=MPS` boundaries while preserving lowercase `mps`.
- Added a WordPress-facing no-execution smoke for runtime import preflight.

## Evidence

Red-first focused run before the full fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeMpsDeviceCaseBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL preserves convert.py exact lowercase mps branch for runtime model handoff
Values are not identical
Expected: false
Actual: true

1 test files, 2 assertions, 1 failures
```

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeMpsDeviceCaseBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS preserves convert.py exact lowercase mps branch for runtime model handoff

1 test files, 74 assertions, 0 failures
```

Runtime family after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeMpsDeviceCaseBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeWorkerInitBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeEmptyQueueModelHandoffBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeShareMemoryErrorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeEmptyModelListHandoffBoundaryCurrentBaseTest.php
7 test files, 1580 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-mps-case-boundary-currentbase.php
exit 0
uppercase_uses_mps_no_shared_memory_branch=false
spaced_uses_mps_no_shared_memory_branch=false
lowercase_uses_mps_no_shared_memory_branch=true
executes_python_or_models=false
executes_multiprocessing=false
executes_external_pdf_tools=false
```

## Non-overlap

This slice is limited to the runtime MPS device case boundary and standalone model-handoff metadata. It does not work on live OCR, Surya/Texify/Torch execution, Streamlit/FastAPI workers, upstream model benchmark parity, pdftext dictionary cache envelopes, metadata extraction, fonts, CMaps, xref repair, annotations, forms, image filters, or supplied-boundary table/equation handoffs.

## Dependency closure

No new support component is needed. The patch reuses existing native runtime preflight planners and remains no-GPU/no-model/no-Python execution.
