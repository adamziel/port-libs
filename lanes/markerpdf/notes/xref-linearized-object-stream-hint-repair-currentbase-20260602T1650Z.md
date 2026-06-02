# markerPDF xref linearized object-stream hint repair

Micro-slice: `xref-linearized-object-stream-hint-repair-currentbase-20260602T1650Z`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes page text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level PDF parsing to `pdftext`/PDFium before Marker block assembly. This native PHP slice keeps that dependency boundary: xref traversal, linearized `/H` hint ranges, object-stream expansion, and stream exclusion happen before WordPress paragraph import and without Python, models, pypdfium, or external PDF tools.

PDF linearized `/H` values are byte ranges for hint-table data. When such a range lands inside an object-stream payload, treating the direct `/ObjStm` carrier as the hint object is too broad because the same carrier can also contain current catalog/page/content members selected by the latest xref stream.

## Behavior

`PdfTextExtractor` now separates linearized hint exclusions into direct objects and object-stream members:

- direct non-object-stream hint objects are still removed from the native object map;
- direct object-stream carriers touched only inside their stream payload are preserved;
- unfiltered object-stream member byte spans are mapped through the object-stream header offsets, and only hinted member object numbers are removed after object-stream expansion;
- filtered carriers are preserved instead of guessing decoded-member byte positions from compressed bytes.

The focused fixture builds a current xref stream whose `/Root`, page tree, font, and current content stream live in one unfiltered object stream. The linearized first object `/H` range points at a stale hint member in the same carrier, and the page `/Contents` array intentionally references both current content and the hinted member. Native extraction now emits only the current content while preserving the carrier-backed page tree.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefLinearizedObjectStreamHintRepairCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS preserves linearized object-stream carrier while skipping hinted member before current-base text extraction

1 test files, 8 assertions, 0 failures
```

Adjacent parser/xref gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefLinearizedObjectStreamHintRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamIndexZeroWidthMemberReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfXrefIncrementalObjectStreamFreeRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php
10 test files, 691 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-linearized-object-stream-hint-repair-currentbase.php
uses_current_object_stream_page=true
skips_linearized_hint_member=true
preserves_object_stream_carrier_page_count=1
executes_python_or_models=false
executes_external_pdf_tools=false
```

Status delta: behavior tests `576 -> 577`; mapped semantics `413 -> 414 / 78`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct linearized hint-table exclusion, indirect `/H` numeric operand resolution, latest startxref trailer precedence, xref-stream zero-width object-stream member-index review, object-stream indirect `/Length`/`/Filter`/`/N`/`/First` recovery, cross-object-stream filter-chain operand recovery, xref stream `/Prev` generation repair, or stream-owned xref offset rejection.

The new behavior is specifically preserving an object-stream carrier whose payload contains a linearized hint range, while excluding only the hinted unfiltered compressed member after current-base xref object-stream expansion.

## Dependency Closure

No new support component is needed. This reuses the native direct-object scanner, xref-stream parser, linearized `/H` range resolver, object-stream decoder, stream payload offset helper, page-tree walker, content-token extractor, and WordPress smoke path. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
