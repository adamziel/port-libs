# markerPDF runtime preflight share_memory slot boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T151150Z`

Session: `port-dev-markerpdf-runtime-preflight-20260605T151150Z`

Accepted base: `2f0ad966b873b84adbeb3aaf8ec428417f6dce2d`

## Source truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` handles `convert.py::main` model handoff differently by device.

For CUDA/CPU, `main` loads `model_lst = load_all_models()` in the parent process, then iterates through the list before printing the conversion summary, task args, or launching the multiprocessing pool:

```text
for model in model_lst:
    if model is None:
        continue
    model.share_memory()
```

For MPS, upstream leaves the parent handoff as `None` and workers load models in `worker_init_fn`.

Under the current no-GPU markerPDF lane rule this slice records that runtime boundary for WordPress import review without executing Python, Torch, Surya, Texify, OCR, multiprocessing workers, pdftext, or external PDF tools.

## Behavior

`BatchConverter::runtimeMainPreflightPlan()` now accepts a review-only `modelSlots` fixture and records `model_handoff.model_share_memory_review`:

- CUDA/CPU reaches the parent `load_all_models()` share-memory review;
- non-null model slots are marked as `model.share_memory()` calls;
- `None` model slots are skipped by the upstream `if model is None: continue` boundary;
- MPS records that parent share-memory review is blocked by `mps-worker-loads-models`;
- spawn-start-method failure remains blocked before the parent model-list review;
- conversion summary, task args, pool launch, Python/model execution, multiprocessing, and external PDF tools all remain unexecuted.

The WordPress smoke now emits the slot-level review so import queues can fail closed or explain model handoff risk before any model runtime starts.

## Red-first evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL records convert.py model share_memory slot skip boundary before task args
Unknown named parameter $modelSlots
1 test files, 919 assertions, 1 failures
```

## Verification

Focused slice:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php
1 test files, 953 assertions, 0 failures
```

Adjacent runtime/batch family:

```text
php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeArgparseBoundaryCurrentBaseTest.php
3 test files, 1167 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php
```

The smoke emits `runtime_model_share_memory_review_reached=true`, `runtime_model_share_memory_none_slot_indexes=[1,4]`, `runtime_model_share_memory_slot_indexes=[0,2,3]`, `runtime_model_share_memory_call_count=3`, `runtime_model_share_memory_skips_none_slots=true`, `mps_runtime_share_memory_review_reached=false`, `mps_runtime_share_memory_blocked_by=mps-worker-loads-models`, `executes_python_or_models=false`, `executes_multiprocessing=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted runtime admission around argparse, input listing, output folder conflict, metadata basename lookup, chunk/max slicing, worker-count clamping, empty-task queue risk, invalid-worker pool risk, model-handoff MPS branch summary, task sidecar, pool cleanup, or output conflict behavior. The new behavior is specifically the parent `model_lst` slot loop where upstream skips `None` entries before calling `share_memory()` on the remaining models.

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP `BatchConverter` runtime preflight planner, manifest/status lane counters, and WordPress smoke path. Full live OCR/model execution, Surya/Texify/Torch model workers, GPU/MPS parity, pdftext runtime calls, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF lane.
