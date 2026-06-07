# markerPDF xref Prev chain compressed Prev page review current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260607T044613Z`
Session: `port-dev-markerpdf-xref-prev-chain-20260607T044613Z`
Base accepted HEAD: `1608f08ebac7656df8e591e9e9564302b71fb161`

## Source Truth

Upstream markerPDF at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF parsing and page review metadata through the PDF parser layer before downstream WordPress conversion. Under the current no-GPU markerPDF scope, this lane owns native xref `/Prev` chain recovery that decides which page `/PieceInfo` and page-associated `/AF` files are current before review metadata is emitted.

PDF xref stream dictionaries can store `/Prev` as an indirect object. Existing page-review coverage handled direct numeric helpers. This slice adds the bounded current-base case where that helper is a generation-zero member inside an earlier `/ObjStm`: the current xref stream uses `/Prev 30 0 R`, object `30` is only safely available as an object-stream member containing the previous xref byte offset, and a stale direct `30 0 obj` after the current xref must not own the helper.

## Implementation

- `PdfPagePropertyExtractor::previousXrefOffsetFromSectionDictionary()` now resolves `/Prev` indirect references from safe numeric object-stream members before current xref-row repair.
- The compressed helper path accepts only numeric scalar helper bodies and rejects bodies containing PDF structural tokens such as `obj`, `stream`, `xref`, `trailer`, or `startxref`.
- The helper is bounded to generation-zero compressed object-stream members before the current xref stream and prefers a newer compressed helper over an older direct helper, matching the surrounding native parser current-base behavior.

## Evidence

Red-first before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageReviewXrefPrevChainCompressedPrevCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs page review metadata when xref-stream Prev is a compressed numeric helper
page review follows compressed /Prev helper before repairing zero-offset current rows
Expected: 1
Actual: 0

1 test files, 1 assertions, 1 failures
```

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageReviewXrefPrevChainCompressedPrevCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs page review metadata when xref-stream Prev is a compressed numeric helper

1 test files, 25 assertions, 0 failures
```

Adjacent xref/page-review gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageReviewXrefPrevChainCompressedPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfPageReviewXrefPrevChainIndirectPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfPageReviewXrefPrevChainCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
32 PASS cases
4 test files, 629 assertions, 0 failures
```

Broader page-property regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
9 PASS cases
1 test files, 249 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-property-xref-prev-chain-compressed-prev-currentbase.php
```

The smoke exits `0` and reports current page review selected, current associated file selected, attachment payload review-only, compressed `/Prev` helper selected, stale page review excluded, stale associated file excluded, current text selected, stale text excluded, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted metadata-side damaged-offset repair, text-side xref-stream row repair, direct page-review `/Prev` helper handling, free-annotation compressed `/Prev` suppression, action-review indirect `/Prev` repair, duplicate xref-stream object-row handling, classic xref rebuild, object-stream member-index repair, or model/OCR/table detection handoffs.

The bounded behavior here is only page review metadata and page-associated file selection when the current xref stream's `/Prev` operand is a compressed object-stream numeric helper needed before repairing zero-offset current rows.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, xref-stream decoder, object-stream decoder, page property extractor, embedded-file metadata reader, text extractor, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium rendering, Streamlit/FastAPI model workers, JavaScript/PDF action execution, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
