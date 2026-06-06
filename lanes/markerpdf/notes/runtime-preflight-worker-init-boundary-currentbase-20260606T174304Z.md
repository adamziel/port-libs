# markerPDF runtime worker_init boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260606T174304Z`
Session: `port-dev-markerpdf-runtime-preflight-20260606T174304Z`
Base accepted HEAD: `90edc6b63e340cfbca7035a078ed73b69217b640`

## Source truth

Pinned upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py` defines:

- `worker_init(shared_model)`: if `shared_model is None`, it calls `load_all_models()`, then assigns `model_refs = shared_model`.
- `main()`: non-MPS devices set `model_lst = load_all_models()` and pass it through `mp.Pool(..., initializer=worker_init, initargs=(model_lst,))`.
- `main()`: MPS devices set `model_lst = None`, so each worker loads models in `worker_init`.

This slice records that branch as native no-execution review metadata only. It does not run Python, Torch, pypdfium2, pdftext, Surya, Texify, multiprocessing workers, OCR/model inference, Streamlit/FastAPI, or external PDF tools.

## Native PHP change

- `BatchConverter::runtimeMainPreflightPlan()` now includes `worker_pool.worker_initializer`.
- The record captures the upstream initializer name, `shared_model` argument, `initargs=(model_lst,)`, process count, parent shared-model reuse for CUDA/CPU, MPS worker-side `load_all_models()` selection, `model_refs = shared_model`, and the `pool-process-count-failed` block before `process_single_pdf`.
- Added `MarkerRuntimeWorkerInitBoundaryCurrentBaseTest.php`.
- Added `wordpress-marker-runtime-worker-init-boundary-currentbase.php`.

## Evidence

Red-first focused run after adding the focused test and before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeWorkerInitBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PHP Warning: Undefined array key "worker_initializer" ...
FAIL records convert.py worker_init shared-model branch before process_single_pdf
1 test files, 1 assertions, 1 failures
```

Focused run after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeWorkerInitBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records convert.py worker_init shared-model branch before process_single_pdf
1 test files, 39 assertions, 0 failures
```

Adjacent runtime family check:

```text
php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePoolContextManagerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeWorkerInitBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 1289 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-marker-runtime-worker-init-boundary-currentbase.php
```

Passed and emitted `shared_parent_model_reused=true`, `mps_loads_models_in_worker=true`, `mps_model_refs_source=worker-loaded-model-list`, `zero_worker_initializer_blocked_by=pool-process-count-failed`, `executes_python_or_models=false`, `executes_multiprocessing=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted runtime argparse, input/output abspath, os.listdir ordering, output-folder conflict, chunk slicing, metadata-file load/shape/value/duplicate/numeric keys, model handoff, share-memory slot, pool creation, pool context manager, result drain, worker cleanup, process_single_pdf return-value, post-conversion, server/benchmark artifact, or native PDF parser/font/xref/security/image/table/form/outline behavior. The bounded behavior is only the `worker_init(shared_model)` branch between pool entry and `process_single_pdf`.

## Dependency closure

No new support component is needed. This reuses the existing native `BatchConverter` runtime preflight model, PHP filesystem fixtures, focused TestRunner coverage, and a WordPress smoke path. GPU/model execution remains intentionally out of scope under the markerPDF no-GPU directive.
