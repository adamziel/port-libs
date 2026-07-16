# markerPDF runtime share_memory error boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260607T054647Z`
Session: `port-dev-markerpdf-runtime-preflight-20260607T054647Z`
Base accepted HEAD: `dc087e94f6b78bbae9da5c244c45cf03600924da`

## Source truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` loads the parent `model_lst`, skips `None` slots, and calls `model.share_memory()` on each remaining model before printing the conversion summary, building `task_args`, or entering `mp.Pool`.

Primary source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.

Under the current no-GPU markerPDF lane rule, this slice records the runtime boundary for WordPress import review without executing Python, Torch, Surya, Texify, OCR, multiprocessing workers, pdftext, pypdfium, or external PDF tools.

## Behavior

`BatchConverter::runtimeMainPreflightPlan()` now supports a review-only `modelSlots` descriptor whose non-null slot can carry a simulated `share_memory` failure. When that failure appears:

- the share-memory review records the first failed model slot, exception class/message, attempted slots, successful slots, skipped `None` slots, and later slots that upstream would never call;
- the model handoff is marked unsuccessful with `model-share-memory-failed`;
- metadata loading and spawn setup remain recorded when they already happened;
- conversion summary, task args, Pool creation, worker init, pool imap, Python/model execution, multiprocessing, and external PDF tools remain blocked and unexecuted.

The existing no-error share-memory slot review, MPS worker-load branch, worker-init review, and empty-queue model handoff behavior are preserved.

## Red-first evidence

Before the implementation edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeShareMemoryErrorBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL records parent model share_memory failures before summary task args and pool launch
1 test files, 9 assertions, 1 failures
```

The failure showed that the planner treated the error descriptor as an ordinary array slot and still marked the following table-recognizer slot as called.

## Verification

Focused slice:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeShareMemoryErrorBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records parent model share_memory failures before summary task args and pool launch
1 test files, 42 assertions, 0 failures
```

Adjacent runtime family:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeShareMemoryErrorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeWorkerInitBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeEmptyQueueModelHandoffBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 1385 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-share-memory-error-boundary-currentbase.php
```

The smoke exits 0 and emits `share_memory_error_found=true`, `share_memory_error_slot_indexes=[2]`, `first_share_memory_error_label=ocr-recognizer`, `model_slots_after_first_error_not_called=[3]`, `conversion_summary_reached=false`, `worker_pool_error_boundary=model-share-memory-failed`, `task_args_count=0`, `pool_launchable=false`, `executes_python_or_models=false`, `executes_multiprocessing=false`, and `executes_external_pdf_tools=false`.

Syntax and diff hygiene:

```text
php -l lanes/markerpdf/src/BatchConverter.php && php -l lanes/markerpdf/tests/MarkerRuntimeShareMemoryErrorBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-marker-runtime-share-memory-error-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/src/BatchConverter.php
No syntax errors detected in lanes/markerpdf/tests/MarkerRuntimeShareMemoryErrorBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-marker-runtime-share-memory-error-boundary-currentbase.php

php -r '$p="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
```

`git diff --check -- lanes/markerpdf` exited 0.

## Non-overlap

This does not repeat accepted runtime argparse, import-order setup, input/output path normalization, output-folder conflicts, metadata load/shape/value/duplicate-key behavior, numeric gates, chunk slicing, empty task queues, invalid worker Pool creation, pool result drain, pool cleanup, MPS model-handoff branch, worker init, or the earlier no-error `None` slot share-memory review. The bounded behavior is specifically the unguarded parent `model.share_memory()` failure boundary before summary/task args/pool launch.

## Dependency closure

No new support component is needed. This reuses the native PHP `BatchConverter` runtime preflight planner, model-handoff review fixture path, focused `TestRunner`, and WordPress smoke harness. Full live OCR/model execution, Surya/Texify/Torch model workers, GPU/MPS runtime parity, pdftext runtime calls, pypdfium/PDFium rendering, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.
