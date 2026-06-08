# CCITT Fax DecodeParms Indirect Scalar Tail Boundary

Slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260608T062455Z`

Source truth:
- Upstream markerPDF treats image streams as image metadata/review inputs and keeps searchable page text extraction separate from raster/OCR/model execution.
- PDF scalar parameters referenced from CCITT `/DecodeParms` must resolve to standalone scalar operands. Indirect helper objects like `16 /BadColumns` are malformed for `/Columns`, even though `16 % comment` is acceptable PDF whitespace/comment syntax.

Implemented behavior:
- `PdfImageRenderer` now requires integer values resolved through `integerFromPdfValue()` to be standalone integer operands after PDF whitespace/comment skipping.
- `PdfTextExtractor` now requires CCITT DecodeParms indirect integer and boolean parameter objects to resolve to standalone scalar operands while preserving normal direct dictionary parsing.
- Direct renderer, XObject import review, and inline image review all mark tailed indirect CCITT scalar parameters as `invalid_ccitt_decodeparms_fail_closed`, apply default effective DecodeParms, keep native raster decode disabled, and keep fax payload bytes out of visible WordPress text/review JSON.

Red-first evidence:
- `php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxDecodeParmsIndirectScalarTailCurrentBaseTest.php`
- Before implementation: `1 test files, 15 assertions, 3 failures`. The renderer and extractor accepted tailed integer helper objects such as `16 /BadColumns`; the extractor also accepted tailed boolean helper objects on XObject review.

Focused verification after implementation:
- `php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxDecodeParmsIndirectScalarTailCurrentBaseTest.php` => `1 test files, 42 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxDecodeParmsIndirectScalarTailCurrentBaseTest.php lanes/markerpdf/tests/PdfCcittFaxDecodeParmsTrailingOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php` => `3 test files, 1250 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-decodeparms-indirect-scalar-tail-currentbase.php` => exits 0 and emits Gutenberg paragraphs for the searchable before/after text only, with `invalid_ccitt_decodeparms_fail_closed` metadata for XObject and inline CCITT review.
- `php -l lanes/markerpdf/src/PdfImageRenderer.php` => no syntax errors.
- `php -l lanes/markerpdf/src/PdfTextExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/PdfCcittFaxDecodeParmsIndirectScalarTailCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-decodeparms-indirect-scalar-tail-currentbase.php` => no syntax errors.
- `php -r '$json = file_get_contents("lanes/markerpdf/lane-status.json"); json_decode($json, true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status.json valid\n";'` => `lane-status.json valid`.
- `git diff --check -- lanes/markerpdf` => no output.
- Root harness: not run - isolated micro-slice.

Non-overlap:
- This does not repeat the accepted CCITT top-level `/DecodeParms` dictionary-tail slice. That slice rejects extra operands after the DecodeParms dictionary itself; this slice rejects malformed nested indirect scalar parameter objects inside an otherwise well-formed DecodeParms dictionary.
- This does not add OCR, Surya, Texify, Torch, raster model execution, pypdfium/PIL, or external PDF tools.

Dependency closure:
- No new support component is needed. The patch reuses the existing native PHP PDF token scanning, object map lookup, image-filter review, and WordPress smoke paths.

Next task:
- Continue with non-overlapping native searchable-PDF/parser behavior, especially filters, xref repair, fonts/CMaps, image metadata, annotations/forms, page geometry, and supplied-boundary handoffs.
