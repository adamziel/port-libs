# Runtime Single Argparse Repeated Option Boundary Current Base

Session: `port-dev-markerpdf-runtime-preflight-20260607T014435Z`
Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260607T014435Z`
Accepted base: `ee38ac4e40d34d8ace81ef748756b7c6f6cb32f9`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`
`convert_single.py::main` uses Python `argparse.ArgumentParser.parse_args()`
for scalar single-document options. Repeating `--langs`, `--max_pages`,
`--start_page`, or `--batch_multiplier` keeps the final occurrence before
language splitting, `load_all_models()`, conversion, markdown saving, or any
model/runtime work.

## Implemented Boundary

`SingleDocumentConverter::runtimeArgumentPreflightPlan()` now records repeated
single-document option occurrence counts, value history, override flags,
repeated-option names, and a `last_occurrence_wins` semantic boundary. The final
parsed values still match upstream argparse behavior, while stale earlier values
remain review metadata for WordPress single-upload diagnostics.

The local WordPress smoke now exercises both batch `convert.py` repeated options
and single-upload `convert_single.py` repeated options without touching uploads,
metadata JSON, Python, model loading, multiprocessing, or external PDF tools.

## Evidence

Red-first focused run after adding the new single-document case and before the
source change:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeArgparseRepeatedOptionBoundaryCurrentBaseTest.php`

Result: `1 test files / 41 assertions / 1 failures`, because
`option_occurrences` was absent for the single-document plan.

Focused run after the patch:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeArgparseRepeatedOptionBoundaryCurrentBaseTest.php`

Result: `1 test files / 55 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-argparse-repeat-option-boundary-currentbase.php`

Result: reports `single_last_occurrence_wins=true`,
`single_langs_final=Spanish,French`, `single_parsed_langs=[Spanish,French]`,
`single_max_pages_final=5`, `single_start_page_final=2`,
`single_batch_multiplier_final=4`, `single_next_stage=load_all_models`,
`single_filesystem_touched_before_error=false`,
`single_executes_python_or_models=false`,
`single_executes_multiprocessing=false`, and
`single_executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`SingleDocumentConverter` runtime preflight surface and stays entirely before
Python, Torch/Surya/Texify, OCR, multiprocessing, filesystem side effects, and
external PDF tools.

## Non-Overlap

This slice does not touch live OCR/model execution, Streamlit/FastAPI workers,
conversion model parity, xref repair, attachment parsing, metadata extraction,
annotations, forms, fonts, CMaps, stream filters, page geometry, image metadata,
or supplied table/equation handoffs. It extends only the existing no-GPU
`convert_single.py::main` argparse preflight boundary with repeated-option
review metadata, complementing the already accepted batch `convert.py` repeated
option boundary.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser
and converter behavior: fonts, CMaps, stream filters, xref repair, metadata,
outlines, annotations, forms, security preflight, page geometry,
image/filter metadata, and supplied-boundary table/equation handoffs.
