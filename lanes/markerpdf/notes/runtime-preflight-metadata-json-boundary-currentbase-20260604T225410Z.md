# markerPDF runtime metadata JSON preflight boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260604T225410Z`
Session: `port-dev-markerpdf-runtime-preflight-20260604T225410Z`
Base accepted HEAD: `524dc40526b2fcb46fefc7d28613d818c4db4c08`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` lists input files, calls `os.makedirs(out_folder, exist_ok=True)`, computes chunk slices, then loads optional `--metadata_file` via `json.load(f)` before `mp.set_start_method('spawn')`, model handoff, the conversion summary print, task tuple construction, or `mp.Pool`.
- Primary source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.
- Live Python, Torch multiprocessing, pdftext, pypdfium2/PDFium, Surya/Texify/tabled models, Streamlit/FastAPI, and external PDF tools remain intentionally out of scope.

## Patch

- `BatchConverter::runtimeMainPreflightPlan()` now catches JSON decode failures from a readable `metadata_file` and returns a structured `metadata-file-json-load-failed` review plan.
- The plan preserves already-computed input listing and chunk selection while proving metadata failure blocks model handoff, conversion summary stdout, task args, and pool launch.
- Missing or unreadable metadata files still preserve the accepted exception behavior from the existing metadata-order test.
- `wordpress-marker-runtime-preflight-boundary-currentbase.php` now emits the malformed metadata JSON boundary for WordPress import review.
- `UPSTREAM_TEST_MANIFEST.json` maps `markerRuntimeMainPreflightBoundaryCurrentBaseBehaviors` from `3` to `4`; `lane-status.json` moves `phpPass` and `wordpressScenarios` from `1098` to `1099`.

## Evidence

Red-first focused run after adding the test and before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Failed as expected: `1 test files, 248 assertions, 1 failures`; the new malformed metadata case threw `Batch metadata file must contain valid JSON.`

Focused assigned gate after patch:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Passed: `1 test files, 275 assertions, 0 failures`.

Adjacent runtime/batch gate:

`php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php`

Passed: `3 test files, 462 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Passed and emitted `metadata_error_boundary=metadata-file-json-load-failed`, `metadata_error_task_args_count=0`, `metadata_error_conversion_summary_reached=false`, and all Python/model/multiprocessing/external-tool execution flags false.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP batch runtime planner, JSON metadata loader, chunk slicing, output-folder admission, conversion-summary review, and WordPress smoke path. Full upstream runtime parity remains dependency-gated on Python, Torch multiprocessing, `pdftext`, `pypdfium2`/PDFium, Surya/Texify/tabled model workers, model downloads, Streamlit/FastAPI/Uvicorn paths, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted per-file `process_single_pdf` skip gates, metadata-file ordering exceptions, output-folder file-conflict admission, runtime numeric gates, negative chunk slicing, input file-list filtering, empty/invalid worker-pool boundaries, conversion summary ordering, single-document runtime preflight, batch progress/resume, marker app/server config, upload/pagination/error artifacts, benchmark artifacts, or native PDF parser/xref/font/image/form/outline/metadata behavior. The bounded behavior is only readable-but-malformed `metadata_file` JSON failing closed before model handoff and worker launch.
