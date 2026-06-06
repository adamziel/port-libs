# Runtime Argparse Repeated Option Boundary Current Base

Session: `port-dev-markerpdf-runtime-preflight-20260606T213517Z`
Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260606T213517Z`
Accepted base: `82417ef603248e0de68523a91f6e2f08dde5f687`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`
`convert.py::main` uses Python `argparse.ArgumentParser.parse_args()` for batch
options. Repeating a scalar option such as `--metadata_file`, `--workers`, or
`--min_length` keeps the final occurrence before any folder listing, metadata
JSON loading, model handoff, multiprocessing pool launch, or external PDF work.

## Implemented Boundary

`BatchConverter::runtimeMainArgumentPreflightPlan()` now records repeated batch
option occurrence counts, value history, override flags, repeated-option names,
and a `last_occurrence_wins` semantic boundary. Final option values still match
upstream argparse behavior, while stale earlier values remain review metadata for
WordPress queue diagnostics.

The local WordPress smoke exercises repeated `--metadata_file`, `--workers`, and
`--min_length` values and confirms the stale metadata/min-length inputs are not
used for final runtime planning.

## Evidence

Red-first focused run before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeArgparseRepeatedOptionBoundaryCurrentBaseTest.php`

Result: `1 test files / 10 assertions / 1 failures`, because
`option_occurrences` was absent.

Focused run after the patch:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeArgparseRepeatedOptionBoundaryCurrentBaseTest.php`

Result: `1 test files / 28 assertions / 0 failures`.

Adjacent runtime argparse family:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeArgparseBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeArgparseRepeatedOptionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Result: `3 test files / 1349 assertions / 0 failures`.

Broader runtime focused sweep:

`php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name 'MarkerRuntime*Test.php' | sort)`

Result: `23 test files / 2391 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-argparse-repeat-option-boundary-currentbase.php`

Result: reports `last_occurrence_wins=true`,
`metadata_file_final=current-wordpress-metadata.json`, `workers_final=4`,
`min_length_final=0`, `stale_metadata_file_excluded=true`,
`stale_min_length_excluded=true`,
`repeated_options_last_occurrence_wins=true`,
`filesystem_touched_before_error=false`,
`executes_python_or_models=false`, `executes_multiprocessing=false`, and
`executes_external_pdf_tools=false`.

Syntax checks:

`php -l lanes/markerpdf/src/BatchConverter.php`

`php -l lanes/markerpdf/tests/MarkerRuntimeArgparseRepeatedOptionBoundaryCurrentBaseTest.php`

`php -l lanes/markerpdf/examples/wordpress-marker-runtime-argparse-repeat-option-boundary-currentbase.php`

All reported no syntax errors.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`BatchConverter` runtime preflight surface and stays entirely before Python,
Torch/Surya/Texify, OCR, multiprocessing, filesystem side effects, and external
PDF tools.

## Non-Overlap

This slice does not touch live OCR/model execution, Streamlit/FastAPI workers,
conversion model parity, xref repair, attachment parsing, metadata extraction,
annotations, forms, fonts, CMaps, stream filters, page geometry, image metadata,
or supplied table/equation handoffs. It extends only the existing no-GPU
`convert.py::main` argparse preflight boundary with repeated-option review
metadata.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser
and converter behavior: fonts, CMaps, stream filters, xref repair, metadata,
outlines, annotations, forms, security preflight, page geometry,
image/filter metadata, and supplied-boundary table/equation handoffs.
