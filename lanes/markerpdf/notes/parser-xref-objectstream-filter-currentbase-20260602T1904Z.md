# markerPDF parser xref object-stream filter current base

Micro-slice: `parser-xref-objectstream-filter-currentbase`

Source truth: upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF page text through `marker/pdf/extract_text.py::get_text_blocks()` via `pdftext.extraction.dictionary_output(...)`, and `naive_get_text()` via pypdfium/PDFium page text. The native PHP port therefore owns the bounded parser dependency boundary where xref-selected object streams, stream `/Length`, `/Filter`, and decoded page-tree members are resolved before WordPress paragraphs are emitted.

PDF stream `/Length` is authoritative over `endstream`-looking bytes in stream payloads, and PDF 1.5 object streams can carry xref-selected catalog/page/font members. A current xref stream can select an object-stream carrier while that carrier's `/Length` and `/Filter` operands are themselves compressed helper objects from another object stream. The previous native order could expand the helper stream, repair the direct carrier boundary, and then stop before expanding the repaired carrier's selected members.

Implementation:

- `PdfTextExtractor::pdfObjects()` now updates object reference owners after direct stream repair and runs one more bounded `withObjectStreamObjects()` pass.
- The second expansion still reuses the existing xref/type-2 selection checks and does not overwrite existing direct/current objects.
- Linearized hint-table member suppression is applied again after the second expansion so accepted hint-object leak boundaries stay intact.

Focused fixture:

- object `6 0` is a current xref-selected `/ObjStm` carrier whose raw RunLength-encoded bytes contain fake `endstream`/`endobj` owner-boundary text inside a catalog note;
- object `6 0` has `/Length 30 0 R` and `/Filter 31 0 R`;
- helper objects `30` and `31` are themselves compressed members of xref-selected object stream `7 0`;
- before the parser edit, the red-check failed with no recovered page text because the repaired carrier was not re-expanded;
- after the parser edit, WordPress extraction emits only `Current repaired filtered object stream page` and `RunLength carrier expanded after repair`.

Verification:

Red-check without the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL expands repaired xref-selected object streams with compressed filter operands on current base
Actual: array (
)
1 test files, 1 assertions, 1 failures
```

Focused pass after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS expands repaired xref-selected object streams with compressed filter operands on current base
1 test files, 10 assertions, 0 failures
```

Adjacent parser/object-stream gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfParserObjectStreamNestedFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterXrefOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 59 assertions, 0 failures
```

Full markerPDF lane regression:

```text
php tools/run-tests.php lanes/markerpdf/tests
Focused test run: 128 selected test files (root lock skipped)
128 test files, 7900 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-parser-xref-objectstream-filter-currentbase.php
uses_current_repaired_object_stream_page=true
expands_after_repair=true
excludes_object_scanner_decoy=true
excludes_filter_operand_text=true
page_count=1
```

Lint and patch hygiene:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-parser-xref-objectstream-filter-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
git diff --check -- lanes/markerpdf
```

Non-overlap:

This does not repeat accepted object-stream indirect `/Length`/`/Filter`/`/N`/`/First` recovery, compressed helper filter-chain recovery, xref-stream DecodeParms predictor handling, nested object-stream filter fail-closed fallback, object-stream carrier fallback exclusion, stream-owned xref object rejection, xref type-2 member-index repair, previous-chain object-stream free-entry suppression, or direct page-stream filter generation owner checks. The bounded behavior is specifically re-expanding current xref-selected object-stream members after the carrier stream is repaired using compressed helper operands.

Dependency closure:

No new support component is needed. This reuses the native PHP direct-object scanner, xref-stream parser, object-stream decoder, stream filter dispatcher, RunLength decoder, direct stream repair path, page-tree walker, content-token extractor, and WordPress smoke path. Full markerPDF upstream parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark tooling, and external OCR/rendering helpers.
