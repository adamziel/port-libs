# markerPDF Runtime Preflight Negative Num Chunks Boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T095142Z`
Session: `port-dev-markerpdf-runtime-preflight-20260605T095142Z`
Base accepted HEAD: `20d1bb54f869351244771d9cbfd24f8d3e6dee83`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` parses `--num_chunks` as an `int`, then computes `chunk_size = math.ceil(len(files) / args.num_chunks)` before Python slicing.
- `--num_chunks 0` still raises at chunk math, but negative values are accepted by argparse and produce negative chunk sizes that Python slice semantics can normalize.
- Source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.
- Live Python, Torch multiprocessing, pdftext, pypdfium/PDFium, Surya/Texify/tabled models, OCR/raster helpers, Streamlit/FastAPI, and external PDF tools remain intentionally out of scope.

## Patch

- `BatchConverter::runtimeMainPreflightPlan()` now uses a runtime-specific chunk planner that preserves the existing zero-count failure while allowing negative `num_chunks` to follow upstream `math.ceil` and Python slice bounds.
- Returned `chunking` rows now expose `negative_num_chunks_active` and `num_chunks_less_than_one_active` review fields.
- `wordpress-marker-runtime-preflight-boundary-currentbase.php` now emits `negative_num_chunks_*` fields for WordPress batch import review.
- Manifest coverage adds `markerRuntimeNegativeNumChunksBoundaryCurrentBase`; lane status moves `phpPass 1691 -> 1692` and `wordpressScenarios 1551 -> 1552`.

## Evidence

Red-first focused run after adding the regression and before the fix:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Result: `1 test files / 780 assertions / 1 failure`; negative `num_chunks` raised `Batch chunk count must be at least one.`

Focused run after fix:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Result: `1 test files / 809 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Result: emitted `negative_num_chunks_chunk_size=-2`, `negative_num_chunks_raw_slice=[0,-2]`, `negative_num_chunks_python_slice=[0,3]`, `negative_num_chunks_task_args_count=3`, and all Python/model/multiprocessing/external-tool execution flags false.

## Dependency Closure

No new support component is needed. This reuses native PHP runtime planning, filesystem listing, Python-slice normalization, metadata lookup, conversion-summary review, and WordPress smoke output. Full live runtime parity remains dependency-gated on Python/Torch multiprocessing, pdftext, pypdfium/PDFium, Surya/Texify/tabled models, OCR/raster helpers, Streamlit/FastAPI, and external PDF tools.

## Non-Overlap

This does not repeat accepted output-folder conflicts, input-folder listing/permission failures, metadata JSON load/shape/value boundaries, numeric max/min_length truthiness, negative `chunk_idx` slicing, spawn-start failures, model handoff branches, worker pool creation/cleanup boundaries, conversion summary ordering, process_single_pdf return-value gates, text-length exceptions, single-document runtime preflight, server/benchmark artifacts, encrypted permission preflight, or native PDF parser/font/xref/security/image/table/form/outline metadata slices. The bounded behavior is only negative `--num_chunks` runtime chunk math and selected-task review before model or multiprocessing execution.
