# parser-object-stream-generation-offset-owner-currentbase

Date: 2026-06-02 UTC
Base: 7a7220f52fd6cdbbaea942c909b4d8b982da4bfa

## Source Truth

Upstream markerPDF delegates low-level PDF object parsing to pdftext/PDFium-style parser behavior. PDF object-stream headers are PDF token streams: percent comments are whitespace and numeric text inside comments must not be treated as `/ObjStm` member object-number or member-offset pairs.

## Behavior

`PdfTextExtractor::decodedObjectStreamMemberTable()` now reads `/ObjStm` header members token-by-token using the existing PDF whitespace/comment skipper. This prevents a header such as `4 0 % 8 0 fake commented member offset owner\n8 ...` from letting the commented `8 0` pair steal explicit type-2 member-index ownership before current-base page extraction.

## Red First

Before the parser repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamGenerationOffsetOwnerCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL skips commented object-stream header numbers before member offset ownership
Expected 3 lines, got only the first compressed page paragraph.
1 test files, 1 assertions, 1 failures
```

## Verification

After the parser repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamGenerationOffsetOwnerCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS skips commented object-stream header numbers before member offset ownership
1 test files, 17 assertions, 0 failures
```

Adjacent parser/object-stream gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamGenerationOffsetOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamIndexZeroWidthMemberReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateZeroWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCommentArrayDictStringTokenCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamStreamDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterDictGenerationCurrentBaseTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 243 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-parser-object-stream-generation-offset-owner-currentbase.php
```

The smoke emitted the native-boundary JSON comment and three Gutenberg paragraphs:

```text
First commented object stream page
Second commented object stream page
Comment offset owner ignored
```

## Non-Overlap

This slice does not repeat the accepted zero-width member-index repair, duplicate zero-width guard, explicit type-2 index selection, generation-zero nonzero-reference guard, `/Prev` object-stream generation owner, stream-owned xref offset boundary, object-stream stream-dictionary generation, object-stream filter-owner boundary, or general comment handling in arrays/dictionaries/strings. The new boundary is comments inside the `/ObjStm` header before `/N` object-number/member-offset parsing.

## Dependency Closure

No new support component is needed. The slice reuses native PHP direct-object scanning, xref-stream parsing, object-stream decoding, the existing PDF comment/whitespace skipper, page-tree traversal, stream decoding, and content text extraction. Full upstream runner parity remains dependency-gated by the Python/model/pdftext/pypdfium stack and was not executed.
