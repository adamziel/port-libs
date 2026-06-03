# markerPDF runtime single-document preflight boundary

Micro-slice: `markerpdf-runtime-preflight-boundary-current-base-20260602T234421Z`

Base accepted HEAD: `7daebccdb1e231332676891328ab6455e928870a`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` `convert_single.py` sets `PYTORCH_ENABLE_MPS_FALLBACK=1`, configures logging, parses `filename`, `output`, `--max_pages`, `--start_page`, `--langs`, and `--batch_multiplier`, splits `--langs` with Python `str.split(",")`, calls `load_all_models()` before `convert_single_pdf()`, then calls `save_markdown()` and prints the saved folder plus elapsed time.
- The top-level batch path in upstream `convert.py` has separate `markdown_exists` and `--min_length` preflight gates. The single-document path does not apply those gates before loading models.

Source URLs used:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert_single.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/convert.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/convert.py`

## Patch

- `SingleDocumentConverter::runtimePreflightPlan()` now emits a no-execution review payload for `convert_single.py` admission: import environment, preflight order, parsed options, model-load boundary, `convert_single_pdf` call shape, and `save_markdown` output policy.
- The plan records that upstream single-document conversion loads models before conversion, does not skip existing Markdown, does not run the batch `--min_length` embedded-text gate, and saves empty output after converter return.
- `wordpress-marker-runtime-single-preflight-boundary-currentbase.php` demonstrates a WordPress single-PDF admission review with existing output present while still proving all execution flags remain false.

## Evidence

Focused runtime/single-document gate:

`php tools/run-tests.php lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php lanes/markerpdf/tests/SingleDocumentConverterTest.php`

Passed: `2 test files, 74 assertions, 0 failures`.

Example smoke:

`php lanes/markerpdf/examples/wordpress-marker-runtime-single-preflight-boundary-currentbase.php`

Passed: emitted `schema="markerpdf.convert_single_runtime_preflight.v1"`, `existing_markdown=true`, `skips_existing_markdown=false`, `min_length_preflight=false`, `saves_empty_output=true`, and all runtime execution flags false.

PHP lint:

- `php -l lanes/markerpdf/src/SingleDocumentConverter.php`
- `php -l lanes/markerpdf/tests/MarkerRuntimePreflightBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-marker-runtime-single-preflight-boundary-currentbase.php`

Passed: no syntax errors detected.

## Status Delta

- Focused behavior PASS cases move `974 -> 976`.
- WordPress scenarios move `974 -> 976`.
- Mapped markerPDF semantics move `681 / 78 -> 682 / 78`.

## Dependency Closure

No new support component is needed. This slice reuses the native single-document converter, output writer, language parsing, and lane test harness. Full upstream single-document runtime parity remains gated on Python, Torch model loading, Surya/Texify/tabled model workers, pdftext, pypdfium2/PDFium, PIL, model downloads, and live runtime infrastructure; none were executed.

## Non-Overlap

This does not repeat accepted batch `process_single_pdf` preflight, convert.py multiprocessing/model-handoff planning, marker app config/runtime preview, server config/upload/pagination/error artifacts, output artifact quality, or native PDF parser/font/xref/security/image/table behavior. The bounded behavior is only single-document `convert_single.py` runtime admission before model loading and output persistence.
