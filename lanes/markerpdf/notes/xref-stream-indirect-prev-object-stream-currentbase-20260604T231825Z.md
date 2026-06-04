# Xref Stream Indirect Prev Object Stream Current Base

Slice: `markerpdf-object-stream-xref-parser-current-base-20260604T231825Z`
Base: `dfccfd252d4ec7968da59da8d0cbc92468a86823`

## Scope

This patch stays in the native no-GPU markerPDF parser boundary. It does not run OCR, Surya, Texify, Torch, PDFium, model workers, or external PDF tools.

The bounded behavior is xref-stream `/Prev` chain recovery when the current xref stream stores the previous xref offset as an indirect integer helper that is itself a compressed object-stream member. That lets the native parser merge previous xref sections before selecting searchable PDF page text, catalog/page trees, and object-stream members for WordPress import.

## Source-Truth Boundary

Upstream markerPDF delegates searchable PDF import to parser-backed text extraction before model/OCR fallback. For the PHP port, the equivalent native boundary is to walk the PDF xref chain and object-stream index accurately enough to recover page trees from searchable PDFs without raw stream fallback leakage. PDF xref streams carry trailer keys such as `/Prev`, and the existing markerPDF port already resolves safe indirect stream dictionary operands for `/W`, `/Index`, `/Size`, `/Filter`, `/DecodeParms`, and `/Length`.

This slice extends that same safe operand-owner bootstrap to `/Prev` so current-base xref streams can resolve a direct or compressed integer helper before following the previous xref section.

## Behavior

The focused fixture has:

- previous xref stream object `20 0 obj` with compressed object-stream rows for catalog/pages/page/font objects `1` through `4`;
- direct page content stream `5 0 obj`;
- a Type3 `/CharProcs << /A 5 0 R >>` guard so raw stream fallback cannot mask a missing page tree;
- current object stream `7 0 obj` containing compressed helper object `30`, whose body is the previous xref offset;
- current xref stream `40 0 obj` with `/Prev 30 0 R` and type-2 row `30 -> object stream 7`.

Before this parser change, that shape cannot follow the previous xref section through the compressed helper object, so no page-tree text is selected. After the change, the current xref stream resolves object `30`, walks the previous xref stream, expands object stream `6`, and extracts only:

- `Prev indirect compressed object stream page`
- `Object stream prev helper selected`

The fixture also asserts the review metadata selects object stream `6` for objects `1` through `4`, object stream `7` for helper object `30`, and does not leak `CharProcs`, `startxref`, raw `30 0 R`, or binary bytes into text.

## Evidence

Focused new test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectPrevObjectStreamCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves xref-stream indirect Prev offsets from compressed helper object streams

1 test files, 34 assertions, 0 failures
```

Adjacent xref/object-stream family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectPrevObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamCompressedOperandOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamObjectOwnerCycleCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevGenerationRebuildCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevGenerationIndexCurrentBaseTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 206 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-stream-indirect-prev-object-stream-currentbase.php
```

The smoke emits `uses_prev_object_stream_page=true`, `recovers_prev_xref_offset_from_compressed_helper=true`, `previous_catalog_recovered_from_object_stream=true`, `excludes_charproc_fallback_scan=true`, `page_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP object scanner, stream dictionary operand-owner resolver, object-stream expander, and xref-stream parser. The remaining model/OCR gap stays intentionally out of scope for this no-GPU markerPDF slice.
