# markerpdf runtime argparse terminator boundary current-base 2026-06-08

## Scope

This no-GPU native slice covers the upstream runtime argparse boundary for
`convert.py` and `convert_single.py` at the manifest-pinned
`sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.

Source truth:

- `convert.py` declares the batch positional arguments and options with
  `argparse.ArgumentParser`, calls `parse_args()`, and only then normalizes
  paths, lists input files, creates output folders, loads metadata, prepares
  model handoff, and launches the multiprocessing pool:
  https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py
- `convert_single.py` declares `filename`, `output`, and optional conversion
  arguments with `argparse.ArgumentParser`, calls `parse_args()`, then parses
  languages and loads models:
  https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert_single.py

PHP now records the standard argparse `--` end-of-options terminator in both
runtime argument preflight reviews. Option-looking WordPress batch folders and
single uploaded filenames are rejected before the separator, but admitted as
positionals after it. The review remains side-effect free: no filesystem
access, Python import, model loading, multiprocessing, OCR, raster rendering,
or external PDF tools.

## Evidence

- `php -l lanes/markerpdf/src/BatchConverter.php`
  - `No syntax errors detected in lanes/markerpdf/src/BatchConverter.php`
- `php -l lanes/markerpdf/src/SingleDocumentConverter.php`
  - `No syntax errors detected in lanes/markerpdf/src/SingleDocumentConverter.php`
- `php -l lanes/markerpdf/tests/MarkerRuntimeArgparseTerminatorBoundaryCurrentBaseTest.php`
  - `No syntax errors detected in lanes/markerpdf/tests/MarkerRuntimeArgparseTerminatorBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-argparse-terminator-currentbase.php`
  - `No syntax errors detected in lanes/markerpdf/examples/wordpress-marker-runtime-argparse-terminator-currentbase.php`
- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeArgparseTerminatorBoundaryCurrentBaseTest.php`
  - `1 test files, 55 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimeArgparseBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeArgparseEqualsTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeArgparseRepeatedOptionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeArgparseResponseFileBoundaryCurrentBaseTest.php lanes/markerpdf/tests/MarkerRuntimeArgparseTerminatorBoundaryCurrentBaseTest.php`
  - `5 test files, 319 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-marker-runtime-argparse-terminator-currentbase.php`
  - exits 0 with `batch_option_like_folder_admitted=true`,
    `single_option_like_filename_admitted=true`,
    `filesystem_touched_before_terminator_handling=false`,
    `executes_python_or_models=false`,
    `executes_multiprocessing=false`, and
    `executes_external_pdf_tools=false`
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/markerpdf`
  - no whitespace errors

## Non-overlap

This does not repeat the existing response-file, equals-token, repeated-option,
argparse default/error, chunk-wrapper shell, output-folder creation,
metadata-file loading, file-list, empty-queue, empty-model-list, worker-init,
pool-context, selected-file-gone, filetype, min-length, OCR/model, Streamlit,
FastAPI, or PDF parser extraction slices.

## Dependency closure

No new support component is needed. This reuses native PHP runtime preflight
helpers and stays review-only at the argparse boundary.
