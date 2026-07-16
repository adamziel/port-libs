# markerPDF runtime preflight model handoff boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260604T232629Z`
Session: `port-dev-markerpdf-runtime-preflight-20260604T232629Z`
Base accepted HEAD: `4e5b254a36b80b692f93413b376a79f6d854dcc7`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` sets the torch multiprocessing start method to `spawn`, computes `total_processes`, then branches on `settings.TORCH_DEVICE` / `settings.TORCH_DEVICE_MODEL`.
- In the non-MPS branch, upstream calls `load_all_models()` in the parent process, calls `share_memory()` on each loaded model, prints the conversion summary, builds task tuples, and starts `mp.Pool(..., initargs=(model_lst,))`.
- In the MPS branch, upstream prints the shared-memory warning, passes `None` to `worker_init`, and each worker loads models itself. The native PHP lane records this as review metadata only.
- Source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.

## Patch

- `BatchConverter::runtimeMainPreflightPlan()` now accepts optional `torchDevice` and `torchDeviceModel` review inputs and emits a `model_handoff` block.
- The block records whether the model handoff is reached, its order before conversion summary/task args, the CPU/CUDA parent `load_all_models` + `share_memory` branch, the MPS worker-load branch, warning text, and no-execution flags.
- Output-folder creation conflicts now include a blocked `model_handoff` row showing that upstream would stop before model loading, summary output, task args, multiprocessing, or external PDF tools.
- `wordpress-marker-runtime-preflight-boundary-currentbase.php` now emits CPU/CUDA and MPS model-handoff review fields for WordPress batch import queues.

## Evidence

Red-first focused run after adding the assertions and before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Failed as expected: `1 test files / 248 assertions / 1 failure` because `runtimeMainPreflightPlan()` did not accept `torchDevice` and did not emit `model_handoff`.

Focused assigned gate after the patch:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Passed: `1 test files / 281 assertions / 0 failures`.

Adjacent batch/runtime gate:

`php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php`

Passed: `3 test files / 468 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Passed and emitted `runtime_parent_loads_models=true`, `runtime_parent_share_memory_before_pool=true`, `runtime_worker_init_argument=model_lst`, `mps_runtime_uses_worker_model_loading=true`, `mps_runtime_parent_loads_models=false`, `mps_runtime_warning_recorded=true`, and all Python/model/multiprocessing/external-tool execution flags false.

## Dependency Closure

No new support component is needed. This slice reuses the existing native batch runtime preflight planner and records upstream model-handoff order without invoking Python, Torch, pdftext, pypdfium/PDFium, Surya, Texify, tabled-pdf, OCR, Streamlit/FastAPI, multiprocessing, or external PDF tools. Full upstream runtime execution remains intentionally out of scope under the current no-GPU markerPDF lane rule.

## Non-Overlap

This does not repeat the existing standalone `MarkerRuntimePlanner::convertPyMultiprocessingPlan()` task tuple planner, single-document runtime preflight, return-value boundaries, numeric gate truthiness, negative chunk slicing, file-list admission, output-folder conflict ordering, conversion-summary stdout ordering, server upload/config/error artifacts, benchmark artifacts, or native PDF parser/font/xref/security/image/table/form/outline metadata slices. The bounded behavior is only `convert.py::main` model handoff ordering inside the batch runtime admission review.
