# markerPDF runtime preflight conversion summary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260604T215132Z`
Session: `port-dev-markerpdf-runtime-preflight-20260604T215132Z`
Base accepted HEAD: `42dddc08604dab6783842b91ae410655f23b3754`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` computes `total_processes = min(len(files_to_convert), args.workers)`, prepares the Torch model handoff, prints `Converting {len(files_to_convert)} pdfs in chunk {args.chunk_idx + 1}/{args.num_chunks} with {total_processes} processes, and storing in {out_folder}`, then builds `task_args` and enters `mp.Pool`.
- The same upstream path reaches that print for empty chunks and invalid worker counts after metadata/model-handoff preparation, but output-folder creation with `os.makedirs(out_folder, exist_ok=True)` fails before the print.
- Primary source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.

## Patch

- `BatchConverter::runtimeMainPreflightPlan()` now includes `print_conversion_summary` in the main preflight order between `prepare_model_handoff` and `build_task_args`.
- Added `console_summary` review metadata with the upstream message line, selected count, displayed chunk index, process count, output folder, order flags, and a blocked-by reason when output folder creation fails first.
- `wordpress-marker-runtime-preflight-boundary-currentbase.php` now emits the conversion summary row plus output-conflict summary-blocking evidence without executing Python, Torch, pdftext, pypdfium, model workers, multiprocessing, or external PDF tools.

## Evidence

Red-first focused run before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Failed as expected: `1 test file / 180 assertions / 4 failures` for missing `print_conversion_summary` and `console_summary` rows.

Focused assigned gate after patch:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Passed: `1 test files, 248 assertions, 0 failures`.

Adjacent batch/runtime gate:

`php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Passed: `2 test files, 359 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Passed and emitted `runtime_conversion_summary_line`, `runtime_conversion_summary_before_task_args=true`, `runtime_conversion_summary_before_pool_launch=true`, `output_conflict_conversion_summary_reached=false`, and all Python/model/multiprocessing/external-tool execution flags false.

## Status Delta

- `lane-status.json` `phpPass`: `1094 -> 1095`.
- `lane-status.json` `wordpressScenarios`: `1094 -> 1095`.
- `UPSTREAM_TEST_MANIFEST.json` maps `markerRuntimeMainPreflightBoundaryCurrentBaseBehaviors`: `2 -> 3`.

## Dependency Closure

No new support component is needed. This reuses native PHP batch runtime planning, chunk slicing, output-folder admission, metadata loading, and worker-count review. Full live upstream parity remains intentionally out of scope under the no-GPU markerPDF rule because it requires Python, Torch multiprocessing, Surya/Texify/tabled models, `pdftext`, `pypdfium2`/PDFium, OCR/raster helpers, Streamlit/FastAPI runtime paths, and model/download infrastructure.

## Non-Overlap

This does not repeat accepted runtime conversion model-handoff planning, single-document runtime preflight, batch progress/resume, output-folder file-conflict admission, runtime numeric gates, input file-list filtering, process_single_pdf return boundaries, marker server upload/pagination/error behavior, benchmark artifacts, or native PDF parser/xref/font/image/form/metadata behavior. The bounded behavior is only the upstream batch `convert.py` stdout conversion-summary boundary and its position before task tuple construction and pool launch.
