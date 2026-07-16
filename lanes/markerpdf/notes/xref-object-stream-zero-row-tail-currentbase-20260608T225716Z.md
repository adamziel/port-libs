# Xref Object Stream Zero Row Tail Current Base

- Slice/session: `markerpdf-object-stream-xref-parser-current-base-20260608T225716Z` / `port-dev-markerpdf-object-xref-20260608T225716Z`.
- Accepted base: `79f9f98965689b71a99ad50e1ab3f41478685bb2`.
- Scope: native no-GPU searchable-PDF parser behavior only. No OCR, Surya, Texify, Torch, PDFium execution, external PDF tools, or model workers were run.

## Behavior

PDF xref-stream type-2 rows select compressed generation-zero objects from `/Type /ObjStm` carriers by archive index. PDFium validates `/Type /ObjStm`, `/N`, and `/First`, reads every object-number/offset pair in the header, skips object number `0`, and parses the selected object body from the selected offset. Source: <https://pdfium.googlesource.com/pdfium/+/refs/heads/main/core/fpdfapi/parser/cpdf_object_stream.cpp>.

The PHP parser already skipped object-number-zero rows as non-selectable members. The remaining gap was body slicing: if a valid selected member was followed by a skipped zero-object row, that skipped row's offset was not used as a boundary. The selected member body therefore included the zero-row payload as a tail and was rejected before WordPress text extraction.

`PdfTextExtractor` now keeps all parsed object-stream header offsets as member body boundaries while still exposing only nonzero object numbers as selectable members. Review rows expose the boundary count and skipped-boundary count.

## Red-First Evidence

Before the source fix, a local focused probe for the new fixture imported only the guard page and reported the compressed page member as malformed:

```text
array (
  0 => 'Current zero-tail guard page',
)
'object_stream_member_has_single_value' => false
'malformed_member_tail_rejected' => true
```

## Verification

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfXrefObjectStreamZeroRowTailBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfXrefObjectStreamZeroRowTailBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-zero-row-tail-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-zero-row-tail-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamZeroRowTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses skipped zero object-number rows as non-selectable object-stream body boundaries
1 test files, 26 assertions, 0 failures
```

Adjacent object-stream header family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamZeroRowTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamSkippedHeaderIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDeclaredCountBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamMemberTailBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 100 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-zero-row-tail-currentbase.php
```

The smoke emits Gutenberg paragraphs for the direct guard page and current compressed page, excludes the ignored zero-object row payload, and records `object_stream_member_offset_boundary_count=2`, `object_stream_skipped_member_boundary_count=1`, `malformed_member_tail_rejected=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted explicit type-2 member-index selection, skipped zero row index alignment before the selected member, duplicate zero-width header rejection, incomplete headers, `/First` body-boundary rejection, literal/comment/nested member-offset rejection, duplicate offsets, zero-width index recovery, later bad offset slicing, stream-member rejection, member-tail rejection, object-stream carrier repair, xref-stream `/Prev` repair, non-`/ObjStm` carrier rejection, header comments, or attachment/metadata object-stream header parsing. The bounded behavior is only a skipped object-number-zero header row after the selected member acting as a non-selectable end boundary for that selected member body.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP xref stream parser, object stream decoder, stream filter stack, page-tree traversal, text extractor, and WordPress smoke path. Live OCR, Surya/Texify/Torch/model execution, pypdfium/pdftext runtime parity, raster rendering, and external PDF tools remain intentionally out of scope under the markerPDF no-GPU directive.

## Next

Continue with non-overlapping native markerPDF parser fidelity around xref repair, stream filters, font/CMap behavior, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
