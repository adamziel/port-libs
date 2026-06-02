# markerPDF Runtime Conversion Boundary

Micro-slice: `runtime-conversion-boundary-currentbase`

Accepted base: `4bfec4c2ed04ec45b69266408311f6827e291bfb`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py` sets `PYTORCH_ENABLE_MPS_FALLBACK=1`, `IN_STREAMLIT=true`, and `PDFTEXT_CPU_WORKERS=1`, then uses `torch.multiprocessing` with `spawn`, builds `(filepath, out_folder, basename metadata, min_length)` task tuples, loads/shared-memory models in the parent unless Torch device/model is `mps`, and otherwise lets `worker_init(None)` load models per worker.
- Upstream `run_marker_app.py` and `marker_server.py` remain live runtime entry points for Streamlit/FastAPI. This PHP slice intentionally does not launch them; it only records the top-level batch conversion runtime boundary needed by WordPress queue admission.

Primary upstream files inspected:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/run_marker_app.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker_server.py`

## Patch

- `MarkerRuntimePlanner::conversionImportEnvironment()` records `convert.py` import-time runtime flags.
- `MarkerRuntimePlanner::convertPyMultiprocessingPlan()` records the `spawn` start method, task tuple shape, total process count, pool function names, CPU/CUDA shared-model preload branch, MPS no-share-memory branch, repeated-spawn failure message, and explicit non-execution flags.
- `wordpress-marker-runtime-conversion-boundary-currentbase.php` demonstrates a WordPress import queue inspecting the plan without running Python, Torch, multiprocessing, pdftext, pypdfium, models, Streamlit, FastAPI, or external PDF tools.

## Evidence

Red-first focused run before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php`

Failed as expected with 4 missing-method cases for `conversionImportEnvironment()` and `convertPyMultiprocessingPlan()`.

Final focused run:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php`

Passed: `1 test files, 42 assertions, 0 failures`.

Adjacent runtime/conversion family:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/ChunkConversionPlannerTest.php lanes/markerpdf/tests/SingleDocumentConverterTest.php lanes/markerpdf/tests/MarkerServerAdapterTest.php lanes/markerpdf/tests/ModelPipelinePlannerTest.php`

Passed: `6 test files, 163 assertions, 0 failures`.

Example smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-conversion-boundary-currentbase.php`

Passed: emitted `start_method="spawn"`, `total_processes=2`, CPU/CUDA shared-model preload metadata, MPS no-shared-model metadata, two task tuples, and all execution flags false.

PHP lint:

- `php -l lanes/markerpdf/src/MarkerRuntimePlanner.php`
- `php -l lanes/markerpdf/tests/MarkerRuntimePlannerTest.php`
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-conversion-boundary-currentbase.php`

All reported no syntax errors.

## Dependency Closure

No new support component is needed. This reuses the existing runtime planner and records upstream runtime decisions as static queue metadata. Full live upstream parity remains dependency-gated on Python, Torch multiprocessing, Surya/Texify/tabled model loading, pdftext, pypdfium2/PDFium, Streamlit, FastAPI/Uvicorn, and model/download/runtime infrastructure.

## Non-Overlap

This does not repeat accepted page/font/parser behavior, PDF object/xref repair, metadata extraction, Streamlit command planning, marker_server upload/local/remote response handling, chunk_convert device sharding, batch skip gates, or single-document artifact writing. The bounded behavior is only the top-level `convert.py` multiprocessing/model-handoff runtime boundary for WordPress queue preflight.
