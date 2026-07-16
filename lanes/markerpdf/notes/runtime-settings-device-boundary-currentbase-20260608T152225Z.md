# markerPDF runtime settings device boundary current-base

## Source truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream `marker/settings.py` defines `Settings.TORCH_DEVICE_MODEL` as explicit `TORCH_DEVICE` when present, otherwise `cuda`, then `mps`, then `cpu` based on Torch backend probes.
- The same upstream settings object computes `CUDA` with the case-sensitive Python expression `"cuda" in self.TORCH_DEVICE_MODEL`, `MODEL_DTYPE` with exact `TORCH_DEVICE_MODEL == "cuda"`, and `TEXIFY_DTYPE` with exact `TORCH_DEVICE_MODEL == "cpu"`.
- Upstream `Settings.Config` points at `find_dotenv("local.env")` with `extra = "ignore"`.

## Implementation

- Added `MarkerSettings::runtimeDevicePreflightPlan()` as a no-execution review plan for the runtime settings boundary before markerPDF conversion workers are admitted.
- The plan records computed `TORCH_DEVICE_MODEL`, `CUDA`, `MODEL_DTYPE`, and `TEXIFY_DTYPE` values, explicit environment keys, ignored unknown setting keys, and the `local.env` lookup boundary.
- Native PHP intentionally does not probe Torch, CUDA, MPS, process environments, or `local.env`; callers pass an explicit environment array into the preflight review.
- Added `wordpress-marker-runtime-settings-device-boundary-currentbase.php` to show WordPress import settings review for uppercase `MPS`, lowercase `cuda`, ignored unknown settings, and no Python/Torch/model/external-tool execution.

## Red-first evidence

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeSettingsDeviceBoundaryCurrentBaseTest.php`

Result before implementation: `1 test files, 0 assertions, 2 failures`; both cases failed on missing `MarkerSettings::runtimeDevicePreflightPlan()`.

## Verification

`php -l lanes/markerpdf/src/MarkerSettings.php`

Result: no syntax errors.

`php -l lanes/markerpdf/tests/MarkerRuntimeSettingsDeviceBoundaryCurrentBaseTest.php`

Result: no syntax errors.

`php -l lanes/markerpdf/examples/wordpress-marker-runtime-settings-device-boundary-currentbase.php`

Result: no syntax errors.

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeSettingsDeviceBoundaryCurrentBaseTest.php`

Result after implementation: `1 test files, 51 assertions, 0 failures`.

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeSettingsDeviceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerSettingsTest.php`

Result: `2 test files, 89 assertions, 0 failures`.

`php lanes/markerpdf/examples/wordpress-marker-runtime-settings-device-boundary-currentbase.php`

Result: exits 0 and emits `uppercase_mps_device_model=MPS`, `uppercase_mps_cuda=false`, `uppercase_mps_model_dtype=float32`, `cuda_model_dtype=bfloat16`, `native_torch_backend_probe_executed=false`, `ignored_environment_keys=["UNKNOWN_MARKER_SETTING"]`, `native_reads_env_file=false`, `executes_python_or_models=false`, `executes_torch_backend_probe=false`, `executes_multiprocessing=false`, and `executes_external_pdf_tools=false`.

`git diff --check -- lanes/markerpdf`

Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This slice reuses the native `MarkerSettings` PHP mirror and records review-only runtime metadata. Full upstream runtime execution still requires Python, Torch, pypdfium/pdftext, Surya/Texify/table models, OCR/raster helpers, multiprocessing, and Streamlit/FastAPI paths, all intentionally out of scope under the current no-GPU markerPDF lane direction.

## Non-overlap

This does not repeat accepted `convert.py` import ordering, argparse admission, metadata-file handling, input/output directory gates, chunking, spawn start-method, model share-memory slots, MPS model-handoff case branching, pool cleanup, worker-init, empty queue, `process_single_pdf`, save-markdown, single-document runtime, PDF parser/xref/filter/font/security/image/form/outline metadata, OCR/model execution, or supplied table/equation handoffs. The bounded behavior is only upstream `settings.py` computed runtime settings and environment review before any model/backend probe or worker launch.
