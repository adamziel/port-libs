# markerPDF named-destination duplicate key boundary

Session: `port-dev-markerpdf-named-destinations-20260605T134157Z`
Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T134157Z`
Base accepted HEAD: `858af475bf12386a38b3216c0cd932565f7f894a`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable PDF text, outline, and link destination resolution to parser-backed pdftext/PDFium boundaries before OCR/model fallback. In this native no-GPU PHP lane, catalog `/Names /Dests` name trees are the equivalent parser boundary for WordPress navigation and review metadata.

Malformed PDFs can contain duplicate destination keys in overlapping name-tree leaves. A stale earlier row must not permanently hide a later current row, and a malformed later row with an out-of-range page operand must not erase the last valid current destination.

## Implementation

- `PdfNamedDestinationExtractor` now deduplicates name-tree rows after successful destination normalization, so later valid duplicate keys replace earlier valid rows while malformed later rows are ignored.
- `PdfMetadataExtractor` now builds document destination summaries from successful per-name rows, with name-tree rows overriding legacy `/Dests` only after valid local-page resolution.
- `PdfActionReviewExtractor` now ignores invalid direct destination values when building the local destination map for annotations/links and bounds integer page operands to the local page count.
- `PdfOutlineExtractor` now applies the same optional local-page validation to destination maps when callers already have page indexes, while preserving accepted UTF-16 indirect name-tree key fallback for decoded outline names.

## Evidence

Red-first focused run before source edits:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationDuplicateKeyBoundaryCurrentBaseTest.php`

Result: `1 test files, 6 assertions, 2 failures`.

Focused behavior after source edits:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationDuplicateKeyBoundaryCurrentBaseTest.php`

Result: `1 test files, 39 assertions, 0 failures`.

Adjacent destination/action/outline family:

`php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/(PdfNamedDestination|PdfOutlineNameTree|PdfLinkAnnotationNameTreeLimits|PdfOutlineExtractor|PdfLinkAnnotationExtractor|PdfAnnotationExtractorTest|PdfMetadataExtractorTest).*Test\.php$' | sort)`

Result: `28 test files, 1177 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-named-destination-duplicate-key-boundary-currentbase.php`

Result: emitted `duplicate_review_page=1`, `duplicate_review_fit=XYZ`, `stale_first_duplicate_hidden=true`, `malformed_later_duplicate_hidden=true`, `visible_text_excludes_destination_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax:

`php -l lanes/markerpdf/src/PdfNamedDestinationExtractor.php`
`php -l lanes/markerpdf/src/PdfMetadataExtractor.php`
`php -l lanes/markerpdf/src/PdfActionReviewExtractor.php`
`php -l lanes/markerpdf/src/PdfOutlineExtractor.php`
`php -l lanes/markerpdf/tests/PdfNamedDestinationDuplicateKeyBoundaryCurrentBaseTest.php`
`php -l lanes/markerpdf/examples/wordpress-pdf-named-destination-duplicate-key-boundary-currentbase.php`

Result: no syntax errors detected.

Whitespace/patch hygiene:

`git diff --check -- lanes/markerpdf`

Result: passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted name-tree `/Limits` pruning, malformed intermediate fallback, indirect arrays, page operands, generation boundaries, xref/trailer-root selection, action-dictionary rejection, link URI promotion, outline transition/action context, or byte-string limit comparisons. The bounded behavior is duplicate named-destination key replacement after successful local-page validation across the standalone extractor, metadata summary, outline review, and annotation/link promotion paths.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object parser, name-tree walkers, page-tree page indexes, named-destination normalization, action review, outline review, link promotion, supplied pdftext page arrays, and WordPress smoke renderer. OCR, Surya/Texify/Torch, PDFium/PIL raster execution, JavaScript execution, and external PDF tools remain intentionally out of scope under the no-GPU markerPDF directive.

## Next Task

Continue with non-overlapping native searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
