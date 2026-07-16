# markerPDF runtime argparse boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T064550Z`
Session: `port-dev-markerpdf-runtime-preflight-20260605T064550Z`
Base accepted HEAD: `648921bad2812fb886ed9ddc4a44b11bdbf63665`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` constructs `argparse.ArgumentParser(description="Convert multiple pdfs to markdown.")`.
- It declares required `in_folder` and `out_folder` positionals plus integer `--chunk_idx`, `--num_chunks`, `--max`, `--workers`, string `--metadata_file`, and integer `--min_length`.
- `args = parser.parse_args()` runs before `os.path.abspath()`, `os.listdir()`, `os.makedirs()`, metadata JSON loading, `mp.set_start_method('spawn')`, model handoff, conversion summary output, task tuple construction, and `mp.Pool`.
- Source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`.

## Patch

- Added `BatchConverter::runtimeMainArgumentPreflightPlan()` as a review-only native PHP mirror of the `convert.py::main` argparse boundary.
- The plan records CLI defaults, explicit option values, upstream-style `SystemExit` code 2 parse failures, missing positionals, missing option values, invalid integer values, unique long-option abbreviation, ambiguous abbreviations, and the stages blocked before runtime side effects.
- Added `MarkerRuntimeArgparseBoundaryCurrentBaseTest.php` with a single focused behavior case and `wordpress-marker-runtime-argparse-boundary-currentbase.php` as the WordPress smoke.
- Updated the markerPDF manifest/status row by one mapped runtime preflight behavior and one WordPress scenario.

## Evidence

Red-first focused run after adding the test and before source implementation:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeArgparseBoundaryCurrentBaseTest.php`

Failed as expected: `1 test files, 0 assertions, 1 failures` with `Call to undefined method PortLibs\MarkerPDF\BatchConverter::runtimeMainArgumentPreflightPlan()`.

Focused assigned gate after the patch:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeArgparseBoundaryCurrentBaseTest.php`

Passed: `1 test files / 53 assertions / 0 failures`.

Adjacent runtime gate:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeArgparseBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePlannerTest.php`

Passed: `4 test files / 952 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-argparse-boundary-currentbase.php`

Passed and emitted `default_parse_success=true`, `invalid_worker_error_boundary=argparse-system-exit`, `invalid_worker_exit_code=2`, `missing_out_folder_blocks_filesystem=true`, `abbreviated_workers_value=3`, `ambiguous_option_error`, and all Python/model/multiprocessing/external-tool execution flags false.

## Dependency Closure

No new support component is needed. This slice reuses the existing native batch runtime preflight lane and records upstream CLI parse admission without invoking Python, Torch, pdftext, pypdfium/PDFium, Surya, Texify, tabled-pdf, OCR, Streamlit/FastAPI, multiprocessing, filesystem conversion, or external PDF tools. Full runtime execution remains intentionally out of scope under the current no-GPU markerPDF lane rule.

## Non-Overlap

This does not repeat output-folder creation conflicts, input-folder listing failures, metadata JSON load/shape/value boundaries, numeric truthiness after typed args, negative chunk slicing, spawn start-method collisions, model handoff branches, conversion summary ordering, worker pool creation/cleanup, per-file `process_single_pdf` preflight, single-document runtime preflight, server runtime artifacts, benchmark artifacts, or native PDF parser/font/xref/security/image/table/form/outline metadata slices. The bounded behavior is only `convert.py::main` CLI `argparse` admission before runtime side effects.
