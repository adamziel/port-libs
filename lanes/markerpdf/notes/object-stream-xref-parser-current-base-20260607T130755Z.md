# markerPDF xref-stream indirect integer array elements current-base

## Scope

Pinned upstream markerPDF routes searchable PDF extraction through PDF parser/PDFium layers before OCR/model handoff. In the native no-GPU PHP boundary, xref streams must decode `/W` and `/Index` arrays before selecting current direct and type-2 object-stream rows. PDF arrays can contain indirect objects, so integer elements such as `/W [30 0 R 31 0 R 32 0 R]` and `/Index [40 0 R 41 0 R]` need the same strict scalar-helper resolution as whole indirect array operands.

## Implementation

`PdfTextExtractor` now uses a strict xref-stream integer array reader for `/W` and `/Index`. It resolves exact indirect scalar integer helper objects through the existing bounded object map, requires each helper to be a standalone integer token, preserves negative-value validation for malformed xref arrays, and keeps non-integer or tailed operands fail-closed.

The focused fixture stores the current catalog, pages, and page dictionary in an `/ObjStm`. The xref stream selects those members through type-2 rows, but both `/W` and `/Index` store their integer array elements as direct helper objects. A later stale direct catalog/page tree after `%%EOF` wins if those range rows are not decoded.

## Evidence

Red-focused evidence before the final range-builder fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamIndirectIntegerArrayElementsCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves indirect integer elements in xref-stream W and Index arrays before object-stream selection
Expected: ['Indirect xref integer array page', 'Object stream rows selected']
Actual: ['Stale direct xref integer page', 'Indirect integer array fallback leak']
1 test files, 1 assertions, 1 failures
```

Focused passing command:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamIndirectIntegerArrayElementsCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves indirect integer elements in xref-stream W and Index arrays before object-stream selection
1 test files, 19 assertions, 0 failures
```

Adjacent xref/object-stream parser regression command:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamMalformedWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamMalformedIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfParserNameArrayCommentEscapeCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamNestedHelperObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamRowAlignmentObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamCompressedOperandCascadeCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamUnsupportedTypeObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedGraphCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectOwnerFreeEntryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFreeEntryPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamTrailerBoundaryTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOutOfRangeIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOffsetOrderCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPlusHeaderReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamIndirectIntegerArrayElementsCurrentBaseTest.php
Focused test run: 19 selected test files (root lock skipped)
19 test files, 368 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-stream-indirect-integer-array-elements-currentbase.php
```

Result: emits Gutenberg paragraphs `Indirect xref integer array page` and `Object stream rows selected`, with smoke booleans `indirect_integer_w_elements_resolved=true`, `indirect_integer_index_elements_resolved=true`, `object_stream_rows_selected=true`, `current_object_stream_text_visible=true`, `stale_direct_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted whole indirect `/W` and `/Index` array operands, nested compressed xref helper arrays, malformed `/W` or `/Index` validation, zero-width member-index repair, plus-signed or comment-aware object-stream headers, xref-stream DecodeParms predictor decoding, object-stream filter-chain operands, omitted object-stream carrier/member repair, hybrid owner suppression, free-row precedence, stream-member rejection, or object-stream member-offset token-boundary guards. The bounded behavior is only scalar indirect integer elements inside xref-stream `/W` and `/Index` arrays before type-2 object-stream row selection.

## Dependency Closure

No new support component is needed. This reuses native PHP direct-object scanning, xref-stream parsing, strict scalar helper resolution, stream decoding, object-stream expansion, page-tree traversal, and WordPress paragraph rendering. Full upstream markerPDF OCR/layout/model parity remains intentionally out of scope under the no-GPU supervisor override.
