# markerpdf runtime worker availability boundary current base

Slice: `markerpdf-runtime-preflight-boundary-current-base-20260608T222400Z`

Base accepted HEAD: `1a91e11e37bf1452c01f3630ee84977c3a03b00f`

## Source truth

- Upstream `convert.py::process_single_pdf` checks `markdown_exists` first, then only runs `find_filetype` and `get_length_of_text` when `min_length` is truthy, then calls `convert_single_pdf`; broad worker exceptions are printed and return Python `None`.
- Upstream `marker.pdf.utils::find_filetype` returns an unsupported type for unavailable or unreadable selected paths.
- References: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py` and `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/utils.py`.

## Patch

- `BatchConverter::processFilePreflightPlan()` now exposes `worker_file_availability_runtime_boundary` for selected input paths that disappear, become directories, become broken symlinks, or are already covered by existing markdown after task construction.
- The new review metadata distinguishes `markdown_exists`, `find_filetype`, and `convert_single_pdf` handling stages without changing the existing worker path-kind status decisions.
- `runtimeProcessSinglePdfPreflightReview()` now aggregates unavailable-input handling stages, return boundaries, and converter-reachability by filename.
- Added a focused WordPress smoke for upload paths that disappear or change type before the worker reaches filetype or conversion.

## Evidence

Red-first:

- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeWorkerAvailabilityBoundaryCurrentBaseTest.php`
- Result before implementation: `1 test files, 1 assertions, 1 failures`, missing `worker_file_availability_runtime_boundary`.

Passing focused checks:

- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeWorkerAvailabilityBoundaryCurrentBaseTest.php`
- Result: `1 test files, 33 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeWorkerPathKindBoundaryCurrentBaseTest.php`
- Result: `1 test files, 44 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-marker-runtime-worker-availability-boundary-currentbase.php`
- Result: exits 0; JSON reports missing no-min-length inputs reach `convert_single_pdf`, missing min-length inputs stop at `find_filetype`, existing markdown stops at `markdown_exists`, and no Python/models/external PDF tools run.

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. The slice reuses native PHP markerPDF preflight helpers in `BatchConverter`, `OutputWriter`, and filetype/path review code. It does not invoke OCR, Surya/Texify/Torch, PDFium/PIL, Python multiprocessing, live services, or external PDF tools.

## Non-overlap

This slice is limited to runtime worker selected-input availability boundaries. It does not change xref repair, stream filters, CMaps, fonts, metadata extraction, outlines, annotations, forms, encryption, image parsing, supplied table/equation boundaries, or model/OCR behavior.
