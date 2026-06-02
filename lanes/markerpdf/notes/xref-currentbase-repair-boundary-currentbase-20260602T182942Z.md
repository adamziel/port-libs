# markerPDF xref current-base repair boundary

Slice: `xref-currentbase-repair-boundary`
Session: `port-dev-markerpdf-xref36pdf-20260602T1829Z`
Base accepted HEAD: `3439e210d8ddc181cab037bb234e5c258deb5ba1`

## Source Truth

- Upstream markerPDF at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates page text extraction through `marker/pdf/extract_text.py` into pdftext/PDFium. That makes PDF xref repair a parser dependency boundary for this native PHP lane.
- PDF xref rows for in-use objects are supposed to point at the byte offset of the selected indirect object. Real incremental PDFs can carry damaged current offsets; tolerant parser recovery should repair from the current trailer-selected object graph instead of falling back to stale `/Prev` page text.

## Behavior

`PdfTextExtractor` now repairs a missing direct nonzero-generation object when the current graph references it and the current xref row is in-use but cannot select a body by its explicit offset. The repair is intentionally bounded to referenced nonzero-generation objects, preserving existing free-entry, object-stream, and stream-owned xref rejection behavior.

The focused fixture has:

- stale `/Prev` generation-zero page/content objects with visible text;
- current catalog/pages generation-one objects with valid offsets;
- current page `4 1 R` and content `9 1 R` bodies present in the file;
- current xref rows for `4 1` and `9 1` marked in-use but carrying invalid offset `0`.

Before the fix, fallback extraction emitted `Stale currentbase repair page`. After the fix, WordPress paragraph extraction emits only `Current xref repair page` and `Current base repair boundary`.

## Verification

- Red baseline before source repair: a one-off fixture emitted `array (0 => 'Stale currentbase repair page')`.
- `php -l lanes/markerpdf/src/PdfTextExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfXrefCurrentBaseRepairBoundaryTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-xref-currentbase-repair-boundary.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefCurrentBaseRepairBoundaryTest.php` passed: 1 test file, 9 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfXrefStreamObjectOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfXrefCurrentBaseRepairBoundaryTest.php` passed: 6 test files, 65 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php` passed: 1 test file, 597 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-xref-currentbase-repair-boundary.php` passed and emitted `uses_current_xref_repair_page=true`, `uses_current_base_repair_boundary=true`, `excluded_stale_prev_page=true`, `excluded_prev_offset_leak=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- `php -r '$files=["lanes/markerpdf/lane-status.json","lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " ok\n"; }'` passed.
- `git diff --check -- lanes/markerpdf` passed.

## Non-Overlap

This does not repeat accepted xref-stream `/Prev` exact-offset generation repair, hybrid `/XRefStm` free-entry precedence, object-stream generation-zero conflict repair, xref-stream trailer metadata precedence, stream-owned fake xref offset rejection, direct stream owner lookup, or parser stream dictionary escape handling. The new behavior is specifically current trailer-selected direct-generation repair when an in-use current xref row has an unrecoverable explicit offset but the current object graph references that generation.

## Dependency Closure

No new support component is needed. This reuses the native direct-object scanner, current startxref chain, xref table parser, page-tree walker, stream decoder, and WordPress smoke path. Full upstream runner parity remains dependency-gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, model downloads, and benchmark tooling.
