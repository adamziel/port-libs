# markerPDF runtime preflight numeric gates

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260603T230520Z`
Session: `port-dev-markerpdf-runtime-preflight-20260603T230520Z`
Base accepted HEAD: `ea0e1f5294ced657a1ca66594f308fcca7dac22c`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert.py::main` applies the batch cap with `if args.max: files_to_convert = files_to_convert[:args.max]`. Python treats `None` and `0` as inactive and negative integers as active slice lengths.
- Upstream `convert.py::process_single_pdf` applies the embedded-text gate with `if min_length:` before `find_filetype()` and `get_length_of_text()`. Python treats `0` as inactive and negative integers as active, so unsupported filetypes are still rejected when a negative value is supplied.
- Primary source inspected: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`

## Patch

- Updated `BatchConverter::chunkFiles()` to use Python-style nullable integer truthiness for `maxFiles`, preserving the existing no-cap behavior for `0` while making negative values active and equivalent to Python list slicing from the head with a negative stop.
- Updated `BatchConverter::processFilePreflightPlan()` to use the same truthiness for `minLength`, so `0` skips filetype/text-length preflight while negative values still trigger the upstream filetype boundary before converter handoff.
- Added explicit review fields `chunking.max_files_limit_active` and `min_length_gate_active` so WordPress import preflight output can explain why a queue cap or filetype/text gate did or did not run.
- Updated `wordpress-marker-runtime-preflight-boundary-currentbase.php` to smoke `--max=0`, negative `--max`, `--min_length=0`, and negative `--min_length` without executing Python, pdftext, pypdfium, OCR, models, multiprocessing, or external PDF tools.

## Evidence

Red-first focused run before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Failed as expected on missing `max_files_limit_active` and the old positive-only gate.

Focused assigned gate after patch:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Passed: `1 test files, 132 assertions, 0 failures`.

Adjacent batch/runtime gate:

`php tools/run-tests.php lanes/markerpdf/tests/BatchConverterTest.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`

Passed: `2 test files, 235 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`

Passed: emitted `runtime_max_files_limit_active=false`, `runtime_zero_max_selected_count=4`, a negative max queue missing the tail file, `zero_min_length_spoof_status=ready-for-conversion`, `negative_min_length_spoof_status=skipped-unsupported-filetype`, and all Python/model/multiprocessing/external-tool execution flags false.

PHP lint:

- `php -l lanes/markerpdf/src/BatchConverter.php`: no syntax errors.
- `php -l lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-preflight-boundary-currentbase.php`: no syntax errors.

## Dependency Closure

No new support component is needed. This slice reuses native PHP task planning, filetype detection, embedded-text length callbacks, and output existence helpers. Full upstream runtime execution remains out of scope under the no-GPU lane rule because it requires Python, Torch multiprocessing, Surya/Texify/tabled models, `pdftext`, `pypdfium2`/PDFium, OCR/raster helpers, and Streamlit/FastAPI runtime paths.

## Non-Overlap

This does not repeat accepted single-document runtime preflight, main runtime admission shape, batch progress/resume, conversion/server/benchmark artifacts, model/OCR planning, or native PDF parser/font/xref/security/image/table/form/outline metadata slices. The bounded behavior is only the upstream Python truthiness boundary for `convert.py` numeric preflight gates.
