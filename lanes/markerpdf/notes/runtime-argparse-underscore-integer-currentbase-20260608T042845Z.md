# markerPDF runtime argparse underscore integer boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260608T042845Z`
Session: `port-dev-markerpdf-runtime-preflight-20260608T042845Z`
Base accepted HEAD: `e8c43317726abb932805c171a399c58fb2c01c99`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` uses Python `argparse.ArgumentParser` with `type=int` for `convert.py` options `--chunk_idx`, `--num_chunks`, `--max`, `--workers`, and `--min_length`, and for `convert_single.py` options `--max_pages`, `--start_page`, and `--batch_multiplier`.
- Python `int()` accepts underscore digit separators only between digits, so values like `1_0`, `+1_2`, and `-3_0` are admitted, while malformed forms like `1__0` and `10_` fail at argparse before filesystem, metadata, model, multiprocessing, or external-tool stages.
- Primary source inspected: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py

## Implementation

- Updated `BatchConverter::runtimeMainArgparseIntegerValue()` and `SingleDocumentConverter::runtimeArgumentIntegerValue()` to mirror Python integer underscore separator admission for runtime review.
- Preserved fail-closed argparse errors for malformed underscore separators.
- Added focused tests covering accepted batch and single-document underscore integers plus malformed batch/single error cases.
- Added a WordPress smoke showing batch and single-document import CLI review reaches the next upstream boundary for valid underscore integers, while malformed separators stop at argparse with no filesystem/model/multiprocessing/external PDF tool execution.

## Red-First Evidence

Before the fix, this probe on accepted base rejected a valid Python integer literal:

`php -r 'require "tools/bootstrap.php"; $p=(new PortLibs\MarkerPDF\BatchConverter())->runtimeMainArgumentPreflightPlan(["/wp/uploads","/wp/out","--workers","1_0"]); var_export([$p["parse_args"]["parse_args_success"], $p["parse_args"]["error_message"] ?? null]); echo "\n";'`

Result before implementation:

`[false, "argument --workers: invalid int value: '1_0'"]`

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeArgparseUnderscoreIntegerBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 58 assertions, 0 failures`
  - New focused PASS cases: 2
- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeArgparseBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeArgparseEqualsTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeArgparseRepeatedOptionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeArgparseResponseFileBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeArgparseUnderscoreIntegerBoundaryCurrentBaseTest.php`
  - Result: `5 test files, 322 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-marker-runtime-argparse-underscore-integer-currentbase.php`
  - Result: exits 0 and emits `batch_workers=10`, `batch_chunk_idx=12`, `batch_max=-30`, `batch_min_length=80`, `single_max_pages=10`, `single_start_page=-20`, `single_batch_multiplier=32`, malformed-separator argparse errors, and all Python/model/multiprocessing/external-tool execution flags false.

## Non-Overlap

This does not repeat accepted runtime preflight numeric truthiness, zero/negative chunk math, repeated options, equals-token abbreviations, response-file literals, metadata file shape/value handling, output-folder conflicts, file-list/symlink admission, worker pool boundaries, model share-memory handoff, or process_single_pdf return gates. The bounded behavior is only Python `type=int` underscore separator parity at argparse admission for batch and single-document markerPDF runtime review.

## Dependency Closure

No new support component is needed. This slice reuses native PHP runtime argument planning and WordPress smoke paths. Live Python, pdftext, pypdfium/PDFium, Torch multiprocessing, Surya/Texify/tabled models, OCR/raster helpers, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF directive.
