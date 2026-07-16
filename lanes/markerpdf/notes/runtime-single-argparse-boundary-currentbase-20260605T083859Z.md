# markerpdf runtime single argparse boundary current base

Slice: `markerpdf-runtime-preflight-boundary-current-base-20260605T083859Z`
Base: `ac12c42e994f416d094241f7a93c82358b0383b8`

## Source truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` has `convert_single.py::main` parse `filename`, `output`, `--max_pages`, `--start_page`, `--langs`, and `--batch_multiplier`, then derive languages with `args.langs.split(",") if args.langs else None` before `load_all_models()`.

## Implemented boundary

`SingleDocumentConverter::runtimeArgumentPreflightPlan()` now records that parser boundary without touching files, loading Python, importing pdfium/Torch, starting model workers, or running external PDF tools. It records defaults, argparse-style invalid integer and missing positional errors, `allow_abbrev` option resolution, zero/negative numeric values that argparse admits for later conversion semantics, empty `--langs` mapping to null, and whitespace-preserving comma language splitting.

The existing WordPress runtime argparse smoke now reports both batch `convert.py` and single-upload `convert_single.py` parser admission so import queues can block malformed single-upload CLI requests before model handoff.

## Evidence

Red-first focused run after adding the regression:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeArgparseBoundaryCurrentBaseTest.php`

Result: `1 test files, 53 assertions, 1 failures` with `Call to undefined method PortLibs\MarkerPDF\SingleDocumentConverter::runtimeArgumentPreflightPlan()`.

Focused runs after implementation:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeArgparseBoundaryCurrentBaseTest.php`

Result: `1 test files, 103 assertions, 0 failures`.

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeArgparseBoundaryCurrentBaseTest.php lanes/markerpdf/tests/SingleDocumentConverterTest.php`

Result: `2 test files, 129 assertions, 0 failures`.

Smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-argparse-boundary-currentbase.php`

Result: emitted `single_default_parse_success=true`, `single_custom_parsed_langs=["English"," Spanish","de"]`, `single_empty_langs_becomes_none=true`, `single_invalid_max_pages_error_boundary=argparse-system-exit`, `single_executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency closure

No new support component is needed. The slice reuses native PHP argument normalization and existing `SingleDocumentConverter::parseLanguages()` behavior. Live OCR, Surya/Texify/Torch execution, Streamlit/FastAPI workers, pdfium rendering, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF lane.
