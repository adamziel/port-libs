# markerPDF runtime task preflight sidecar boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T024528Z`
Session: `port-dev-markerpdf-runtime-preflight-20260605T024528Z`
Base accepted HEAD: `9256381009cdcb486320eac5ba9009ee7a949f5c`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` builds `files = [os.path.join(in_folder, f) for f in os.listdir(in_folder)]`, filters only `os.path.isfile`, then builds `task_args = [(f, out_folder, metadata.get(os.path.basename(f)), args.min_length) for f in files_to_convert]`.
- Upstream `process_single_pdf` checks `markdown_exists` first. Only when `min_length` is truthy does it run `find_filetype(filepath)`, return `0` for `filetype == "other"`, and otherwise call `get_length_of_text` before `convert_single_pdf`.
- Primary source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.
- Live Python, Torch multiprocessing, Surya/Texify/tabled models, `pdftext`, PDFium/PIL, Streamlit/FastAPI, and external OCR/rendering tools remain intentionally out of scope.

## Patch

- `BatchConverter::runtimeMainPreflightPlan()` now includes `worker_pool.process_single_pdf_preflight` for the selected task args when the pool would launch.
- The review records task-arg filenames, selected non-PDF sidecars, worker-side `markdown_exists`/`min_length`/`find_filetype`/text-length statuses, upstream return boundaries, and a sidecar-specific `unsupported-filetype-return-zero` marker.
- The existing WordPress runtime preflight smoke now emits `runtime_task_preflight_*` and sidecar rejection fields.
- `UPSTREAM_TEST_MANIFEST.json` maps `markerRuntimeMainPreflightBoundaryCurrentBaseBehaviors` from `3` to `4`; `lane-status.json` moves `phpPass` `1312 -> 1313` and `wordpressScenarios` `1267 -> 1268`.

## Evidence

Red-first focused run after adding the test and before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Failed as expected: `1 test files, 513 assertions, 1 failures`; the new case found missing `worker_pool.process_single_pdf_preflight`.

Focused assigned gate after patch:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Passed: `1 test files, 536 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Passed and emitted `runtime_task_preflight_reached=true`, `runtime_sidecar_reaches_task_args_before_preflight=true`, `runtime_sidecar_rejected_by_process_single_pdf_filenames=["upload-notes.txt"]`, `runtime_sidecar_rejection_boundary=unsupported-filetype-return-zero`, and all Python/model/multiprocessing/external-tool execution flags false.

## Dependency Closure

No new support component is needed. This slice reuses native PHP runtime task planning, output markdown detection, filetype detection, native text-length preflight, and existing no-execution runtime review. Full upstream runtime parity remains dependency-gated on Python/Torch multiprocessing, model execution, `pdftext`, PDFium/PIL, Streamlit/FastAPI/Uvicorn, and external OCR/rendering helpers; none were executed.

## Non-Overlap

This does not repeat accepted output-folder conflict ordering, metadata JSON load/shape/value boundaries, model handoff, spawn start-method failures, conversion-summary ordering, pool process-count creation, numeric truthiness, negative chunk slicing, input file-list extension-filter admission, standalone `process_single_pdf` return-value tests, post-conversion return boundaries, single-document runtime preflight, server/benchmark artifacts, or native PDF parser/font/xref/security/image/table/form/outline metadata slices. The bounded behavior is only the handoff from selected `convert.py` task args into worker-side `process_single_pdf` preflight for non-PDF sidecars before conversion/model execution.
