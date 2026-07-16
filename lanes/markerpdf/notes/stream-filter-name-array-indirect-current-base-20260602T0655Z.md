## Stream Filter Name Array Indirect Current Base

Source truth: upstream markerPDF reaches PDF page text through `marker/pdf/extract_text.py::naive_get_text` / `get_text_blocks` at the pdftext/pypdfium boundary. This reduced native slice maps the PDF stream dictionary case where `/Filter` is a direct array, but individual filter names are indirect objects.

Scope: `PdfTextExtractor` already resolves the current-base parser path for `/Filter [ 2 0 R null 3 0 R ]`, so this patch adds focused regression coverage and a WordPress smoke rather than changing production parsing code. The fixture decodes ASCIIHex then Flate through indirect filter-name objects, ignores a `null` array entry, and fails closed on a cyclic filter-name reference so raw filtered bytes do not leak into visible text.

Focused evidence:

- `php -l lanes/markerpdf/tests/PdfTextExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-filter-name-array-indirect-import.php` passed.
- `jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json` passed.
- `php lanes/markerpdf/examples/wordpress-pdf-filter-name-array-indirect-import.php` emitted `Name Array Indirect Filter` and `Block Ready Import` with `executes_python_or_models=false` and `executes_external_pdf_tools=false`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php` passed with 1 test file, 398 assertions, and 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests` passed with 58 test files, 2423 assertions, and 0 failures.

Dependency closure: no new support component is needed. The slice reuses the existing native PDF object table, stream-filter resolver, filter dispatcher, and content-token parser. Full upstream Python/model/benchmark parity remains dependency-gated.

Non-overlap: this does not repeat accepted stream filter error-boundary, indirect `/DecodeParms` numeric predictor, stale `/Length`, object-stream indirect `/Filter`, or ASCIIHex/Flate decoding behavior; it specifically locks the direct filter-array with indirect filter-name entries on current base.
